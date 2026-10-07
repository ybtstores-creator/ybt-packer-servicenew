package com.packtracker.activities

import android.Manifest
import android.content.ComponentName
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.os.Handler
import android.os.Looper
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.navigation.NavOptions
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.AppBarConfiguration
import androidx.navigation.ui.setupActionBarWithNavController
import androidx.navigation.ui.setupWithNavController
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.databinding.ActivityMainBinding
import com.packtracker.models.ApiResponse
import com.packtracker.models.UnreadData
import com.packtracker.utils.GlobalNotificationPoller
import com.packtracker.utils.NotificationPopup
import com.packtracker.utils.OfflineScanStorage
import com.packtracker.utils.Session
import com.packtracker.workers.ScanSyncWorker
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class MainActivity : AppCompatActivity() {

    companion object {
        private const val BADGE_POLL_MS = 60000L

        /** Intent extra: open the Notifications screen after launch/resume. */
        const val EXTRA_OPEN_NOTIFICATIONS = "open_notifications"
        const val EXTRA_OPEN_CHAT = "open_chat"
        const val EXTRA_OPEN_TRACKING = "open_tracking"
        const val EXTRA_CHAT_USER_ID = "chat_user_id"
        const val EXTRA_CHAT_USER_NAME = "chat_user_name"
        const val EXTRA_TRACKING_NUMBER = "tracking_number"

        /**
         * Parking spot for a parcel deep-link: the notification only knows
         * the tracking number, the detail sheet needs the full row — which
         * TrackingFragment has once its list loads. Set by MainActivity,
         * consumed (and cleared) by TrackingFragment.
         */
        @Volatile
        var pendingParcelTrackingNumber: String? = null
    }

    private lateinit var binding: ActivityMainBinding

    // Set when a popup tap arrives while another activity (scanner, scan
    // history…) is on top; consumed in onNewIntent after MainActivity
    // comes back to the foreground.
    private var pendingTarget: com.packtracker.utils.NotificationPopup.PopupTarget? = null

    // Notification badge polling
    private val badgeHandler = Handler(Looper.getMainLooper())
    private var badgeRunnable: Runnable? = null

    override fun onCreateOptionsMenu(menu: android.view.Menu?): Boolean {
        menuInflater.inflate(R.menu.menu_main, menu)
        // Admins get Notifications in the toolbar overflow; customers have
        // it as a bottom-nav tab (Material caps the bar at 5 items).
        menu?.findItem(R.id.action_notifications)?.isVisible = Session.isAdmin(this)
        return true
    }

    override fun onOptionsItemSelected(item: android.view.MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_logout -> { confirmLogout(); true }
            R.id.action_notifications -> {
                val navHostFragment = supportFragmentManager
                    .findFragmentById(R.id.nav_host_fragment) as? NavHostFragment
                navHostFragment?.navController?.navigate(R.id.notificationsFragment)
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }

    /**
     * Confirm, wipe the session, and bounce to the login screen. The task is
     * cleared so Back can never return into the app without credentials.
     */
    private fun confirmLogout() {
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("Sign out")
            .setMessage("You will need your credentials to sign back in.")
            .setPositiveButton("Sign out") { _, _ -> doLogout() }
            .setNegativeButton("Cancel", null)
            .show()
    }

    private fun doLogout() {
        ApiClient.clearToken(this)
        Session.clear(this)
        // Scans queued by this account must not replay under whoever signs in next.
        ScanSyncWorker.cancelSync(this)
        GlobalNotificationPoller.stop()
        com.packtracker.workers.NotificationPollWorker.cancel(this)
        // Remove this device from the user's FCM token registry.
        com.packtracker.utils.FcmTokenManager.unregisterAndClear(this)
        OfflineScanStorage.clearAll(this)
        ScanSyncWorker.schedulePeriodicSync(this)
        val intent = Intent(this, LoginActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        }
        startActivity(intent)
        finish()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        // Authenticated session guard: direct invocations from external intents must not bypass login.
        if (!Session.isLoggedIn(this)) {
            val loginIntent = Intent(this, LoginActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            }
            startActivity(loginIntent)
            finish()
            return
        }

        // Regular users must not reach admin areas — or even learn they exist.
        // If a non-admin opens an admin deep link, silently rewrite the intent
        // to a plain launch so navigation simply starts at Home. No error, no
        // message, nothing that reveals an admin interface exists.
        if (!Session.isAdmin(this) && intent?.data?.host == "admin") {
            intent = Intent(intent).apply {
                data = null
                action = Intent.ACTION_MAIN
                component = ComponentName(this@MainActivity, MainActivity::class.java)
            }
        }
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setSupportActionBar(binding.toolbar)

        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        val navController = navHostFragment.navController

        val isAdmin = Session.isAdmin(this)
        if (isAdmin) {
            binding.bottomNav.menu.clear()
            binding.bottomNav.inflateMenu(R.menu.bottom_nav_menu_admin)
        }

        // Top-level destinations (no back button). Admins reach Notifications
        // through the toolbar overflow, so there Up should pop back.
        val topLevelDestinations = if (isAdmin) {
            setOf(
                R.id.dashboardFragment,
                R.id.adminOrdersFragment,
                R.id.adminAddTrackingFragment,
                R.id.packingFragment,
                R.id.adminChatFragment
            )
        } else {
            setOf(
                R.id.dashboardFragment,
                R.id.trackingFragment,
                R.id.addFragment,
                R.id.chatFragment,
                R.id.notificationsFragment
            )
        }
        val appBarConfiguration = AppBarConfiguration(topLevelDestinations)

        setupActionBarWithNavController(navController, appBarConfiguration)
        binding.bottomNav.setupWithNavController(navController)

        // Top-level tab switching. Wired manually instead of NavigationUI's
        // default selection handler: on this graph its pop-to-start(inclusive)
        // + restore-state options could resolve a Dashboard tab click to the
        // WRONG destination (observed: Dashboard click landing on Admin Orders
        // whenever Orders had been opened first). Plain navigate() with
        // single-top + pop-above-root gives the intended tab behavior: one
        // instance per tab, Back returns to the start tab, no state juggling.
        binding.bottomNav.setOnItemSelectedListener { item ->
            navController.navigate(
                item.itemId, null,
                NavOptions.Builder()
                    .setLaunchSingleTop(true)
                    .setPopUpTo(R.id.dashboardFragment, false)
                    .build()
            )
            true
        }

        // The WhatsApp-style conversation screens bring their own toolbar —
        // hide the app bar there so there's no double header.
        navController.addOnDestinationChangedListener { _, destination, _ ->
            if (destination.id == R.id.adminChatConversationFragment) {
                supportActionBar?.hide()
            } else {
                supportActionBar?.show()
            }
        }

        // Heads-up popup taps route to the same deep links as the tray
        // children (chat conversation / parcel detail / inbox). Works from
        // any screen — if another activity is on top, MainActivity is
        // brought forward first (see openFromAnywhere).
        NotificationPopup.setOnTap { target -> openFromAnywhere(target) }

        // App-wide unread poller: pops the banner over any screen.
        GlobalNotificationPoller.start(this)

        // Admin announcements: modal popup right after sign-in / on launch,
        // until each is acknowledged ("Got it"). Web counterpart pops the
        // same rows as a dashboard modal.
        com.packtracker.utils.AnnouncementChecker.checkAndShow(this)

        // Background tray-notification poller: re-schedule (KEEP policy, so
        // it's a no-op if already running) and ask permission on Android 13+.
        com.packtracker.workers.NotificationPollWorker.ensureChannel(this)
        com.packtracker.workers.NotificationPollWorker.schedule(this)
        requestNotificationPermissionIfNeeded()

        // FCM: re-sync the device token each launch (self-heals missed
        // registrations, e.g. token refreshed while the app was closed).
        com.packtracker.utils.FcmTokenManager.fetchAndStore(this) {
            com.packtracker.utils.FcmTokenManager.syncToServer(this)
        }

        // Arrived from a tray-notification tap (cold start or warm resume).
        handleOpenNotificationsIntent(intent)

        refreshNotificationBadge()
    }

    override fun onNewIntent(intent: Intent) {
        // Regular users must not reach admin areas if delivered via onNewIntent
        if (!Session.isAdmin(this) && intent.data?.host == "admin") {
            intent.data = null
            intent.action = Intent.ACTION_MAIN
            intent.component = ComponentName(this, MainActivity::class.java)
        }
        super.onNewIntent(intent)
        if (pendingTarget != null) {
            val target = pendingTarget
            pendingTarget = null
            // Post so the activity finishes resuming before the nav call.
            Handler(Looper.getMainLooper()).post { dispatchTarget(target) }
        }
        handleOpenNotificationsIntent(intent)
    }

    private fun handleOpenNotificationsIntent(intent: Intent?) {
        if (intent?.getBooleanExtra(EXTRA_OPEN_NOTIFICATIONS, false) != true) return

        // The user acted on a tray alert — clear the grouped group.
        com.packtracker.utils.TrayNotifier.clearAll(this)

        val target = when {
            intent.getBooleanExtra(EXTRA_OPEN_CHAT, false) -> com.packtracker.utils.NotificationPopup.PopupTarget(
                type = "chat_message",
                chatUserId = intent.getIntExtra(EXTRA_CHAT_USER_ID, 0),
                chatUserName = intent.getStringExtra(EXTRA_CHAT_USER_NAME)
            )
            intent.getBooleanExtra(EXTRA_OPEN_TRACKING, false) -> com.packtracker.utils.NotificationPopup.PopupTarget(
                type = "tracking_update",
                trackingNumber = intent.getStringExtra(EXTRA_TRACKING_NUMBER)
            )
            else -> null
        }
        // Post so the nav graph is ready before navigating.
        Handler(Looper.getMainLooper()).post { dispatchTarget(target) }
    }

    /** Single dispatcher for every "open this notification's content" path. */
    private fun dispatchTarget(target: com.packtracker.utils.NotificationPopup.PopupTarget?) {
        when {
            target?.type == "chat_message" -> openChatThread(target.chatUserId, target.chatUserName)
            target?.type == "tracking_update" -> openParcelFromNotification(target.trackingNumber)
            else -> navigateToNotifications()
        }
    }

    /** Chat notification tap: admins get the conversation, customers their support thread. */
    private fun openChatThread(userId: Int, username: String?) {
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as? NavHostFragment ?: return
        val controller = navHostFragment.navController
        try {
            if (Session.isAdmin(this)) {
                if (userId <= 0) {
                    controller.navigate(R.id.adminChatFragment)
                } else {
                    controller.navigate(
                        R.id.adminChatConversationFragment,
                        Bundle().apply {
                            putInt("userId", userId)
                            putString("username", username ?: "User")
                        }
                    )
                }
            } else {
                // Customers: the chat tab IS the support conversation.
                controller.navigate(
                    R.id.chatFragment, null,
                    NavOptions.Builder()
                        .setLaunchSingleTop(true)
                        .setPopUpTo(R.id.dashboardFragment, false)
                        .build()
                )
            }
        } catch (_: Exception) {
            runCatching { controller.navigate(R.id.notificationsFragment) }
        }
    }

    /**
     * Parcel notification tap: land on the Tracking tab and let the fragment
     * pop the detail sheet once its list contains the parcel (the sheet needs
     * the full row, not just the number from the notification).
     */
    private fun openParcelFromNotification(trackingNumber: String?) {
        if (!trackingNumber.isNullOrBlank()) {
            pendingParcelTrackingNumber = trackingNumber
        }
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as? NavHostFragment ?: return
        try {
            navHostFragment.navController.navigate(
                R.id.trackingFragment, null,
                NavOptions.Builder()
                    .setLaunchSingleTop(true)
                    .setPopUpTo(R.id.dashboardFragment, false)
                    .build()
            )
        } catch (_: Exception) {
            // trackingFragment may not exist for some roles — fall back to inbox.
            runCatching { navHostFragment.navController.navigate(R.id.notificationsFragment) }
        }
    }

    /** Android 13+ needs a runtime grant before tray notifications can show. */
    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
        ) {
            requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 5001)
        }
    }

    override fun onResume() {
        super.onResume()
        if (!Session.isLoggedIn(this)) {
            doLogout()
            return
        }
        startBadgePolling()
    }

    override fun onPause() {
        super.onPause()
        stopBadgePolling()
    }

    // ── Notification badge ─────────────────────────────────────────────────

    /** Unread-count poll for the Alerts tab; also called after the inbox is read. */
    fun refreshNotificationBadge() {
        if (isFinishing || isDestroyed) return
        ApiClient.getService(this).getUnreadNotificationCount()
            .enqueue(object : Callback<ApiResponse<UnreadData>> {
                override fun onResponse(
                    call: Call<ApiResponse<UnreadData>>,
                    response: Response<ApiResponse<UnreadData>>
                ) {
                    if (isFinishing || isDestroyed) return
                    val data = response.body()?.data
                    // Only customers have the Alerts tab to badge; admins use
                    // the toolbar entry (Material caps the bottom bar at 5).
                    if (binding.bottomNav.menu.findItem(R.id.notificationsFragment) == null) return
                    val badge = binding.bottomNav.getOrCreateBadge(R.id.notificationsFragment)
                    val unread = data?.unread ?: 0
                    if (response.body()?.success == true && unread > 0) {
                        badge.number = unread
                        badge.isVisible = true
                    } else {
                        badge.isVisible = false
                    }
                }

                override fun onFailure(call: Call<ApiResponse<UnreadData>>, t: Throwable) {
                    // Offline or transient — leave the badge as-is.
                }
            })
    }

    // ── Popup tap → target screen, from any screen ────────────────────────

    private fun openFromAnywhere(target: com.packtracker.utils.NotificationPopup.PopupTarget?) {
        if (isFinishing || isDestroyed) return
        if (GlobalNotificationPoller.PopupHost.currentActivity === this) {
            // Already on top — navigate in place.
            dispatchTarget(target)
        } else {
            // Another activity (scanner, scan history…) is showing the
            // banner. Bring this activity forward; the target is consumed
            // in onNewIntent.
            pendingTarget = target
            val intent = Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            startActivity(intent)
        }
    }

    private fun navigateToNotifications() {
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as? NavHostFragment
        navHostFragment?.navController?.navigate(R.id.notificationsFragment)
    }

    private fun startBadgePolling() {
        stopBadgePolling()
        badgeRunnable = object : Runnable {
            override fun run() {
                refreshNotificationBadge()
                badgeHandler.postDelayed(this, BADGE_POLL_MS)
            }
        }
        badgeHandler.postDelayed(badgeRunnable!!, BADGE_POLL_MS)
    }

    private fun stopBadgePolling() {
        badgeRunnable?.let { badgeHandler.removeCallbacks(it) }
        badgeRunnable = null
    }

    override fun onSupportNavigateUp(): Boolean {
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        return navHostFragment.navController.navigateUp() || super.onSupportNavigateUp()
    }
}
