package com.packtracker.utils

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * DEBUG-ONLY crash-report test hook. Registered via the debug manifest
 * overlay so release builds never contain the receiver.
 *
 *   adb shell am broadcast -n com.packtracker/.utils.TestCrashReceiver \
 *       --es note "manual probe"
 */
class TestCrashReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val note = intent.getStringExtra("note") ?: "no-note"
        try {
            CrashReporter.persistSyntheticForTest(context.applicationContext, note)
            Log.e("TestCrashReceiver", "Synthetic crash persisted (note=$note); restart app to flush")
        } catch (t: Throwable) {
            Log.e("TestCrashReceiver", "persist failed", t)
        }
    }
}
