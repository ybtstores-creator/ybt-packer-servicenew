package com.packtracker.activities

import android.app.AlertDialog
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.android.material.button.MaterialButton
import com.google.android.material.card.MaterialCardView
import com.google.android.material.chip.Chip
import com.google.android.material.chip.ChipGroup
import com.packtracker.R
import com.packtracker.utils.OfflineScanStorage
import com.packtracker.utils.OfflineScanStorage.ScanHistoryRecord
import java.text.SimpleDateFormat
import java.util.*
import java.util.concurrent.TimeUnit

class ScanHistoryActivity : AppCompatActivity() {

    private lateinit var rvHistory: RecyclerView
    private lateinit var emptyState: View
    private lateinit var adapter: HistoryAdapter
    private lateinit var etSearch: android.widget.EditText
    private lateinit var chipGroup: ChipGroup

    private var allRecords = listOf<ScanHistoryRecord>()
    private var filteredRecords = mutableListOf<ScanHistoryRecord>()
    private var activeFilter: String = "all"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_scan_history)

        val toolbar = findViewById<com.google.android.material.appbar.MaterialToolbar>(R.id.toolbar)
        toolbar.setNavigationOnClickListener { finish() }

        rvHistory = findViewById(R.id.rvHistory)
        emptyState = findViewById(R.id.emptyState)
        etSearch = findViewById(R.id.etSearch)
        chipGroup = findViewById(R.id.chipGroupFilter)

        // Stats
        val tvTotal = findViewById<TextView>(R.id.tvTotalScans)
        val tvSynced = findViewById<TextView>(R.id.tvSyncedCount)
        val tvPending = findViewById<TextView>(R.id.tvPendingCount)
        val tvFailed = findViewById<TextView>(R.id.tvFailedCount)

        // Load data
        allRecords = OfflineScanStorage.getScanHistory(this)
        val pendingCount = OfflineScanStorage.getUnsyncedCount(this)
        val syncedInHistory = allRecords.count { it.status == ScanHistoryRecord.HistoryStatus.SYNCED ||
                                                   it.status == ScanHistoryRecord.HistoryStatus.SAVED_ONLINE }
        val failedInHistory = allRecords.count { it.status == ScanHistoryRecord.HistoryStatus.FAILED }

        tvTotal.text = "${allRecords.size}"
        tvSynced.text = "$syncedInHistory"
        tvPending.text = "$pendingCount"
        tvFailed.text = "$failedInHistory"

        // Setup adapter
        filteredRecords.addAll(allRecords)
        adapter = HistoryAdapter(filteredRecords)
        rvHistory.layoutManager = LinearLayoutManager(this)
        rvHistory.adapter = adapter

        updateEmptyState()

        // Search
        etSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                applyFilter()
            }
            override fun afterTextChanged(s: Editable?) {}
        })

        // Filter chips
        chipGroup.setOnCheckedStateChangeListener { _, checkedIds ->
            activeFilter = when {
                checkedIds.contains(R.id.chipSavedOnline) -> "online"
                checkedIds.contains(R.id.chipSavedOffline) -> "offline"
                checkedIds.contains(R.id.chipSynced) -> "synced"
                checkedIds.contains(R.id.chipFailed) -> "failed"
                else -> "all"
            }
            applyFilter()
        }

        // Clear history
        val btnClear = findViewById<MaterialButton>(R.id.btnClearHistory)
        btnClear.setOnClickListener {
            if (allRecords.isEmpty()) {
                Toast.makeText(this, "No history to clear", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            AlertDialog.Builder(this)
                .setTitle("Clear scan history")
                .setMessage("This will remove all ${allRecords.size} scan records from history. This cannot be undone.")
                .setPositiveButton("Clear All") { _, _ ->
                    OfflineScanStorage.clearHistory(this)
                    allRecords = emptyList()
                    filteredRecords.clear()
                    adapter.notifyDataSetChanged()
                    updateEmptyState()
                    tvTotal.text = "0"
                    tvSynced.text = "0"
                    tvPending.text = "0"
                    tvFailed.text = "0"
                    Toast.makeText(this, "History cleared", Toast.LENGTH_SHORT).show()
                }
                .setNegativeButton("Cancel", null)
                .show()
        }
    }

    private fun applyFilter() {
        val query = etSearch.text.toString().trim().lowercase()

        filteredRecords.clear()
        filteredRecords.addAll(allRecords.filter { record ->
            // Status filter
            val matchesFilter = when (activeFilter) {
                "online" -> record.status == ScanHistoryRecord.HistoryStatus.SAVED_ONLINE
                "offline" -> record.status == ScanHistoryRecord.HistoryStatus.SAVED_OFFLINE
                "synced" -> record.status == ScanHistoryRecord.HistoryStatus.SYNCED
                "failed" -> record.status == ScanHistoryRecord.HistoryStatus.FAILED
                else -> true
            }

            // Search filter
            val matchesSearch = query.isEmpty() ||
                record.trackingNumber.lowercase().contains(query) ||
                record.format.lowercase().contains(query)

            matchesFilter && matchesSearch
        })

        adapter.notifyDataSetChanged()
        updateEmptyState()
    }

    private fun updateEmptyState() {
        if (filteredRecords.isEmpty()) {
            emptyState.visibility = View.VISIBLE
            rvHistory.visibility = View.GONE
        } else {
            emptyState.visibility = View.GONE
            rvHistory.visibility = View.VISIBLE
        }
    }

    // ═══════════════════════════════════════════════
    // ADAPTER
    // ═══════════════════════════════════════════════

    inner class HistoryAdapter(
        private val items: List<ScanHistoryRecord>
    ) : RecyclerView.Adapter<HistoryAdapter.VH>() {

        inner class VH(view: View) : RecyclerView.ViewHolder(view) {
            val statusDot: View = view.findViewById(R.id.statusDot)
            val tvTrackingNumber: TextView = view.findViewById(R.id.tvTrackingNumber)
            val tvFormat: TextView = view.findViewById(R.id.tvFormat)
            val tvOfflineBadge: TextView = view.findViewById(R.id.tvOfflineBadge)
            val tvTimestamp: TextView = view.findViewById(R.id.tvTimestamp)
            val tvStatus: TextView = view.findViewById(R.id.tvStatus)
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
            val view = LayoutInflater.from(parent.context)
                .inflate(R.layout.item_scan_history, parent, false)
            return VH(view)
        }

        override fun onBindViewHolder(holder: VH, position: Int) {
            val record = items[position]

            holder.tvTrackingNumber.text = record.trackingNumber
            holder.tvFormat.text = record.format
            holder.tvTimestamp.text = formatTimestamp(record.timestamp)

            // Status dot color and badge
            when (record.status) {
                ScanHistoryRecord.HistoryStatus.SAVED_ONLINE -> {
                    holder.statusDot.setBackgroundResource(R.drawable.status_dot_green)
                    holder.tvStatus.text = "ONLINE"
                    holder.tvStatus.setTextColor(0xFF4CAF50.toInt())
                    holder.tvStatus.setBackgroundColor(0x154CAF50)
                    holder.tvOfflineBadge.visibility = View.GONE
                }
                ScanHistoryRecord.HistoryStatus.SAVED_OFFLINE -> {
                    holder.statusDot.setBackgroundResource(R.drawable.status_dot_orange)
                    holder.tvStatus.text = "OFFLINE"
                    holder.tvStatus.setTextColor(0xFFFF9800.toInt())
                    holder.tvStatus.setBackgroundColor(0x15FF9800)
                    holder.tvOfflineBadge.visibility = View.VISIBLE
                }
                ScanHistoryRecord.HistoryStatus.SYNCED -> {
                    holder.statusDot.setBackgroundResource(R.drawable.status_dot_blue)
                    holder.tvStatus.text = "SYNCED"
                    holder.tvStatus.setTextColor(0xFF2196F3.toInt())
                    holder.tvStatus.setBackgroundColor(0x152196F3)
                    holder.tvOfflineBadge.visibility = View.GONE
                }
                ScanHistoryRecord.HistoryStatus.FAILED -> {
                    holder.statusDot.setBackgroundResource(R.drawable.status_dot_red)
                    holder.tvStatus.text = "FAILED"
                    holder.tvStatus.setTextColor(0xFFF44336.toInt())
                    holder.tvStatus.setBackgroundColor(0x15F44336)
                    holder.tvOfflineBadge.visibility = View.GONE
                }
            }

            // Dim the item slightly if it was saved online (most common, less interesting)
            val alpha = if (record.status == ScanHistoryRecord.HistoryStatus.SAVED_ONLINE) 0.85f else 1.0f
            holder.itemView.alpha = alpha
        }

        override fun getItemCount() = items.size

        private fun formatTimestamp(timestamp: Long): String {
            val now = System.currentTimeMillis()
            val diff = now - timestamp

            return when {
                diff < TimeUnit.MINUTES.toMillis(1) -> "Just now"
                diff < TimeUnit.HOURS.toMillis(1) -> "${diff / TimeUnit.MINUTES.toMillis(1)} min ago"
                diff < TimeUnit.DAYS.toMillis(1) -> "${diff / TimeUnit.HOURS.toMillis(1)}h ago"
                diff < TimeUnit.DAYS.toMillis(7) -> "${diff / TimeUnit.DAYS.toMillis(1)}d ago"
                else -> {
                    val sdf = SimpleDateFormat("MMM dd, HH:mm", Locale.getDefault())
                    sdf.format(Date(timestamp))
                }
            }
        }
    }
}
