package com.packtracker.utils

import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken

/**
 * Stores tracking number scans locally when the network is unavailable.
 * Pending scans are synced to the server when connectivity is restored.
 * Maintains a full history of all scans for the scan history screen.
 */
object OfflineScanStorage {

    private const val PREFS_NAME = "offline_scans"
    private const val KEY_PENDING_SCANS = "pending_scans"
    private const val KEY_SCAN_HISTORY = "scan_history"
    private const val KEY_SCAN_STATS = "scan_stats"
    private val gson = Gson()

    data class PendingScan(
        val trackingNumber: String,
        val format: String,
        val timestamp: Long = System.currentTimeMillis(),
        val isAdmin: Boolean = false,
        val targetUserId: Int = 0,
        var syncStatus: SyncStatus = SyncStatus.PENDING
    ) {
        enum class SyncStatus { PENDING, SYNCING, SYNCED, FAILED }
    }

    /**
     * A completed record in the scan history. Created when a scan is
     * saved — either directly to server or to offline storage.
     */
    data class ScanHistoryRecord(
        val trackingNumber: String,
        val format: String,
        val timestamp: Long = System.currentTimeMillis(),
        val isAdmin: Boolean = false,
        val targetUserId: Int = 0,
        val wasOffline: Boolean = false,
        var status: HistoryStatus = HistoryStatus.SAVED_ONLINE
    ) {
        enum class HistoryStatus {
            SAVED_ONLINE,    // Saved directly to server
            SAVED_OFFLINE,   // Saved to local storage
            SYNCED,          // Was offline, now synced to server
            FAILED           // Sync failed
        }
    }

    private fun prefs(context: Context): SharedPreferences =
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    // ═══════════════════════════════════════════════
    // PENDING SCANS (for sync queue)
    // ═══════════════════════════════════════════════

    fun savePendingScan(context: Context, scan: PendingScan) {
        val list = getPendingScans(context).toMutableList()
        list.add(scan)
        prefs(context).edit()
            .putString(KEY_PENDING_SCANS, gson.toJson(list))
            .apply()

        // Also add to history
        val wasOffline = scan.syncStatus == PendingScan.SyncStatus.PENDING
        val historyRecord = ScanHistoryRecord(
            trackingNumber = scan.trackingNumber,
            format = scan.format,
            timestamp = scan.timestamp,
            isAdmin = scan.isAdmin,
            targetUserId = scan.targetUserId,
            wasOffline = wasOffline,
            status = if (wasOffline) ScanHistoryRecord.HistoryStatus.SAVED_OFFLINE
                     else ScanHistoryRecord.HistoryStatus.SAVED_ONLINE
        )
        addHistoryRecord(context, historyRecord)
    }

    fun getPendingScans(context: Context): List<PendingScan> {
        val json = prefs(context).getString(KEY_PENDING_SCANS, null) ?: return emptyList()
        val type = object : TypeToken<List<PendingScan>>() {}.type
        return try {
            gson.fromJson(json, type) ?: emptyList()
        } catch (e: Exception) {
            emptyList()
        }
    }

    fun getUnsyncedCount(context: Context): Int {
        return getPendingScans(context).count {
            it.syncStatus == PendingScan.SyncStatus.PENDING ||
            it.syncStatus == PendingScan.SyncStatus.FAILED
        }
    }

    fun getTotalPendingCount(context: Context): Int {
        return getPendingScans(context).size
    }

    fun getSyncedCount(context: Context): Int {
        return getPendingScans(context).count {
            it.syncStatus == PendingScan.SyncStatus.SYNCED
        }
    }

    fun markSyncing(context: Context, index: Int) {
        val list = getPendingScans(context).toMutableList()
        if (index in list.indices) {
            list[index].syncStatus = PendingScan.SyncStatus.SYNCING
            prefs(context).edit()
                .putString(KEY_PENDING_SCANS, gson.toJson(list))
                .apply()
        }
    }

    fun markSynced(context: Context, index: Int) {
        val list = getPendingScans(context).toMutableList()
        if (index in list.indices) {
            list[index].syncStatus = PendingScan.SyncStatus.SYNCED
            prefs(context).edit()
                .putString(KEY_PENDING_SCANS, gson.toJson(list))
                .apply()

            // Update history record to SYNCED
            updateHistoryStatus(context, list[index].trackingNumber, list[index].timestamp,
                ScanHistoryRecord.HistoryStatus.SYNCED)
        }
    }

