package com.packtracker.models

/** Badge-poll response from `ybt_mobile_notifications_unread`. */
data class UnreadData(
    val last_id: Int? = null,
    val unread: Int? = null
)
