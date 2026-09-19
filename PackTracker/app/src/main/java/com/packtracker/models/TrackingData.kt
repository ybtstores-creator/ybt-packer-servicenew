package com.packtracker.models

data class TrackingData(
    val not_received: List<TrackingItem>? = null,
    val received: List<TrackingItem>? = null,
    val packed: List<TrackingItem>? = null,
    val completed: List<TrackingItem>? = null,
    val total: Int? = null,
    val items: List<TrackingItem>? = null
)
