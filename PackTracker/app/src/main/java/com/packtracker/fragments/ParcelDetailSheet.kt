package com.packtracker.fragments

import android.app.Dialog
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageButton
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.google.android.material.bottomsheet.BottomSheetDialog
import com.google.android.material.bottomsheet.BottomSheetDialogFragment
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.TimelineData
import com.packtracker.models.TimelineEvent
import com.packtracker.models.TrackingItem
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

/**
 * Bottom sheet showing full details for one tracking number:
 * added / received / packed / completed dates, item count, weight,
 * shipping method, the server-side event history and content images.
 */
class ParcelDetailSheet : BottomSheetDialogFragment() {

    companion object {
        private const val ARG_ITEM = "item"

        fun newInstance(item: TrackingItem): ParcelDetailSheet =
            ParcelDetailSheet().apply {
                // Arguments are persisted by the FragmentManager, so the sheet
                // survives process death (a plain field assignment would not).
                arguments = Bundle().apply { putSerializable(ARG_ITEM, item) }
            }
    }

    private var item: TrackingItem? = null

    override fun onCreateDialog(savedInstanceState: Bundle?): Dialog {
        val dialog = super.onCreateDialog(savedInstanceState) as BottomSheetDialog
        // Show the content fully expanded rather than half-screen.
        dialog.behavior.peekHeight = (resources.displayMetrics.heightPixels * 0.62).toInt()
        return dialog
    }

    @Suppress("DEPRECATION")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        item = arguments?.getSerializable(ARG_ITEM) as? TrackingItem
        if (item == null) dismissAllowingStateLoss()
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return inflater.inflate(R.layout.sheet_parcel_detail, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        val parcel = item
        if (parcel == null) {
            dismissAllowingStateLoss()
            return
        }

        view.findViewById<ImageButton>(R.id.btnCloseSheet).setOnClickListener { dismiss() }

        view.findViewById<TextView>(R.id.tvSheetTrackingNumber).text =
            parcel.tracking_number ?: "—"

        val statusRaw = parcel.status ?: "not_received"
        val statusLabel = statusRaw.replace('_', ' ')
            .replaceFirstChar { it.uppercase() }
        view.findViewById<TextView>(R.id.tvSheetStatus).apply {
            text = statusLabel
            com.packtracker.utils.StatusChipStyle.bind(this, statusRaw)
        }

        view.findViewById<TextView>(R.id.tvSheetDescription).text =
            parcel.description?.ifBlank { null } ?: "No description"

        view.findViewById<TextView>(R.id.tvSheetItemCount).text =
            (parcel.item_count ?: 0).toString()

        view.findViewById<TextView>(R.id.tvSheetWeight).text =
            parcel.weight_kg?.let { String.format("%.1f", it) } ?: "—"

        view.findViewById<TextView>(R.id.tvSheetMethod).text =
            parcel.shipping_method?.replaceFirstChar { it.uppercase() } ?: "—"

        view.findViewById<TextView>(R.id.tvSheetDateAdded).text = fmtDate(parcel.date_added)
        view.findViewById<TextView>(R.id.tvSheetDateReceived).text = fmtDate(parcel.date_received)
        view.findViewById<TextView>(R.id.tvSheetDatePacked).text = fmtDate(parcel.date_packed)
        view.findViewById<TextView>(R.id.tvSheetDateCompleted).text = fmtDate(parcel.date_completed)

        setupTimeline(view, parcel.id)
        setupImages(view, parcel)
    }

    /** Canonical YBT status palette (mirrors ybt_get_status_badge_color()); resolved
        from resources so the hues lift with the night colour set. */
    private fun statusColor(status: String?): Int {
        val id = when (status) {
            "received" -> R.color.status_received
            "packing_in_progress" -> R.color.status_packing
            "packed" -> R.color.status_packed
            "completed" -> R.color.status_completed
            else -> R.color.status_not_received
        }
        return requireContext().getColor(id)
    }

    private fun fmtDate(raw: String?): String {
        if (raw.isNullOrBlank()) return "—"
        // "2026-09-14 16:22:05" -> "14 Sep 2026, 16:22"
        return try {
            val datePart = raw.take(10)
            val timePart = if (raw.length >= 16) raw.substring(11, 16) else ""
            val parts = datePart.split("-")
            if (parts.size == 3) {
                val month = when (parts[1].toInt()) {
                    1 -> "Jan"; 2 -> "Feb"; 3 -> "Mar"; 4 -> "Apr"; 5 -> "May"; 6 -> "Jun"
                    7 -> "Jul"; 8 -> "Aug"; 9 -> "Sep"; 10 -> "Oct"; 11 -> "Nov"; else -> "Dec"
                }
                if (timePart.isNotBlank()) "${parts[2]} $month ${parts[0]}, $timePart"
                else "${parts[2]} $month ${parts[0]}"
            } else raw
        } catch (_: Exception) { raw }
    }

