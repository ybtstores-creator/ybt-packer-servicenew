package com.packtracker.utils

import android.content.Context

/**
 * Tiny persisted state shared by every notification channel (FCM push,
 * in-app banner poller, WorkManager fallback poll) so a message is surfaced
 * exactly once, wherever the user is.
 *
 * Replaces the NotifState object that previously lived inside
 * NotificationPollWorker.kt — same prefs file ("notif_state"), so existing
 * state survives the refactor.
 */
object NotifState {
    private const val KEY_LAST_UNREAD = "last_unread"
    private const val KEY_LAST_NOTIFIED_ID = "last_notified_id"

    /** Sentinel meaning "no id baseline yet". */
    const val LAST_ID_UNKNOWN = -1

    private fun prefs(context: Context) =
        context.getSharedPreferences("notif_state", Context.MODE_PRIVATE)

    fun getLastUnread(context: Context): Int = prefs(context).getInt(KEY_LAST_UNREAD, -1)

    fun setLastUnread(context: Context, value: Int) {
        prefs(context).edit().putInt(KEY_LAST_UNREAD, value).apply()
    }

    fun getLastNotifiedId(context: Context): Int =
        prefs(context).getInt(KEY_LAST_NOTIFIED_ID, LAST_ID_UNKNOWN)

    fun setLastNotifiedId(context: Context, value: Int) {
        prefs(context).edit().putInt(KEY_LAST_NOTIFIED_ID, value).apply()
    }

    /** A push was shown for this inbox row — the banner poller must skip it. */
    fun markPushSeen(context: Context, notificationId: Int) {
        if (notificationId != LAST_ID_UNKNOWN) setLastNotifiedId(context, notificationId)
    }

    /** The banner showed this inbox row — the WorkManager poller must skip it. */
    fun markInAppSeen(context: Context, notificationId: Int) {
        markPushSeen(context, notificationId)
    }

    fun clear(context: Context) {
        prefs(context).edit().clear().apply()
    }
}
