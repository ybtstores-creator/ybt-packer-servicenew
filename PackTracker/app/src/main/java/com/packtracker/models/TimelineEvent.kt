package com.packtracker.models

/**
 * One customer-facing parcel event from `ybt_mobile_parcel_timeline`
 * (wp_ybt_tracking_events row subset). All fields nullable so partial
 * payloads never break Gson parsing.
 */
data class TimelineEvent(
    val id: Int? = null,
    val event_type: String? = null,
    val title: String? = null,
    val message: String? = null,
    val status_from: String? = null,
    val status_to: String? = null,
    val actor_name: String? = null,
    val actor_role: String? = null,
    val source: String? = null,
    val occurred_at: String? = null,
    val recorded_at: String? = null
)

/** Paged timeline envelope: { items, has_more } newest-first. */
data class TimelineData(
    val items: List<TimelineEvent>? = null,
    val has_more: Boolean? = null
)
