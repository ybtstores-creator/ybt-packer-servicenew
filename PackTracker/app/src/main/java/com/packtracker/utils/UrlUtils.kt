package com.packtracker.utils

object UrlUtils {
    /**
     * Rewrite URLs so localhost/127.0.0.1 map to 10.0.2.2 when running on Android emulators.
     * Preserves port numbers (e.g. :8088).
     */
    fun fixUrl(url: String?): String? {
        if (url == null) return null
        return url
            .replace("://localhost", "://10.0.2.2")
            .replace("://127.0.0.1", "://10.0.2.2")
    }
}