    fun markFailed(context: Context, index: Int) {
        val list = getPendingScans(context).toMutableList()
        if (index in list.indices) {
            list[index].syncStatus = PendingScan.SyncStatus.FAILED
            prefs(context).edit()
                .putString(KEY_PENDING_SCANS, gson.toJson(list))
                .apply()

            // Update history record to FAILED
            updateHistoryStatus(context, list[index].trackingNumber, list[index].timestamp,
                ScanHistoryRecord.HistoryStatus.FAILED)
        }
    }

    fun clearSynced(context: Context) {
        val list = getPendingScans(context).filter {
            it.syncStatus != PendingScan.SyncStatus.SYNCED
        }
        prefs(context).edit()
            .putString(KEY_PENDING_SCANS, gson.toJson(list))
            .apply()
    }

    fun clearAll(context: Context) {
        prefs(context).edit()
            .remove(KEY_PENDING_SCANS)
            .remove(KEY_SCAN_HISTORY)
            .remove(KEY_SCAN_STATS)
            .apply()
    }

    // ═══════════════════════════════════════════════
    // SCAN HISTORY (persistent log of all scans)
    // ═══════════════════════════════════════════════

    private fun addHistoryRecord(context: Context, record: ScanHistoryRecord) {
        val list = getScanHistory(context).toMutableList()
        list.add(0, record)  // newest first
        // Keep last 500 records to prevent SharedPreferences bloat
        if (list.size > 500) {
            val trimmed = list.take(500).toMutableList()
            prefs(context).edit()
                .putString(KEY_SCAN_HISTORY, gson.toJson(trimmed))
                .apply()
        } else {
            prefs(context).edit()
                .putString(KEY_SCAN_HISTORY, gson.toJson(list))
                .apply()
        }
    }

    /** Add a record for a scan saved directly to server (not offline) */
    fun recordOnlineSave(context: Context, trackingNumber: String, format: String,
                         isAdmin: Boolean = false, targetUserId: Int = 0) {
        val record = ScanHistoryRecord(
            trackingNumber = trackingNumber,
            format = format,
            isAdmin = isAdmin,
            targetUserId = targetUserId,
            wasOffline = false,
            status = ScanHistoryRecord.HistoryStatus.SAVED_ONLINE
        )
        addHistoryRecord(context, record)
    }

    private fun updateHistoryStatus(context: Context, trackingNumber: String,
                                     timestamp: Long, newStatus: ScanHistoryRecord.HistoryStatus) {
        val list = getScanHistory(context).toMutableList()
        val index = list.indexOfFirst {
            it.trackingNumber == trackingNumber && it.timestamp == timestamp
        }
        if (index >= 0) {
            list[index].status = newStatus
            prefs(context).edit()
                .putString(KEY_SCAN_HISTORY, gson.toJson(list))
                .apply()
        }
    }

    fun getScanHistory(context: Context): List<ScanHistoryRecord> {
        val json = prefs(context).getString(KEY_SCAN_HISTORY, null) ?: return emptyList()
        val type = object : TypeToken<List<ScanHistoryRecord>>() {}.type
        return try {
            gson.fromJson(json, type) ?: emptyList()
        } catch (e: Exception) {
            emptyList()
        }
    }

    fun getHistoryCount(context: Context): Int {
        return getScanHistory(context).size
    }

    fun getHistoryCountByStatus(context: Context, status: ScanHistoryRecord.HistoryStatus): Int {
        return getScanHistory(context).count { it.status == status }
    }

    fun clearHistory(context: Context) {
        prefs(context).edit()
            .remove(KEY_SCAN_HISTORY)
            .apply()
    }

    // ═══════════════════════════════════════════════
    // STATS
    // ═══════════════════════════════════════════════

    fun getStats(context: Context): ScanStats {
        val json = prefs(context).getString(KEY_SCAN_STATS, null)
        return if (json != null) {
            try {
                gson.fromJson(json, ScanStats::class.java) ?: ScanStats()
            } catch (e: Exception) {
                ScanStats()
            }
        } else ScanStats()
    }

    fun incrementStats(context: Context, wasOffline: Boolean) {
        val stats = getStats(context)
        stats.totalScans++
        if (wasOffline) stats.offlineScans++
        prefs(context).edit()
            .putString(KEY_SCAN_STATS, gson.toJson(stats))
            .apply()
    }

    data class ScanStats(
        var totalScans: Int = 0,
        var offlineScans: Int = 0,
        var totalSynced: Int = 0
    )
}
