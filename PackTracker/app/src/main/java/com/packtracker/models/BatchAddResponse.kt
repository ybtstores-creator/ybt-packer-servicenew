package com.packtracker.models

data class BatchAddResponse(
    val added_count: Int? = null,
    val batch_id: Int? = null,
    val errors: List<String>? = null
)
