package com.packtracker.utils

import android.os.Handler
import android.os.Looper
import android.util.Log
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.packtracker.R

/**
 * Receives FCM pushes from the WordPress sender (YBT_Notifications).
 *
 * Two delivery modes:
 *  - App in background / closed: adds to the grouped tray notification
 *    (children + InboxStyle summary via [TrayNotifier]) that deep-links to
 *    the Notifications screen.
 *  - App in foreground: shows the in-app heads-up banner instead — no tray
 *    icon while the user is already inside the app.
 *
 * The message's `notification_id` data field mirrors the inbox row the
 * server wrote, so the persisted NotifState can dedupe against banners and
 * the WorkManager fallback poll.
 */
class PackTrackerMessagingService : FirebaseMessagingService() {

    companion object {
        private const val TAG = "FCMService"
    }

    override fun onNewToken(token: String) {
        Log.d(TAG, "FCM token refreshed")
        FcmTokenManager.storeToken(this, token)
        // Fire-and-forget: if the server call fails, MainActivity's retry on
        // next launch picks it up (updateFcmToken with the stored token).
        FcmTokenManager.syncToServer(this)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val data = message.data

        // Foreground: the user is looking at the app — use the in-app banner.
        if (GlobalNotificationPoller.PopupHost.currentActivity != null) {
            val activity = GlobalNotificationPoller.PopupHost.currentActivity ?: return
            NotifState.markInAppSeen(
                this,
                data["notification_id"]?.toIntOrNull() ?: NotifState.LAST_ID_UNKNOWN
            )
            Handler(Looper.getMainLooper()).post {
                NotificationPopup.show(
                    activity,
                    title = message.notification?.title ?: data["title"] ?: "New notification",
                    body = message.notification?.body ?: data["body"],
                    ref = data["tracking_number"],
                    target = NotificationPopup.targetFor(
                        type = data["type"],
                        trackingNumber = data["tracking_number"],
                        chatUserId = data["sender_id"]?.toIntOrNull() ?: 0,
                        chatUserName = data["sender_name"]
                    )
                )
            }
            return
        }

        // Background / closed: grouped tray notification.
        addToTray(message)
    }

    private fun addToTray(message: RemoteMessage) {
        val data = message.data
        val notificationId = data["notification_id"]?.toIntOrNull() ?: NotifState.LAST_ID_UNKNOWN
        NotifState.markPushSeen(this, notificationId)

        val title = message.notification?.title ?: data["title"]
            ?: getString(R.string.notif_fallback_title)
        val body = message.notification?.body ?: data["body"] ?: ""

        // One code path for all tray rendering: children + InboxStyle summary.
        // type/chat ids/tracking number ride the data payload for deep links.
        TrayNotifier.add(
            this,
            notificationId = notificationId,
            title = title,
            body = body,
            ref = data["tracking_number"],
            type = data["type"],
            trackingNumber = data["tracking_number"],
            chatUserId = data["sender_id"]?.toIntOrNull() ?: 0,
            chatUserName = data["sender_name"]
        )
    }
}
