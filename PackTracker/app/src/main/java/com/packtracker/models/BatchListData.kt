package com.packtracker.models

data class BatchListData(
    val batches: List<BatchHeader>? = null,
    val total: Int? = null
)
