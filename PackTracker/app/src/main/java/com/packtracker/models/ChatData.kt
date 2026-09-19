package com.packtracker.models

data class ChatData(
    val messages: List<ChatMessage>? = null,
    val last_message: ChatMessage? = null,
    val unread_count: Int? = null
)
