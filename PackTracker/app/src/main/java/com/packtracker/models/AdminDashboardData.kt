package com.packtracker.models

import com.google.gson.annotations.SerializedName

data class AdminDashboardData(
    @SerializedName("user_stats")
    val userStats: UserStats? = null,
    @SerializedName("order_stats")
    val orderStats: OrderStats? = null,
    @SerializedName("recent_activity")
    val recentActivity: List<ActivityItem>? = null
) {
    // Convenience getters for backward compatibility
    val total_users: Int? get() = userStats?.total
    val total_tracking: Int? get() = null  // not returned by server
    val pending_packing: Int? get() = orderStats?.pendingPacking
    val active_orders: Int? get() = null
    val completed_packing: Int? get() = null
}

data class UserStats(
    val total: Int? = null,
    val roles: Map<String, Int>? = null
)

data class OrderStats(
    @SerializedName("pending_packing")
    val pendingPacking: Int? = null,
    @SerializedName("not_received")
    val notReceived: Int? = null,
    @SerializedName("without_images")
    val withoutImages: Int? = null
)

data class ActivityItem(
    val id: Int? = null,
    val action: String? = null,
    val details: String? = null,
    val user_name: String? = null,
    val ip_address: String? = null,
    val date: String? = null
)
