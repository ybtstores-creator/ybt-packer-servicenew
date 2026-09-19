package com.packtracker.models

import com.google.gson.annotations.SerializedName

data class DashboardInfo(
    @SerializedName("announcement")
    val announcement: Announcement? = null,
    @SerializedName("payment_settings")
    val paymentSettings: PaymentSettings? = null,
    @SerializedName("warehouse_address")
    val warehouseAddress: WarehouseAddress? = null,
    @SerializedName("user_profile")
    val userProfile: UserProfile? = null
)

data class Announcement(
    @SerializedName("message")
    val message: String? = null,
    @SerializedName("image_url")
    val imageUrl: String? = null,
    @SerializedName("video_url")
    val videoUrl: String? = null,
    @SerializedName("date_created")
    val dateCreated: String? = null
)

data class PaymentSettings(
    @SerializedName("bank_name")
    val bankName: String? = null,
    @SerializedName("account_name")
    val accountName: String? = null,
    @SerializedName("account_number")
    val accountNumber: String? = null,
    @SerializedName("qr_code_image")
    val qrCodeImage: String? = null
)

data class WarehouseAddress(
    @SerializedName("address")
    val address: String? = null,
    @SerializedName("hours")
    val hours: String? = null,
    @SerializedName("phone")
    val phone: String? = null
)

data class UserProfile(
    @SerializedName("username")
    val username: String? = null,
    @SerializedName("display_name")
    val displayName: String? = null,
    @SerializedName("custom_user_id")
    val customUserId: String? = null,
    @SerializedName("email")
    val email: String? = null
)
