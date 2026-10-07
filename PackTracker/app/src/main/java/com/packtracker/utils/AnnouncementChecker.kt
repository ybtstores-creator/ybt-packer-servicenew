package com.packtracker.utils

import android.app.Activity
import android.util.Log
import androidx.fragment.app.FragmentActivity
import com.packtracker.api.ApiClient
import com.packtracker.fragments.Announcement
import com.packtracker.fragments.AnnouncementDialogFragment
import com.packtracker.models.ApiResponse
import com.packtracker.models.NotificationItem
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

/**
 * Admin-announcement popup at login: fetches the user's unread announcements
 * (ybt_mobile_announcements_unread) and pops them as a queued modal dialog.
 *
 * Called from MainActivity onCreate — i.e. right after sign-in (or on the
 * next launch if the app was closed). Rows stay unread server-side until the
 * user taps "Got it", so a message survives force-kills and re-installation
 * and never pops twice in one session.
 *
 * Web counterpart: assets/js/ybt-timeline.js (announcement modal).
 */
object AnnouncementChecker {

    private const val TAG = "Announcement"
    private var inFlight = false

    /**
     * Fetch unread announcements and show the dialog if any exist.
     * Safe to call every launch: no items → no-op; network failure → silent.
     */
    fun checkAndShow(activity: Activity) {
        if (activity.isFinishing || activity.isDestroyed) return
        if (inFlight) return
        inFlight = true

        ApiClient.getService(activity).getAnnouncements()
            .enqueue(object : Callback<ApiResponse<List<NotificationItem>>> {
                override fun onResponse(
                    call: Call<ApiResponse<List<NotificationItem>>>,
                    response: Response<ApiResponse<List<NotificationItem>>>
                ) {
                    inFlight = false
                    if (activity.isFinishing || activity.isDestroyed) return
                    if (activity !is FragmentActivity) return

                    val body = response.body()
                    val items = body?.data
                    if (body?.success != true || items.isNullOrEmpty()) return

                    val queue = items.mapNotNull {
                        if (it.id == null) null
                        else Announcement(
                            id = it.id,
                            title = it.title,
                            body = it.body,
                            created_at = it.created_at
                        )
                    }
                    if (queue.isEmpty()) return

                    AnnouncementDialogFragment.pendingQueue = queue
                    AnnouncementDialogFragment()
                        .show(activity.supportFragmentManager, "AnnouncementDialog")
                }

                override fun onFailure(
                    call: Call<ApiResponse<List<NotificationItem>>>,
                    t: Throwable
                ) {
                    inFlight = false
                    // Offline or transient — the announcement pops next launch.
                    Log.d(TAG, "announcement fetch failed", t)
                }
            })
    }
}
