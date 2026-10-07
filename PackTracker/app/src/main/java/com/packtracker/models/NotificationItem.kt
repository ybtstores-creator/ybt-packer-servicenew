package com.packtracker.models

data class NotificationItem(
    val id: Int? = null,
    val title: String? = null,
    val body: String? = null,
    val type: String? = null,
    val tracking_id: Int? = null,
    val tracking_number: String? = null,
    val created_at: String? = null,
    val is_read: Boolean? = null
)
