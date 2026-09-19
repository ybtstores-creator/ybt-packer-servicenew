package com.packtracker.models

/**
 * One packing batch header as returned by the API.
 * Recreated from fields accessed in AdminPackingFragment.
 */
data class BatchHeader(
    val batch_id: String? = null,
    val username: String? = null,
    val user_id: Int? = null,
    val item_count: Int? = null,
    val request_date: String? = null,
    val status: String? = null,
    val shipping_company_name: String? = null
)
