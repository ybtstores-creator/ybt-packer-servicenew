package com.packtracker.fragments

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.packtracker.R
import com.packtracker.models.ChatMessage

/**
 * WhatsApp-style chat adapter shared by the user chat screen and the admin
 * conversation screen. The *viewer's own* messages always render in green
 * bubbles on the right; the other party's messages render in white bubbles
 * on the left. Supports photo messages and date separator rows.
 *
 * @param mineIsAdmin true when the viewer is the admin (admin's messages are
 *                    the ones with is_admin=1); false for the user chat.
 */
class WhatsAppChatAdapter(
    private val items: List<ChatMessage>,
    private val showSenderNames: Boolean = false,
    private val mineIsAdmin: Boolean = false,
    private val onImageClick: ((ChatMessage) -> Unit)? = null
) : RecyclerView.Adapter<RecyclerView.ViewHolder>() {

    companion object {
        private const val TYPE_DATE = 0
        private const val TYPE_SENT = 1
        private const val TYPE_RECEIVED = 2
    }

    private class Row(val date: String?, val message: ChatMessage?, val showDate: Boolean)

    private val rows: List<Row>

    init {
        val list = mutableListOf<Row>()
        var lastDay = ""
        for (m in items) {
            val day = m.date_created?.take(10) ?: ""
            if (day.isNotEmpty() && day != lastDay) {
                list.add(Row(m.date_created?.take(10), null, true))
                lastDay = day
            }
            list.add(Row(null, m, false))
        }
        rows = list
    }

    class VH(v: View) : RecyclerView.ViewHolder(v) {
        // The date-row layout only has tvDateHeader; the rest resolve to null.
        val tvDate: TextView? = try { v.findViewById(R.id.tvDateHeader) } catch (_: Exception) { null }
        val bubble: View? = try { v.findViewById(R.id.bubble) } catch (_: Exception) { null }
        val tvSender: TextView? = try { v.findViewById(R.id.tvSender) } catch (_: Exception) { null }
        val tvMessage: TextView? = try { v.findViewById(R.id.tvMessage) } catch (_: Exception) { null }
        val ivImage: ImageView? = try { v.findViewById(R.id.ivImage) } catch (_: Exception) { null }
        val tvTime: TextView? = try { v.findViewById(R.id.tvTime) } catch (_: Exception) { null }
        val ivStatus: ImageView? = try { v.findViewById(R.id.ivStatus) } catch (_: Exception) { null }
    }

    override fun getItemViewType(position: Int): Int {
        val row = rows[position]
        return when {
            row.date != null -> TYPE_DATE
            isMine(row.message) -> TYPE_SENT
            else -> TYPE_RECEIVED
        }
    }

    /** A message is "mine" (right side) when it was sent by the viewer. */
    private fun isMine(m: ChatMessage?): Boolean {
        m ?: return false
        val fromAdmin = (m.is_admin ?: 0) == 1
        return if (mineIsAdmin) fromAdmin else !fromAdmin
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): RecyclerView.ViewHolder {
        val layout = when (viewType) {
            TYPE_DATE -> R.layout.item_chat_date
            TYPE_SENT -> R.layout.item_chat_message_sent
            else -> R.layout.item_chat_message_received
        }
        return VH(LayoutInflater.from(parent.context).inflate(layout, parent, false))
    }

    override fun onBindViewHolder(holder: RecyclerView.ViewHolder, position: Int) {
        val vh = holder as VH
        val row = rows[position]

        if (row.date != null) {
            vh.tvDate?.text = friendlyDate(row.date)
            return
        }

        val m = row.message ?: return
        val hasImage = !m.attachment.isNullOrBlank()
        val hasText = !m.message.isNullOrBlank()

        if (showSenderNames) {
            if (!isMine(m)) {
                vh.tvSender?.visibility = View.VISIBLE
                vh.tvSender?.text = m.displayName ?: "Support"
            } else {
                vh.tvSender?.visibility = View.GONE
            }
        } else {
            vh.tvSender?.visibility = View.GONE
        }

        if (hasImage) {
            vh.ivImage?.visibility = View.VISIBLE
            vh.ivImage?.let { iv ->
                // Emulator can't resolve the host's "localhost" — remap like
                // the other image loaders in the app do.
                val url = m.attachment
                    ?.replace("http://localhost/", "http://10.0.2.2/")
                    ?.replace("https://localhost/", "https://10.0.2.2/")
                    ?.replace("http://127.0.0.1/", "http://10.0.2.2/")
                    ?: m.attachment
                Glide.with(iv).load(url).placeholder(R.drawable.bg_glass_pill).into(iv)
                iv.setOnClickListener { onImageClick?.invoke(m) }
            }
        } else {
            vh.ivImage?.visibility = View.GONE
        }

        if (hasText) {
            vh.tvMessage?.visibility = View.VISIBLE
            vh.tvMessage?.text = m.message
        } else {
            vh.tvMessage?.visibility = View.GONE
        }

        vh.tvTime?.text = m.timeDisplay ?: ""

        if (isMine(m)) {
            vh.ivStatus?.let { iv ->
                iv.visibility = View.VISIBLE
                if (m.isRead) {
                    iv.setImageResource(R.drawable.ic_check_all)
                    iv.setColorFilter(android.graphics.Color.parseColor("#34B7F1"))
                    iv.contentDescription = "Read"
                } else {
                    iv.setImageResource(R.drawable.ic_check)
                    iv.setColorFilter(android.graphics.Color.parseColor("#7A8B7A"))
                    iv.contentDescription = "Sent"
                }
            }
        } else {
            vh.ivStatus?.visibility = View.GONE
        }
    }

    override fun getItemCount() = rows.size

    private fun friendlyDate(day: String): String {
        // day is yyyy-MM-dd
        val today = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US)
            .format(java.util.Date())
        val cal = java.util.Calendar.getInstance()
        try {
            cal.time = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).parse(day)!!
        } catch (_: Exception) { return day }
        val yesterday = java.util.Calendar.getInstance().apply { add(java.util.Calendar.DAY_OF_YEAR, -1) }
        val yStr = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).format(yesterday.time)
        return when (day) {
            today -> "Today"
            yStr -> "Yesterday"
            else -> java.text.SimpleDateFormat("dd MMM yyyy", java.util.Locale.US).format(cal.time)
        }
    }
}
