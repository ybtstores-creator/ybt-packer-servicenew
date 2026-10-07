package com.packtracker.utils

import android.app.Activity
import android.app.Application
import android.content.Context
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import com.packtracker.AppContextHolder
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.NotificationItem
import com.packtracker.models.UnreadData
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import java.lang.ref.WeakReference

/**
 * Application-wide notification poller: watches the unread count no matter
 * which screen is open and pops a heads-up banner over whatever the user is
 * looking at (dashboard, chat conversation, barcode scanner, detail sheets).
 *
 * Lifecycle:
 *  - [start] called once per sign-in (MainActivity onCreate). Takes a
 *    baseline unread snapshot so the first poll never pops stale messages.
 *  - Polls every [POLL_MS] until [stop] (logout) — independent of any one
 *    activity, so the banner works everywhere in the app.
 *
 * Only one poll call is in flight at a time; [pollNow] lets other features
 * (e.g. the inbox refresh) piggy-back on the same cadence.
 */
object GlobalNotificationPoller {

    private const val TAG = "NotifPoller"
    private const val POLL_MS = 60000L

    private val handler = Handler(Looper.getMainLooper())
    private var pollRunnable: Runnable? = null
    private var inFlight = false

    /** Last unread count seen; -1 = no baseline yet, so never pop. */
    @Volatile
    private var lastUnreadCount = -1

    @Volatile
    private var running = false

    /** Begin polling with a baseline snapshot (no popup on the first tick). */
    @Synchronized
    fun start(context: Context) {
        if (running) return
        running = true
        lastUnreadCount = -1
        scheduleNext()
        Log.d(TAG, "Global notification polling started")
    }

    /** Stop polling (logout). Leaves any on-screen banner alone. */
    @Synchronized
    fun stop() {
        running = false
        pollRunnable?.let { handler.removeCallbacks(it) }
        pollRunnable = null
        lastUnreadCount = -1
        Log.d(TAG, "Global notification polling stopped")
    }

    /** Force a poll right now — used after the inbox is read to re-baseline. */
    fun pollNow(context: Context) {
        if (!running) return
        poll()
    }

    private fun scheduleNext() {
        pollRunnable?.let { handler.removeCallbacks(it) }
        pollRunnable = object : Runnable {
            override fun run() {
                if (!running) return
                poll()
                handler.postDelayed(this, POLL_MS)
            }
        }
        handler.postDelayed(pollRunnable!!, POLL_MS)
    }

    private fun poll() {
        if (inFlight) return
        inFlight = true
        val appContext = AppContextHolder.get()
        ApiClient.getService(appContext).getUnreadNotificationCount()
            .enqueue(object : Callback<ApiResponse<UnreadData>> {
                override fun onResponse(
                    call: Call<ApiResponse<UnreadData>>,
                    response: Response<ApiResponse<UnreadData>>
                ) {
                    inFlight = false
                    val data = response.body()?.data
                    val unread = data?.unread ?: 0
                    if (response.body()?.success != true) return

                    if (unread > 0 && lastUnreadCount >= 0 && unread > lastUnreadCount) {
                        fetchLatestAndPop(appContext)
                    }
                    lastUnreadCount = unread
                }

                override fun onFailure(call: Call<ApiResponse<UnreadData>>, t: Throwable) {
                    inFlight = false
                    // Offline or transient — keep the last known count.
                }
            })
    }

    /** Grab the newest inbox item so the banner shows real title/body/ref. */
    private fun fetchLatestAndPop(context: Context) {
        ApiClient.getService(context).getNotifications(limit = 1)
            .enqueue(object : Callback<ApiResponse<List<NotificationItem>>> {
                override fun onResponse(
                    call: Call<ApiResponse<List<NotificationItem>>>,
                    response: Response<ApiResponse<List<NotificationItem>>>
                ) {
                    val item = response.body()?.data?.firstOrNull()
                    pop(item)
                }

                override fun onFailure(call: Call<ApiResponse<List<NotificationItem>>>, t: Throwable) {
                    pop(null)
                }
            })
    }

    private fun pop(item: NotificationItem?) {
        val activity = PopupHost.currentActivity ?: return
        // Already staring at the inbox — the message will appear in the list.
        if (NotificationPopup.suppressed) return
        if (item != null) {
            NotificationPopup.show(activity, item)   // show() derives the target
        } else {
            NotificationPopup.show(activity, "New notification")
        }
    }

    /**
     * Tracks whichever activity is on top so the banner always has a host,
     * whatever screen the user is on.
     */
    object PopupHost : Application.ActivityLifecycleCallbacks {
        private var activityRef: WeakReference<Activity>? = null
        
        val currentActivity: Activity?
            get() = activityRef?.get()

        override fun onActivityCreated(activity: Activity, savedInstanceState: Bundle?) {}
        override fun onActivityStarted(activity: Activity) {}
        override fun onActivityResumed(activity: Activity) {
            activityRef = WeakReference(activity)
        }
        override fun onActivityPaused(activity: Activity) {
            if (activityRef?.get() === activity) activityRef = null
        }
        override fun onActivityStopped(activity: Activity) {}
        override fun onActivitySaveInstanceState(activity: Activity, savedInstanceState: Bundle) {}
        override fun onActivityDestroyed(activity: Activity) {
            if (activityRef?.get() === activity) activityRef = null
        }
    }
}
