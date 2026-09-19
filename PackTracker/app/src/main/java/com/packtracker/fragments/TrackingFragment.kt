package com.packtracker.fragments

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.google.android.material.chip.ChipGroup
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.TrackingData
import com.packtracker.models.TrackingItem
import com.packtracker.utils.UrlUtils
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

/**
 * Track tab: the user's own parcels with status filter chips and search.
 * Backed by ybt_mobile_tracking (server scopes results to the caller).
 */
class TrackingFragment : Fragment() {

    private lateinit var recycler: RecyclerView
    private lateinit var editSearch: EditText
    private lateinit var chipGroup: ChipGroup
    private lateinit var tvEmpty: TextView
    private var allItems: List<TrackingItem> = emptyList()
    private var currentStatus: String = "all"
    private var currentSearch: String = ""

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return inflater.inflate(R.layout.fragment_tracking, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        // Admins should see the system-wide Orders screen instead of personal empty tracking
        if (com.packtracker.utils.Session.isAdmin(requireContext())) {
            try {
                findNavController().navigate(
                    R.id.adminOrdersFragment,
                    null,
                    androidx.navigation.navOptions {
                        popUpTo(R.id.trackingFragment) { inclusive = true }
                    }
                )
            } catch (_: Exception) {}
            return
        }

        recycler = view.findViewById(R.id.recyclerTracking)
        editSearch = view.findViewById(R.id.editSearch)
        chipGroup = view.findViewById(R.id.chipGroupFilter)
        tvEmpty = view.findViewById(R.id.tvEmptyTracking)

        recycler.layoutManager = LinearLayoutManager(requireContext())

        editSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) {}
            override fun onTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) {}
            override fun afterTextChanged(s: Editable?) {
                currentSearch = s?.toString()?.trim() ?: ""
                applyFilters()
            }
        })

        chipGroup.setOnCheckedStateChangeListener { _, checkedIds ->
            currentStatus = when (checkedIds.firstOrNull()) {
                R.id.chipPending -> "not_received"
                R.id.chipDelivered -> "completed"
                else -> "all"
            }
            applyFilters()
        }

        // A failed load shows this text; tapping retries.
        tvEmpty.setOnClickListener { loadParcels() }

        loadParcels()
    }

    override fun onResume() {
        super.onResume()
        // Always refresh on return — keeps status/date changes from the
        // dashboard, web console or admin edits visible without manual reload.
        if (view != null) loadParcels()
    }

    private var loadFailed: Boolean = false

    private fun loadParcels() {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getTracking(status = "all")
            .enqueue(object : Callback<ApiResponse<TrackingData>> {
                override fun onResponse(call: Call<ApiResponse<TrackingData>>, response: Response<ApiResponse<TrackingData>>) {
                    if (!isAdded) return
                    loadFailed = !response.isSuccessful || response.body() == null
                    allItems = response.body()?.data?.items ?: emptyList()
                    applyFilters()
                }

                override fun onFailure(call: Call<ApiResponse<TrackingData>>, t: Throwable) {
                    if (!isAdded) return
                    loadFailed = true
                    applyFilters()
                }
            })
    }

    private fun applyFilters() {
        if (!isAdded) return
        var list = allItems
        if (currentStatus != "all") {
            list = list.filter { it.status.equals(currentStatus, ignoreCase = true) }
        }
        if (currentSearch.isNotEmpty()) {
            val q = currentSearch.lowercase()
            list = list.filter {
                (it.tracking_number ?: "").lowercase().contains(q) ||
                (it.description ?: "").lowercase().contains(q)
            }
        }
        recycler.adapter = TrackingListAdapter(list)
        tvEmpty.visibility = if (list.isEmpty()) View.VISIBLE else View.GONE
        recycler.visibility = if (list.isEmpty()) View.GONE else View.VISIBLE
        if (list.isEmpty()) {
            tvEmpty.text = if (loadFailed) "Couldn't load parcels — tap to retry" else "No parcels yet"
        }
    }
}

/** Simple adapter for the user's parcel rows. */
class TrackingListAdapter(private val items: List<TrackingItem>) :
    RecyclerView.Adapter<TrackingListAdapter.VH>() {

    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val tvNumber: TextView = v.findViewById(R.id.tvTrackingNumber)
        val tvStatus: TextView = v.findViewById(R.id.tvStatus)
        val tvDescription: TextView = v.findViewById(R.id.tvDescription)
        val tvDate: TextView = v.findViewById(R.id.tvDate)
        val cardThumbnail: View = v.findViewById(R.id.cardThumbnail)
        val ivThumbnail: ImageView = v.findViewById(R.id.ivThumbnail)
        val tvPhotoCount: TextView = v.findViewById(R.id.tvPhotoCount)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_tracking_row, parent, false))

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        holder.tvNumber.text = item.tracking_number ?: "—"
        holder.tvDescription.text = item.description?.ifBlank { "No description" } ?: "No description"
        holder.tvDate.text = item.date_added?.take(10) ?: ""
        val status = (item.status ?: "pending").replace('_', ' ')
        holder.tvStatus.text = status.replaceFirstChar { it.uppercase() }
        holder.tvStatus.setTextColor(
            when (item.status) {
                "received" -> 0xFF0E7490.toInt()
                "packing_in_progress" -> 0xFFB45309.toInt()
                "packed" -> 0xFF047857.toInt()
                "completed" -> 0xFF15803D.toInt()
                else -> 0xFF6B7280.toInt()
            }
        )

        // Parse images for thumbnail preview
        val urls = mutableListOf<String>()
        item.images?.split(",")?.forEach {
            val s = it.trim().removeSurrounding("\"").removeSurrounding("'")
            if (s.isNotBlank() && s != "null" && s != "[]" && s.startsWith("http")) urls.add(s)
        }
        item.carton_images?.split(",")?.forEach {
            val s = it.trim().removeSurrounding("\"").removeSurrounding("'")
            if (s.isNotBlank() && s != "null" && s != "[]" && s.startsWith("http") && !urls.contains(s)) urls.add(s)
        }

        if (urls.isNotEmpty()) {
            holder.cardThumbnail.visibility = View.VISIBLE
            val firstUrl = UrlUtils.fixUrl(urls.first())
            Glide.with(holder.ivThumbnail.context)
                .load(firstUrl)
                .centerCrop()
                .placeholder(R.color.grey_light)
                .error(R.color.grey_light)
                .into(holder.ivThumbnail)

            if (urls.size > 1) {
                holder.tvPhotoCount.visibility = View.VISIBLE
                holder.tvPhotoCount.text = "+${urls.size - 1}"
            } else {
                holder.tvPhotoCount.visibility = View.GONE
            }
        } else {
            holder.cardThumbnail.visibility = View.GONE
            holder.tvPhotoCount.visibility = View.GONE
            Glide.with(holder.ivThumbnail.context).clear(holder.ivThumbnail)
            holder.ivThumbnail.setImageDrawable(null)
        }

        // Tap anywhere on the row (incl. the tracking number) to open details.
        holder.itemView.setOnClickListener { view ->
            val fm = (view.context as? androidx.fragment.app.FragmentActivity)?.supportFragmentManager ?: return@setOnClickListener
            ParcelDetailSheet.newInstance(item).show(fm, "parcel_detail")
        }
    }

    override fun getItemCount() = items.size
}
