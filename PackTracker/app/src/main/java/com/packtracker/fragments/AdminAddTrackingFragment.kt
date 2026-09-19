package com.packtracker.fragments

import android.app.Activity
import android.content.Intent
import android.content.SharedPreferences
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.packtracker.R
import com.packtracker.activities.BarcodeScannerActivity
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class AdminAddTrackingFragment : Fragment() {

    private lateinit var etNumbers: TextInputEditText

    private val batchScanLauncher = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val batchMode = result.data?.getBooleanExtra(BarcodeScannerActivity.EXTRA_BATCH_MODE, false) ?: false

            if (batchMode) {
                // Items already saved to server — just show summary
                val values = result.data?.getStringExtra(BarcodeScannerActivity.EXTRA_SCANNED_VALUES) ?: ""
                if (values.isNotEmpty()) {
                    val count = values.lines().size
                    try { Toast.makeText(context, "$count tracking number(s) saved to server", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                }
            } else {
                val scannedValue = result.data?.getStringExtra(BarcodeScannerActivity.EXTRA_SCANNED_VALUE) ?: ""
                if (scannedValue.isNotEmpty()) {
                    try { Toast.makeText(context, "Saved: $scannedValue", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                }
            }
        }
    }

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_admin_add_tracking, container, false)

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val etUserId = view.findViewById<TextInputEditText>(R.id.etUserId)
        etNumbers = view.findViewById(R.id.etTrackingNumbers)
        val etDesc = view.findViewById<TextInputEditText>(R.id.etDescription)
        val btn = view.findViewById<MaterialButton>(R.id.btnAddBulk)

        // Wire up scan button — batch mode for admin, auto-save each scan
        view.findViewById<MaterialButton>(R.id.btnScanBarcodeAdmin)?.setOnClickListener {
            val userId = etUserId.text.toString().trim().toIntOrNull() ?: 0
            if (userId <= 0) {
                etUserId.error = "Enter User ID first"
                return@setOnClickListener
            }
            try {
                val intent = Intent(requireContext(), BarcodeScannerActivity::class.java).apply {
                    putExtra(BarcodeScannerActivity.EXTRA_BATCH_MODE, true)
                    putExtra(BarcodeScannerActivity.EXTRA_IS_ADMIN, true)
                    putExtra(BarcodeScannerActivity.EXTRA_TARGET_USER_ID, userId)
                }
                batchScanLauncher.launch(intent)
            } catch (e: Exception) {
                try { Toast.makeText(context, "Scanner not available", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
            }
        }

        btn.setOnClickListener {
            val uid = etUserId.text.toString().trim()
            val nums = etNumbers.text.toString().trim()
            if (uid.isEmpty()) { etUserId.error = "Required"; return@setOnClickListener }
            if (nums.isEmpty()) { etNumbers.error = "Required"; return@setOnClickListener }
            btn.isEnabled = false; btn.text = "ADDING..."
            ApiClient.getService(requireContext()).addAdminTrackingBulk(
                targetUserId = uid.toIntOrNull() ?: 0, trackingNumbers = nums,
                description = etDesc.text.toString().trim().ifEmpty { null }
            ).enqueue(object : Callback<ApiResponse<String>> {
                override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                    if (!isAdded) return
                    btn.isEnabled = true; btn.text = "ADD BULK TRACKING"
                    if (response.isSuccessful && response.body()?.success == true) {
                        try { Toast.makeText(context, "Added for user $uid", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                        etNumbers.text?.clear(); etDesc.text?.clear()
                    } else { try { Toast.makeText(context, response.body()?.message ?: "Failed", Toast.LENGTH_SHORT).show() } catch (_: Exception) {} }
                }
                override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                    if (!isAdded) return
                    btn.isEnabled = true; btn.text = "ADD BULK TRACKING"
                    try { Toast.makeText(context, "Error: ${t.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                }
            })
        }
    }
}
