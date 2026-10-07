package com.packtracker.fragments

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.android.material.bottomsheet.BottomSheetDialog
import com.google.android.material.floatingactionbutton.FloatingActionButton
import com.packtracker.R
import com.packtracker.api.ApiClient
import com.packtracker.models.*
import com.packtracker.utils.Session
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class AdminChatFragment : Fragment() {
    companion object { private const val TAG = "PackTracker" }

    private var allUsers = listOf<User>()
    private var chatList = listOf<AdminChatItem>()
    private lateinit var rvChats: RecyclerView
    private lateinit var tvEmpty: TextView

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View? =
        inflater.inflate(R.layout.fragment_admin_chat, container, false)

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        // Role guard: non-admins must never access the admin chat inbox
        if (!Session.isAdmin(requireContext())) {
            try {
                findNavController().popBackStack(R.id.dashboardFragment, false)
            } catch (_: Exception) {}
            return
        }

        rvChats = view.findViewById(R.id.rvChats)
        tvEmpty = view.findViewById(R.id.tvEmpty)
        val etSearch = view.findViewById<EditText>(R.id.etSearch)
        rvChats.layoutManager = LinearLayoutManager(context)

        etSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) { filterChats(s?.toString() ?: "") }
        })

        view.findViewById<FloatingActionButton>(R.id.fabNewChat)?.setOnClickListener { showUserPicker() }
        view.findViewById<View>(R.id.btnRefresh)?.setOnClickListener { loadChatsAndUsers() }
        loadChatsAndUsers()
    }

    override fun onResume() {
        super.onResume()
        // Coming back from a conversation (e.g. after initiating a brand-new
        // chat) — refresh so the new thread appears immediately.
        if (view != null && isAdded && rvChats.adapter != null) {
            loadChatsAndUsers()
        }
    }

    private fun loadChatsAndUsers() {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getAdminChats()
            .enqueue(object : Callback<ApiResponse<AdminChatData>> {
                override fun onResponse(call: Call<ApiResponse<AdminChatData>>, response: Response<ApiResponse<AdminChatData>>) {
                    if (!isAdded) return
                    chatList = response.body()?.data?.chats ?: emptyList()
                    showChatList(chatList)
                }
                override fun onFailure(call: Call<ApiResponse<AdminChatData>>, t: Throwable) {
                    if (!isAdded) return
                    showChatList(emptyList())
                }
            })
        ApiClient.getService(requireContext()).getAdminUsers()
            .enqueue(object : Callback<ApiResponse<UserListData>> {
                override fun onResponse(call: Call<ApiResponse<UserListData>>, response: Response<ApiResponse<UserListData>>) {
                    if (!isAdded) return
                    allUsers = response.body()?.data?.users ?: emptyList()
                }
                override fun onFailure(call: Call<ApiResponse<UserListData>>, t: Throwable) {}
            })
    }

    private fun showChatList(chats: List<AdminChatItem>) {
        if (!isAdded) return
        rvChats.adapter = AdminChatListAdapter(chats) { chat -> openChat(chat) }
        tvEmpty.visibility = if (chats.isEmpty()) View.VISIBLE else View.GONE
        rvChats.visibility = if (chats.isEmpty()) View.GONE else View.VISIBLE
    }

    private fun filterChats(query: String) {
        val q = query.lowercase().trim()
        if (q.isEmpty()) { showChatList(chatList); return }
        val filtered = chatList.filter {
            (it.display_name?.lowercase()?.contains(q) == true) ||
            (it.username?.lowercase()?.contains(q) == true) ||
            (it.custom_user_id?.lowercase()?.contains(q) == true)
        }
        showChatList(filtered)
    }

    private fun showUserPicker() {
        val ctx = context ?: return
        if (allUsers.isEmpty()) {
            try { Toast.makeText(ctx, "Loading users...", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
            ApiClient.getService(requireContext()).getAdminUsers()
                .enqueue(object : Callback<ApiResponse<UserListData>> {
                    override fun onResponse(call: Call<ApiResponse<UserListData>>, response: Response<ApiResponse<UserListData>>) {
                        if (!isAdded) return
                        allUsers = response.body()?.data?.users ?: emptyList()
                        if (allUsers.isNotEmpty()) showUserPickerDialog()
                    }
                    override fun onFailure(call: Call<ApiResponse<UserListData>>, t: Throwable) {}
                })
            return
        }
        showUserPickerDialog()
    }

    private fun showUserPickerDialog() {
        val ctx = context ?: return
        val dialog = BottomSheetDialog(ctx)
        val view = LayoutInflater.from(ctx).inflate(R.layout.dialog_user_picker, null)
        val rvUsers = view.findViewById<RecyclerView>(R.id.rvUsers)
        val etUserSearch = view.findViewById<EditText>(R.id.etUserSearch)
        rvUsers.layoutManager = LinearLayoutManager(ctx)
        val regularUsers = allUsers.filter { it.is_admin != true }
        val adapter = UserPickerAdapter(regularUsers) { user -> dialog.dismiss(); openChatWithUser(user) }
        rvUsers.adapter = adapter
        etUserSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                val q = s?.toString()?.lowercase()?.trim() ?: ""
                if (q.isEmpty()) adapter.updateData(regularUsers)
                else adapter.updateData(regularUsers.filter {
                    (it.username?.lowercase()?.contains(q) == true) ||
                    (it.display_name?.lowercase()?.contains(q) == true) ||
                    (it.effectiveCustomId?.lowercase()?.contains(q) == true)
                })
            }
        })
        dialog.setContentView(view)
        dialog.show()
    }

    private fun openChatWithUser(user: User) {
        val userId = user.user_id ?: user.id ?: return
        val username = user.display_name ?: user.username ?: "User"
        openChatById(userId, username)
    }

    private fun openChat(chat: AdminChatItem) {
        val userId = (chat.user_id as? Number)?.toInt() ?: return
        val username = chat.display_name ?: chat.username ?: "User"
        openChatById(userId, username)
    }

    private fun openChatById(userId: Int, username: String) {
        // Open the full-screen WhatsApp-style conversation.
        try {
            findNavController().navigate(
                R.id.adminChatConversationFragment,
                Bundle().apply {
                    putInt("userId", userId)
                    putString("username", username)
                }
            )
        } catch (e: Exception) {
            try { Toast.makeText(context, "Could not open chat", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
        }
    }

    private fun loadMessages(userId: Int, rvMessages: RecyclerView) {
        if (!isAdded) return
        ApiClient.getService(requireContext()).getAdminMessages(targetUserId = userId)
            .enqueue(object : Callback<ApiResponse<ChatData>> {
                override fun onResponse(call: Call<ApiResponse<ChatData>>, response: Response<ApiResponse<ChatData>>) {
                    if (!isAdded) return
                    val msgs = response.body()?.data?.messages ?: emptyList()
                    rvMessages.adapter = WhatsAppChatAdapter(msgs, showSenderNames = true, mineIsAdmin = true)
                    if (msgs.isNotEmpty()) rvMessages.scrollToPosition(msgs.size - 1)
                }
                override fun onFailure(call: Call<ApiResponse<ChatData>>, t: Throwable) {}
            })
    }
}

class AdminChatListAdapter(
    private val items: List<AdminChatItem>,
    private val onClick: (AdminChatItem) -> Unit
) : RecyclerView.Adapter<AdminChatListAdapter.VH>() {
    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val tvAvatar: TextView = v.findViewById(R.id.tvAvatar)
        val tvName: TextView = v.findViewById(R.id.tvName)
        val tvLastMessage: TextView = v.findViewById(R.id.tvLastMessage)
        val tvTime: TextView = v.findViewById(R.id.tvTime)
        val tvUnread: TextView = v.findViewById(R.id.tvUnread)
        val viewUnreadDot: View = v.findViewById(R.id.viewUnreadDot)
    }
    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_chat_list, parent, false))
    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        val name = item.display_name ?: item.username ?: "Unknown"
        holder.tvAvatar.text = name.take(1).uppercase()
        holder.tvName.text = name
        holder.tvLastMessage.text = item.last_message?.take(60) ?: "No messages yet"
        holder.tvTime.text = item.last_message_date?.take(10) ?: ""
        val unread = item.unread_count ?: 0
        if (unread > 0) { holder.tvUnread.visibility = View.VISIBLE; holder.tvUnread.text = unread.toString(); holder.viewUnreadDot.visibility = View.VISIBLE }
        else { holder.tvUnread.visibility = View.GONE; holder.viewUnreadDot.visibility = View.GONE }
        holder.itemView.setOnClickListener { onClick(item) }
    }
    override fun getItemCount() = items.size
}

