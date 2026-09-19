package com.packtracker.fragments

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class AddTrackingFragment : Fragment() {
    companion object { private const val TAG = "PackTracker" }

    private lateinit var etNum: TextInputEditText

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_add_tracking, container, false)

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        // Admins should see the Warehouse Intake / Bulk Assignment screen
        if (com.packtracker.utils.Session.isAdmin(requireContext())) {
            try {
                findNavController().navigate(
                    R.id.adminAddTrackingFragment,
                    null,
                    androidx.navigation.navOptions {
                        popUpTo(R.id.addFragment) { inclusive = true }
                    }
                )
            } catch (_: Exception) {}
            return
        }

        etNum = view.findViewById(R.id.etTrackingNumber)
        val etDesc = view.findViewById<TextInputEditText>(R.id.etDescription)
        val btnAdd = view.findViewById<MaterialButton>(R.id.btnAddTracking)

        // Manual add button (regular customer manual entry only)
        btnAdd.setOnClickListener {
            val num = etNum.text.toString().trim()
            if (num.isEmpty()) { etNum.error = "Required"; return@setOnClickListener }
            btnAdd.isEnabled = false; btnAdd.text = "ADDING..."
            ApiClient.getService(requireContext()).addTracking(trackingNumber = num, description = etDesc.text.toString().trim().ifEmpty { null })
                .enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                        if (!isAdded) return
                        btnAdd.isEnabled = true; btnAdd.text = "ADD TRACKING"
                        if (response.isSuccessful && response.body()?.success == true) {
                            try { Toast.makeText(context, "Added: $num", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                            etNum.text?.clear(); etDesc.text?.clear()
                        } else { try { Toast.makeText(context, response.body()?.message ?: "Failed", Toast.LENGTH_SHORT).show() } catch (_: Exception) {} }
                    }
                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        if (!isAdded) return
                        btnAdd.isEnabled = true; btnAdd.text = "ADD TRACKING"
                        try { Toast.makeText(context, "Error: ${t.message}", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                    }
                })
        }
    }
}
