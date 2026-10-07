package com.packtracker

import android.content.Context

/**
 * Application-context holder set in Application.onCreate *before* anything
 * that can crash, so CrashReporter (which runs on a dying thread) can reach
 * storage without depending onPackTrackerApplication.get() being healthy.
 */
object AppContextHolder {
    @Volatile
    private var app: Context? = null

    fun set(context: Context) { app = context.applicationContext }

    fun get(): Context =
        app ?: throw IllegalStateException("AppContextHolder not initialised")
}
