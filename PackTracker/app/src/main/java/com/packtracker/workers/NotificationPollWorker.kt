package com.packtracker.workers

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build
import android.util.Log
import androidx.work.*
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.NotificationItem
import com.packtracker.models.UnreadData
import com.packtracker.utils.TrayNotifier
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * Background poll for new notifications — the "pop up outside the app" path.
 *
 * WorkManager runs this every 15 minutes even when the app is closed. When
 * the unread count has grown since the last check, the newest message is
 * fetched and posted as a system tray notification (heads-up banner on the
 * lock screen). Tapping it opens the Notifications screen.
 *
 * State:
 *  - "last seen unread count" and "last notified id" live in the `notif`
 *    SharedPreferences so the check survives process death.
 *  - The in-app [com.packtracker.utils.GlobalNotificationPoller] shares the
 *    same prefs, so a message shown as a tray notification is never popped
 *    again in-app (and vice versa: reading in-app suppresses the tray).
 */
class NotificationPollWorker(
    context: Context,
    params: WorkerParameters
) : CoroutineWorker(context, params) {

    companion object {
        private const val TAG = "NotifPollWorker"
        private const val PERIODIC_WORK_NAME = "periodic_notification_poll"

        const val CHANNEL_ID = "new_notifications"
        private const val NOTIFICATION_ID = 4001

        /** Schedule the periodic background poll (every 15 min, network-gated). */
        fun schedule(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = PeriodicWorkRequestBuilder<NotificationPollWorker>(
                15, TimeUnit.MINUTES,
                5, TimeUnit.MINUTES
            )
                .setConstraints(constraints)
                .setBackoffCriteria(
                    BackoffPolicy.EXPONENTIAL,
                    WorkRequest.MIN_BACKOFF_MILLIS,
                    TimeUnit.MILLISECONDS
                )
                .addTag("notif_poll")
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                PERIODIC_WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request
            )
            Log.d(TAG, "Periodic notification poll scheduled (every 15 min)")
        }

        /** Run one check right now (used after sign-in). */
        fun runNow(context: Context) {
            val request = OneTimeWorkRequestBuilder<NotificationPollWorker>()
                .setConstraints(
                    Constraints.Builder()
                        .setRequiredNetworkType(NetworkType.CONNECTED)
                        .build()
                )
                .addTag("notif_poll_now")
                .build()
            WorkManager.getInstance(context).enqueueUniqueWork(
                "${PERIODIC_WORK_NAME}_now",
                ExistingWorkPolicy.REPLACE,
                request
            )
        }

        /** Stop background polling (logout). */
        fun cancel(context: Context) {
            WorkManager.getInstance(context).cancelUniqueWork(PERIODIC_WORK_NAME)
            WorkManager.getInstance(context).cancelUniqueWork("${PERIODIC_WORK_NAME}_now")
            com.packtracker.utils.NotifState.clear(context)
            Log.d(TAG, "Notification poll cancelled")
        }

        /** Create the notification channel (idempotent). */
        fun ensureChannel(context: Context) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val channel = NotificationChannel(
                    CHANNEL_ID,
                    context.getString(R.string.notif_channel_name),
                    NotificationManager.IMPORTANCE_HIGH   // heads-up + lock screen
                ).apply {
                    description = context.getString(R.string.notif_channel_desc)
                }
                context.getSystemService(NotificationManager::class.java)
                    ?.createNotificationChannel(channel)
            }
        }
    }

    override suspend fun doWork(): Result {
        val context = applicationContext

        // No signed-in session — nothing to poll for.
        if (!com.packtracker.utils.Session.isLoggedIn(context)) {
            Log.d(TAG, "No session; skipping poll")
            return Result.success()
        }

        // App is in the foreground: the in-app banner owns this message.
        if (com.packtracker.utils.GlobalNotificationPoller.PopupHost.currentActivity != null) {
            Log.d(TAG, "App in foreground; skipping tray notification")
            return Result.success()
        }

        val unread = pollUnreadBlocking() ?: return Result.success()
        val lastSeen = com.packtracker.utils.NotifState.getLastUnread(context)

        // First run after (re)install or state clear: baseline, don't spam.
        if (lastSeen < 0) {
            com.packtracker.utils.NotifState.setLastUnread(context, unread)
            com.packtracker.utils.NotifState.setLastNotifiedId(context, com.packtracker.utils.NotifState.LAST_ID_UNKNOWN)
            return Result.success()
        }

        if (unread <= lastSeen) {
            com.packtracker.utils.NotifState.setLastUnread(context, unread)
            return Result.success()
        }

        // New messages! Fetch the newest few so the tray can group them.
        val newestList = fetchRecentBlocking(limit = TrayNotifier.MAX_CHILDREN)
        val lastNotifiedId = com.packtracker.utils.NotifState.getLastNotifiedId(context)
        val fresh = newestList.filter { item ->
            lastNotifiedId == com.packtracker.utils.NotifState.LAST_ID_UNKNOWN ||
                    (item.id ?: 0) > lastNotifiedId
        }
        if (fresh.isEmpty()) {
            // Already surfaced via FCM or the in-app banner (count drift).
            com.packtracker.utils.NotifState.setLastUnread(context, unread)
            return Result.success()
        }

        postGrouped(context, fresh)

        com.packtracker.utils.NotifState.setLastUnread(context, unread)
        fresh.maxOfOrNull { it.id ?: 0 }?.let {
            com.packtracker.utils.NotifState.setLastNotifiedId(context, it)
        }
        return Result.success()
    }

    /** Synchronous unread poll via CountDownLatch (project idiom). */
    private fun pollUnreadBlocking(): Int? {
        val latch = CountDownLatch(1)
        val result = java.util.concurrent.atomic.AtomicReference<Int?>(null)
        try {
            ApiClient.getService(applicationContext).getUnreadNotificationCount()
                .enqueue(object : Callback<ApiResponse<UnreadData>> {
                    override fun onResponse(
                        call: Call<ApiResponse<UnreadData>>,
                        response: Response<ApiResponse<UnreadData>>
                    ) {
                        if (response.body()?.success == true) {
                            result.set(response.body()?.data?.unread ?: 0)
                        }
                        latch.countDown()
                    }

                    override fun onFailure(call: Call<ApiResponse<UnreadData>>, t: Throwable) {
                        Log.d(TAG, "Unread poll failed: ${t.message}")
                        latch.countDown()
                    }
                })
            latch.await(30, TimeUnit.SECONDS)
        } catch (e: Exception) {
            Log.e(TAG, "pollUnreadBlocking error", e)
        }
        return result.get()
    }

    /** Synchronous recent-messages fetch; the list endpoint marks it read. */
    private fun fetchRecentBlocking(limit: Int): List<NotificationItem> {
        val latch = CountDownLatch(1)
        val result = java.util.concurrent.atomic.AtomicReference<List<NotificationItem>>(emptyList())
        try {
            ApiClient.getService(applicationContext).getNotifications(limit = limit)
                .enqueue(object : Callback<ApiResponse<List<NotificationItem>>> {
                    override fun onResponse(
                        call: Call<ApiResponse<List<NotificationItem>>>,
                        response: Response<ApiResponse<List<NotificationItem>>>
                    ) {
                        result.set(response.body()?.data.orEmpty())
                        latch.countDown()
                    }

                    override fun onFailure(call: Call<ApiResponse<List<NotificationItem>>>, t: Throwable) {
                        Log.d(TAG, "Recent fetch failed: ${t.message}")
                        latch.countDown()
                    }
                })
            latch.await(30, TimeUnit.SECONDS)
        } catch (e: Exception) {
            Log.e(TAG, "fetchRecentBlocking error", e)
        }
        return result.get()
    }

    /**
     * Add each fresh message to the grouped tray. The first entry alerts;
     * the rest join silently under the same summary (one heads-up total).
     */
    private fun postGrouped(context: Context, items: List<NotificationItem>) {
        items.forEachIndexed { index, item ->
            TrayNotifier.add(
                context,
                notificationId = item.id ?: 0,
                title = item.title?.ifBlank { null } ?: context.getString(R.string.notif_fallback_title),
                body = item.body,
                ref = item.tracking_number,
                alert = index == 0,
                type = item.type,
                trackingNumber = item.tracking_number
            )
        }
    }
}
