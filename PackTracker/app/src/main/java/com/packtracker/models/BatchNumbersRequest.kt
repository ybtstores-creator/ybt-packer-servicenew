package com.packtracker.models

data class BatchNumbersRequest(
    val tracking_numbers: List<String> = emptyList()
)
