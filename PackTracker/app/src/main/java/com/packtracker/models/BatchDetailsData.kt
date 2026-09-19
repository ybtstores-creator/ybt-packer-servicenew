package com.packtracker.models

data class BatchDetailsData(
    val items: List<TrackingItem>? = null,
    val cartons: List<Carton>? = null
)
