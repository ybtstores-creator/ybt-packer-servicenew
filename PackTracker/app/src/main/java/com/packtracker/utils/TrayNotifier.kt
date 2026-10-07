package com.packtracker.utils

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.packtracker.R
import com.packtracker.activities.MainActivity

/**
 * Grouped tray notifications, Gmail-style: up to [MAX_CHILDREN] individual
 * messages as silent children under a summary that alerts (heads-up, sound)
 * and expands to an InboxStyle digest.
 *
 * Entries persist across process death (SharedPreferences) so the summary
 * count stays truthful after the system kills the app. Posting is idempotent
 * per message id — the same id never appears twice in the group.
 *
 * Used by both the FCM service (instant) and the WorkManager fallback poll
 * (delayed); each just calls [add] with the inbox row it wants to surface.
 */
object TrayNotifier {

    private const val TAG = "TrayNotifier"

    const val GROUP_KEY = "com.packtracker.NOTIF_GROUP"
    const val ACTION_DISMISS = "com.packtracker.action.NOTIF_DISMISS"
    const val EXTRA_INDEX = "dismiss_index"

    /** Stable ids for the notification manager. */
    private const val SUMMARY_ID = 4001
    private const val CHILD_ID_BASE = 4100   // child id = BASE + index in shown list

    /** Max individual children rendered under the summary. */
    const val MAX_CHILDREN = 5
    private const val MAX_PERSISTED = 20

    private const val PREFS = "tray_notifier"
    private const val KEY_ENTRIES = "entries"

    // ── Persisted entries ────────────────────────────────────────────────

    private class Entry(
        val id: Int,
        val title: String,
        val body: String,
        val ref: String?,
        val type: String? = null,       // notification type: chat_message / tracking_update / …
        val trackingNumber: String? = null,
        val chatUserId: Int = 0,
        val chatUserName: String? = null
    )

    @Synchronized
    private fun loadEntries(context: Context): MutableList<Entry> {
        val json = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(KEY_ENTRIES, null) ?: return mutableListOf()
        return try {
            val arr = com.google.gson.JsonParser.parseString(json).asJsonArray
            arr.map { e ->
                val o = e.asJsonObject
                Entry(
                    id = o.get("id")?.asInt ?: 0,
                    title = o.get("t")?.asString ?: "",
                    body = o.get("b")?.asString ?: "",
                    ref = o.get("r")?.takeIf { !it.isJsonNull }?.asString,
                    type = o.get("ty")?.takeIf { !it.isJsonNull }?.asString,
                    trackingNumber = o.get("tn")?.takeIf { !it.isJsonNull }?.asString,
                    chatUserId = o.get("cu")?.takeIf { !it.isJsonNull }?.asInt ?: 0,
                    chatUserName = o.get("cn")?.takeIf { !it.isJsonNull }?.asString
                )
            }.toMutableList()
        } catch (_: Exception) {
            mutableListOf()
        }
    }

    @Synchronized
    private fun saveEntries(context: Context, entries: List<Entry>) {
        val arr = com.google.gson.JsonArray()
        entries.forEach { e ->
            arr.add(com.google.gson.JsonObject().apply {
                addProperty("id", e.id)
                addProperty("t", e.title)
                addProperty("b", e.body)
                e.ref?.let { addProperty("r", it) }
                e.type?.let { addProperty("ty", it) }
                e.trackingNumber?.let { addProperty("tn", it) }
                if (e.chatUserId > 0) addProperty("cu", e.chatUserId)
                e.chatUserName?.let { addProperty("cn", it) }
            })
        }
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
            .putString(KEY_ENTRIES, arr.toString())
            .apply()
    }

    // ── Public API ───────────────────────────────────────────────────────

    /**
     * Add a message to the group and (re)render the tray. Alerting is the
     * summary's job. Entries without a real inbox id (id <= 0) get a
     * synthetic negative id so they still dedupe and dismiss cleanly.
     */
    @Synchronized
    fun add(
        context: Context,
        notificationId: Int,
        title: String,
        body: String?,
        ref: String? = null,
        alert: Boolean = true,
        type: String? = null,
        trackingNumber: String? = null,
        chatUserId: Int = 0,
        chatUserName: String? = null
    ) {
        val id = if (notificationId > 0) notificationId else syntheticId()
        val entries = loadEntries(context)
        if (entries.any { it.id == id }) {
            // Already in the tray — just re-render (summary may need updating).
            render(context, entries, alert)
            return
        }
        entries.add(0, Entry(id, title, body ?: "", ref, type, trackingNumber, chatUserId, chatUserName))
        while (entries.size > MAX_PERSISTED) entries.removeAt(entries.size - 1)
        saveEntries(context, entries)
        render(context, entries, alert)
    }

    /** Negative, time-based id for pushes without an inbox row id. */
    private fun syntheticId(): Int = -(System.currentTimeMillis() / 1000L).toInt()

    /** Clear the whole group — used when the user opens the inbox. */
    @Synchronized
    fun clearAll(context: Context) {
        saveEntries(context, emptyList())
        val nm = NotificationManagerCompat.from(context)
        nm.cancel(SUMMARY_ID)
        for (i in 0 until MAX_CHILDREN) nm.cancel(CHILD_ID_BASE + i)
    }

    /** True when at least one entry is currently shown in the tray. */
    @Synchronized
    fun hasEntries(context: Context): Boolean = loadEntries(context).isNotEmpty()

