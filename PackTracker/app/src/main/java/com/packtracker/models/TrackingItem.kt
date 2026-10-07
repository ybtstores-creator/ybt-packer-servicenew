package com.packtracker.models

/**
 * One tracking number as returned by the API.
 *
 * NOTE: this class was missing from the working tree (the file exists in the
 * real build tree). Recreated from the fields accessed across fragments.
 * All fields are nullable so partial API responses never break parsing.
 *
 * Serializable so fragments can carry it in their arguments Bundle — that
 * survives process death, unlike passing it through a field.
 */
data class TrackingItem(
    val id: Int? = null,
    val tracking_number: String? = null,
    val user_id: Int? = null,
    val username: String? = null,
    val description: String? = null,
    val status: String? = null,
    val images: String? = null,
    val carton_images: String? = null,
    val batch_id: String? = null,
    val item_count: Int? = null,
    val item_type: String? = null,
    val weight_kg: Double? = null,
    val shipping_method: String? = null,
    val date_added: String? = null,
    val date_received: String? = null,
    val date_packed: String? = null,
    val date_completed: String? = null,
    val last_edited_by_name: String? = null,
    val last_edited_at: String? = null,
    val edit_count: Int? = null
) : java.io.Serializable
