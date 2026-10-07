package com.packtracker.utils

import android.content.Context
import com.packtracker.AppContextHolder
import android.os.Build
import android.util.Log
import java.io.File
import java.io.PrintWriter
import java.io.StringWriter
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import kotlin.concurrent.thread

/**
 * Uncaught-exception crash reporting.
 *
 * Chains onto the default handler so the process still dies normally; before
 * dying it persists the crash to local storage, and on the next launch any
 * pending report(s) are POSTed to the site's `ybt_mobile_crash_log` endpoint
 * (best-effort, never throws, capped so a crash loop can't flood storage).
 *
 * Verification hook: the debug-only TestCrashReceiver can synthesize a crash
 * (see AndroidManifest debug overlay) without a real bug.
 */
object CrashReporter {

    private const val TAG = "CrashReporter"
    private const val PREFS = "crash_reports"
    private const val KEY_PENDING = "pending_crash"
    private const val DIR = "crash-reports"
    private const val MAX_REPORTS = 5          // ring of pending files
    private const val ENDPOINT_PATH = "wp-admin/admin-ajax.php"

    fun install(appVersionName: String, appVersionCode: Int, appId: String) {
        val appContext: Context = AppContextHolder.get()
        installInternal(appContext, appVersionName, appVersionCode, appId)
    }

    private fun installInternal(
        context: Context,
        versionName: String,
        versionCode: Int,
        appId: String
    ) {
        val previous = Thread.getDefaultUncaughtExceptionHandler()

        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            try {
                val stack = throwableToText(throwable, thread)
                persist(context, stack)
                Log.e(TAG, "Crash persisted; process will exit via default handler")
            } catch (_: Throwable) {
                // Never let reporting interfere with the crash path.
            }
            previous?.uncaughtException(thread, throwable)
        }

        // Best-effort flush of anything pending from previous runs, off the main thread.
        thread(name = "crash-report-flush", isDaemon = true) {
            flushPending(context, versionName, versionCode, appId)
        }
    }

    /** Called by the test receiver / debug hooks to simulate a crash report. */
    fun persistSyntheticForTest(context: Context, note: String) {
        val st = Thread.currentThread().stackTrace.joinToString("\n")
        persist(context, "TEST CRASH ($note)\njava.lang.RuntimeException: synthetic test crash\n\tat $st")
    }

    private fun throwableToText(throwable: Throwable, thread: Thread): String {
        val sw = StringWriter()
        PrintWriter(sw).use { pw ->
            pw.println("Thread: ${thread.name}")
            throwable.printStackTrace(pw)
        }
        return sw.toString().take(16000)
    }

    @Synchronized
    private fun persist(context: Context, stack: String) {
        try {
            val dir = File(context.filesDir, DIR).apply { mkdirs() }
            // rotate: keep newest MAX_REPORTS-1 + the new one
            val existing = dir.listFiles()?.sortedBy { it.name }.orEmpty()
            if (existing.size >= MAX_REPORTS) {
                existing.take(existing.size - MAX_REPORTS + 1).forEach { it.delete() }
            }
            val stamp = SimpleDateFormat("yyyyMMdd-HHmmss", Locale.US).format(Date())
            File(dir, "crash-$stamp.txt").writeText(stack)
            context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
                .edit().putBoolean(KEY_PENDING, true).apply()
        } catch (_: Throwable) {}
    }

    private fun pendingFiles(context: Context): List<File> =
        try {
            File(context.filesDir, DIR).listFiles()?.sortedBy { it.name }.orEmpty()
        } catch (_: Throwable) { emptyList() }

    private fun flushPending(
        context: Context,
        versionName: String,
        versionCode: Int,
        appId: String
    ) {
        val files = pendingFiles(context)
        if (files.isEmpty()) return

        for (file in files) {
            val stack = try { file.readText() } catch (_: Throwable) { continue }
            val ok = postReport(
                context = context,
                appId = appId,
                versionName = versionName,
                versionCode = versionCode,
                stack = stack
            )
            if (!ok) break   // server unreachable; keep the rest for next launch
            try { file.delete() } catch (_: Throwable) {}
        }

        if (pendingFiles(context).isEmpty()) {
            context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
                .edit().remove(KEY_PENDING).apply()
        }
    }

    /**
     * Raw OkHttp POST to admin-ajax (no Retrofit dependency so this module
     * stays usable from the Application before anything is initialised).
     */
    private fun postReport(
        context: Context,
        appId: String,
        versionName: String,
        versionCode: Int,
        stack: String
    ): Boolean {
        return try {
        val prefs = context.getSharedPreferences("auth", Context.MODE_PRIVATE)
        val token = prefs.getString("token", null)

        val form = okhttp3.FormBody.Builder()
            .add("action", "ybt_mobile_crash_log")
            .add("app", appId)
            .add("app_version", "$versionName ($versionCode)")
            .add("android_ver", Build.VERSION.RELEASE)
            .add("device", "${Build.MANUFACTURER} ${Build.MODEL}".take(120))
            .add("username", prefs.getString("username", null) ?: "(anonymous)")
            .add("stack_trace", stack)
            .build()

        val base = baseUrl(context)
        if (base.isNullOrBlank()) return false

        val request = okhttp3.Request.Builder()
            .url(base + ENDPOINT_PATH)
            .post(form)
            .apply { if (!token.isNullOrEmpty()) header("Authorization", "Bearer $token") }
            .build()

        val client = okhttp3.OkHttpClient.Builder()
            .connectTimeout(10, java.util.concurrent.TimeUnit.SECONDS)
            .readTimeout(10, java.util.concurrent.TimeUnit.SECONDS)
            .build()

        client.newCall(request).execute().use { resp ->
            resp.isSuccessful
        }
        } catch (_: Throwable) {
            false
        }
    }

    /** Base URL: PackTracker hard-codes it in ApiClient; read the same value here. */
    private fun baseUrl(context: Context): String? = try {
        com.packtracker.api.ApiClient.BASE_URL
    } catch (_: Throwable) { null }
}
