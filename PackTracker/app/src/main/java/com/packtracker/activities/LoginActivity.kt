package com.packtracker.activities

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.view.View
import android.widget.ProgressBar
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.User
import com.packtracker.utils.Session
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class LoginActivity : AppCompatActivity() {

    private lateinit var etUsername: TextInputEditText
    private lateinit var etPassword: TextInputEditText
    private lateinit var btnLogin: MaterialButton
    private lateinit var progressBar: ProgressBar

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (Session.isLoggedIn(this)) {
            startActivity(Intent(this, MainActivity::class.java))
            finish()
            return
        } else {
            Session.clear(this)
        }

        setContentView(R.layout.activity_login)

        etUsername = findViewById(R.id.etUsername)
        etPassword = findViewById(R.id.etPassword)
        btnLogin = findViewById(R.id.btnLogin)
        progressBar = findViewById(R.id.progressBar)

        btnLogin.setOnClickListener { performLogin() }

        // Web-parity link: Create Account.
        findViewById<View>(R.id.tvRegister)?.setOnClickListener {
            startActivity(Intent(this, RegisterActivity::class.java))
        }
        findViewById<View>(R.id.tvForgot)?.setOnClickListener {
            try { Toast.makeText(this, "Password reset is available on the website", Toast.LENGTH_LONG).show() } catch (_: Exception) {}
        }
    }

    private fun performLogin() {
        val username = etUsername.text.toString().trim()
        val password = etPassword.text.toString().trim()

        if (username.isEmpty()) {
            etUsername.error = "Username is required"
            return
        }
        if (password.isEmpty()) {
            etPassword.error = "Password is required"
            return
        }

        showLoading(true)

        ApiClient.getService(this).login(username = username, password = password)
            .enqueue(object : Callback<ApiResponse<User>> {
                override fun onResponse(call: Call<ApiResponse<User>>, response: Response<ApiResponse<User>>) {
                    showLoading(false)
                    val body = response.body()
                    if (response.isSuccessful && body?.success == true) {
                        body.data?.let { user ->
                            getSharedPreferences("auth", MODE_PRIVATE).edit()
                                .putString("token", user.token)
                                .putString("user_id", user.user_id?.toString() ?: user.id?.toString())
                                .putString("username", user.username)
                                .putString("display_name", user.display_name)
                                .putString("role", user.role)
                                .putBoolean("is_admin", user.is_admin == true)
                                .apply()
                        }
                        Toast.makeText(this@LoginActivity, "Login successful!", Toast.LENGTH_SHORT).show()
                        // Register this device for FCM pushes under the new session.
                        com.packtracker.utils.FcmTokenManager.fetchAndStore(applicationContext) { token ->
                            if (token != null) {
                                com.packtracker.utils.FcmTokenManager.syncToServer(applicationContext)
                            }
                        }
                        startActivity(Intent(this@LoginActivity, MainActivity::class.java))
                        finish()
                    } else {
                        Toast.makeText(this@LoginActivity, body?.message ?: "Login failed", Toast.LENGTH_SHORT).show()
                    }
                }

                override fun onFailure(call: Call<ApiResponse<User>>, t: Throwable) {
                    showLoading(false)
                    Log.e("PackTracker", "Login network failure", t)
                    Toast.makeText(this@LoginActivity,
                        "Could not reach the server. Check your connection and try again.",
                        Toast.LENGTH_LONG).show()
                }
            })
    }

    private fun showLoading(show: Boolean) {
        progressBar.visibility = if (show) View.VISIBLE else View.GONE
        btnLogin.isEnabled = !show
        btnLogin.text = if (show) "SIGNING IN..." else "SIGN IN TO DASHBOARD"
    }
}
