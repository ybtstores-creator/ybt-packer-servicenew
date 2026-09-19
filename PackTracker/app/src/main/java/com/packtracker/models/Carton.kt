package com.packtracker.models

data class Carton(
    val id: String? = null,
    val batch_id: String? = null,
    val carton_number: String? = null,
    val weight_kg: String? = null,
    val payment_amount: String? = null,
    val payment_currency: String? = null,
    val carton_image: String? = null,
    val date_created: String? = null
)
