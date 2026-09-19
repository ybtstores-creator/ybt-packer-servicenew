package com.packtracker.activities

import android.content.ComponentName
import android.content.Intent
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.AppBarConfiguration
import androidx.navigation.ui.setupActionBarWithNavController
import androidx.navigation.ui.setupWithNavController
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.databinding.ActivityMainBinding
import com.packtracker.utils.Session

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding

    override fun onCreateOptionsMenu(menu: android.view.Menu?): Boolean {
        menuInflater.inflate(R.menu.menu_main, menu)
        return true
    }

    override fun onOptionsItemSelected(item: android.view.MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_logout -> { confirmLogout(); true }
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
        getSharedPreferences("auth", MODE_PRIVATE).edit().clear().apply()
        val intent = Intent(this, LoginActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        }
        startActivity(intent)
        finish()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
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
        } else {
            binding.bottomNav.menu.findItem(R.id.packingFragment)?.isVisible = false
        }

        // Top-level destinations (no back button)
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
                R.id.chatFragment
            )
        }
        val appBarConfiguration = AppBarConfiguration(topLevelDestinations)

        setupActionBarWithNavController(navController, appBarConfiguration)
        binding.bottomNav.setupWithNavController(navController)

        // The WhatsApp-style conversation screens bring their own toolbar —
        // hide the app bar there so there's no double header.
        navController.addOnDestinationChangedListener { _, destination, _ ->
            if (destination.id == R.id.adminChatConversationFragment) {
                supportActionBar?.hide()
            } else {
                supportActionBar?.show()
            }
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        return navHostFragment.navController.navigateUp() || super.onSupportNavigateUp()
    }
}
