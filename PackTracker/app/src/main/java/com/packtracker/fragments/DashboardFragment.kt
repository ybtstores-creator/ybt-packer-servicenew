package com.packtracker.fragments

import android.Manifest
import android.content.ClipData
import android.content.ClipboardManager
import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.drawable.Drawable
import android.media.MediaScannerConnection
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.provider.MediaStore
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.bumptech.glide.Glide
import com.bumptech.glide.load.DataSource
import com.bumptech.glide.load.engine.GlideException
import com.bumptech.glide.request.RequestListener
import com.bumptech.glide.request.target.CustomTarget
import com.bumptech.glide.request.target.Target
import com.bumptech.glide.request.transition.Transition
import com.packtracker.R
import com.packtracker.activities.BarcodeScannerActivity
import com.packtracker.api.ApiClient
import com.packtracker.models.AdminDashboardData
import com.packtracker.models.ApiResponse
import com.packtracker.models.DashboardData
import com.packtracker.models.DashboardInfo
import com.packtracker.utils.UrlUtils
import java.io.File
import java.io.FileInputStream
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class DashboardFragment : Fragment() {

    companion object {
        private const val TAG = "PackTracker"
        private const val UNAVAILABLE = "Unavailable"
    }

    private lateinit var tvNotReceived: TextView
    private lateinit var tvReceived: TextView
    private lateinit var tvPacked: TextView
    private lateinit var tvCompleted: TextView
    private lateinit var tvAnnouncement: TextView
    private lateinit var tvWarehouseAddress: TextView
    private lateinit var tvWarehousePhone: TextView
    private lateinit var tvWarehouseHours: TextView
    private lateinit var tvBankName: TextView
    private lateinit var tvAccountName: TextView
    private lateinit var tvAccountNumber: TextView
    private lateinit var ivQrCode: ImageView
    private var qrCodeUrl: String? = null

    private val qrPermissionLauncher = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) {
            qrCodeUrl?.let { fetchAndSaveQr(it) }
        } else {
            context?.let { Toast.makeText(it, "Permission needed to save the QR", Toast.LENGTH_SHORT).show() }
        }
    }
    private lateinit var swipeRefresh: androidx.swiperefreshlayout.widget.SwipeRefreshLayout

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? {
        return inflater.inflate(R.layout.fragment_dashboard, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        tvNotReceived = view.findViewById(R.id.tvNotReceived)
        tvReceived = view.findViewById(R.id.tvReceived)
        tvPacked = view.findViewById(R.id.tvPacked)
        tvCompleted = view.findViewById(R.id.tvCompleted)
        tvAnnouncement = view.findViewById(R.id.tvAnnouncement)
        tvWarehouseAddress = view.findViewById(R.id.tvWarehouseAddress)
        tvWarehousePhone = view.findViewById(R.id.tvWarehousePhone)
        tvWarehouseHours = view.findViewById(R.id.tvWarehouseHours)
        tvBankName = view.findViewById(R.id.tvBankName)
        tvAccountName = view.findViewById(R.id.tvAccountName)
        tvAccountNumber = view.findViewById(R.id.tvAccountNumber)
        ivQrCode = view.findViewById(R.id.ivQrCode)
        swipeRefresh = view.findViewById(R.id.swipeRefresh)

        swipeRefresh.setOnRefreshListener { loadDashboard() }

        val isAdmin = com.packtracker.utils.Session.isAdmin(requireContext())

        val tvServiceStatus = view.findViewById<TextView>(R.id.tvServiceStatus)
        val tvHeroText = view.findViewById<TextView>(R.id.tvHeroText)
        val tvNotReceivedLabel = view.findViewById<TextView>(R.id.tvNotReceivedLabel)
        val tvReceivedLabel = view.findViewById<TextView>(R.id.tvReceivedLabel)
        val tvPackedLabel = view.findViewById<TextView>(R.id.tvPackedLabel)
        val tvCompletedLabel = view.findViewById<TextView>(R.id.tvCompletedLabel)
        val cardAdminOrders = view.findViewById<View>(R.id.cardAdminOrders)
        val cardAdminUsers = view.findViewById<View>(R.id.cardAdminUsers)
        val cardAdminBatches = view.findViewById<View>(R.id.cardAdminBatches)
        val cardAdminScanner = view.findViewById<View>(R.id.cardAdminScanner)
        val cardAnnouncement = view.findViewById<View>(R.id.cardAnnouncement)
        val cardQuickStart = view.findViewById<View>(R.id.cardQuickStart)
        val cardWarehouseAddress = view.findViewById<View>(R.id.cardWarehouseAddress)
        val cardBankDetails = view.findViewById<View>(R.id.cardBankDetails)
        val cardNotReceived = view.findViewById<View>(R.id.cardNotReceived)
        val cardReceived = view.findViewById<View>(R.id.cardReceived)
        val cardPacked = view.findViewById<View>(R.id.cardPacked)
        val cardCompleted = view.findViewById<View>(R.id.cardCompleted)

        if (isAdmin) {
            tvServiceStatus?.text = "ADMIN CONSOLE"
            tvHeroText?.text = "Warehouse Control"
            tvNotReceivedLabel?.text = "PACKING"
            tvReceivedLabel?.text = "NOT RECEIVED"
            tvPackedLabel?.text = "NO PHOTO"
            tvCompletedLabel?.text = "USERS"

            cardAnnouncement?.visibility = View.GONE
            cardQuickStart?.visibility = View.GONE
            cardWarehouseAddress?.visibility = View.GONE
            cardBankDetails?.visibility = View.GONE

            cardAdminOrders?.visibility = View.VISIBLE
            cardAdminUsers?.visibility = View.VISIBLE
            cardAdminBatches?.visibility = View.VISIBLE
            cardAdminScanner?.visibility = View.VISIBLE

            cardAdminOrders?.setOnClickListener { try { findNavController().navigate(R.id.adminOrdersFragment) } catch (_: Exception) {} }
            cardAdminUsers?.setOnClickListener { try { findNavController().navigate(R.id.adminUsersFragment) } catch (_: Exception) {} }
            cardAdminBatches?.setOnClickListener { try { findNavController().navigate(R.id.packingFragment) } catch (_: Exception) {} }
            cardAdminScanner?.setOnClickListener {
                startActivity(Intent(requireContext(), BarcodeScannerActivity::class.java).apply {
                    putExtra(BarcodeScannerActivity.EXTRA_IS_ADMIN, true)
                })
            }

            cardNotReceived?.setOnClickListener { try { findNavController().navigate(R.id.adminOrdersFragment) } catch (_: Exception) {} }
            cardReceived?.setOnClickListener { try { findNavController().navigate(R.id.adminOrdersFragment) } catch (_: Exception) {} }
            cardPacked?.setOnClickListener { try { findNavController().navigate(R.id.adminOrdersFragment) } catch (_: Exception) {} }
            cardCompleted?.setOnClickListener { try { findNavController().navigate(R.id.adminUsersFragment) } catch (_: Exception) {} }
        } else {
            (cardAdminOrders?.parent as? android.view.ViewGroup)?.removeView(cardAdminOrders)
            (cardAdminUsers?.parent as? android.view.ViewGroup)?.removeView(cardAdminUsers)
            (cardAdminBatches?.parent as? android.view.ViewGroup)?.removeView(cardAdminBatches)
            (cardAdminScanner?.parent as? android.view.ViewGroup)?.removeView(cardAdminScanner)

            cardAnnouncement?.visibility = View.VISIBLE
            cardQuickStart?.visibility = View.VISIBLE
            cardWarehouseAddress?.visibility = View.VISIBLE
            cardBankDetails?.visibility = View.VISIBLE
        }

        view.findViewById<View>(R.id.cardWarehouseAddress)?.setOnClickListener { copyWarehouseAddress() }
        view.findViewById<View>(R.id.ivCopyAddress)?.setOnClickListener { copyWarehouseAddress() }
        view.findViewById<View>(R.id.cardBankDetails)?.setOnClickListener { copyBankDetails() }
        view.findViewById<View>(R.id.ivCopyBank)?.setOnClickListener { copyBankDetails() }
        view.findViewById<View>(R.id.btnDownloadQr)?.setOnClickListener { downloadQrCode() }
        tvBankName.setOnClickListener { copyText("Bank Name", tvBankName.text.toString()) }
        tvAccountName.setOnClickListener { copyText("Account Name", tvAccountName.text.toString()) }
        tvAccountNumber.setOnClickListener { copyText("Account Number", tvAccountNumber.text.toString()) }

        Log.d(TAG, "DashboardFragment onViewCreated")
        loadDashboard()
    }

    private fun loadDashboard() {        if (!isAdded) return
        val ctx = context ?: return
        swipeRefresh.isRefreshing = true

        val isAdmin = com.packtracker.utils.Session.isAdmin(ctx)
        if (isAdmin) {
            Log.d(TAG, "loadDashboard: calling getAdminDashboard()")
            ApiClient.getService(ctx).getAdminDashboard()
                .enqueue(object : Callback<ApiResponse<AdminDashboardData>> {
                    override fun onResponse(call: Call<ApiResponse<AdminDashboardData>>, response: Response<ApiResponse<AdminDashboardData>>) {
                        if (!isAdded || context == null) return
                        swipeRefresh.isRefreshing = false
                        if (response.isSuccessful && response.body()?.data != null) {
                            response.body()?.data?.let { data ->
                                tvNotReceived.text = data.orderStats?.pendingPacking?.toString() ?: "0"
                                tvReceived.text = data.orderStats?.notReceived?.toString() ?: "0"
                                tvPacked.text = data.orderStats?.withoutImages?.toString() ?: "0"
                                tvCompleted.text = data.userStats?.total?.toString() ?: "0"
                            }
                        } else {
                            showStatsUnavailable()
                        }
                    }
                    override fun onFailure(call: Call<ApiResponse<AdminDashboardData>>, t: Throwable) {
                        if (!isAdded || context == null) return
                        swipeRefresh.isRefreshing = false
                        Log.e(TAG, "Admin Dashboard stats error: ${t.message}")
                        showStatsUnavailable()
                    }
                })
            return
        } else {
            Log.d(TAG, "loadDashboard: calling getDashboard()")
            ApiClient.getService(ctx).getDashboard()
                .enqueue(object : Callback<ApiResponse<DashboardData>> {
                    override fun onResponse(call: Call<ApiResponse<DashboardData>>, response: Response<ApiResponse<DashboardData>>) {
                        if (!isAdded || context == null) return
                        Log.d(TAG, "Dashboard stats response: ${response.code()}")
                        if (response.isSuccessful && response.body()?.data != null) {
                            response.body()?.data?.let { data ->
                                tvNotReceived.text = data.stats?.not_received?.toString() ?: "0"
                                tvReceived.text = data.stats?.received?.toString() ?: "0"
                                tvPacked.text = data.stats?.packed?.toString() ?: "0"
                                tvCompleted.text = data.stats?.completed?.toString() ?: "0"
                            }
                        } else {
                            showStatsUnavailable()
                        }
                    }
                    override fun onFailure(call: Call<ApiResponse<DashboardData>>, t: Throwable) {
                        if (!isAdded || context == null) return
                        swipeRefresh.isRefreshing = false
                        Log.e(TAG, "Dashboard stats error: ${t.message}")
                        showStatsUnavailable()
                    }
                })
        }

        Log.d(TAG, "loadDashboard: calling getDashboardInfo()")
        ApiClient.getService(ctx).getDashboardInfo()
            .enqueue(object : Callback<ApiResponse<DashboardInfo>> {

                override fun onResponse(call: Call<ApiResponse<DashboardInfo>>, response: Response<ApiResponse<DashboardInfo>>) {
                    if (!isAdded || context == null) return
                    swipeRefresh.isRefreshing = false
                    Log.d(TAG, "DashboardInfo response: ${response.code()}")
                    val body = response.body()
                    if (response.isSuccessful && body?.success == true && body.data != null) {
                        updateInfoUI(body.data!!)
                    } else {
                        Log.w(TAG, "DashboardInfo failed")
                        showInfoUnavailable()
                    }
                }
                override fun onFailure(call: Call<ApiResponse<DashboardInfo>>, t: Throwable) {
                    if (!isAdded || context == null) return
                    swipeRefresh.isRefreshing = false
                    Log.e(TAG, "DashboardInfo error: ${t.message}")
                    showInfoUnavailable()
                }
            })
    }

    private fun updateInfoUI(info: DashboardInfo) {
        if (!isAdded) return
        Log.d(TAG, "updateInfoUI: bank=${info.paymentSettings?.bankName}")

        info.announcement?.let { a ->
            if (!a.message.isNullOrEmpty()) tvAnnouncement.text = a.message
        }

        info.warehouseAddress?.let { w ->
            val parts = mutableListOf<String>()
            if (!w.address.isNullOrEmpty()) parts.add(w.address)
            if (!w.phone.isNullOrEmpty()) parts.add("Phone: ${w.phone}")
            if (!w.hours.isNullOrEmpty()) parts.add("Hours: ${w.hours}")
            if (parts.isNotEmpty()) tvWarehouseAddress.text = parts.joinToString("\n")
            if (!w.phone.isNullOrEmpty()) {
                tvWarehousePhone.visibility = View.VISIBLE
                tvWarehousePhone.text = w.phone
            }
            if (!w.hours.isNullOrEmpty()) {
                tvWarehouseHours.visibility = View.VISIBLE
                tvWarehouseHours.text = w.hours
            }
        }

        info.paymentSettings?.let { p ->
            if (!p.bankName.isNullOrEmpty()) tvBankName.text = p.bankName
            if (!p.accountName.isNullOrEmpty()) tvAccountName.text = p.accountName
            if (!p.accountNumber.isNullOrEmpty()) tvAccountNumber.text = p.accountNumber
            if (!p.qrCodeImage.isNullOrEmpty()) {
                val qrUrl = UrlUtils.fixUrl(p.qrCodeImage)
                if (qrUrl != null) {
                    qrCodeUrl = qrUrl
                    ivQrCode.visibility = View.VISIBLE
                    view?.findViewById<View>(R.id.btnDownloadQr)?.visibility = View.VISIBLE
                    Glide.with(this)
                        .load(qrUrl)
                        .listener(object : RequestListener<Drawable> {
                            override fun onLoadFailed(e: GlideException?, model: Any?, target: Target<Drawable>, isFirstResource: Boolean): Boolean {
                                Log.e(TAG, "QR image load failed: ${e?.message}")
                                ivQrCode.visibility = View.GONE
                                qrCodeUrl = null
                                view?.findViewById<View>(R.id.btnDownloadQr)?.visibility = View.GONE
                                return false
                            }

                            override fun onResourceReady(resource: Drawable, model: Any, target: Target<Drawable>, dataSource: DataSource, isFirstResource: Boolean): Boolean {
                                return false
                            }
                        })
                        .into(ivQrCode)
                } else {
                    qrCodeUrl = null
                    view?.findViewById<View>(R.id.btnDownloadQr)?.visibility = View.GONE
                }
            } else {
                qrCodeUrl = null
                view?.findViewById<View>(R.id.btnDownloadQr)?.visibility = View.GONE
            }
        }
    }

    private fun downloadQrCode() {
        val ctx = context ?: return
        val url = qrCodeUrl
        if (url.isNullOrEmpty()) {
            Toast.makeText(ctx, "No QR image to download", Toast.LENGTH_SHORT).show()
            return
        }
        val needsPermission = Build.VERSION.SDK_INT < Build.VERSION_CODES.Q &&
            ContextCompat.checkSelfPermission(ctx, Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED
        if (needsPermission) {
            qrPermissionLauncher.launch(Manifest.permission.WRITE_EXTERNAL_STORAGE)
            return
        }
        fetchAndSaveQr(url)
    }

    private fun fetchAndSaveQr(url: String) {
        val ctx = context ?: return
        Toast.makeText(ctx, "Downloading QR…", Toast.LENGTH_SHORT).show()
        Glide.with(this)
            .downloadOnly()
            .load(url)
            .into(object : CustomTarget<File>() {
                override fun onResourceReady(resource: File, transition: Transition<in File>?) {
                    saveQrToDownloads(resource)
                }

                override fun onLoadFailed(errorDrawable: Drawable?) {
                    super.onLoadFailed(errorDrawable)
                    Log.e(TAG, "QR download fetch failed")
                    context?.let { Toast.makeText(it, "Could not download QR", Toast.LENGTH_SHORT).show() }
                }

                override fun onLoadCleared(placeholder: Drawable?) {}
            })
    }

    private fun saveQrToDownloads(src: File) {
        val ctx = context ?: return
        val filename = "YBT_Payment_QR.jpg"
        val mime = "image/jpeg"
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.Downloads.DISPLAY_NAME, filename)
                    put(MediaStore.Downloads.MIME_TYPE, mime)
                    put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS)
                    put(MediaStore.Downloads.IS_PENDING, 1)
                }
                val resolver = ctx.contentResolver
                val collection = MediaStore.Downloads.getContentUri(MediaStore.VOLUME_EXTERNAL_PRIMARY)
                resolver.delete(collection,
                    "${MediaStore.MediaColumns.RELATIVE_PATH}=? AND ${MediaStore.MediaColumns.DISPLAY_NAME}=?",
                    arrayOf("${Environment.DIRECTORY_DOWNLOADS}/", filename))
                val uri = resolver.insert(collection, values) ?: throw IllegalStateException("MediaStore insert failed")
                resolver.openOutputStream(uri)?.use { out ->
                    FileInputStream(src).use { input -> input.copyTo(out) }
                } ?: throw IllegalStateException("Could not open output stream")
                values.clear()
                values.put(MediaStore.Downloads.IS_PENDING, 0)
                resolver.update(uri, values, null, null)
            } else {
                @Suppress("DEPRECATION")
                val dir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS)
                if (!dir.exists()) dir.mkdirs()
                val dest = File(dir, filename)
                FileInputStream(src).use { input -> dest.outputStream().use { input.copyTo(it) } }
                MediaScannerConnection.scanFile(ctx, arrayOf(dest.absolutePath), arrayOf(mime), null)
            }
            Toast.makeText(ctx, "QR saved to Downloads", Toast.LENGTH_SHORT).show()
        } catch (e: Exception) {
            Log.e(TAG, "QR save failed: ${e.message}")
            Toast.makeText(ctx, "Could not save QR", Toast.LENGTH_SHORT).show()
        }
    }

    private fun copyWarehouseAddress() {
        val ctx = context ?: return
        val address = tvWarehouseAddress.text.toString()
        if (address.isEmpty() || address == "Loading..." || address == UNAVAILABLE) {
            Toast.makeText(ctx, "Not loaded yet", Toast.LENGTH_SHORT).show()
            return
        }
        val prefs = ctx.getSharedPreferences("auth", Context.MODE_PRIVATE)
        val username = prefs.getString("username", null)
        val sb = StringBuilder(address)
        if (!username.isNullOrEmpty() && !address.contains(username)) {
            sb.append("\n\nUsername: ").append(username)
        }
        copyToClipboard("Warehouse Address", sb.toString())
        Toast.makeText(ctx, "Address copied", Toast.LENGTH_SHORT).show()
    }

    private fun copyBankDetails() {
        val ctx = context ?: return
        val bankName = tvBankName.text.toString()
        if (bankName == "Bank Name" || bankName == UNAVAILABLE || bankName.isEmpty()) {
            Toast.makeText(ctx, "Not loaded yet", Toast.LENGTH_SHORT).show()
            return
        }
        val details = "Bank: $bankName\nAccount Name: ${tvAccountName.text}\nAccount: ${tvAccountNumber.text}"
        copyToClipboard("Bank Details", details)
        Toast.makeText(ctx, "Bank details copied", Toast.LENGTH_SHORT).show()
    }

    private fun copyText(label: String, text: String) {
        val ctx = context ?: return
        if (text.isEmpty() || text == "Bank Name" || text == "Account Name" || text == "Account Number" || text == UNAVAILABLE) return
        copyToClipboard(label, text)
        Toast.makeText(ctx, "$label copied", Toast.LENGTH_SHORT).show()
    }

    private fun copyToClipboard(label: String, text: String) {
        val ctx = context ?: return
        val clipboard = ContextCompat.getSystemService(ctx, ClipboardManager::class.java)
        clipboard?.setPrimaryClip(ClipData.newPlainText(label, text))
    }

    private fun showStatsUnavailable() {
        if (!isAdded) return
        tvNotReceived.text = "–"
        tvReceived.text = "–"
        tvPacked.text = "–"
        tvCompleted.text = "–"
    }

    private fun showInfoUnavailable() {
        if (!isAdded) return
        tvAnnouncement.text = "Could not load dashboard data. Check your connection, then pull down to retry."
        tvWarehouseAddress.text = UNAVAILABLE
        tvWarehousePhone.text = ""
        tvWarehouseHours.text = ""
        tvBankName.text = UNAVAILABLE
        tvAccountName.text = ""
        tvAccountNumber.text = ""
        ivQrCode.visibility = View.GONE
        qrCodeUrl = null
        view?.findViewById<View>(R.id.btnDownloadQr)?.visibility = View.GONE
    }
}
