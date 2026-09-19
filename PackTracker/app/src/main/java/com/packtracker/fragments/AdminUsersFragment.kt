package com.packtracker.fragments

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.android.material.bottomsheet.BottomSheetDialog
import com.google.android.material.button.MaterialButton
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.*
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class AdminUsersFragment : Fragment() {
    companion object { private const val TAG = "PackTracker" }

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_admin_chat, container, false) // reuse layout with search + list

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val rv = view.findViewById<RecyclerView>(R.id.rvChats)
        val tvEmpty = view.findViewById<TextView>(R.id.tvEmpty)
        rv.layoutManager = LinearLayoutManager(context)

        // Find and update the search hint
        view.findViewById<EditText>(R.id.etSearch)?.hint = "Search users..."

        loadUsers(rv, tvEmpty)

        view.findViewById<EditText>(R.id.etSearch)?.addTextChangedListener(object : android.text.TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                loadUsers(rv, tvEmpty, s.toString().ifBlank { null })
            }
            override fun afterTextChanged(s: android.text.Editable?) {}
        })
    }

    private fun loadUsers(rv: RecyclerView, tvEmpty: TextView, search: String? = null) {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getAdminUsers(search = search)
            .enqueue(object : Callback<ApiResponse<UserListData>> {
                override fun onResponse(call: Call<ApiResponse<UserListData>>, response: Response<ApiResponse<UserListData>>) {
                    if (!isAdded) return
                    val users = response.body()?.data?.users ?: emptyList()
                    rv.adapter = AdminUserAdapter(users) { user -> showUserDetail(user) }
                    tvEmpty.visibility = if (users.isEmpty()) View.VISIBLE else View.GONE
                    rv.visibility = if (users.isEmpty()) View.GONE else View.VISIBLE
                }
                override fun onFailure(call: Call<ApiResponse<UserListData>>, t: Throwable) {
                    if (!isAdded) return
                    tvEmpty.visibility = View.VISIBLE
                }
            })
    }

    private fun showUserDetail(user: User) {
        val userId = user.user_id ?: user.id ?: return
        val ctx = context ?: return
        val dialog = BottomSheetDialog(ctx)
        val view = LayoutInflater.from(ctx).inflate(R.layout.dialog_user_detail, null)

        view.findViewById<TextView>(R.id.tvUsername).text = user.username ?: "Unknown"
        view.findViewById<TextView>(R.id.tvEmail).text = user.email ?: "No email"
        view.findViewById<TextView>(R.id.tvRole).text = "Role: ${user.role ?: "user"}"
        view.findViewById<TextView>(R.id.tvUserId).text = "User ID: $userId"

        // Load detailed user info
        ApiClient.getService(requireContext()).getUserDetails(userId)
            .enqueue(object : Callback<ApiResponse<UserDetailsData>> {
                override fun onResponse(call: Call<ApiResponse<UserDetailsData>>, response: Response<ApiResponse<UserDetailsData>>) {
                    if (!isAdded) return
                    val data = response.body()?.data ?: return@onResponse
                    view.findViewById<TextView>(R.id.tvTrackingCount).text = "Tracking: ${data.tracking_count ?: 0}"
                    view.findViewById<TextView>(R.id.tvPackingCount).text = "Packing: ${data.packing_count ?: 0}"
                }
                override fun onFailure(call: Call<ApiResponse<UserDetailsData>>, t: Throwable) {}
            })

        // Reset password button
        view.findViewById<MaterialButton>(R.id.btnResetPassword)?.setOnClickListener {
            val etNewPass = view.findViewById<EditText>(R.id.etNewPassword)
            val newPass = etNewPass?.text.toString().trim()
            if (newPass.length < 6) {
                try { Toast.makeText(ctx, "Password must be 6+ characters", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                return@setOnClickListener
            }
            ApiClient.getService(requireContext()).resetUserPassword(userId, newPass)
                .enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(call: Call<ApiResponse<String>>, response: Response<ApiResponse<String>>) {
                        if (!isAdded) return
                        try { Toast.makeText(ctx, response.body()?.message ?: "Done", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                        etNewPass?.text?.clear()
                    }
                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {}
                })
        }

        dialog.setContentView(view)
        dialog.show()
    }
}

class AdminUserAdapter(
    private val items: List<User>,
    private val onClick: (User) -> Unit
) : RecyclerView.Adapter<AdminUserAdapter.VH>() {
    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val tv1: TextView = v.findViewById(android.R.id.text1)
        val tv2: TextView = v.findViewById(android.R.id.text2)
    }
    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(LayoutInflater.from(parent.context).inflate(android.R.layout.simple_list_item_2, parent, false))
    override fun onBindViewHolder(holder: VH, position: Int) {
        val user = items[position]
        holder.tv1.text = user.username ?: "Unknown"
        holder.tv2.text = "${user.email ?: "No email"} | ${user.role ?: "user"}"
        holder.itemView.setOnClickListener { onClick(user) }
    }
    override fun getItemCount() = items.size
}
