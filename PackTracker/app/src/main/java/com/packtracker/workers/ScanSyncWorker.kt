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

        /** Cap immediate backoff retries; the periodic sync still re-processes FAILED items */
        private const val MAX_RETRY_ATTEMPTS = 3

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
        // Recover scans left in SYNCING by a previous run that was killed or timed out
        OfflineScanStorage.resetStuckSyncing(applicationContext)

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
        var retryableFailures = 0
        var terminalFailures = 0

        // Sync each pending scan
        for (scan in pendingScans) {
            val originalIndex = OfflineScanStorage.getPendingScans(applicationContext).indexOfFirst {
                it.trackingNumber == scan.trackingNumber &&
                it.timestamp == scan.timestamp &&
                it.syncStatus != OfflineScanStorage.PendingScan.SyncStatus.SYNCED
            }

            if (originalIndex < 0) continue

            OfflineScanStorage.markSyncing(applicationContext, originalIndex)

            when (syncSingleScan(scan)) {
                SyncOutcome.SUCCESS -> {
                    OfflineScanStorage.markSynced(applicationContext, originalIndex)
                    syncedCount++
                }
                SyncOutcome.RETRYABLE -> {
                    OfflineScanStorage.markFailed(applicationContext, originalIndex)
                    retryableFailures++
                }
                SyncOutcome.TERMINAL -> {
                    OfflineScanStorage.markFailed(applicationContext, originalIndex)
                    terminalFailures++
                }
            }
        }

        Log.d(TAG, "Sync complete: $syncedCount synced, $retryableFailures retryable, " +
                "$terminalFailures terminal failures out of ${pendingScans.size}")

        // Clean up fully synced items
        OfflineScanStorage.clearSynced(applicationContext)

        // Update lifetime stats
        val stats = OfflineScanStorage.getStats(applicationContext)
        stats.totalSynced += syncedCount
        applicationContext.getSharedPreferences("offline_scans", Context.MODE_PRIVATE)
            .edit()
            .putString("scan_stats", com.google.gson.Gson().toJson(stats))
            .apply()

        return if (retryableFailures > 0 && runAttemptCount < MAX_RETRY_ATTEMPTS) {
            Log.d(TAG, "$retryableFailures scans hit network errors, will retry with backoff")
            Result.retry()
        } else {
            // Remaining failures are terminal or the retry budget is spent;
            // FAILED items stay queued and the periodic sync will pick them up.
            Result.success()
        }
    }

    private enum class SyncOutcome { SUCCESS, RETRYABLE, TERMINAL }

    /**
     * Sync a single scan to the server. Uses CountDownLatch to make
     * the Retrofit synchronous call work within the coroutine worker.
     */
    private fun syncSingleScan(scan: OfflineScanStorage.PendingScan): SyncOutcome {
        val latch = CountDownLatch(1)
        val outcome = java.util.concurrent.atomic.AtomicReference(SyncOutcome.RETRYABLE)

        try {
            if (scan.isAdmin && scan.targetUserId > 0) {
                ApiClient.getService(applicationContext).addAdminTrackingBulk(
                    targetUserId = scan.targetUserId,
                    trackingNumbers = scan.trackingNumber,
                    description = scan.description,
                    itemCount = 1,
                    shippingMethod = null
                ).enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(
                        call: Call<ApiResponse<String>>,
                        response: Response<ApiResponse<String>>
                    ) {
                        // Server answered: reject = terminal, so replaying never duplicates writes
                        outcome.set(if (response.isSuccessful && response.body()?.success == true)
                            SyncOutcome.SUCCESS else SyncOutcome.TERMINAL)
                        latch.countDown()
                    }

                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        Log.e(TAG, "Sync failed for ${scan.trackingNumber}: ${t.message}")
                        outcome.set(SyncOutcome.RETRYABLE)
                        latch.countDown()
                    }
                })
            } else if (!scan.isAdmin) {
                ApiClient.getService(applicationContext).addTracking(
                    trackingNumber = scan.trackingNumber,
                    description = scan.description
                ).enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(
                        call: Call<ApiResponse<String>>,
                        response: Response<ApiResponse<String>>
                    ) {
                        outcome.set(if (response.isSuccessful && response.body()?.success == true)
                            SyncOutcome.SUCCESS else SyncOutcome.TERMINAL)
                        latch.countDown()
                    }

                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        Log.e(TAG, "Sync failed for ${scan.trackingNumber}: ${t.message}")
                        outcome.set(SyncOutcome.RETRYABLE)
                        latch.countDown()
                    }
                })
            } else {
                // Admin without user ID — can't sync
                Log.w(TAG, "Skipping ${scan.trackingNumber} — admin without user ID")
                outcome.set(SyncOutcome.TERMINAL)
                latch.countDown()
            }

            // Wait up to 30 seconds for the network call
            if (!latch.await(30, TimeUnit.SECONDS)) {
                // Timed out — the request may still be in flight; treat as retryable
                Log.w(TAG, "Sync timed out for ${scan.trackingNumber}")
                return SyncOutcome.RETRYABLE
            }
        } catch (e: Exception) {
            Log.e(TAG, "Sync exception for ${scan.trackingNumber}: ${e.message}")
            return SyncOutcome.RETRYABLE
        }

        return outcome.get()
    }
}
