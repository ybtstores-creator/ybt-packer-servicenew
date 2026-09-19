package com.packtracker.fragments

import android.app.Activity
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.ImageButton
import android.widget.ImageView
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.google.android.material.bottomsheet.BottomSheetDialog
import com.google.android.material.textfield.TextInputEditText
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.*
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import java.io.File
import java.text.SimpleDateFormat
import java.util.*

class AdminPackingFragment : Fragment() {
    companion object { private const val TAG = "PackTracker" }

    private var allBatches = listOf<BatchHeader>()
    private lateinit var rv: RecyclerView
    private lateinit var tvEmpty: TextView
    private lateinit var adapter: AdminPackingAdapter

    // Image upload state
    private var uploadTrackingItem: TrackingItem? = null
    private var uploadPosition: Int = -1
    private var currentDetailAdapter: BatchTrackingItemAdapter? = null

    private val galleryLauncher = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let { uploadImageToServer(it) }
    }

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_admin_packing, container, false)

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        rv = view.findViewById(R.id.rvPacking)
        tvEmpty = view.findViewById(R.id.tvEmpty)
        val etSearch = view.findViewById<TextInputEditText>(R.id.etSearch)

        // Role guard: this tab hosts the admin packing console. Regular users
        // get a friendly notice instead of admin tools. (The admin APIs reject
        // non-admin tokens server-side regardless.)
        if (!com.packtracker.utils.Session.isAdmin(requireContext())) {
            tvEmpty.text = "Packing requests appear here after you submit one."
            tvEmpty.visibility = View.VISIBLE
            rv.visibility = View.GONE
            etSearch?.visibility = View.GONE
            return
        }

        rv.layoutManager = LinearLayoutManager(context)
        adapter = AdminPackingAdapter(emptyList()) { batch -> loadBatchDetails(batch) }
        rv.adapter = adapter

        etSearch?.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) { filterBatches(s?.toString() ?: "") }
        })

        loadPackingRequests()
    }

    private fun loadPackingRequests() {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getAdminPackingRequests()
            .enqueue(object : Callback<ApiResponse<BatchListData>> {
                override fun onResponse(call: Call<ApiResponse<BatchListData>>, response: Response<ApiResponse<BatchListData>>) {
                    if (!isAdded) return
                    allBatches = response.body()?.data?.batches ?: emptyList()
                    filterBatches("")
                }
                override fun onFailure(call: Call<ApiResponse<BatchListData>>, t: Throwable) {
                    if (!isAdded) return
                    tvEmpty.visibility = View.VISIBLE; rv.visibility = View.GONE
                }
            })
    }

    private fun filterBatches(query: String) {
        val q = query.lowercase().trim()
        val filtered = if (q.isEmpty()) allBatches else allBatches.filter { b ->
            (b.batch_id?.lowercase()?.contains(q) == true) ||
            (b.shipping_company_name?.lowercase()?.contains(q) == true) ||
            (b.username?.lowercase()?.contains(q) == true) ||
            (b.request_date?.contains(q) == true)
        }
        adapter.updateData(filtered)
        tvEmpty.visibility = if (filtered.isEmpty()) View.VISIBLE else View.GONE
        rv.visibility = if (filtered.isEmpty()) View.GONE else View.VISIBLE
    }

    private fun loadBatchDetails(batch: BatchHeader) {
        val batchId = batch.batch_id ?: return
        if (!isAdded) return
        ApiClient.getService(requireContext()).getAdminBatchDetails(batchId = batchId)
            .enqueue(object : Callback<ApiResponse<BatchDetailsData>> {
                override fun onResponse(call: Call<ApiResponse<BatchDetailsData>>, response: Response<ApiResponse<BatchDetailsData>>) {
                    if (!isAdded) return
                    response.body()?.data?.let { showBatchDetailSheet(batch, it) }
                }
                override fun onFailure(call: Call<ApiResponse<BatchDetailsData>>, t: Throwable) {}
            })
    }

    private var currentSheet: BottomSheetDialog? = null

    override fun onDestroyView() {
        currentSheet?.dismiss(); currentSheet = null
        super.onDestroyView()
    }

    private fun showBatchDetailSheet(batch: BatchHeader, details: BatchDetailsData) {
        val ctx = context ?: return
        val dialog = BottomSheetDialog(ctx)
        val view = LayoutInflater.from(ctx).inflate(R.layout.dialog_batch_detail, null)

        view.findViewById<TextView>(R.id.tvBatchId).text = batch.batch_id

        val allItems = details.items ?: emptyList()
        val cartons = details.cartons ?: emptyList()
        view.findViewById<TextView>(R.id.tvItemCount).text = "${allItems.size}"
        val totalWeight = cartons.sumOf { it.weight_kg?.toDoubleOrNull() ?: 0.0 }
        view.findViewById<TextView>(R.id.tvTotalWeight).text = "%.1f kg".format(totalWeight)
        view.findViewById<TextView>(R.id.tvCartonCount).text = "${cartons.size}"
        view.findViewById<TextView>(R.id.tvCompany).text = "\uD83D\uDEA2 ${batch.shipping_company_name ?: "\u2014"}"
        view.findViewById<TextView>(R.id.tvRequestDate).text = batch.request_date?.take(10) ?: ""

        // Tracking items
        val rvItems = view.findViewById<RecyclerView>(R.id.rvItems)
        val tvEmptyItems = view.findViewById<TextView>(R.id.tvEmptyItems)
        val tvFilterCount = view.findViewById<TextView>(R.id.tvFilterCount)
        val etItemSearch = view.findViewById<TextInputEditText>(R.id.etItemSearch)

        rvItems.layoutManager = LinearLayoutManager(ctx)
        val itemAdapter = BatchTrackingItemAdapter(allItems) { item, position ->
            // Upload button clicked — launch gallery
            uploadTrackingItem = item
            uploadPosition = position
            try { galleryLauncher.launch("image/*") } catch (_: Exception) {}
        }
        rvItems.adapter = itemAdapter
        currentDetailAdapter = itemAdapter

        tvFilterCount.text = "${allItems.size} items"
        tvEmptyItems.visibility = if (allItems.isEmpty()) View.VISIBLE else View.GONE
        rvItems.visibility = if (allItems.isEmpty()) View.GONE else View.VISIBLE

        // Filter tracking items
        etItemSearch?.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                val q = s?.toString()?.lowercase()?.trim() ?: ""
                val filtered = if (q.isEmpty()) allItems else allItems.filter { item ->
                    (item.tracking_number?.lowercase()?.contains(q) == true) ||
                    (item.description?.lowercase()?.contains(q) == true) ||
                    (item.username?.lowercase()?.contains(q) == true)
                }
                itemAdapter.updateData(filtered)
                tvFilterCount.text = "${filtered.size}/${allItems.size} items"
                tvEmptyItems.visibility = if (filtered.isEmpty()) View.VISIBLE else View.GONE
                rvItems.visibility = if (filtered.isEmpty()) View.GONE else View.VISIBLE
            }
        })

        // Cartons
        val rvCartons = view.findViewById<RecyclerView>(R.id.rvCartons)
        val tvEmptyCartons = view.findViewById<TextView>(R.id.tvEmptyCartons)
        rvCartons.layoutManager = LinearLayoutManager(ctx)
        if (cartons.isNotEmpty()) {
            rvCartons.adapter = BatchCartonAdapter(cartons)
            tvEmptyCartons.visibility = View.GONE
        } else {
            tvEmptyCartons.visibility = View.VISIBLE
        }

        currentSheet = dialog
        dialog.setOnDismissListener { currentSheet = null }
        dialog.setContentView(view)
        dialog.show()
    }

    // ═══════════════════════════════════════
    // Image Upload to Server
    // ═══════════════════════════════════════
    private fun uploadImageToServer(uri: Uri) {
        val item = uploadTrackingItem ?: return
        val position = uploadPosition
        val trackingId = item.id ?: return
        val ctx = context ?: return

        try {
            // Copy URI to temp file
            val inputStream = ctx.contentResolver.openInputStream(uri) ?: return
            val tempFile = File.createTempFile("upload_", ".jpg", ctx.cacheDir)
            tempFile.outputStream().use { inputStream.copyTo(it) }
            inputStream.close()

            val requestBody = tempFile.asRequestBody("image/jpeg".toMediaTypeOrNull())
            val filePart = MultipartBody.Part.createFormData("image", tempFile.name, requestBody)
            val actionPart = "ybt_mobile_admin_upload_image".toRequestBody("text/plain".toMediaTypeOrNull())
            val trackingIdPart = trackingId.toString().toRequestBody("text/plain".toMediaTypeOrNull())
            val itemCountPart = (item.item_count ?: 1).toString().toRequestBody("text/plain".toMediaTypeOrNull())

            ApiClient.getService(ctx).adminUploadImage(
                action = actionPart,
                trackingId = trackingIdPart,
                itemCount = itemCountPart,
                file = filePart
            ).enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    tempFile.delete()
                    if (!isAdded) return
                    if (response.isSuccessful && response.body()?.success == true) {
                        try { Toast.makeText(ctx, "Image uploaded: ${item.tracking_number}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                        // Reload batch details to refresh images
                        currentDetailAdapter?.notifyItemChanged(position)
                    } else {
                        try { Toast.makeText(ctx, "Upload failed: ${response.body()?.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                    }
                }
                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    tempFile.delete()
                    if (!isAdded) return
                    try { Toast.makeText(ctx, "Upload error: ${t.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                }
            })
        } catch (e: Exception) {
            try { Toast.makeText(ctx, "Error: ${e.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
        }
    }
}

// ═══════════════════════════════════════
// Admin Packing Batch List Adapter
// ═══════════════════════════════════════
class AdminPackingAdapter(
    private var items: List<BatchHeader>,
    private val onClick: (BatchHeader) -> Unit
) : RecyclerView.Adapter<AdminPackingAdapter.VH>() {

    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val tv1: TextView = v.findViewById(android.R.id.text1)
        val tv2: TextView = v.findViewById(android.R.id.text2)
    }

    fun updateData(newItems: List<BatchHeader>) {
        items = newItems
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(LayoutInflater.from(parent.context).inflate(android.R.layout.simple_list_item_2, parent, false))

    override fun onBindViewHolder(holder: VH, position: Int) {
        val b = items[position]
        holder.tv1.text = b.batch_id ?: "Batch"
        val parts = mutableListOf<String>()
        b.item_count?.let { parts.add("$it items") }
        b.shipping_company_name?.let { parts.add(it) }
        b.request_date?.take(10)?.let { parts.add(it) }
        holder.tv2.text = parts.joinToString(" \u00b7 ").ifEmpty { b.username ?: "Batch" }
        holder.itemView.setOnClickListener { onClick(b) }
    }

    override fun getItemCount() = items.size
}

// ═══════════════════════════════════════
// Batch Tracking Items Adapter (with image upload + thumbnails)
// ═══════════════════════════════════════
class BatchTrackingItemAdapter(
    private var items: List<TrackingItem>,
    private val onUploadClick: (TrackingItem, Int) -> Unit
) : RecyclerView.Adapter<BatchTrackingItemAdapter.VH>() {

    fun updateData(newItems: List<TrackingItem>) {
        items = newItems
        notifyDataSetChanged()
    }

    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val statusDot: View = v.findViewById(R.id.statusDot)
        val tvTrackingNumber: TextView = v.findViewById(R.id.tvTrackingNumber)
        val tvStatus: TextView = v.findViewById(R.id.tvStatus)
        val tvDescription: TextView = v.findViewById(R.id.tvDescription)
        val tvUser: TextView = v.findViewById(R.id.tvUser)
        val tvItems: TextView = v.findViewById(R.id.tvItems)
        val tvWeight: TextView = v.findViewById(R.id.tvWeight)
        val imageRow: View = v.findViewById(R.id.imageRow)
        val rvImages: RecyclerView = v.findViewById(R.id.rvImages)
        val btnUploadImage: ImageButton = v.findViewById(R.id.btnUploadImage)
        val tvUploadStatus: TextView = v.findViewById(R.id.tvUploadStatus)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_batch_tracking, parent, false))

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]

        holder.tvTrackingNumber.text = item.tracking_number ?: "\u2014"
        holder.tvDescription.text = item.description ?: "No description"
        holder.tvUser.text = item.username ?: "\u2014"
        holder.tvItems.text = "${item.item_count ?: 1} item(s)"
        holder.tvWeight.text = "${item.weight_kg ?: 0} kg"

        val status = item.status ?: ""
        val (statusText, color) = when (status) {
            "not_received" -> "Not Received" to Color.parseColor("#EF4444")
            "received" -> "Received" to Color.parseColor("#10B981")
            "packing_in_progress" -> "Packing" to Color.parseColor("#F59E0B")
            "packed" -> "Packed" to Color.parseColor("#3B82F6")
            "completed" -> "Completed" to Color.parseColor("#6366F1")
            else -> status to Color.parseColor("#6B7280")
        }
        holder.statusDot.setBackgroundColor(color)
        holder.tvStatus.text = statusText
        holder.tvStatus.setTextColor(color)

        // Show images row with thumbnails
        val images = parseImages(item.images)
        holder.imageRow.visibility = View.VISIBLE

        if (images.isNotEmpty()) {
            holder.rvImages.visibility = View.VISIBLE
            holder.rvImages.layoutManager = LinearLayoutManager(
                holder.itemView.context, LinearLayoutManager.HORIZONTAL, false
            )
            holder.rvImages.adapter = ImageThumbnailAdapter(images)
        } else {
            holder.rvImages.visibility = View.GONE
        }

        // Upload button
        holder.btnUploadImage.setOnClickListener {
            onUploadClick(item, holder.adapterPosition)
        }

        holder.tvUploadStatus.visibility = View.GONE
    }

    private fun parseImages(images: Any?): List<String> {
        return try {
            when (images) {
                is String -> {
                    if (images.isBlank() || images == "[]" || images == "null") emptyList()
                    else {
                        val trimmed = images.trim()
                        if (trimmed.startsWith("[")) {
                            val cleaned = trimmed.removeSurrounding("[", "]")
                            cleaned.split(",").map { it.trim().removeSurrounding("\"").removeSurrounding("'") }
                                .filter { it.isNotBlank() && it != "null" }
                        } else if (trimmed.contains(",")) {
                            trimmed.split(",").map { it.trim() }.filter { it.isNotBlank() }
                        } else listOf(trimmed)
                    }
                }
                is List<*> -> images.mapNotNull { it?.toString() }.filter { it.isNotBlank() }
                else -> emptyList()
            }
        } catch (e: Exception) { emptyList() }
    }

    override fun getItemCount() = items.size
}

// ═══════════════════════════════════════
// Image Thumbnail Adapter (horizontal row)
// ═══════════════════════════════════════
class ImageThumbnailAdapter(
    private val images: List<String>
) : RecyclerView.Adapter<ImageThumbnailAdapter.VH>() {

    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val iv: ImageView = v.findViewById(R.id.ivThumbnail)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_image_thumbnail, parent, false))

    override fun onBindViewHolder(holder: VH, position: Int) {
        val url = images[position].replace("localhost", "10.0.2.2").replace("127.0.0.1", "10.0.2.2")
        Glide.with(holder.itemView.context)
            .load(url)
            .placeholder(R.drawable.ic_camera)
            .centerCrop()
            .into(holder.iv)
    }

    override fun getItemCount() = images.size
}

// ═══════════════════════════════════════
// Cartons Adapter
// ═══════════════════════════════════════
class BatchCartonAdapter(
    private val items: List<Carton>
) : RecyclerView.Adapter<BatchCartonAdapter.VH>() {

    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val tvCartonTitle: TextView = v.findViewById(R.id.tvCartonTitle)
        val tvWeight: TextView = v.findViewById(R.id.tvWeight)
        val tvCost: TextView = v.findViewById(R.id.tvCost)
        val tvDate: TextView = v.findViewById(R.id.tvDate)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_batch_carton, parent, false))

    override fun onBindViewHolder(holder: VH, position: Int) {
        val carton = items[position]
        holder.tvCartonTitle.text = "Carton #${carton.carton_number ?: (position + 1)}"
        holder.tvWeight.text = "${carton.weight_kg ?: "\u2014"} kg"
        val amount = carton.payment_amount ?: "0"
        val currency = carton.payment_currency ?: "CNY"
        holder.tvCost.text = "$currency $amount"
        holder.tvDate.text = carton.date_created?.take(10) ?: ""
    }

    override fun getItemCount() = items.size
}
