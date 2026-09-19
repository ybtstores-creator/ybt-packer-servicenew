package com.packtracker.models

data class DashboardData(
    val stats: DashboardStats? = null,
    val user_info: DashboardUserInfo? = null
)

data class DashboardStats(
    val not_received: Int? = null,
    val received: Int? = null,
    val packed: Int? = null,
    val completed: Int? = null
)

data class DashboardUserInfo(
    val username: String? = null,
    val display_name: String? = null,
    val email: String? = null,
    val custom_user_id: String? = null,
    val role: String? = null,
    val is_admin: Boolean? = null
)
