package com.packtracker.utils

import android.content.Context

/**
 * Thin wrapper around the "auth" SharedPreferences written by LoginActivity.
 * Single source of truth for role-aware UI gating.
 */
object Session {
    private const val PREFS = "auth"

    fun isAdmin(context: Context): Boolean =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getBoolean("is_admin", false)
}
