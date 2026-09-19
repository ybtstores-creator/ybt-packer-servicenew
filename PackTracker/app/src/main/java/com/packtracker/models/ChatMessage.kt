package com.packtracker.models

/**
 * One chat message as returned by the API.
 * `sender_id`/`is_admin` drive sent-vs-received rendering; `attachment` is a
 * full image URL when the message carries a photo.
 */
data class ChatMessage(
    val id: Long? = null,
    val user_id: Int? = null,
    val sender_id: Int? = null,
    val username: String? = null,
    val sender_name: String? = null,
    val message: String? = null,
    val attachment: String? = null,
    val is_admin: Int? = null,
    val is_read: Int? = null,
    val date_created: String? = null,
    val created_at: String? = null
) {
    /** Display helper used by adapters: falls back to sender_name/username. */
    val displayName: String?
        get() = sender_name ?: username

    /** True when this message came from support/admin (renders on the left). */
    val fromSupport: Boolean
        get() = (is_admin ?: 0) == 1

    /** True when this message has been read by the recipient. */
    val isRead: Boolean
        get() = (is_read ?: 0) == 1

    /** Display helper: time portion of the timestamp whichever field arrives. */
    val timeDisplay: String?
        get() {
            val raw = date_created ?: created_at ?: return null
            return if (raw.length >= 16) raw.substring(11, 16) else raw
        }
}
