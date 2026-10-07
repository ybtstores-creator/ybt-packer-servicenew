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
import com.packtracker.activities.MainActivity
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
    private lateinit var adapter: TrackingListAdapter
    private val items = mutableListOf<TrackingItem>()
    private var currentStatus: String = "all"
    private var currentSearch: String = ""

    // The server paginates, so the filter has to travel with the request:
    // client-side filtering over one page hides everything past the newest rows.
    private var nextOffset = 0
    private var loading = false
    private var endReached = false
    private var requestSeq = 0
    private val debounce = android.os.Handler(android.os.Looper.getMainLooper())

    companion object {
        private const val PAGE_SIZE = 50
        private const val SEARCH_DEBOUNCE_MS = 350L
    }

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
        adapter = TrackingListAdapter(items)
        recycler.adapter = adapter

        editSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) {}
            override fun onTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) {}
            override fun afterTextChanged(s: Editable?) {
                currentSearch = s?.toString()?.trim() ?: ""
                debounce.removeCallbacksAndMessages(null)
                debounce.postDelayed({ loadParcels() }, SEARCH_DEBOUNCE_MS)
            }
        })

        chipGroup.setOnCheckedStateChangeListener { _, checkedIds ->
            currentStatus = when (checkedIds.firstOrNull()) {
                R.id.chipPending -> "not_received"
                R.id.chipDelivered -> "completed"
                else -> "all"
            }
            loadParcels()
        }

        recycler.addOnScrollListener(object : RecyclerView.OnScrollListener() {
            override fun onScrolled(rv: RecyclerView, dx: Int, dy: Int) {
                val lm = rv.layoutManager as? LinearLayoutManager ?: return
                if (dy > 0 && !loading && !endReached &&
                    lm.findLastVisibleItemPosition() >= items.size - 5
                ) {
                    loadMore()
                }
            }
        })

        // A failed load shows this text; tapping retries.
        tvEmpty.setOnClickListener { loadParcels() }

        loadParcels()
    }

    /**
     * Deep-link consumer: opened from a parcel notification. Waits for the
     * list to load, then pops the detail sheet for that tracking number.
     */
    private fun consumePendingParcel() {
        val wanted = MainActivity.pendingParcelTrackingNumber ?: return
        MainActivity.pendingParcelTrackingNumber = null
        if (wanted.isBlank()) return

        // The sheet needs the full row; the list is the source. If the parcel
        // isn't in this page (or the load failed), the tab still opens normally.
        val match = items.firstOrNull { it.tracking_number == wanted }
        if (match != null) {
            if (!isAdded || parentFragmentManager.isStateSaved) return
            val fm = activity?.supportFragmentManager ?: return
            ParcelDetailSheet.newInstance(match).show(fm, "parcel_detail")
        }
    }

    override fun onResume() {
        super.onResume()
        // Always refresh on return — keeps status/date changes from the
        // dashboard, web console or admin edits visible without manual reload.
        if (view != null) loadParcels()
    }

    override fun onDestroyView() {
        debounce.removeCallbacksAndMessages(null)
        super.onDestroyView()
    }

    private var loadFailed: Boolean = false

    private fun loadParcels() {
        requestSeq++
        nextOffset = 0
        endReached = false
        items.clear()
        fetch()
    }

    private fun loadMore() {
        requestSeq++
        fetch()
    }

    private fun fetch() {
        if (!isAdded) return
        val seq = requestSeq
        loading = true
        val reset = nextOffset == 0
        if (reset) render()

        ApiClient.getService(requireContext()).getTracking(
            status = currentStatus,
            search = currentSearch.ifEmpty { null },
            limit = PAGE_SIZE,
            offset = nextOffset
        ).enqueue(object : Callback<ApiResponse<TrackingData>> {
            override fun onResponse(call: Call<ApiResponse<TrackingData>>, response: Response<ApiResponse<TrackingData>>) {
                if (!isAdded || seq != requestSeq) return
                loading = false
                loadFailed = !response.isSuccessful || response.body() == null
                val page = response.body()?.data?.items ?: emptyList()
                if (!loadFailed) {
                    items.addAll(page)
                    nextOffset += page.size
                    endReached = page.size < PAGE_SIZE
                }
                render()
                // Deep-link target may live anywhere in the paginated list.
                if (MainActivity.pendingParcelTrackingNumber != null) {
                    if (items.any { it.tracking_number == MainActivity.pendingParcelTrackingNumber }) {
                        consumePendingParcel()
                    } else if (endReached) {
                        consumePendingParcel()  // clears the pending flag gracefully
                    } else if (nextOffset == 0 && items.isNotEmpty()) {
                        // keep loading pages until found or end
                    }
                }
            }

            override fun onFailure(call: Call<ApiResponse<TrackingData>>, t: Throwable) {
                if (!isAdded || seq != requestSeq) return
                loading = false
                loadFailed = true
                render()
            }
        })
    }

    private fun render() {
        if (!isAdded) return
        adapter.notifyDataSetChanged()
        tvEmpty.visibility = if (items.isEmpty()) View.VISIBLE else View.GONE
        recycler.visibility = if (items.isEmpty()) View.GONE else View.VISIBLE
        if (items.isEmpty()) {
            tvEmpty.text = if (loadFailed) "Couldn't load parcels — tap to retry"
                else if (currentStatus != "all" || currentSearch.isNotEmpty()) "No matching parcels"
                else "No parcels yet"
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
        // Presentation only: web-style status chip (was colored text)
        com.packtracker.utils.StatusChipStyle.bind(holder.tvStatus, item.status)

        // Parse images for thumbnail preview
        val urls = mutableListOf<String>()
        item.images?.split(",")?.forEach {
            val resolved = UrlUtils.fixUrl(it)
            if (!resolved.isNullOrBlank() && !urls.contains(resolved)) urls.add(resolved)
        }
        item.carton_images?.split(",")?.forEach {
            val resolved = UrlUtils.fixUrl(it)
            if (!resolved.isNullOrBlank() && !urls.contains(resolved)) urls.add(resolved)
        }

        if (urls.isNotEmpty()) {
            holder.cardThumbnail.visibility = View.VISIBLE
            val firstUrl = urls.first()
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
