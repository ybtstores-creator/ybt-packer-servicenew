package com.packtracker.workers

import android.content.Context
import android.util.Log
import androidx.work.*
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.utils.OfflineScanStorage
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * Background WorkManager worker that syncs offline scans to the server.
 * Runs even when the app is closed. Schedules periodic retries with
 * exponential backoff when the network is unavailable.
 */
class ScanSyncWorker(
    context: Context,
    params: WorkerParameters
) : CoroutineWorker(context, params) {

    companion object {
        private const val TAG = "ScanSyncWorker"
        const val WORK_NAME = "offline_scan_sync"
        const val WORK_NAME_IMMEDIATE = "offline_scan_sync_immediate"

        /** Unique work name for periodic scheduling */
        private const val PERIODIC_WORK_NAME = "periodic_scan_sync"

        /**
         * Schedule a periodic sync worker that runs every 15 minutes.
         * Uses flex interval so it runs within a 15-minute window.
         * Will retry with exponential backoff on failure.
         */
        fun schedulePeriodicSync(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val workRequest = PeriodicWorkRequestBuilder<ScanSyncWorker>(
                15, TimeUnit.MINUTES,   // repeat interval
                5, TimeUnit.MINUTES     // flex interval
            )
                .setConstraints(constraints)
                .setBackoffCriteria(
                    BackoffPolicy.EXPONENTIAL,
                    WorkRequest.MIN_BACKOFF_MILLIS,  // 10 seconds initial
                    TimeUnit.MILLISECONDS
                )
                .addTag("sync")
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                PERIODIC_WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,  // Don't duplicate
                workRequest
            )

            Log.d(TAG, "Periodic sync scheduled (every 15 min)")
        }

        /**
         * Schedule a one-time immediate sync with retry.
         * Used when connectivity is restored.
         */
        fun scheduleImmediateSync(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val workRequest = OneTimeWorkRequestBuilder<ScanSyncWorker>()
                .setConstraints(constraints)
                .setBackoffCriteria(
                    BackoffPolicy.EXPONENTIAL,
                    10_000,  // 10 seconds initial backoff
                    TimeUnit.MILLISECONDS
                )
                .addTag("sync_immediate")
                .build()

            WorkManager.getInstance(context).enqueueUniqueWork(
                WORK_NAME_IMMEDIATE,
                ExistingWorkPolicy.REPLACE,  // Replace any pending immediate sync
                workRequest
            )

            Log.d(TAG, "Immediate sync scheduled")
        }

        /**
         * Cancel all pending sync work.
         */
        fun cancelSync(context: Context) {
            WorkManager.getInstance(context).cancelUniqueWork(PERIODIC_WORK_NAME)
            WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME_IMMEDIATE)
            Log.d(TAG, "All sync work cancelled")
        }

        /**
         * Get the pending sync count for display.
         */
        fun getPendingCount(context: Context): Int {
            return OfflineScanStorage.getUnsyncedCount(context)
        }
    }

    override suspend fun doWork(): Result {
        val pendingScans = OfflineScanStorage.getPendingScans(applicationContext)
            .filter {
                it.syncStatus != OfflineScanStorage.PendingScan.SyncStatus.SYNCED &&
                it.syncStatus != OfflineScanStorage.PendingScan.SyncStatus.SYNCING
            }

        if (pendingScans.isEmpty()) {
            Log.d(TAG, "No pending scans to sync")
            return Result.success()
        }

        Log.d(TAG, "Starting sync of ${pendingScans.size} pending scans")

        var syncedCount = 0
        var failedCount = 0

        // Sync each pending scan
        for (scan in pendingScans) {
            val originalIndex = OfflineScanStorage.getPendingScans(applicationContext).indexOfFirst {
                it.trackingNumber == scan.trackingNumber &&
                it.timestamp == scan.timestamp &&
                it.syncStatus != OfflineScanStorage.PendingScan.SyncStatus.SYNCED
            }

            if (originalIndex < 0) continue

            OfflineScanStorage.markSyncing(applicationContext, originalIndex)

            val success = syncSingleScan(scan)

            if (success) {
                OfflineScanStorage.markSynced(applicationContext, originalIndex)
                syncedCount++
            } else {
                OfflineScanStorage.markFailed(applicationContext, originalIndex)
                failedCount++
            }
        }

        Log.d(TAG, "Sync complete: $syncedCount synced, $failedCount failed out of ${pendingScans.size}")

        // Clean up fully synced items
        OfflineScanStorage.clearSynced(applicationContext)

        // Update lifetime stats
        val stats = OfflineScanStorage.getStats(applicationContext)
        stats.totalSynced += syncedCount
        applicationContext.getSharedPreferences("offline_scans", Context.MODE_PRIVATE)
            .edit()
            .putString("scan_stats", com.google.gson.Gson().toJson(stats))
            .apply()

        return if (failedCount > 0) {
            // There are failures — retry with backoff
            Log.d(TAG, "$failedCount scans failed, will retry with backoff")
            Result.retry()
        } else {
            Result.success()
        }
    }

    /**
     * Sync a single scan to the server. Uses CountDownLatch to make
     * the Retrofit synchronous call work within the coroutine worker.
     */
    private fun syncSingleScan(scan: OfflineScanStorage.PendingScan): Boolean {
        val latch = CountDownLatch(1)
        var success = false

        try {
            if (scan.isAdmin && scan.targetUserId > 0) {
                ApiClient.getService(applicationContext).addAdminTrackingBulk(
                    targetUserId = scan.targetUserId,
                    trackingNumbers = scan.trackingNumber,
                    description = null,
                    itemCount = 1,
                    shippingMethod = null
                ).enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(
                        call: Call<ApiResponse<String>>,
                        response: Response<ApiResponse<String>>
                    ) {
                        success = response.isSuccessful && response.body()?.success == true
                        latch.countDown()
                    }

                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        Log.e(TAG, "Sync failed for ${scan.trackingNumber}: ${t.message}")
                        success = false
                        latch.countDown()
                    }
                })
            } else if (!scan.isAdmin) {
                ApiClient.getService(applicationContext).addTracking(
                    trackingNumber = scan.trackingNumber,
                    description = null
                ).enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(
                        call: Call<ApiResponse<String>>,
                        response: Response<ApiResponse<String>>
                    ) {
                        success = response.isSuccessful && response.body()?.success == true
                        latch.countDown()
                    }

                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        Log.e(TAG, "Sync failed for ${scan.trackingNumber}: ${t.message}")
                        success = false
                        latch.countDown()
                    }
                })
            } else {
                // Admin without user ID — can't sync
                Log.w(TAG, "Skipping ${scan.trackingNumber} — admin without user ID")
                latch.countDown()
            }

            // Wait up to 30 seconds for the network call
            latch.await(30, TimeUnit.SECONDS)
        } catch (e: Exception) {
            Log.e(TAG, "Sync exception for ${scan.trackingNumber}: ${e.message}")
        }

        return success
    }
}
