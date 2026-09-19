package com.packtracker.models

data class AdminChatData(
    val chats: List<AdminChatItem>? = null
)

data class AdminChatItem(
    val user_id: Any? = null,
    val username: String? = null,
    val display_name: String? = null,
    val last_message: String? = null,
    val last_message_date: String? = null,
    val custom_user_id: String? = null,
    val unread_count: Int? = null
)
