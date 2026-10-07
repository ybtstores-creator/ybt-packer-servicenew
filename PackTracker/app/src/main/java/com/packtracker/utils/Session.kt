package com.packtracker.utils

import android.content.Context

/**
 * Thin wrapper around the "auth" SharedPreferences written by LoginActivity.
 * Single source of truth for role-aware UI gating.
 */
object Session {
    private const val PREFS = "auth"

    fun isLoggedIn(context: Context): Boolean {
        val token = getToken(context)
        return !token.isNullOrEmpty() && token != "demo_token" && token != "guest_session"
    }

    fun getToken(context: Context): String? =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString("token", null)

    fun isAdmin(context: Context): Boolean =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getBoolean("is_admin", false)

    fun clear(context: Context) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().clear().apply()
    }
}
