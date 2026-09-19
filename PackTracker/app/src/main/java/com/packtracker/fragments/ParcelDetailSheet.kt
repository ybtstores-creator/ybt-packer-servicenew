package com.packtracker.fragments

import android.app.Dialog
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageButton
import android.widget.TextView
import com.bumptech.glide.Glide
import com.google.android.material.bottomsheet.BottomSheetDialog
import com.google.android.material.bottomsheet.BottomSheetDialogFragment
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.packtracker.R
import com.packtracker.models.TrackingItem

/**
 * Bottom sheet showing full details for one tracking number:
 * added / received / packed / completed dates, item count, weight,
 * shipping method and the parcel's content images.
 */
class ParcelDetailSheet : BottomSheetDialogFragment() {

    companion object {
        fun newInstance(item: TrackingItem): ParcelDetailSheet {
            val sheet = ParcelDetailSheet()
            sheet.item = item
            return sheet
        }
    }

    private lateinit var item: TrackingItem

    override fun onCreateDialog(savedInstanceState: Bundle?): Dialog {
        val dialog = super.onCreateDialog(savedInstanceState) as BottomSheetDialog
        // Show the content fully expanded rather than half-screen.
        dialog.behavior.peekHeight = (resources.displayMetrics.heightPixels * 0.62).toInt()
        return dialog
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

        view.findViewById<ImageButton>(R.id.btnCloseSheet).setOnClickListener { dismiss() }

        view.findViewById<TextView>(R.id.tvSheetTrackingNumber).text =
            item.tracking_number ?: "—"

        val statusRaw = item.status ?: "not_received"
        val statusLabel = statusRaw.replace('_', ' ')
            .replaceFirstChar { it.uppercase() }
        view.findViewById<TextView>(R.id.tvSheetStatus).text = statusLabel
        view.findViewById<TextView>(R.id.tvSheetStatus).setTextColor(
            when (statusRaw) {
                "received" -> 0xFF0E7490.toInt()
                "packing_in_progress" -> 0xFFB45309.toInt()
                "packed" -> 0xFF047857.toInt()
                "completed" -> 0xFF15803D.toInt()
                else -> 0xFF6B7280.toInt()
            }
        )

        view.findViewById<TextView>(R.id.tvSheetDescription).text =
            item.description?.ifBlank { null } ?: "No description"

        view.findViewById<TextView>(R.id.tvSheetItemCount).text =
            (item.item_count ?: 0).toString()

        view.findViewById<TextView>(R.id.tvSheetWeight).text =
            item.weight_kg?.let { String.format("%.1f", it) } ?: "—"

        view.findViewById<TextView>(R.id.tvSheetMethod).text =
            item.shipping_method?.replaceFirstChar { it.uppercase() } ?: "—"

        view.findViewById<TextView>(R.id.tvSheetDateAdded).text = fmtDate(item.date_added)
        view.findViewById<TextView>(R.id.tvSheetDateReceived).text = fmtDate(item.date_received)
        view.findViewById<TextView>(R.id.tvSheetDatePacked).text = fmtDate(item.date_packed)
        view.findViewById<TextView>(R.id.tvSheetDateCompleted).text = fmtDate(item.date_completed)

        setupImages(view)
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

    private fun setupImages(view: View) {
        val recycler = view.findViewById<RecyclerView>(R.id.recyclerSheetImages)
        val tvNo = view.findViewById<TextView>(R.id.tvSheetNoImages)

        val urls = mutableListOf<String>()
        item.images?.split(",")?.forEach { if (it.isNotBlank()) urls.add(it.trim()) }
        item.carton_images?.split(",")?.forEach {
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
