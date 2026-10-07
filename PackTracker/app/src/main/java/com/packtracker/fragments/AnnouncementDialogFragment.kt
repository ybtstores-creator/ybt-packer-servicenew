package com.packtracker.fragments

import android.app.Dialog
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.Window
import android.widget.Button
import android.widget.TextView
import androidx.fragment.app.DialogFragment
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

/**
 * One announcement queued by [AnnouncementChecker] (parsed from
 * ybt_mobile_announcements_unread).
 */
data class Announcement(
    val id: Int? = null,
    val title: String? = null,
    val body: String? = null,
    val created_at: String? = null
)

/**
 * Admin-announcement popup: a modal dialog shown right after sign-in (or on
 * the next launch) until the user taps "Got it", which marks the row read on
 * the server (ybt_mobile_announcements_dismiss). Multiple unread messages
 * queue up one after another.
 *
 * The queue travels through [pendingQueue] rather than a Bundle — the dialog
 * is always shown in the same process immediately after the queue is filled,
 * and this keeps the data class free of Parcelable plumbing.
 *
 * Mirrors the web dashboard's announcement modal
 * (templates/components/notifications-bell.php + assets/js/ybt-timeline.js).
 */
class AnnouncementDialogFragment : DialogFragment() {

    companion object {
        private const val TAG = "Announcement"

        /** Queue set by [AnnouncementChecker] right before showing the dialog. */
        @JvmStatic
        var pendingQueue: List<Announcement> = emptyList()
    }

    private val queue = mutableListOf<Announcement>()
    private var shownItem: Announcement? = null

    override fun onCreateDialog(savedInstanceState: Bundle?): Dialog {
        val dialog = super.onCreateDialog(savedInstanceState)
        dialog.requestWindowFeature(Window.FEATURE_NO_TITLE)
        return dialog
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        queue.clear()
        queue.addAll(pendingQueue)
        pendingQueue = emptyList()
        return inflater.inflate(R.layout.dialog_announcement, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        val tvKicker = view.findViewById<TextView>(R.id.tvAnnKicker)
        val tvTitle = view.findViewById<TextView>(R.id.tvAnnTitle)
        val tvBody = view.findViewById<TextView>(R.id.tvAnnBody)
        val tvDate = view.findViewById<TextView>(R.id.tvAnnDate)
        val btnGotIt = view.findViewById<Button>(R.id.btnAnnGotIt)

        btnGotIt.setOnClickListener {
            shownItem?.id?.let { id -> markRead(id) }
            shownItem = null
            showNext(tvKicker, tvTitle, tvBody, tvDate, btnGotIt)
        }

        showNext(tvKicker, tvTitle, tvBody, tvDate, btnGotIt)
    }

    private fun showNext(
        tvKicker: TextView,
        tvTitle: TextView,
        tvBody: TextView,
        tvDate: TextView,
        btnGotIt: Button
    ) {
        val item = queue.removeFirstOrNull()
        if (item == null) {
            dismissAllowingStateLoss()
            return
        }
        shownItem = item

        tvTitle.text = item.title?.takeIf { it.isNotBlank() } ?: "Announcement"
        tvBody.text = item.body.orEmpty()
        tvBody.visibility = if (item.body.isNullOrBlank()) View.GONE else View.VISIBLE
        tvDate.text = item.created_at.orEmpty()
        // Keep the button enabled even while the network call runs — the
        // dialog moves on immediately and the dismiss fires fire-and-forget.
        btnGotIt.isEnabled = true
    }

    private fun markRead(id: Int) {
        ApiClient.getService(requireContext()).dismissAnnouncement(id = id)
            .enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(
                    call: Call<ApiResponse<String>>,
                    response: Response<ApiResponse<String>>
                ) {
                    if (response.body()?.success != true) {
                        Log.w(TAG, "dismiss failed: ${response.body()?.message}")
                    }
                }

                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    Log.w(TAG, "dismiss network failure", t)
                    // Stays unread on the server; it pops again next launch.
                }
            })
    }
}
