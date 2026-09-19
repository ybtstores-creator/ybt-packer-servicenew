package com.packtracker.fragments

import android.app.Activity
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.provider.MediaStore
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.ImageView
import android.widget.Spinner
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import androidx.navigation.fragment.findNavController
import com.bumptech.glide.Glide
import com.google.android.material.bottomsheet.BottomSheetDialog
import com.google.android.material.textfield.TextInputEditText
import androidx.navigation.fragment.findNavController
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.OrderListData
import com.packtracker.models.TrackingItem
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import java.io.File
import java.io.FileOutputStream

class AdminOrdersFragment : Fragment() {

    companion object {
        private const val TAG = "PackTracker"
        private val STATUS_OPTIONS = arrayOf(
            "not_received", "received", "packing_in_progress", "packed", "completed"
        )
        private val STATUS_LABELS = arrayOf(
            "Not Received", "Received", "Packing in Progress", "Packed", "Completed"
        )
    }

    private lateinit var rvOrders: RecyclerView
    private lateinit var tvEmpty: TextView
    private lateinit var etSearch: TextInputEditText
    private var allOrders: List<TrackingItem> = emptyList()
    private var currentOrderId: Int? = null

    private val pickImageLauncher = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val uri = result.data?.data ?: return@registerForActivityResult
            val id = currentOrderId ?: return@registerForActivityResult
            uploadImage(id, uri)
        }
    }

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_admin_orders, container, false)

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        rvOrders = view.findViewById(R.id.rvOrders)
        tvEmpty = view.findViewById(R.id.tvEmpty)
        etSearch = view.findViewById(R.id.etSearch)
        rvOrders.layoutManager = LinearLayoutManager(context)

        // Role guard: non-admins must never see this screen at all — bounce
        // back to the dashboard quietly instead of rendering an empty shell.
        // (The server rejects the API calls anyway.)
        if (!com.packtracker.utils.Session.isAdmin(requireContext())) {
            try {
                findNavController().popBackStack(R.id.dashboardFragment, false)
            } catch (e: Exception) {
                // Nav not ready yet — leave; nothing admin will render.
            }
            return
        }

        loadOrders()

        etSearch.addTextChangedListener(object : android.text.TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                filterOrders(s.toString())
            }
            override fun afterTextChanged(s: android.text.Editable?) {}
        })
    }

    private fun loadOrders() {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getAdminOrders()
            .enqueue(object : Callback<ApiResponse<OrderListData>> {
                override fun onResponse(call: Call<ApiResponse<OrderListData>>, response: Response<ApiResponse<OrderListData>>) {
                    if (!isAdded) return
                    allOrders = response.body()?.data?.orders ?: emptyList()
                    rvOrders.adapter = OrderAdapter(allOrders, { order -> showOrderDetail(order) }, { order -> showEditHistory(order) })
                    tvEmpty.visibility = if (allOrders.isEmpty()) View.VISIBLE else View.GONE
                    rvOrders.visibility = if (allOrders.isEmpty()) View.GONE else View.VISIBLE
                }
                override fun onFailure(call: Call<ApiResponse<OrderListData>>, t: Throwable) {
                    if (!isAdded) return
                    tvEmpty.visibility = View.VISIBLE
                }
            })
    }

    private fun filterOrders(query: String) {
        val filtered = if (query.isBlank()) allOrders else allOrders.filter {
            it.tracking_number?.contains(query, true) == true ||
            it.description?.contains(query, true) == true ||
            it.username?.contains(query, true) == true ||
            it.status?.contains(query, true) == true
        }
        rvOrders.adapter = OrderAdapter(filtered, { order -> showOrderDetail(order) }, { order -> showEditHistory(order) })
        tvEmpty.visibility = if (filtered.isEmpty()) View.VISIBLE else View.GONE
        rvOrders.visibility = if (filtered.isEmpty()) View.GONE else View.VISIBLE
    }

    /**
     * Long-press anywhere on an order row (or on the dialog's tracking-number
     * header) to open the edit history: which admin made each change, when,
     * and what changed.
     */
    private fun showEditHistory(order: TrackingItem) {
        val id = order.id ?: return
        val fm = childFragmentManager
        if (fm.isStateSaved) return
        EditHistoryDialogFragment.newInstance(id, order.tracking_number)
            .show(fm, "edit_history")
    }

    private fun showOrderDetail(order: TrackingItem) {
        val context = context ?: return
        val dialog = BottomSheetDialog(context)
        val view = LayoutInflater.from(context).inflate(R.layout.dialog_order_detail, null)

        view.findViewById<TextView>(R.id.tvDetailTrackingNumber)?.let { header ->
            header.text = "#${order.tracking_number}"
            // Long-press the header for the edit history too.
            header.setOnLongClickListener {
                order.id?.let { id ->
                    EditHistoryDialogFragment.newInstance(id, order.tracking_number)
                        .show(childFragmentManager, "edit_history")
                }
                true
            }
        } ?: run { view.findViewById<TextView>(R.id.tvDetailTrackingNumber)?.text = "#${order.tracking_number}" }

        val spinner = view.findViewById<Spinner>(R.id.spinnerStatus)
        val statusAdapter = ArrayAdapter(context, android.R.layout.simple_spinner_item, STATUS_LABELS)
        statusAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        spinner.adapter = statusAdapter
        spinner.setSelection(STATUS_OPTIONS.indexOf(order.status).coerceAtLeast(0))

        view.findViewById<TextView>(R.id.tvDetailDescription).text = order.description ?: "N/A"
        view.findViewById<TextView>(R.id.tvDetailUser).text = order.username ?: "N/A"
        view.findViewById<TextView>(R.id.tvDetailItemCount).text = "${order.item_count ?: 0} items (${order.item_type ?: "unit"})"
        view.findViewById<TextView>(R.id.tvDetailShipping).text = order.shipping_method?.replaceFirstChar { it.uppercase() } ?: "N/A"
        view.findViewById<TextView>(R.id.tvDetailWeight).text = if (order.weight_kg != null) "${order.weight_kg} kg" else "Not weighed"
        view.findViewById<TextView>(R.id.tvDetailDateAdded).text = order.date_added ?: "N/A"
        view.findViewById<TextView>(R.id.tvDetailDateReceived).text = order.date_received ?: "Not yet received"

        // Images in detail view
        val rvImages = view.findViewById<RecyclerView>(R.id.rvImages)
        rvImages.layoutManager = GridLayoutManager(context, 3)
        val imageList = parseImages(order.images)
        rvImages.adapter = ImageAdapter(imageList)

        // Image upload button - check if receipt confirmed first
        view.findViewById<TextView>(R.id.btnUploadImage).setOnClickListener {
            if (order.status == "not_received") {
                android.app.AlertDialog.Builder(context)
                    .setTitle("Confirm Receipt First")
                    .setMessage("This tracking number is marked as Not Received. You must confirm receipt before uploading images. Change status to Received?")
                    .setPositiveButton("Confirm Receipt") { _, _ ->
                        updateStatus(order.id ?: return@setPositiveButton, "received", dialog)
                        currentOrderId = order.id
                        val intent = Intent(Intent.ACTION_PICK, MediaStore.Images.Media.EXTERNAL_CONTENT_URI)
                        pickImageLauncher.launch(intent)
                    }
                    .setNegativeButton("Cancel", null)
                    .show()
            } else {
                currentOrderId = order.id
                val intent = Intent(Intent.ACTION_PICK, MediaStore.Images.Media.EXTERNAL_CONTENT_URI)
                pickImageLauncher.launch(intent)
            }
        }

        // Delete button
        view.findViewById<View>(R.id.btnDeleteOrder)?.setOnClickListener {
            android.app.AlertDialog.Builder(context)
                .setTitle("Delete Order")
                .setMessage("Delete ${order.tracking_number}? This cannot be undone.")
                .setPositiveButton("Delete") { _, _ ->
                    deleteOrder(order.id ?: return@setPositiveButton, dialog)
                }
                .setNegativeButton("Cancel", null)
                .show()
        }

        // Update status button
        view.findViewById<Button>(R.id.btnUpdateStatus).setOnClickListener {
            val newStatus = STATUS_OPTIONS[spinner.selectedItemPosition]
            updateStatus(order.id ?: return@setOnClickListener, newStatus, dialog)
        }

        dialog.setContentView(view)
        dialog.show()
    }

    private fun uploadImage(trackingId: Int, uri: Uri) {
        if (!isAdded) return
        val context = requireContext()
        val inputStream = context.contentResolver.openInputStream(uri) ?: return
        val tempFile = File(context.cacheDir, "upload_${System.currentTimeMillis()}.jpg")
        FileOutputStream(tempFile).use { output -> inputStream.copyTo(output) }
        inputStream.close()

        val requestFile = tempFile.asRequestBody("image/*".toMediaTypeOrNull())
        val filePart = MultipartBody.Part.createFormData("image", tempFile.name, requestFile)
        val actionPart = "ybt_mobile_admin_upload_image".toRequestBody("text/plain".toMediaTypeOrNull())
        val idPart = trackingId.toString().toRequestBody("text/plain".toMediaTypeOrNull())
        val countPart = "1".toRequestBody("text/plain".toMediaTypeOrNull())

        ApiClient.getService(context).adminUploadImage(actionPart, idPart, countPart, filePart)
            .enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (!isAdded) return
                    try { Toast.makeText(context, "Image uploaded!", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                    tempFile.delete()
                    loadOrders()
                }
                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    if (!isAdded) return
                    try { Toast.makeText(context, "Upload failed: ${t.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                    tempFile.delete()
                }
            })
    }

    private fun updateStatus(trackingId: Int, newStatus: String, dialog: BottomSheetDialog) {
        if (!isAdded) return
        val context = requireContext()
        ApiClient.getService(context).updateOrderStatus(trackingId = trackingId, status = newStatus)
            .enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (!isAdded) return
                    val body = response.body()
                    if (response.isSuccessful && body?.success == true) {
                        try { Toast.makeText(context, "Status updated to $newStatus", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                        dialog.dismiss()
                        loadOrders()
                    } else {
                        try { Toast.makeText(context, body?.message ?: "Update failed", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                    }
                }
                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    if (!isAdded) return
                    try { Toast.makeText(context, "Error: ${t.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                }
            })
    }

    private fun deleteOrder(trackingId: Int, dialog: BottomSheetDialog) {
        if (!isAdded) return
        val context = requireContext()
        ApiClient.getService(context).deleteTracking(trackingId = trackingId)
            .enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (!isAdded) return
                    try { Toast.makeText(context, "Order deleted", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                    dialog.dismiss()
                    loadOrders()
                }
                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    if (!isAdded) return
                    try { Toast.makeText(context, "Failed: ${t.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                }
            })
    }

    private fun parseImages(images: Any?): List<String> {
        if (images == null) return emptyList()
        val result = mutableListOf<String>()
        if (images is String) {
            val trimmed = images.trim()
            if (trimmed.isEmpty() || trimmed == "[]" || trimmed == "null") return emptyList()
            try {
                val arr = org.json.JSONArray(trimmed)
                for (i in 0 until arr.length()) {
                    val item = arr.getString(i).trim()
                    if (item.startsWith("http")) result.add(item)
                }
                if (result.isNotEmpty()) return result
            } catch (_: Exception) {}
            val parts = trimmed.split(",")
            for (part in parts) {
                val url = part.trim().removeSurrounding("\"").removeSurrounding("'")
                if (url.startsWith("http") && !url.contains("[]")) {
                    result.add(url)
                }
            }
            return result
        }
        if (images is List<*>) {
            for (item in images) {
                val s = item?.toString()?.trim() ?: continue
                if (s.startsWith("http")) result.add(s)
            }
        }
        return result
    }
}

// ═══════════════════════════════════════
// Order Adapter with image thumbnails
// ═══════════════════════════════════════
class OrderAdapter(
    private val items: List<TrackingItem>,
    private val onClick: (TrackingItem) -> Unit,
    private val onLongClick: (TrackingItem) -> Unit = {}
) : RecyclerView.Adapter<OrderAdapter.VH>() {

    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val tvTrackingNumber: TextView = v.findViewById(R.id.tvTrackingNumber)
        val tvStatus: TextView = v.findViewById(R.id.tvStatus)
        val tvDescription: TextView = v.findViewById(R.id.tvDescription)
        val tvUsername: TextView = v.findViewById(R.id.tvUsername)
        val tvDate: TextView = v.findViewById(R.id.tvDate)
        val imageScroll: android.widget.HorizontalScrollView = v.findViewById(R.id.imageScroll)
        val imageContainer: android.widget.LinearLayout = v.findViewById(R.id.imageContainer)
    }

    private fun fixUrl(url: String): String {
        return url.replace("http://localhost/", "http://10.0.2.2/")
                  .replace("https://localhost/", "https://10.0.2.2/")
    }

    private fun parseImages(images: Any?): List<String> {
        if (images == null) return emptyList()
        val result = mutableListOf<String>()
        if (images is String) {
            val trimmed = images.trim()
            if (trimmed.isEmpty() || trimmed == "[]" || trimmed == "null") return emptyList()
            try {
                val arr = org.json.JSONArray(trimmed)
                for (i in 0 until arr.length()) {
                    val item = arr.getString(i).trim()
                    if (item.startsWith("http")) result.add(item)
                }
                if (result.isNotEmpty()) return result
            } catch (_: Exception) {}
            val parts = trimmed.split(",")
            for (part in parts) {
                val url = part.trim().removeSurrounding("\"").removeSurrounding("'")
                if (url.startsWith("http") && !url.contains("[]")) {
                    result.add(url)
                }
            }
            return result
        }
        if (images is List<*>) {
            for (item in images) {
                val s = item?.toString()?.trim() ?: continue
                if (s.startsWith("http")) result.add(s)
            }
        }
        return result
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_order, parent, false))

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        holder.tvTrackingNumber.text = item.tracking_number ?: "N/A"
        holder.tvDescription.text = item.description ?: "No description"
        holder.tvUsername.text = "@${item.username ?: "unknown"}"
        holder.tvDate.text = item.date_added?.take(10) ?: ""

        val statusText = item.status?.replace("_", " ")?.replaceFirstChar { it.uppercase() } ?: "?"
        holder.tvStatus.text = statusText
        val color = when (item.status) {
            "not_received" -> 0xFFEF4444.toInt()
            "received" -> 0xFF10B981.toInt()
            "packing_in_progress" -> 0xFFF59E0B.toInt()
            "packed" -> 0xFF3B82F6.toInt()
            "completed" -> 0xFF6366F1.toInt()
            else -> 0xFF94A3B8.toInt()
        }
        holder.tvStatus.setBackgroundColor(color)

        // Load image thumbnails
        val images = parseImages(item.images)
        holder.imageContainer.removeAllViews()
        if (images.isNotEmpty()) {
            holder.imageScroll.visibility = View.VISIBLE
            for (url in images.take(4)) {
                val iv = android.widget.ImageView(holder.itemView.context).apply {
                    layoutParams = android.widget.LinearLayout.LayoutParams(240, 240).apply { marginEnd = 8 }
                    scaleType = android.widget.ImageView.ScaleType.CENTER_CROP
                }
                holder.imageContainer.addView(iv)
                try {
                    Glide.with(holder.itemView.context)
                        .load(fixUrl(url))
                        .centerCrop()
                        .placeholder(android.R.drawable.ic_menu_gallery)
                        .error(android.R.drawable.ic_menu_gallery)
                        .into(iv)
                } catch (_: Exception) {}
            }
        } else {
            holder.imageScroll.visibility = View.GONE
        }

        holder.itemView.setOnClickListener { onClick(item) }
        holder.itemView.setOnLongClickListener {
            onLongClick(item)
            true
        }
    }

    override fun getItemCount() = items.size
}

// ═══════════════════════════════════════
// Image Adapter for detail view
// ═══════════════════════════════════════
class ImageAdapter(private val images: List<String>) : RecyclerView.Adapter<ImageAdapter.VH>() {
    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val ivImage: ImageView = v.findViewById(R.id.ivImage)
        val tvLabel: TextView = v.findViewById(R.id.tvImageLabel)
    }

    private fun fixUrl(url: String): String {
        return url.replace("http://localhost/", "http://10.0.2.2/")
                  .replace("https://localhost/", "https://10.0.2.2/")
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_order_image, parent, false))

    override fun onBindViewHolder(holder: VH, position: Int) {
        val url = images[position]
        holder.tvLabel.text = "Image ${position + 1}"
        try {
            Glide.with(holder.itemView.context)
                .load(fixUrl(url))
                .centerCrop()
                .placeholder(android.R.drawable.ic_menu_gallery)
                .error(android.R.drawable.ic_menu_gallery)
                .into(holder.ivImage)
        } catch (_: Exception) {
            holder.ivImage.setImageResource(android.R.drawable.ic_menu_gallery)
        }
    }

    override fun getItemCount() = images.size
}
