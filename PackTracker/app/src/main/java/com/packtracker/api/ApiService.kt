package com.packtracker.api

import com.packtracker.models.*
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.Call
import retrofit2.http.*

interface ApiService {

    // ═══════════════════════════════════════════════
    // AUTH
    // ═══════════════════════════════════════════════

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun login(
        @Field("action") action: String = "ybt_mobile_login",
        @Field("username") username: String,
        @Field("password") password: String,
        @Field("fcm_token") fcmToken: String? = null
    ): Call<ApiResponse<User>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun register(
        @Field("action") action: String = "ybt_mobile_register",
        @Field("username") username: String,
        @Field("email") email: String,
        @Field("password") password: String,
        @Field("mobile_number") mobile: String? = null
    ): Call<ApiResponse<String>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun updateFcmToken(
        @Field("action") action: String = "ybt_mobile_update_fcm",
        @Field("fcm_token") fcmToken: String,
        @Field("user_id") userId: Int
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getDashboard(
        @Query("action") action: String = "ybt_mobile_dashboard"
    ): Call<ApiResponse<DashboardData>>

    @GET("wp-admin/admin-ajax.php")
    fun getDashboardInfo(
        @Query("action") action: String = "ybt_mobile_dashboard_info"
    ): Call<ApiResponse<DashboardInfo>>

    @GET("wp-json/ybt/v1/app-version")
    fun checkAppVersion(
        @Query("current_version") currentVersion: String
    ): Call<AppVersionResponse>

    // ═══════════════════════════════════════════════
    // TRACKING
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getTracking(
        @Query("action") action: String = "ybt_mobile_tracking",
        @Query("status") status: String? = null,
        @Query("search") search: String? = null,
        @Query("limit") limit: Int = 20,
        @Query("offset") offset: Int = 0
    ): Call<ApiResponse<TrackingData>>

    @GET("wp-admin/admin-ajax.php")
    fun searchTracking(
        @Query("action") action: String = "ybt_mobile_search_tracking",
        @Query("query") query: String
    ): Call<ApiResponse<TrackingData>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun addTracking(
        @Field("action") action: String = "ybt_mobile_add_tracking",
        @Field("tracking_number") trackingNumber: String,
        @Field("description") description: String? = null,
        @Field("shipping_method") shippingMethod: String? = null,
        @Field("item_count") itemCount: Int = 1
    ): Call<ApiResponse<String>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun claimParcel(
        @Field("action") action: String = "ybt_mobile_claim_parcel",
        @Field("tracking_number") trackingNumber: String
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // PACKING
    // ═══════════════════════════════════════════════

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun requestPacking(
        @Field("action") action: String = "ybt_mobile_request_packing",
        @Field("tracking_ids") trackingIds: String,
        @Field("shipping_company_name") companyName: String? = null,
        @Field("shipping_company_address") companyAddress: String? = null,
        @Field("shipping_company_phone") companyPhone: String? = null,
        @Field("shipping_mark") shippingMark: String? = null,
        @Field("instructions") instructions: String? = null
    ): Call<ApiResponse<PackingResponse>>

    @POST("wp-json/ybt/v1/packing-requests/{id}/tracking-numbers")
    fun addTrackingNumbersBatch(
        @Path("id") batchId: Long,
        @Body request: BatchNumbersRequest
    ): Call<ApiResponse<BatchAddResponse>>

    // ═══════════════════════════════════════════════
    // CHAT — User
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getChat(
        @Query("action") action: String = "ybt_mobile_chat"
    ): Call<ApiResponse<ChatData>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun sendChat(
        @Field("action") action: String = "ybt_mobile_send_chat",
        @Field("message") message: String
    ): Call<ApiResponse<String>>

    @Multipart
    @POST("wp-admin/admin-ajax.php")
    fun sendChatMultipart(
        @Part("action") action: RequestBody,
        @Part("message") message: RequestBody,
        @Part file: MultipartBody.Part
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getNotifications(
        @Query("action") action: String = "ybt_mobile_notifications_v2"
    ): Call<ApiResponse<List<NotificationItem>>>

    // ═══════════════════════════════════════════════
    // ORDERS — Payment
    // ═══════════════════════════════════════════════

    @Multipart
    @POST("wp-admin/admin-ajax.php")
    fun uploadPayment(
        @Part("action") action: RequestBody,
        @Part("tracking_id") trackingId: RequestBody,
        @Part file: MultipartBody.Part
    ): Call<ApiResponse<String>>

    @Multipart
    @POST("wp-admin/admin-ajax.php")
    fun uploadBatchPayment(
        @Part("action") action: RequestBody,
        @Part("batch_id") batchId: RequestBody,
        @Part file: MultipartBody.Part
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // ADMIN — Dashboard
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getAdminDashboard(
        @Query("action") action: String = "ybt_mobile_admin_dashboard"
    ): Call<ApiResponse<AdminDashboardData>>

    // ═══════════════════════════════════════════════
    // ADMIN — Users
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getAdminUsers(
        @Query("action") action: String = "ybt_mobile_admin_users",
        @Query("search") search: String? = null,
        @Query("offset") offset: Int = 0,
        @Query("limit") limit: Int = 20
    ): Call<ApiResponse<UserListData>>

    @GET("wp-admin/admin-ajax.php")
    fun getUserDetails(
        @Query("target_user_id") targetUserId: Int,
        @Query("action") action: String = "ybt_mobile_admin_user_details"
    ): Call<ApiResponse<UserDetailsData>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun resetUserPassword(
        @Field("target_user_id") targetUserId: Int,
        @Field("new_password") newPassword: String,
        @Field("action") action: String = "ybt_mobile_admin_reset_password"
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // ADMIN — Orders
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getAdminOrders(
        @Query("action") action: String = "ybt_mobile_admin_orders",
        @Query("status") status: String? = null,
        @Query("search") search: String? = null,
        @Query("user_id") userId: Int = 0,
        @Query("offset") offset: Int = 0
    ): Call<ApiResponse<OrderListData>>

    /**
     * Edit history for one tracking number: which admin made each edit, when,
     * and what changed. Requires an admin JWT token.
     */
    @GET("wp-admin/admin-ajax.php")
    fun getTrackingEditHistory(
        @Query("action") action: String = "ybt_mobile_admin_tracking_history",
        @Query("tracking_id") trackingId: Int = 0,
        @Query("tracking_number") trackingNumber: String? = null,
        @Query("limit") limit: Int = 100
    ): Call<ApiResponse<TrackingEditHistory>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun updateOrderStatus(
        @Field("action") action: String = "ybt_mobile_admin_update_status",
        @Field("tracking_id") trackingId: Int,
        @Field("status") status: String
    ): Call<ApiResponse<String>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun updateItemQuantity(
        @Field("action") action: String = "ybt_mobile_admin_update_item",
        @Field("id") id: Int,
        @Field("item_count") itemCount: Int
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // ADMIN — Tracking (Bulk)
    // ═══════════════════════════════════════════════

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun addAdminTrackingBulk(
        @Field("action") action: String = "ybt_mobile_admin_add_tracking_bulk",
        @Field("target_user_id") targetUserId: Int,
        @Field("tracking_numbers") trackingNumbers: String,
        @Field("description") description: String? = null,
        @Field("item_count") itemCount: Int = 1,
        @Field("shipping_method") shippingMethod: String? = null
    ): Call<ApiResponse<String>>

    @Multipart
    @POST("wp-admin/admin-ajax.php")
    fun addAdminTrackingBulkMultipart(
        @Part("action") action: RequestBody,
        @Part("target_user_id") targetUserId: RequestBody,
        @Part("tracking_numbers") trackingNumbers: RequestBody,
        @Part("description") description: RequestBody,
        @Part("item_count") itemCount: RequestBody,
        @Part("shipping_method") shippingMethod: RequestBody,
        @Part files: List<MultipartBody.Part>
    ): Call<ApiResponse<String>>

    @Multipart
    @POST("wp-admin/admin-ajax.php")
    fun adminUploadImage(
        @Part("action") action: RequestBody,
        @Part("tracking_id") trackingId: RequestBody,
        @Part("item_count") itemCount: RequestBody,
        @Part file: MultipartBody.Part
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // ADMIN — Parcel Assignment
    // ═══════════════════════════════════════════════

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun assignParcel(
        @Field("action") action: String = "ybt_mobile_assign_parcel",
        @Field("tracking_id") trackingId: Int,
        @Field("user_id") userId: Int
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // ADMIN — Packing
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getAdminPackingRequests(
        @Query("action") action: String = "ybt_mobile_admin_packing_requests",
        @Query("tracking_number") trackingNumber: String? = null
    ): Call<ApiResponse<BatchListData>>

    @GET("wp-admin/admin-ajax.php")
    fun getAdminBatchDetails(
        @Query("action") action: String = "ybt_mobile_admin_batch_details",
        @Query("batch_id") batchId: String
    ): Call<ApiResponse<BatchDetailsData>>

    @Multipart
    @POST("wp-admin/admin-ajax.php")
    fun saveAdminCartons(
        @Part("action") action: RequestBody,
        @Part("batch_id") batchId: RequestBody,
        @Part("carton_data") cartonData: RequestBody,
        @Part files: List<MultipartBody.Part>
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // ADMIN — Chat
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getAdminChats(
        @Query("action") action: String = "ybt_mobile_admin_get_chats",
        @Query("search") search: String? = null
    ): Call<ApiResponse<AdminChatData>>

    @GET("wp-admin/admin-ajax.php")
    fun getAdminMessages(
        @Query("action") action: String = "ybt_mobile_admin_get_messages",
        @Query("target_user_id") targetUserId: Int
    ): Call<ApiResponse<ChatData>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun sendAdminChat(
        @Field("action") action: String = "ybt_mobile_admin_send_chat",
        @Field("target_user_id") targetUserId: Int,
        @Field("message") message: String
    ): Call<ApiResponse<String>>

    @Multipart
    @POST("wp-admin/admin-ajax.php")
    fun sendAdminChatMultipart(
        @Part("action") action: RequestBody,
        @Part("target_user_id") targetUserId: RequestBody,
        @Part("message") message: RequestBody,
        @Part file: MultipartBody.Part
    ): Call<ApiResponse<String>>

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun deleteAdminChat(
        @Field("action") action: String = "ybt_mobile_admin_delete_chat",
        @Field("message_id") messageId: Int
    ): Call<ApiResponse<String>>

    // ADMIN Dashboard Stats: the JWT-authenticated admin-ajax endpoint
    // (getAdminDashboard above) is the one the app uses; the REST variant was
    // never wired up and its response model is not in the tree.

    // ═══════════════════════════════════════════════
    // ADMIN — Delete Tracking
    // ═══════════════════════════════════════════════

    @FormUrlEncoded
    @POST("wp-admin/admin-ajax.php")
    fun deleteTracking(
        @Field("action") action: String = "ybt_mobile_admin_delete_tracking",
        @Field("tracking_id") trackingId: Int
    ): Call<ApiResponse<String>>

    // ═══════════════════════════════════════════════
    // PACKING — User Packed Batches
    // ═══════════════════════════════════════════════

    @GET("wp-admin/admin-ajax.php")
    fun getPackedBatches(
        @Query("action") action: String = "ybt_mobile_packed_batches"
    ): Call<ApiResponse<BatchListData>>

}
