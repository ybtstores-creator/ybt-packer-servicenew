package com.packtracker.models

import com.google.gson.annotations.SerializedName

data class UserDetailsData(
    @SerializedName("profile")
    val user: User? = null,
    @SerializedName("recent_parcels")
    val recent_tracking: List<TrackingItem>? = null,
    val recent_batches: List<BatchHeader>? = null
) {
    val tracking_count: Int? get() = recent_tracking?.size
    val packing_count: Int? get() = recent_batches?.size
}
