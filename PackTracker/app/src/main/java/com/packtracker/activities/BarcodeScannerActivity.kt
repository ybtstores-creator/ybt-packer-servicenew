package com.packtracker.activities

import android.Manifest
import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.BroadcastReceiver
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioManager
import android.media.ToneGenerator
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.text.InputType
import android.view.LayoutInflater
import android.view.View
import android.view.animation.TranslateAnimation
import android.widget.EditText
import android.widget.FrameLayout
import android.widget.ImageButton
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.mlkit.vision.barcode.BarcodeScanner
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.utils.NetworkMonitor
import com.packtracker.utils.OfflineScanStorage
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

class BarcodeScannerActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_SCANNED_VALUE = "scanned_value"
        const val EXTRA_SCANNED_FORMAT = "scanned_format"
        const val EXTRA_SCANNED_VALUES = "scanned_values"
        const val EXTRA_BATCH_MODE = "batch_mode"
        const val EXTRA_TARGET_USER_ID = "target_user_id"
        const val EXTRA_IS_ADMIN = "is_admin"
        private const val TAG = "BarcodeScanner"
        private const val REQUEST_CAMERA = 100
    }

    private lateinit var previewView: PreviewView
    private lateinit var tvScanStatus: TextView
    private lateinit var tvBatchCount: TextView
    private lateinit var batchBadge: FrameLayout
    private lateinit var rvScannedItems: RecyclerView
    private lateinit var btnConfirm: TextView
    private lateinit var btnTorch: ImageButton
    private var cameraExecutor: ExecutorService? = null
    private var cameraProvider: ProcessCameraProvider? = null
    private var barcodeScanner: BarcodeScanner? = null

    private fun scanner(): BarcodeScanner =
        barcodeScanner ?: BarcodeScanning.getClient().also { barcodeScanner = it }
    private var isScanning = true
    private var torchEnabled = false
    private var camera: Camera? = null
    private var batchMode = false
    private var isAdmin = false
    private var targetUserId = 0

    // Offline mode
    private var isOffline = false
    private var connectivityReceiver: BroadcastReceiver? = null

    // Batch scanning with auto-save
    private val scannedItems = mutableListOf<ScannedItem>()
    private lateinit var scannedAdapter: ScannedItemAdapter

    // Sound & vibration
    private var toneGenerator: ToneGenerator? = null
    private var vibrator: Vibrator? = null

    // Gallery picker
    private val galleryLauncher = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let { scanImageForBarcode(it) }
    }

    data class ScannedItem(
        val value: String,
        val format: String,
        var isDuplicate: Boolean = false,
        var saveStatus: SaveStatus = SaveStatus.PENDING,
        var isOfflineSave: Boolean = false,
        var offlineIndex: Int = -1  // index in OfflineScanStorage
    )

    enum class SaveStatus { PENDING, SAVING, SAVED, FAILED, OFFLINE_SAVED }

    @SuppressLint("MissingInflatedId")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Security Barrier: Barcode scanner is strictly restricted to administrators
        if (!com.packtracker.utils.Session.isAdmin(this)) {
            Toast.makeText(this, "Barcode scanner is only available for administrators", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        setContentView(R.layout.activity_barcode_scanner)

        batchMode = intent.getBooleanExtra(EXTRA_BATCH_MODE, false)
        isAdmin = true
        targetUserId = intent.getIntExtra(EXTRA_TARGET_USER_ID, 0)

        previewView = findViewById(R.id.previewView)
        tvScanStatus = findViewById(R.id.tvScanStatus)
        tvBatchCount = findViewById(R.id.tvBatchCount)
        batchBadge = findViewById(R.id.batchBadge)
        rvScannedItems = findViewById(R.id.rvScannedItems)
        btnConfirm = findViewById(R.id.btnConfirm)
        btnTorch = findViewById(R.id.btnTorch)
        val btnClose = findViewById<ImageButton>(R.id.btnClose)
        val btnGallery = findViewById<ImageButton>(R.id.btnGallery)
        val btnManualEntry = findViewById<com.google.android.material.button.MaterialButton>(R.id.btnManualEntry)
        val btnSyncPending = findViewById<com.google.android.material.button.MaterialButton>(R.id.btnSyncPending)

        cameraExecutor = Executors.newSingleThreadExecutor()

        initSoundAndVibration()

        if (batchMode) {
            batchBadge.visibility = View.VISIBLE
            rvScannedItems.visibility = View.VISIBLE
            btnConfirm.visibility = View.VISIBLE
            tvBatchCount.text = "0 scanned"
            setupScannedItemsList()
        }

        btnClose.setOnClickListener {
            if (batchMode && scannedItems.isNotEmpty()) {
                showExitDialog()
            } else {
                setResult(RESULT_CANCELED)
                finish()
            }
        }

        btnTorch.setOnClickListener { toggleTorch() }
        btnGallery.setOnClickListener { galleryLauncher.launch("image/*") }
        btnManualEntry.setOnClickListener { showManualEntryDialog() }
        btnConfirm.setOnClickListener { confirmBatchScan() }
        btnSyncPending.setOnClickListener { syncPendingScans() }

        startScanningAnimation()

        // Initialize network monitoring
        updateNetworkStatus()
        registerConnectivityListener()

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            startCamera()
        } else {
            ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.CAMERA), REQUEST_CAMERA)
        }
    }

    // ═══════════════════════════════════════════════════
    // OFFLINE MODE — Network detection & UI updates
    // ═══════════════════════════════════════════════════

    private fun registerConnectivityListener() {
        connectivityReceiver = NetworkMonitor.register(this, object : NetworkMonitor.ConnectivityListener {
            override fun onConnectivityChanged(connected: Boolean) {
                runOnUiThread {
                    val wasOffline = isOffline
                    isOffline = !connected
                    updateNetworkStatus()

                    // When coming back online, auto-sync pending scans
                    if (wasOffline && connected) {
                        Toast.makeText(this@BarcodeScannerActivity, "Back online! Syncing pending scans...", Toast.LENGTH_SHORT).show()
                        syncPendingScans()
                    }
                }
            }
        })
    }

    private fun updateNetworkStatus() {
        isOffline = !NetworkMonitor.isOnline(this)

        val tvNetworkStatus = findViewById<TextView>(R.id.tvNetworkStatus)
        val offlineBanner = findViewById<View>(R.id.offlineBanner)
        val tvPendingSyncCount = findViewById<TextView>(R.id.tvPendingSyncCount)
        val tvOfflineHint = findViewById<TextView>(R.id.tvOfflineHint)
        val btnSyncPending = findViewById<com.google.android.material.button.MaterialButton>(R.id.btnSyncPending)

        val pendingCount = OfflineScanStorage.getUnsyncedCount(this)

        if (isOffline) {
            tvNetworkStatus.text = "OFFLINE"
            tvNetworkStatus.setTextColor(0xFFFF5252.toInt())
            tvNetworkStatus.background = getDrawable(R.drawable.badge_bg)
            offlineBanner.visibility = View.VISIBLE
            tvPendingSyncCount.text = "$pendingCount pending sync"
            tvOfflineHint.visibility = View.VISIBLE
            if (pendingCount > 0) {
                btnSyncPending.visibility = View.GONE  // Can't sync when offline
            }
        } else {
            tvNetworkStatus.text = "ONLINE"
            tvNetworkStatus.setTextColor(0xFF4CAF50.toInt())
            tvNetworkStatus.setBackgroundColor(0x304CAF50)
            offlineBanner.visibility = View.GONE
            tvOfflineHint.visibility = View.GONE
            if (pendingCount > 0) {
                btnSyncPending.visibility = View.VISIBLE
                btnSyncPending.text = "SYNC ($pendingCount)"
            } else {
                btnSyncPending.visibility = View.GONE
            }
        }

        // Update batch count to reflect offline saves
        if (batchMode) {
            updateBatchCount()
        }
    }

    private fun syncPendingScans() {
        if (isOffline) {
            Toast.makeText(this, "No network — scans will sync when online", Toast.LENGTH_SHORT).show()
            return
        }

        val pending = OfflineScanStorage.getPendingScans(this)
            .filter { it.syncStatus != OfflineScanStorage.PendingScan.SyncStatus.SYNCED }

        if (pending.isEmpty()) {
            Toast.makeText(this, "All scans already synced", Toast.LENGTH_SHORT).show()
            return
        }

        var syncedCount = 0
        var failedCount = 0
        val totalCount = pending.size

        tvScanStatus.text = "Syncing $totalCount pending scans..."
        tvScanStatus.setTextColor(0xFFFFB74D.toInt())

        pending.forEachIndexed { index, scan ->
            val originalIndex = OfflineScanStorage.getPendingScans(this).indexOf(scan)
            OfflineScanStorage.markSyncing(this, originalIndex)

            if (scan.isAdmin && scan.targetUserId > 0) {
                ApiClient.getService(this).addAdminTrackingBulk(
                    targetUserId = scan.targetUserId,
                    trackingNumbers = scan.trackingNumber,
                    description = null,
                    itemCount = 1,
                    shippingMethod = null
                ).enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                        if (response.isSuccessful && response.body()?.success == true) {
                            OfflineScanStorage.markSynced(this@BarcodeScannerActivity, originalIndex)
                            syncedCount++
                        } else {
                            OfflineScanStorage.markFailed(this@BarcodeScannerActivity, originalIndex)
                            failedCount++
                        }
                        runOnUiThread { updateSyncProgress(syncedCount, failedCount, totalCount) }
                    }

                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        OfflineScanStorage.markFailed(this@BarcodeScannerActivity, originalIndex)
                        failedCount++
                        runOnUiThread { updateSyncProgress(syncedCount, failedCount, totalCount) }
                    }
                })
            } else if (!scan.isAdmin) {
                ApiClient.getService(this).addTracking(
                    trackingNumber = scan.trackingNumber,
                    description = null
                ).enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                        if (response.isSuccessful && response.body()?.success == true) {
                            OfflineScanStorage.markSynced(this@BarcodeScannerActivity, originalIndex)
                            syncedCount++
                        } else {
                            OfflineScanStorage.markFailed(this@BarcodeScannerActivity, originalIndex)
                            failedCount++
                        }
                        runOnUiThread { updateSyncProgress(syncedCount, failedCount, totalCount) }
                    }

                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        OfflineScanStorage.markFailed(this@BarcodeScannerActivity, originalIndex)
                        failedCount++
                        runOnUiThread { updateSyncProgress(syncedCount, failedCount, totalCount) }
                    }
                })
            }
        }
    }

    private fun updateSyncProgress(synced: Int, failed: Int, total: Int) {
        val tvBatchCount = findViewById<TextView>(R.id.tvBatchCount)
        val btnSyncPending = findViewById<com.google.android.material.button.MaterialButton>(R.id.btnSyncPending)

        when {
            synced + failed >= total -> {
                if (failed == 0) {
                    tvScanStatus.text = "All $total scans synced!"
                    tvScanStatus.setTextColor(0xFF4CAF50.toInt())
                    OfflineScanStorage.clearSynced(this)
                    btnSyncPending.visibility = View.GONE
                } else {
                    tvScanStatus.text = "$synced synced, $failed failed"
                    tvScanStatus.setTextColor(0xFFFF5252.toInt())
                }
                updateNetworkStatus()
            }
            else -> {
                tvScanStatus.text = "Syncing: $synced/$total synced..."
                tvScanStatus.setTextColor(0xFFFFB74D.toInt())
            }
        }
    }

    // ═══════════════════════════════════════════════════
    // SOUND & VIBRATION
    // ═══════════════════════════════════════════════════

    private fun initSoundAndVibration() {
        try {
            toneGenerator = ToneGenerator(AudioManager.STREAM_NOTIFICATION, 100)
        } catch (e: Exception) {
            android.util.Log.e(TAG, "ToneGenerator unavailable", e)
            toneGenerator = null
        }
        vibrator = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            getSystemService(VibratorManager::class.java)?.defaultVibrator
        } else {
            @Suppress("DEPRECATION")
            getSystemService(VIBRATOR_SERVICE) as? Vibrator
        }
    }

    private fun playScanFeedback() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                vibrator?.vibrate(VibrationEffect.createOneShot(100, VibrationEffect.DEFAULT_AMPLITUDE))
            } else {
                @Suppress("DEPRECATION")
                vibrator?.vibrate(100)
            }
            toneGenerator?.startTone(ToneGenerator.TONE_PROP_BEEP, 150)
        } catch (_: Exception) {}
    }

    private fun playErrorFeedback() {
        try {
            toneGenerator?.startTone(ToneGenerator.TONE_PROP_NACK, 200)
        } catch (_: Exception) {}
    }

    // ═══════════════════════════════════════════════════
    // BATCH SCANNING
    // ═══════════════════════════════════════════════════

    private fun setupScannedItemsList() {
        scannedAdapter = ScannedItemAdapter(scannedItems,
            onRemove = { position ->
                val item = scannedItems[position]
                when (item.saveStatus) {
                    SaveStatus.SAVED -> {
                        Toast.makeText(this, "Already saved to server", Toast.LENGTH_SHORT).show()
                    }
                    SaveStatus.OFFLINE_SAVED -> {
                        // Remove from OfflineScanStorage
                        if (item.offlineIndex >= 0) {
                            val pending = OfflineScanStorage.getPendingScans(this).toMutableList()
                            if (item.offlineIndex < pending.size) {
                                pending.removeAt(item.offlineIndex)
                                getSharedPreferences("offline_scans", MODE_PRIVATE).edit()
                                    .putString("pending_scans", com.google.gson.Gson().toJson(pending))
                                    .apply()
                            }
                        }
                        scannedItems.removeAt(position)
                        scannedAdapter.notifyItemRemoved(position)
                        updateBatchCount()
                    }
                    else -> {
                        scannedItems.removeAt(position)
                        scannedAdapter.notifyItemRemoved(position)
                        updateBatchCount()
                    }
                }
            },
            onRetry = { position ->
                retrySave(position)
            }
        )
        rvScannedItems.layoutManager = LinearLayoutManager(this)
        rvScannedItems.adapter = scannedAdapter
    }

    private fun addToBatch(value: String, format: String) {
        val isDuplicate = scannedItems.any { it.value == value && it.saveStatus == SaveStatus.SAVED }
        val item = ScannedItem(value, format, isDuplicate)
        scannedItems.add(item)
        scannedAdapter.notifyItemInserted(scannedItems.size - 1)
        rvScannedItems.scrollToPosition(scannedItems.size - 1)
        updateBatchCount()

        if (isDuplicate) {
            item.saveStatus = SaveStatus.SAVED
            scannedAdapter.notifyItemChanged(scannedItems.size - 1)
            tvScanStatus.text = "Duplicate skipped: $value"
            tvScanStatus.setTextColor(0xFFFF5252.toInt())
            playErrorFeedback()
        } else {
            // Auto-save to server or offline storage
            saveItem(scannedItems.size - 1)
            tvScanStatus.text = "Scanning: $value"
            tvScanStatus.setTextColor(0xFFB0B0B0.toInt())
        }
    }

    private fun saveItem(position: Int) {
        val item = scannedItems[position]

        if (isOffline) {
            // Save to offline storage
            saveItemOffline(position)
        } else {
            // Save to server
            item.saveStatus = SaveStatus.SAVING
            scannedAdapter.notifyItemChanged(position)
            saveItemToServer(position)
        }
    }

    private fun saveItemOffline(position: Int) {
        val item = scannedItems[position]
        item.saveStatus = SaveStatus.OFFLINE_SAVED
        item.isOfflineSave = true

        val pendingScan = OfflineScanStorage.PendingScan(
            trackingNumber = item.value,
            format = item.format,
            isAdmin = isAdmin,
            targetUserId = targetUserId,
            syncStatus = OfflineScanStorage.PendingScan.SyncStatus.PENDING
        )
        OfflineScanStorage.savePendingScan(this, pendingScan)
        item.offlineIndex = OfflineScanStorage.getPendingScans(this).size - 1
        OfflineScanStorage.incrementStats(this, wasOffline = true)

        scannedAdapter.notifyItemChanged(position)
        updateBatchCount()
        updateNetworkStatus()
    }

    private fun saveItemOfflineAt(index: Int) {
        if (index in scannedItems.indices) saveItemOffline(index)
    }

    private fun notifyItemRow(item: ScannedItem) {
        val index = scannedItems.indexOf(item)
        if (index >= 0) scannedAdapter.notifyItemChanged(index)
    }

    private fun saveItemToServer(position: Int) {
        val item = scannedItems[position]
        item.saveStatus = SaveStatus.SAVING
        scannedAdapter.notifyItemChanged(position)

        if (isAdmin && targetUserId > 0) {
            ApiClient.getService(this).addAdminTrackingBulk(
                targetUserId = targetUserId,
                trackingNumbers = item.value,
                description = null,
                itemCount = 1,
                shippingMethod = null
            ).enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (response.isSuccessful && response.body()?.success == true) {
                        item.saveStatus = SaveStatus.SAVED
                        runOnUiThread {
                            notifyItemRow(item)
                            updateBatchCount()
                        }
                    } else {
                        // Server rejected — save offline
                        runOnUiThread {
                            saveItemOfflineAt(scannedItems.indexOf(item))
                        }
                    }
                }

                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    // Network error — save offline
                    runOnUiThread {
                        saveItemOfflineAt(scannedItems.indexOf(item))
                    }
                }
            })
        } else if (!isAdmin) {
            ApiClient.getService(this).addTracking(
                trackingNumber = item.value,
                description = null
            ).enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (response.isSuccessful && response.body()?.success == true) {
                        item.saveStatus = SaveStatus.SAVED
                        runOnUiThread {
                            notifyItemRow(item)
                            updateBatchCount()
                        }
                    } else {
                        runOnUiThread {
                            saveItemOfflineAt(scannedItems.indexOf(item))
                        }
                    }
                }

                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    runOnUiThread {
                        saveItemOfflineAt(scannedItems.indexOf(item))
                    }
                }
            })
        } else {
            item.saveStatus = SaveStatus.PENDING
            scannedAdapter.notifyItemChanged(position)
        }
    }

    private fun retrySave(position: Int) {
        if (position in scannedItems.indices) {
            val item = scannedItems[position]
            if (isOffline) {
                saveItemOffline(position)
            } else {
                saveItemToServer(position)
            }
        }
    }

    private fun updateBatchCount() {
        val saved = scannedItems.count { it.saveStatus == SaveStatus.SAVED }
        val offlineSaved = scannedItems.count { it.saveStatus == SaveStatus.OFFLINE_SAVED }
        val total = scannedItems.size
        val saving = scannedItems.count { it.saveStatus == SaveStatus.SAVING }
        val failed = scannedItems.count { it.saveStatus == SaveStatus.FAILED }

        val status = buildString {
            append("$saved saved")
            if (offlineSaved > 0) append(" / $offlineSaved offline")
            if (saving > 0) append(" / $saving saving")
            if (failed > 0) append(" / $failed failed")
            append(" ($total total)")
        }
        tvBatchCount.text = status
    }

    private fun confirmBatchScan() {
        val saved = scannedItems.count { it.saveStatus == SaveStatus.SAVED }
        val offlineSaved = scannedItems.count { it.saveStatus == SaveStatus.OFFLINE_SAVED }
        val pending = scannedItems.count { it.saveStatus == SaveStatus.PENDING || it.saveStatus == SaveStatus.SAVING }
        val failed = scannedItems.count { it.saveStatus == SaveStatus.FAILED }

        when {
            pending > 0 -> {
                AlertDialog.Builder(this)
                    .setTitle("Still saving...")
                    .setMessage("$pending item(s) are still being saved. Wait for them to finish?")
                    .setPositiveButton("Wait") { d, _ -> d.dismiss() }
                    .setNegativeButton("Close anyway") { _, _ -> finishWithResult() }
                    .show()
            }
            failed > 0 -> {
                AlertDialog.Builder(this)
                    .setTitle("Some saves failed")
                    .setMessage("$failed item(s) failed to save. Close anyway?")
                    .setPositiveButton("Retry failed") { _, _ ->
                        scannedItems.forEachIndexed { i, item ->
                            if (item.saveStatus == SaveStatus.FAILED) retrySave(i)
                        }
                    }
                    .setNegativeButton("Close") { _, _ -> finishWithResult() }
                    .show()
            }
            saved + offlineSaved > 0 -> finishWithResult()
            else -> Toast.makeText(this, "No items scanned", Toast.LENGTH_SHORT).show()
        }
    }

    private fun finishWithResult() {
        val values = scannedItems
            .filter { it.saveStatus == SaveStatus.SAVED || it.saveStatus == SaveStatus.OFFLINE_SAVED }
            .joinToString("\n") { it.value }
        val resultIntent = Intent().apply {
            putExtra(EXTRA_SCANNED_VALUES, values)
            putExtra(EXTRA_BATCH_MODE, true)
        }
        setResult(RESULT_OK, resultIntent)
        finish()
    }

    private fun showExitDialog() {
        val saved = scannedItems.count { it.saveStatus == SaveStatus.SAVED }
        val offlineSaved = scannedItems.count { it.saveStatus == SaveStatus.OFFLINE_SAVED }
        val pending = scannedItems.count { it.saveStatus == SaveStatus.PENDING || it.saveStatus == SaveStatus.SAVING }
        val failed = scannedItems.count { it.saveStatus == SaveStatus.FAILED }

        val msg = buildString {
            append("$saved item(s) saved to server.")
            if (offlineSaved > 0) append(" $offlineSaved saved offline (will sync when online).")
            if (pending > 0) append(" $pending still saving.")
            if (failed > 0) append(" $failed failed.")
            append("\nClose scanner?")
        }

        AlertDialog.Builder(this)
            .setTitle("Close scanner?")
            .setMessage(msg)
            .setPositiveButton("Stay") { d, _ -> d.dismiss() }
            .setNegativeButton("Close") { _, _ ->
                setResult(RESULT_CANCELED)
                finish()
            }
            .show()
    }

    // ═══════════════════════════════════════════════════
    // SINGLE SCAN MODE
    // ═══════════════════════════════════════════════════

    private fun showManualEntryDialog() {
        val input = EditText(this).apply {
            hint = "Enter tracking number"
            inputType = InputType.TYPE_CLASS_TEXT
            setPadding(60, 40, 60, 20)
        }
        AlertDialog.Builder(this)
            .setTitle("Manual Entry")
            .setMessage("Type the barcode or tracking number")
            .setView(input)
            .setPositiveButton("Add") { _, _ ->
                val value = input.text.toString().trim()
                if (value.isNotEmpty()) {
                    playScanFeedback()
                    if (batchMode) {
                        addToBatch(value, "Manual")
                    } else {
                        saveSingleAndReturn(value, "Manual")
                    }
                }
            }
            .setNegativeButton("Cancel", null)
            .show()
    }

    private fun saveSingleAndReturn(value: String, format: String) {
        if (isOffline) {
            // Save offline
            val pendingScan = OfflineScanStorage.PendingScan(
                trackingNumber = value,
                format = format,
                isAdmin = isAdmin,
                targetUserId = targetUserId
            )
            OfflineScanStorage.savePendingScan(this, pendingScan)
            OfflineScanStorage.incrementStats(this, wasOffline = true)

            Toast.makeText(this, "Saved offline — will sync when online", Toast.LENGTH_SHORT).show()

            val intent = Intent().apply {
                putExtra(EXTRA_SCANNED_VALUE, value)
                putExtra(EXTRA_SCANNED_FORMAT, format)
            }
            setResult(RESULT_OK, intent)
            finish()
            return
        }

        tvScanStatus.text = "Saving $value..."

        if (isAdmin && targetUserId > 0) {
            ApiClient.getService(this).addAdminTrackingBulk(
                targetUserId = targetUserId,
                trackingNumbers = value,
                description = null,
                itemCount = 1,
                shippingMethod = null
            ).enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (response.isSuccessful && response.body()?.success == true) {
                        val intent = Intent().apply {
                            putExtra(EXTRA_SCANNED_VALUE, value)
                            putExtra(EXTRA_SCANNED_FORMAT, format)
                        }
                        setResult(RESULT_OK, intent)
                        finish()
                    } else {
                        // Server error — fall back to offline save
                        val pendingScan = OfflineScanStorage.PendingScan(
                            trackingNumber = value, format = format,
                            isAdmin = isAdmin, targetUserId = targetUserId
                        )
                        OfflineScanStorage.savePendingScan(this@BarcodeScannerActivity, pendingScan)
                        Toast.makeText(this@BarcodeScannerActivity, "Saved offline", Toast.LENGTH_SHORT).show()
                        val intent = Intent().apply {
                            putExtra(EXTRA_SCANNED_VALUE, value)
                            putExtra(EXTRA_SCANNED_FORMAT, format)
                        }
                        setResult(RESULT_OK, intent)
                        finish()
                    }
                }
                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    // Network error — save offline
                    val pendingScan = OfflineScanStorage.PendingScan(
                        trackingNumber = value, format = format,
                        isAdmin = isAdmin, targetUserId = targetUserId
                    )
                    OfflineScanStorage.savePendingScan(this@BarcodeScannerActivity, pendingScan)
                    Toast.makeText(this@BarcodeScannerActivity, "Saved offline — will sync when online", Toast.LENGTH_SHORT).show()
                    val intent = Intent().apply {
                        putExtra(EXTRA_SCANNED_VALUE, value)
                        putExtra(EXTRA_SCANNED_FORMAT, format)
                    }
                    setResult(RESULT_OK, intent)
                    finish()
                }
            })
        } else if (!isAdmin) {
            ApiClient.getService(this).addTracking(trackingNumber = value, description = null)
                .enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                        if (response.isSuccessful && response.body()?.success == true) {
                            val intent = Intent().apply {
                                putExtra(EXTRA_SCANNED_VALUE, value)
                                putExtra(EXTRA_SCANNED_FORMAT, format)
                            }
                            setResult(RESULT_OK, intent)
                            finish()
                        } else {
                            val pendingScan = OfflineScanStorage.PendingScan(
                                trackingNumber = value, format = format,
                                isAdmin = isAdmin, targetUserId = targetUserId
                            )
                            OfflineScanStorage.savePendingScan(this@BarcodeScannerActivity, pendingScan)
                            Toast.makeText(this@BarcodeScannerActivity, "Saved offline", Toast.LENGTH_SHORT).show()
                            val intent = Intent().apply {
                                putExtra(EXTRA_SCANNED_VALUE, value)
                                putExtra(EXTRA_SCANNED_FORMAT, format)
                            }
                            setResult(RESULT_OK, intent)
                            finish()
                        }
                    }
                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        val pendingScan = OfflineScanStorage.PendingScan(
                            trackingNumber = value, format = format,
                            isAdmin = isAdmin, targetUserId = targetUserId
                        )
                        OfflineScanStorage.savePendingScan(this@BarcodeScannerActivity, pendingScan)
                        Toast.makeText(this@BarcodeScannerActivity, "Saved offline — will sync when online", Toast.LENGTH_SHORT).show()
                        val intent = Intent().apply {
                            putExtra(EXTRA_SCANNED_VALUE, value)
                            putExtra(EXTRA_SCANNED_FORMAT, format)
                        }
                        setResult(RESULT_OK, intent)
                        finish()
                    }
                })
        } else {
            val intent = Intent().apply {
                putExtra(EXTRA_SCANNED_VALUE, value)
                putExtra(EXTRA_SCANNED_FORMAT, format)
            }
            setResult(RESULT_OK, intent)
            finish()
        }
    }

    // ═══════════════════════════════════════════════════
    // TORCH
    // ═══════════════════════════════════════════════════

    private fun toggleTorch() {
        camera?.let { cam ->
            if (cam.cameraInfo.hasFlashUnit()) {
                torchEnabled = !torchEnabled
                cam.cameraControl.enableTorch(torchEnabled)
                btnTorch.setImageResource(if (torchEnabled) R.drawable.ic_flash_on else R.drawable.ic_flash_off)
            } else {
                Toast.makeText(this, "Flash not available", Toast.LENGTH_SHORT).show()
            }
        }
    }

    // ═══════════════════════════════════════════════════
    // GALLERY SCAN
    // ═══════════════════════════════════════════════════

    @SuppressLint("UnsafeOptInUsageError")
    private fun scanImageForBarcode(uri: Uri) {
        try {
            val image = InputImage.fromFilePath(this, uri)
            tvScanStatus.text = "Scanning image..."
            scanner().process(image)
                .addOnSuccessListener { barcodes ->
                    if (barcodes.isNotEmpty()) {
                        val barcode = barcodes.first()
                        val value = barcode.rawValue ?: ""
                        val format = getFormatName(barcode.format)
                        playScanFeedback()
                        if (batchMode) {
                            addToBatch(value, format)
                        } else {
                            saveSingleAndReturn(value, format)
                        }
                    } else {
                        tvScanStatus.text = "No barcode found in image"
                        Toast.makeText(this, "No barcode detected", Toast.LENGTH_SHORT).show()
                    }
                }
                .addOnFailureListener {
                    tvScanStatus.text = "Failed to scan image"
                }
        } catch (e: Exception) {
            Toast.makeText(this, "Cannot read image: ${e.message}", Toast.LENGTH_SHORT).show()
        }
    }

    // ═══════════════════════════════════════════════════
    // CAMERA PERMISSIONS & LIFECYCLE
    // ═══════════════════════════════════════════════════

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == REQUEST_CAMERA && grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            startCamera()
        } else {
            Toast.makeText(this, "Camera permission required", Toast.LENGTH_LONG).show()
            setResult(RESULT_CANCELED)
            finish()
        }
    }

    @SuppressLint("UnsafeOptInUsageError")
    private fun startCamera() {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(this)
        cameraProviderFuture.addListener({
            try {
                val provider = cameraProviderFuture.get()
                cameraProvider = provider
                val preview = Preview.Builder().build().also { it.setSurfaceProvider(previewView.surfaceProvider) }
                val imageAnalysis = ImageAnalysis.Builder()
                    .setTargetResolution(android.util.Size(1280, 720))
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                    .build()

                cameraExecutor?.let { executor ->
                    imageAnalysis.setAnalyzer(executor) { imageProxy ->
                        if (!isScanning) { imageProxy.close(); return@setAnalyzer }
                        processImage(imageProxy)
                    }
                }

                provider.unbindAll()
                camera = provider.bindToLifecycle(this, CameraSelector.DEFAULT_BACK_CAMERA, preview, imageAnalysis)
            } catch (e: Exception) {
                android.util.Log.e(TAG, "Camera start failed", e)
                Toast.makeText(this, "Camera failed to start", Toast.LENGTH_SHORT).show()
                finish()
            }
        }, ContextCompat.getMainExecutor(this))
    }

    @SuppressLint("UnsafeOptInUsageError")
    private fun processImage(imageProxy: ImageProxy) {
        val mediaImage = imageProxy.image ?: run { imageProxy.close(); return }
        val image = InputImage.fromMediaImage(mediaImage, imageProxy.imageInfo.rotationDegrees)

        scanner().process(image)
            .addOnSuccessListener { barcodes ->
                if (barcodes.isNotEmpty() && isScanning) {
                    val barcode = barcodes.first()
                    val value = barcode.rawValue ?: ""
                    val format = getFormatName(barcode.format)
                    playScanFeedback()

                    if (batchMode) {
                        isScanning = false
                        runOnUiThread { addToBatch(value, format) }
                        previewView.postDelayed({ isScanning = true }, 2000)
                    } else {
                        isScanning = false
                        runOnUiThread {
                            tvScanStatus.text = "Found: $value — saving..."
                        }
                        runOnUiThread { saveSingleAndReturn(value, format) }
                    }
                }
            }
            .addOnFailureListener { e -> android.util.Log.e(TAG, "Scan failed", e) }
            .addOnCompleteListener { imageProxy.close() }
    }

    private fun getFormatName(format: Int): String = when (format) {
        Barcode.FORMAT_QR_CODE -> "QR"
        Barcode.FORMAT_AZTEC -> "Aztec"
        Barcode.FORMAT_EAN_13 -> "EAN-13"
        Barcode.FORMAT_EAN_8 -> "EAN-8"
        Barcode.FORMAT_UPC_A -> "UPC-A"
        Barcode.FORMAT_UPC_E -> "UPC-E"
        Barcode.FORMAT_CODE_128 -> "C128"
        Barcode.FORMAT_CODE_39 -> "C39"
        Barcode.FORMAT_CODE_93 -> "C93"
        Barcode.FORMAT_CODABAR -> "Coda"
        Barcode.FORMAT_DATA_MATRIX -> "DM"
        Barcode.FORMAT_PDF417 -> "PDF417"
        Barcode.FORMAT_ITF -> "ITF"
        else -> "Unknown"
    }

    private fun startScanningAnimation() {
        val scanningLine = findViewById<View>(R.id.scanningLine)
        val animator = TranslateAnimation(
            TranslateAnimation.RELATIVE_TO_PARENT, 0f,
            TranslateAnimation.RELATIVE_TO_PARENT, 0f,
            TranslateAnimation.RELATIVE_TO_PARENT, 0f,
            TranslateAnimation.RELATIVE_TO_PARENT, 0.9f
        ).apply {
            duration = 2000
            repeatCount = android.view.animation.Animation.INFINITE
            repeatMode = android.view.animation.Animation.REVERSE
        }
        scanningLine.animation = animator
    }

    override fun onDestroy() {
        super.onDestroy()
        connectivityReceiver?.let { NetworkMonitor.unregister(this, it) }
        try { cameraProvider?.unbindAll() } catch (_: Exception) {}
        cameraExecutor?.shutdownNow()
        try { toneGenerator?.release() } catch (_: Exception) {}
        toneGenerator = null
        try { barcodeScanner?.close() } catch (_: Exception) {}
        barcodeScanner = null
    }

    // ═══════════════════════════════════════════════════
    // ADAPTER — shows per-item save status
    // ═══════════════════════════════════════════════════
    inner class ScannedItemAdapter(
        private val items: MutableList<ScannedItem>,
        private val onRemove: (Int) -> Unit,
        private val onRetry: (Int) -> Unit
    ) : RecyclerView.Adapter<ScannedItemAdapter.VH>() {

        inner class VH(view: View) : RecyclerView.ViewHolder(view) {
            val tvIndex: TextView = view.findViewById(R.id.tvIndex)
            val tvBarcodeValue: TextView = view.findViewById(R.id.tvBarcodeValue)
            val tvFormat: TextView = view.findViewById(R.id.tvFormat)
            val tvDuplicate: TextView = view.findViewById(R.id.tvDuplicate)
            val tvOfflineSaved: TextView = view.findViewById(R.id.tvOfflineSaved)
            val progressSaving: ProgressBar = view.findViewById(R.id.progressSaving)
            val tvSaved: TextView = view.findViewById(R.id.tvSaved)
            val tvError: TextView = view.findViewById(R.id.tvError)
            val btnRetry: ImageButton = view.findViewById(R.id.btnRetry)
        }

        override fun onCreateViewHolder(parent: android.view.ViewGroup, viewType: Int): VH {
            val view = LayoutInflater.from(parent.context).inflate(R.layout.item_scanned_barcode, parent, false)
            return VH(view)
        }

        override fun onBindViewHolder(holder: VH, position: Int) {
            val item = items[position]
            holder.tvIndex.text = "${position + 1}"
            holder.tvBarcodeValue.text = item.value
            holder.tvFormat.text = item.format
            holder.tvDuplicate.visibility = if (item.isDuplicate) View.VISIBLE else View.GONE

            when (item.saveStatus) {
                SaveStatus.PENDING -> {
                    holder.progressSaving.visibility = View.GONE
                    holder.tvSaved.visibility = View.GONE
                    holder.tvError.visibility = View.GONE
                    holder.btnRetry.visibility = View.GONE
                    holder.tvOfflineSaved.visibility = View.GONE
                }
                SaveStatus.SAVING -> {
                    holder.progressSaving.visibility = View.VISIBLE
                    holder.tvSaved.visibility = View.GONE
                    holder.tvError.visibility = View.GONE
                    holder.btnRetry.visibility = View.GONE
                    holder.tvOfflineSaved.visibility = View.GONE
                }
                SaveStatus.SAVED -> {
                    holder.progressSaving.visibility = View.GONE
                    holder.tvSaved.visibility = View.VISIBLE
                    holder.tvError.visibility = View.GONE
                    holder.btnRetry.visibility = View.GONE
                    holder.tvOfflineSaved.visibility = View.GONE
                }
                SaveStatus.FAILED -> {
                    holder.progressSaving.visibility = View.GONE
                    holder.tvSaved.visibility = View.GONE
                    holder.tvError.visibility = View.VISIBLE
                    holder.btnRetry.visibility = View.VISIBLE
                    holder.tvOfflineSaved.visibility = View.GONE
                }
                SaveStatus.OFFLINE_SAVED -> {
                    holder.progressSaving.visibility = View.GONE
                    holder.tvSaved.visibility = View.GONE
                    holder.tvError.visibility = View.GONE
                    holder.btnRetry.visibility = View.GONE
                    holder.tvOfflineSaved.visibility = View.VISIBLE
                }
            }

            holder.btnRetry.setOnClickListener {
                val pos = holder.bindingAdapterPosition
                if (pos != RecyclerView.NO_POSITION) onRetry(pos)
            }
        }

        override fun getItemCount() = items.size
    }
}
