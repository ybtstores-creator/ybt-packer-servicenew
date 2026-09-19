package com.packtracker.models

data class AppVersionResponse(
    val update_available: Boolean? = null,
    val latest_version: String? = null,
    val download_url: String? = null,
    val release_notes: String? = null
)
