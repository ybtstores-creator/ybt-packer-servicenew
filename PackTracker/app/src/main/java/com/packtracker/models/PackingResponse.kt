package com.packtracker.models

data class PackingResponse(
    val batch_id: String? = null,
    val batch_number: String? = null,
    val items_count: Int? = null,
    val tracking_count: Int? = null,
    val merged: Boolean? = null
)