    private fun setupTimeline(view: View, trackingId: Int?) {
        val recycler = view.findViewById<RecyclerView>(R.id.recyclerSheetTimeline)
        val tvNone = view.findViewById<TextView>(R.id.tvSheetNoTimeline)

        fun showEmpty() {
            recycler.visibility = View.GONE
            tvNone.visibility = View.VISIBLE
        }

        if (trackingId == null || trackingId <= 0) {
            showEmpty()
            return
        }

        recycler.layoutManager = LinearLayoutManager(requireContext())
        ApiClient.getService(requireContext())
            .getParcelTimeline(trackingId = trackingId, limit = 20)
            .enqueue(object : Callback<ApiResponse<TimelineData>> {
                override fun onResponse(
                    call: Call<ApiResponse<TimelineData>>,
                    response: Response<ApiResponse<TimelineData>>
                ) {
                    if (!isAdded) return
                    val body = response.body()
                    val events =
                        if (response.isSuccessful && body?.success == true) body.data?.items.orEmpty()
                        else emptyList()
                    if (events.isEmpty()) {
                        showEmpty()
                    } else {
                        tvNone.visibility = View.GONE
                        recycler.visibility = View.VISIBLE
                        recycler.adapter = TimelineAdapter(events)
                    }
                }

                override fun onFailure(call: Call<ApiResponse<TimelineData>>, t: Throwable) {
                    if (isAdded) showEmpty()
                }
            })
    }

    /** Vertical adapter for the parcel's server-side event history. */
    private inner class TimelineAdapter(private val events: List<TimelineEvent>) :
        RecyclerView.Adapter<TimelineAdapter.EventVH>() {

        inner class EventVH(v: View) : RecyclerView.ViewHolder(v) {
            val dot: ImageView = v.findViewById(R.id.ivTimelineDot)
            val title: TextView = v.findViewById(R.id.tvTimelineTitle)
            val message: TextView = v.findViewById(R.id.tvTimelineMessage)
            val meta: TextView = v.findViewById(R.id.tvTimelineMeta)
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): EventVH =
            EventVH(
                LayoutInflater.from(parent.context)
                    .inflate(R.layout.item_timeline_row, parent, false)
            )

        override fun onBindViewHolder(holder: EventVH, position: Int) {
            val event = events[position]

            holder.dot.setColorFilter(statusColor(event.status_to))

            holder.title.text = event.title?.ifBlank { null }
                ?: (event.event_type ?: "Update").replace('_', ' ')
                    .replaceFirstChar { it.uppercase() }

            if (event.message.isNullOrBlank()) {
                holder.message.visibility = View.GONE
            } else {
                holder.message.visibility = View.VISIBLE
                holder.message.text = event.message
            }

            val metaBits = mutableListOf<String>()
            fmtDate(event.occurred_at).takeIf { it != "—" }?.let { metaBits.add(it) }
            event.actor_name?.ifBlank { null }?.let { metaBits.add("by $it") }
            if (metaBits.isEmpty()) {
                holder.meta.visibility = View.GONE
            } else {
                holder.meta.visibility = View.VISIBLE
                holder.meta.text = metaBits.joinToString(" · ")
            }
        }

        override fun getItemCount() = events.size
    }

    private fun setupImages(view: View, parcel: TrackingItem) {
        val recycler = view.findViewById<RecyclerView>(R.id.recyclerSheetImages)
        val tvNo = view.findViewById<TextView>(R.id.tvSheetNoImages)

        val urls = mutableListOf<String>()
        parcel.images?.split(",")?.forEach { if (it.isNotBlank()) urls.add(it.trim()) }
        parcel.carton_images?.split(",")?.forEach {
            if (it.isNotBlank() && !urls.contains(it.trim())) urls.add(it.trim())
        }

        if (urls.isEmpty()) {
            recycler.visibility = View.GONE
            tvNo.visibility = View.VISIBLE
            return
        }

        recycler.visibility = View.VISIBLE
        tvNo.visibility = View.GONE
        recycler.layoutManager =
            LinearLayoutManager(requireContext(), LinearLayoutManager.HORIZONTAL, false)
        recycler.adapter = DetailImageAdapter(urls)
    }

    /** Horizontal gallery adapter for the parcel's content images. */
    private inner class DetailImageAdapter(private val urls: List<String>) :
        RecyclerView.Adapter<DetailImageAdapter.ImgVH>() {

        inner class ImgVH(v: View) : RecyclerView.ViewHolder(v) {
            val iv: android.widget.ImageView = v.findViewById(R.id.ivDetailImage)
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ImgVH =
            ImgVH(LayoutInflater.from(parent.context).inflate(R.layout.item_detail_image, parent, false))

        override fun onBindViewHolder(holder: ImgVH, position: Int) {
            val raw = urls[position]
            val url = com.packtracker.utils.UrlUtils.fixUrl(raw) ?: raw
            Glide.with(holder.iv.context).load(url)
                .centerCrop()
                .placeholder(R.color.grey_light)
                .into(holder.iv)

            holder.iv.setOnClickListener { showFullScreenPhoto(url) }
        }

        override fun getItemCount() = urls.size
    }

    /** Full-screen photo view. */
    private fun showFullScreenPhoto(url: String) {
        if (!isAdded) return
        val dialog = Dialog(requireContext(), android.R.style.Theme_Black_NoTitleBar_Fullscreen)
        val iv = android.widget.ImageView(requireContext())
        iv.scaleType = android.widget.ImageView.ScaleType.FIT_CENTER
        Glide.with(this).load(url).fitCenter().into(iv)
        iv.setOnClickListener { dialog.dismiss() }
        dialog.setContentView(iv)
        dialog.show()
    }
}
