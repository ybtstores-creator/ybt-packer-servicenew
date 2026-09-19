package com.packtracker.models

data class OrderListData(
    val orders: List<TrackingItem>? = null,
    val total: Int? = null
)
