package com.packtracker.fragments

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageButton
import android.widget.TextView
import androidx.fragment.app.Fragment
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.packtracker.R
import com.packtracker.activities.MainActivity
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.NotificationItem
import com.packtracker.utils.GlobalNotificationPoller
import com.packtracker.utils.NotificationPopup
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

/**
 * Notification inbox: server-side status/packing/chat notifications,
 * newest first. Opening the list marks them read server-side, so the
 * bottom-nav badge is refreshed right after each load.
 */
class NotificationsFragment : Fragment() {

    companion object {
        private const val POLL_MS = 60000L
    }

    private lateinit var rv: RecyclerView
    private lateinit var tvEmpty: TextView
    private val handler = Handler(Looper.getMainLooper())
    private var pollRunnable: Runnable? = null

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_notifications, container, false)

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        rv = view.findViewById(R.id.rvNotifications)
        tvEmpty = view.findViewById(R.id.tvNotificationsEmpty)
        rv.layoutManager = LinearLayoutManager(requireContext())

        view.findViewById<ImageButton>(R.id.btnRefreshNotifications).setOnClickListener { load() }

        load()
    }

    override fun onResume() {
        super.onResume()
        startPolling()
        // Don't pop banners over the inbox the user is already reading — and
        // re-baseline the poller, since the list just marked everything read.
        NotificationPopup.suppressed = true
        (activity as? MainActivity)?.let { GlobalNotificationPoller.pollNow(it) }
    }

    override fun onPause() {
        super.onPause()
        NotificationPopup.suppressed = false
        stopPolling()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        stopPolling()
    }

    private fun startPolling() {
        stopPolling()
        pollRunnable = object : Runnable {
            override fun run() {
                if (isAdded) {
                    load(silent = true)
                    handler.postDelayed(this, POLL_MS)
                }
            }
        }
        handler.postDelayed(pollRunnable!!, POLL_MS)
    }

    private fun stopPolling() {
        pollRunnable?.let { handler.removeCallbacks(it) }
        pollRunnable = null
    }

    private fun load(silent: Boolean = false) {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getNotifications()
            .enqueue(object : Callback<ApiResponse<List<NotificationItem>>> {
                override fun onResponse(
                    call: Call<ApiResponse<List<NotificationItem>>>,
                    response: Response<ApiResponse<List<NotificationItem>>>
                ) {
                    if (!isAdded) return
                    val body = response.body()
                    if (response.isSuccessful && body?.success == true) {
                        render(body.data.orEmpty())
                        // The list endpoint marks everything read — re-baseline
                        // the global poller so a fresh message still pops, and
                        // sync the bottom-nav badge.
                        GlobalNotificationPoller.pollNow(requireContext())
                        (activity as? MainActivity)?.refreshNotificationBadge()
                    } else if (!silent) {
                        render(emptyList())
                    }
                }

                override fun onFailure(call: Call<ApiResponse<List<NotificationItem>>>, t: Throwable) {
                    // Keep whatever is already on screen during transient errors.
                }
            })
    }

    private fun render(items: List<NotificationItem>) {
        if (items.isEmpty()) {
            tvEmpty.visibility = View.VISIBLE
            rv.visibility = View.GONE
        } else {
            tvEmpty.visibility = View.GONE
            rv.visibility = View.VISIBLE
        }
        rv.adapter = NotificationAdapter(items)
    }

    private inner class NotificationAdapter(private val items: List<NotificationItem>) :
        RecyclerView.Adapter<NotificationAdapter.NotifVH>() {

        inner class NotifVH(v: View) : RecyclerView.ViewHolder(v) {
            val dot: View = v.findViewById(R.id.viewUnreadDot)
            val title: TextView = v.findViewById(R.id.tvNotifTitle)
            val time: TextView = v.findViewById(R.id.tvNotifTime)
            val body: TextView = v.findViewById(R.id.tvNotifBody)
            val ref: TextView = v.findViewById(R.id.tvNotifRef)
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): NotifVH =
            NotifVH(
                LayoutInflater.from(parent.context)
                    .inflate(R.layout.item_notification, parent, false)
            )

        override fun onBindViewHolder(holder: NotifVH, position: Int) {
            val n = items[position]
            holder.title.text = n.title?.ifBlank { null } ?: "Notification"
            holder.dot.visibility = if (n.is_read == true) View.INVISIBLE else View.VISIBLE

            if (n.body.isNullOrBlank()) {
                holder.body.visibility = View.GONE
            } else {
                holder.body.visibility = View.VISIBLE
                holder.body.text = n.body
            }

            if (n.tracking_number.isNullOrBlank()) {
                holder.ref.visibility = View.GONE
            } else {
                holder.ref.visibility = View.VISIBLE
                holder.ref.text = n.tracking_number
            }

            holder.time.text = shortDate(n.created_at)
        }

        override fun getItemCount() = items.size
    }

    /** "2026-09-14 16:22:05" -> "14 Sep, 16:22" (same year) / "14 Sep 2026". */
    private fun shortDate(raw: String?): String {
        if (raw.isNullOrBlank()) return ""
        return try {
            val parts = raw.take(10).split("-")
            if (parts.size != 3) return raw
            val month = when (parts[1].toInt()) {
                1 -> "Jan"; 2 -> "Feb"; 3 -> "Mar"; 4 -> "Apr"; 5 -> "May"; 6 -> "Jun"
                7 -> "Jul"; 8 -> "Aug"; 9 -> "Sep"; 10 -> "Oct"; 11 -> "Nov"; else -> "Dec"
            }
            val now = java.util.Calendar.getInstance()
            val yearSuffix = if (parts[0].toInt() == now.get(java.util.Calendar.YEAR)) "" else " ${parts[0]}"
            val time = if (raw.length >= 16) raw.substring(11, 16) else null
            listOf("${parts[2]} $month$yearSuffix", time)
                .filter { !it.isNullOrBlank() }
                .joinToString(", ")
        } catch (_: Exception) { raw }
    }
}
