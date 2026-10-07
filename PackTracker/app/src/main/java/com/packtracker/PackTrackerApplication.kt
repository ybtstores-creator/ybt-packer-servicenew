package com.packtracker

import android.app.Application
import androidx.appcompat.app.AppCompatDelegate
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

        // Crash reporting FIRST - any later init crash must still be reported.
        com.packtracker.AppContextHolder.set(this)
        com.packtracker.utils.CrashReporter.install(
            appVersionName = BuildConfig.VERSION_NAME,
            appVersionCode = BuildConfig.VERSION_CODE,
            appId = "packtracker"
        )

        // Brand dark mode follows the system setting; no in-app override in v1.
        AppCompatDelegate.setDefaultNightMode(AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM)

        // Track the top activity so heads-up notification popups can appear
        // over ANY screen (dashboard, chat, scanner, detail sheets).
        com.packtracker.utils.GlobalNotificationPoller.PopupHost.let { registerActivityLifecycleCallbacks(it) }

        // Defer WorkManager scheduling to avoid blocking the main thread
        Handler(Looper.getMainLooper()).post {
            try {
                // Schedule periodic background sync (every 15 minutes when online)
                ScanSyncWorker.schedulePeriodicSync(this)
                Log.d(TAG, "Background sync scheduled")
            } catch (e: Exception) {
                Log.e(TAG, "Failed to schedule background sync", e)
            }

            try {
                // Background poll for new messages → system tray notification.
                // Cancelled on logout; re-scheduled on sign-in.
                com.packtracker.workers.NotificationPollWorker.ensureChannel(this)
                val hasSession = getSharedPreferences("auth", MODE_PRIVATE)
                    .getString("token", null)?.isNotBlank() == true
                if (hasSession) {
                    com.packtracker.workers.NotificationPollWorker.schedule(this)
                }
            } catch (e: Exception) {
                Log.e(TAG, "Failed to schedule notification poll", e)
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
