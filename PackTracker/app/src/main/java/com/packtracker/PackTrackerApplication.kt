package com.packtracker

import android.app.Application
import android.content.BroadcastReceiver
import android.content.Context
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.work.Configuration
import com.packtracker.utils.NetworkMonitor
import com.packtracker.workers.ScanSyncWorker

/**
 * Custom Application class that:
 * 1. Initializes WorkManager with custom configuration
 * 2. Schedules periodic background sync for offline scans
 * 3. Listens for connectivity changes and triggers immediate sync when back online
 */
class PackTrackerApplication : Application(), Configuration.Provider {

    companion object {
        private const val TAG = "PackTrackerApp"
    }

    private var connectivityReceiver: BroadcastReceiver? = null

    override fun onCreate() {
        super.onCreate()

        Log.d(TAG, "PackTrackerApplication created")

        // Defer WorkManager scheduling to avoid blocking the main thread
        Handler(Looper.getMainLooper()).post {
            try {
                // Schedule periodic background sync (every 15 minutes when online)
                ScanSyncWorker.schedulePeriodicSync(this)
                Log.d(TAG, "Background sync scheduled")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to schedule background sync", e)
            }

            // Listen for connectivity changes to trigger immediate sync
            registerConnectivityListener()
        }
    }

    private fun registerConnectivityListener() {
        try {
            connectivityReceiver = NetworkMonitor.register(this, object : NetworkMonitor.ConnectivityListener {
                override fun onConnectivityChanged(isConnected: Boolean) {
                    if (isConnected) {
                        // Network is back — trigger immediate sync
                        try {
                            val pendingCount = ScanSyncWorker.getPendingCount(this@PackTrackerApplication)
                            if (pendingCount > 0) {
                                Log.d(TAG, "Network restored with $pendingCount pending scans — scheduling sync")
                                ScanSyncWorker.scheduleImmediateSync(this@PackTrackerApplication)
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Failed to sync on network restore", e)
                        }
                    }
                }
            })
        } catch (e: Exception) {
            Log.e(TAG, "Failed to register connectivity listener", e)
        }
    }

    override fun onTerminate() {
        super.onTerminate()
        connectivityReceiver?.let { NetworkMonitor.unregister(this, it) }
    }

    /**
     * Provide custom WorkManager initialization.
     * This lets us customize the worker configuration.
     */
    override val workManagerConfiguration: Configuration
        get() = Configuration.Builder()
            .setMinimumLoggingLevel(Log.INFO)
            .build()
}