class UserPickerAdapter(
    private var users: List<User>,
    private val onClick: (User) -> Unit
) : RecyclerView.Adapter<UserPickerAdapter.VH>() {
    fun updateData(newUsers: List<User>) { users = newUsers; notifyDataSetChanged() }
    class VH(v: View) : RecyclerView.ViewHolder(v) {
        val tvAvatar: TextView = v.findViewById(R.id.tvAvatar)
        val tvName: TextView = v.findViewById(R.id.tvName)
        val tvInfo: TextView = v.findViewById(R.id.tvInfo)
    }
    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH =
        VH(LayoutInflater.from(parent.context).inflate(R.layout.item_chat_list, parent, false))
    override fun onBindViewHolder(holder: VH, position: Int) {
        val user = users[position]
        val name = user.display_name ?: user.username ?: "Unknown"
        holder.tvAvatar.text = name.take(1).uppercase()
        holder.tvName.text = name
        holder.tvInfo.text = user.effectiveCustomId ?: user.email ?: ""
        holder.tvInfo.visibility = View.VISIBLE
        holder.itemView.findViewById<TextView>(R.id.tvTime)?.visibility = View.GONE
        holder.itemView.findViewById<TextView>(R.id.tvUnread)?.visibility = View.GONE
        holder.itemView.findViewById<View>(R.id.viewUnreadDot)?.visibility = View.GONE
        holder.itemView.setOnClickListener { onClick(user) }
    }
    override fun getItemCount() = users.size
}


