package com.packtracker.fragments

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.graphics.drawable.Drawable
import android.net.Uri
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.bumptech.glide.Glide
import com.bumptech.glide.load.DataSource
import com.bumptech.glide.load.engine.GlideException
import com.bumptech.glide.request.RequestListener
import com.bumptech.glide.request.target.Target
import com.packtracker.R
import com.packtracker.activities.BarcodeScannerActivity
import com.packtracker.api.ApiClient
import com.packtracker.models.AdminDashboardData
import com.packtracker.models.ApiResponse
import com.packtracker.models.DashboardData
import com.packtracker.models.DashboardInfo
import com.packtracker.utils.UrlUtils
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

            cardAdminOrders?.setOnClickListener { findNavController().navigate(R.id.adminOrdersFragment) }
            cardAdminUsers?.setOnClickListener { findNavController().navigate(R.id.adminUsersFragment) }
            cardAdminBatches?.setOnClickListener { findNavController().navigate(R.id.packingFragment) }
            cardAdminScanner?.setOnClickListener {
                startActivity(Intent(requireContext(), BarcodeScannerActivity::class.java).apply {
                    putExtra(BarcodeScannerActivity.EXTRA_IS_ADMIN, true)
                })
            }

            cardNotReceived?.setOnClickListener { findNavController().navigate(R.id.adminOrdersFragment) }
            cardReceived?.setOnClickListener { findNavController().navigate(R.id.adminOrdersFragment) }
            cardPacked?.setOnClickListener { findNavController().navigate(R.id.adminOrdersFragment) }
            cardCompleted?.setOnClickListener { findNavController().navigate(R.id.adminUsersFragment) }
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
        tvBankName.setOnClickListener { copyText("Bank Name", tvBankName.text.toString()) }
        tvAccountName.setOnClickListener { copyText("Account Name", tvAccountName.text.toString()) }
        tvAccountNumber.setOnClickListener { copyText("Account Number", tvAccountNumber.text.toString()) }

        Log.d(TAG, "DashboardFragment onViewCreated")
        loadDashboard()
    }

    private fun loadDashboard() {
        if (!isAdded) return
        swipeRefresh.isRefreshing = true

        val isAdmin = com.packtracker.utils.Session.isAdmin(requireContext())
        if (isAdmin) {
            Log.d(TAG, "loadDashboard: calling getAdminDashboard()")
            ApiClient.getService(requireContext()).getAdminDashboard()
                .enqueue(object : Callback<ApiResponse<AdminDashboardData>> {
                    override fun onResponse(call: Call<ApiResponse<AdminDashboardData>>, response: Response<ApiResponse<AdminDashboardData>>) {
                        if (!isAdded) return
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
                        if (!isAdded) return
                        swipeRefresh.isRefreshing = false
                        Log.e(TAG, "Admin Dashboard stats error: ${t.message}")
                        showStatsUnavailable()
                    }
                })
            return
        } else {
            Log.d(TAG, "loadDashboard: calling getDashboard()")
            ApiClient.getService(requireContext()).getDashboard()
                .enqueue(object : Callback<ApiResponse<DashboardData>> {
                    override fun onResponse(call: Call<ApiResponse<DashboardData>>, response: Response<ApiResponse<DashboardData>>) {
                        if (!isAdded) return
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
                        if (!isAdded) return
                        swipeRefresh.isRefreshing = false
                        Log.e(TAG, "Dashboard stats error: ${t.message}")
                        showStatsUnavailable()
                    }
                })
        }

        Log.d(TAG, "loadDashboard: calling getDashboardInfo()")
        ApiClient.getService(requireContext()).getDashboardInfo()
            .enqueue(object : Callback<ApiResponse<DashboardInfo>> {
                override fun onResponse(call: Call<ApiResponse<DashboardInfo>>, response: Response<ApiResponse<DashboardInfo>>) {
                    if (!isAdded) return
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
                    if (!isAdded) return
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
                    ivQrCode.visibility = View.VISIBLE
                    Glide.with(this)
                        .load(qrUrl)
                        .listener(object : RequestListener<Drawable> {
                            override fun onLoadFailed(e: GlideException?, model: Any?, target: Target<Drawable>, isFirstResource: Boolean): Boolean {
                                Log.e(TAG, "QR image load failed: ${e?.message}")
                                ivQrCode.visibility = View.GONE
                                return false
                            }

                            override fun onResourceReady(resource: Drawable, model: Any, target: Target<Drawable>, dataSource: DataSource, isFirstResource: Boolean): Boolean {
                                return false
                            }
                        })
                        .into(ivQrCode)
                }
            }
        }
    }

    private fun copyWarehouseAddress() {
        val address = tvWarehouseAddress.text.toString()
        if (address.isEmpty() || address == "Loading..." || address == UNAVAILABLE) {
            Toast.makeText(context, "Not loaded yet", Toast.LENGTH_SHORT).show()
            return
        }
        val prefs = requireContext().getSharedPreferences("auth", Context.MODE_PRIVATE)
        val username = prefs.getString("username", null)
        val sb = StringBuilder(address)
        if (!username.isNullOrEmpty() && !address.contains(username)) {
            sb.append("\n\nUsername: ").append(username)
        }
        copyToClipboard("Warehouse Address", sb.toString())
        Toast.makeText(context, "Address copied", Toast.LENGTH_SHORT).show()
    }

    private fun copyBankDetails() {
        val bankName = tvBankName.text.toString()
        if (bankName == "Bank Name" || bankName == UNAVAILABLE || bankName.isEmpty()) {
            Toast.makeText(context, "Not loaded yet", Toast.LENGTH_SHORT).show()
            return
        }
        val details = "Bank: $bankName\nAccount Name: ${tvAccountName.text}\nAccount: ${tvAccountNumber.text}"
        copyToClipboard("Bank Details", details)
        Toast.makeText(context, "Bank details copied", Toast.LENGTH_SHORT).show()
    }

    private fun copyText(label: String, text: String) {
        if (text.isEmpty() || text == "Bank Name" || text == "Account Name" || text == "Account Number" || text == UNAVAILABLE) return
        copyToClipboard(label, text)
        Toast.makeText(context, "$label copied", Toast.LENGTH_SHORT).show()
    }

    private fun copyToClipboard(label: String, text: String) {
        val clipboard = requireContext().getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        clipboard.setPrimaryClip(ClipData.newPlainText(label, text))
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
    }
}
