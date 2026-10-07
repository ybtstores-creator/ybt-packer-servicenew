package com.packtracker.utils

import com.packtracker.api.ApiClient

object UrlUtils {
    /**
     * Rewrite URLs so localhost/127.0.0.1 map to 10.0.2.2 when running on Android emulators.
     * Also resolves relative paths (e.g. /wp-content/uploads/...) against the API base URL.
     * Preserves port numbers (e.g. :8088).
     */
    fun fixUrl(url: String?): String? {
        if (url.isNullOrBlank() || url == "null" || url == "[]") return null

        var resolved = url.trim().removeSurrounding("\"").removeSurrounding("'")
        if (resolved.isBlank() || resolved == "null" || resolved == "[]") return null

        // Handle scheme-relative URLs (e.g. //domain.com/path)
        if (resolved.startsWith("//")) {
            resolved = "https:$resolved"
        } else if (!resolved.startsWith("http://", ignoreCase = true) && !resolved.startsWith("https://", ignoreCase = true)) {
            // Handle relative paths (e.g. /wp-content/uploads/... or wp-content/uploads/...)
            val base = ApiClient.BASE_URL.trimEnd('/')
            resolved = if (resolved.startsWith("/")) {
                "$base$resolved"
            } else {
                "$base/$resolved"
            }
        }

        return resolved
            .replace("://localhost", "://10.0.2.2")
            .replace("://127.0.0.1", "://10.0.2.2")
    }
}