    /** One child was swiped away: drop it and re-render without re-alerting. */
    @Synchronized
    fun onChildDismissed(context: Context, index: Int) {
        val entries = loadEntries(context)
        if (index < 0 || index >= entries.size) return
        entries.removeAt(index)
        saveEntries(context, entries)
        render(context, entries, alert = false)
    }

    // ── Rendering ────────────────────────────────────────────────────────

    private fun openInboxIntent(context: Context): PendingIntent =
        deepLinkIntent(context, null)

    /** Swipe-to-dismiss: remove that entry from state so counts stay truthful. */
    private fun dismissIntent(context: Context, childIndex: Int): PendingIntent {
        val intent = Intent(context, TrayDismissReceiver::class.java).apply {
            action = ACTION_DISMISS
            putExtra(EXTRA_INDEX, childIndex)
        }
        return PendingIntent.getBroadcast(
            context, CHILD_ID_BASE + childIndex, intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    /**
     * Per-entry tap target: chat → the conversation, parcel → the parcel
     * detail, anything else → the inbox. Resolved at build time from the
     * entry's persisted payload, so it works after process death too.
     */
    private fun deepLinkIntent(context: Context, entry: Entry?): PendingIntent {
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra(MainActivity.EXTRA_OPEN_NOTIFICATIONS, true)
            if (entry != null) {
                when {
                    entry.type == "chat_message" && entry.chatUserId > 0 -> {
                        putExtra(MainActivity.EXTRA_OPEN_CHAT, true)
                        putExtra(MainActivity.EXTRA_CHAT_USER_ID, entry.chatUserId)
                        putExtra(MainActivity.EXTRA_CHAT_USER_NAME, entry.chatUserName ?: "User")
                    }
                    entry.type == "tracking_update" && !entry.trackingNumber.isNullOrBlank() -> {
                        putExtra(MainActivity.EXTRA_OPEN_TRACKING, true)
                        putExtra(MainActivity.EXTRA_TRACKING_NUMBER, entry.trackingNumber)
                    }
                }
            }
        }
        // Request codes must differ per entry or later PendingIntents
        // silently overwrite earlier ones (FLAG_UPDATE_CURRENT).
        val requestCode = entry?.id ?: 0
        return PendingIntent.getActivity(
            context, requestCode, intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    private fun render(context: Context, entries: List<Entry>, alert: Boolean) {
        if (entries.isEmpty()) {
            clearAll(context)
            return
        }

        ensureChannel(context)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
        ) {
            Log.d(TAG, "POST_NOTIFICATIONS not granted; skipping tray render")
            return
        }

        val nm = NotificationManagerCompat.from(context)
        val shown = entries.take(MAX_CHILDREN)

        // ── Children: silent, grouped, individually dismissible ──────────
        shown.forEachIndexed { index, entry ->
            val child = NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_notifications)
                .setContentTitle(entry.title)
                .setContentText(entry.body)
                .setStyle(NotificationCompat.BigTextStyle().bigText(entry.body))
                .setGroup(GROUP_KEY)
                .setGroupSummary(false)
                .setSilent(true)                       // only the summary alerts
                .setCategory(NotificationCompat.CATEGORY_MESSAGE)
                .setContentIntent(deepLinkIntent(context, entry))
                .setDeleteIntent(dismissIntent(context, index))
                .setAutoCancel(true)
                .build()
            try {
                nm.notify(CHILD_ID_BASE + index, child)
            } catch (_: SecurityException) {}
        }
        // Cancel stale children left over from a bigger previous group
        // (e.g. after a swipe, the list shrank by one).
        for (i in shown.size until MAX_CHILDREN) {
            nm.cancel(CHILD_ID_BASE + i)
        }

        // ── Summary: alerts + expands to an InboxStyle digest ────────────
        val digest = NotificationCompat.InboxStyle()
            .setSummaryText(context.getString(R.string.app_name))
        shown.forEach { entry ->
            digest.addLine(entry.title + (entry.body.takeIf { it.isNotBlank() }?.let { " — $it" } ?: ""))
        }
        if (entries.size > MAX_CHILDREN) {
            digest.addLine(context.getString(R.string.notif_more_line, entries.size - MAX_CHILDREN))
        }

        val count = entries.size
        val summary = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notifications)
            .setContentTitle(context.resources.getQuantityString(R.plurals.notif_new_messages, count, count))
            .setContentText(shown.first().title)
            .setStyle(digest)
            .setNumber(count)
            .setGroup(GROUP_KEY)
            .setGroupSummary(true)
            // New message → new heads-up; child posts and dismiss-resets stay silent.
            .setOnlyAlertOnce(!alert)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setContentIntent(openInboxIntent(context))
            .build()

        try {
            nm.notify(SUMMARY_ID, summary)
        } catch (_: SecurityException) {}
    }

    private const val CHANNEL_ID = "new_notifications"

    private fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                context.getString(R.string.notif_channel_name),
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = context.getString(R.string.notif_channel_desc)
            }
            context.getSystemService(NotificationManager::class.java)
                ?.createNotificationChannel(channel)
        }
    }
}

/**
 * Swipe-dismiss receiver: forwards to [TrayNotifier.onChildDismissed] so the
 * persisted group stays in sync with what the user actually sees.
 */
class TrayDismissReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != TrayNotifier.ACTION_DISMISS) return
        val index = intent.getIntExtra(TrayNotifier.EXTRA_INDEX, -1)
        if (index >= 0) {
            TrayNotifier.onChildDismissed(context, index)
        }
    }
}
