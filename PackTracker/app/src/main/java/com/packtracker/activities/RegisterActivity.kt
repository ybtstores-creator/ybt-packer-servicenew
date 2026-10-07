package com.packtracker.activities

import android.content.Context
import android.content.Intent
import android.os.Bundle
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
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class RegisterActivity : AppCompatActivity() {

    private lateinit var etUsername: TextInputEditText
    private lateinit var etEmail: TextInputEditText
    private lateinit var etPassword: TextInputEditText
    private lateinit var btnRegister: MaterialButton
    private lateinit var progressBar: ProgressBar

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_register)

        etUsername = findViewById(R.id.etUsername)
        etEmail = findViewById(R.id.etEmail)
        etPassword = findViewById(R.id.etPassword)
        btnRegister = findViewById(R.id.btnRegister)
        progressBar = findViewById(R.id.progressBar)

        btnRegister.setOnClickListener { performRegister() }

        findViewById<View>(R.id.tvBackToLogin)?.setOnClickListener {
            finish()
        }
    }

    private fun performRegister() {
        val username = etUsername.text.toString().trim()
        val email = etEmail.text.toString().trim()
        val password = etPassword.text.toString().trim()

        if (username.isEmpty()) {
            etUsername.error = "Username is required"
            return
        }
        if (email.isEmpty()) {
            etEmail.error = "Email is required"
            return
        }
        if (password.length < 6) {
            etPassword.error = "Password must be 6+ characters"
            return
        }

        showLoading(true)

        ApiClient.getService(this).register(username = username, email = email, password = password)
            .enqueue(object : Callback<ApiResponse<User>> {
                override fun onResponse(call: Call<ApiResponse<User>>, response: Response<ApiResponse<User>>) {
                    showLoading(false)
                    val body = response.body()
                    if (response.isSuccessful && body?.success == true) {
                        Toast.makeText(this@RegisterActivity, "Account created! Please log in.", Toast.LENGTH_LONG).show()
                        startActivity(Intent(this@RegisterActivity, LoginActivity::class.java))
                        finish()
                    } else {
                        Toast.makeText(this@RegisterActivity, body?.message ?: "Registration failed", Toast.LENGTH_LONG).show()
                    }
                }

                override fun onFailure(call: Call<ApiResponse<User>>, t: Throwable) {
                    showLoading(false)
                    Toast.makeText(this@RegisterActivity, "Network error: ${t.message}", Toast.LENGTH_LONG).show()
                }
            })
    }

    private fun showLoading(show: Boolean) {
        progressBar.visibility = if (show) View.VISIBLE else View.GONE
        btnRegister.isEnabled = !show
        btnRegister.text = if (show) "CREATING..." else "REGISTER"
    }
}
