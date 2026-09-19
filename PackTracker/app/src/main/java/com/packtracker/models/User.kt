package com.packtracker.models

import com.google.gson.annotations.SerializedName

data class User(
    val id: Int? = null,
    val user_id: Int? = null,
    val username: String? = null,
    val email: String? = null,
    val display_name: String? = null,
    val mobile_number: String? = null,
    val role: String? = null,
    val is_admin: Boolean? = null,
    val custom_user_id: String? = null,
    @SerializedName("custom_id")
    val customId: String? = null,
    val registered: String? = null,
    val profile_image: String? = null,
    val token: String? = null
) {
    val effectiveCustomId: String?
        get() = custom_user_id ?: customId
}
