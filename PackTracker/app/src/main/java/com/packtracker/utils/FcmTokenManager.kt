package com.packtracker.utils

import android.content.Context
import android.util.Log
import com.google.android.gms.tasks.OnCompleteListener
import com.google.firebase.messaging.FirebaseMessaging
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

/**
 * FCM device-token lifecycle:
 *  - [storeToken] persists the token locally (survives process death).
 *  - [syncToServer] registers it against the signed-in user
 *    (`ybt_mobile_update_fcm`); safe to call repeatedly.
 *  - [unregisterAndClear] removes the server token (via `ybt_mobile_unregister_fcm`)
 *    and wipes local state (logout).
 *
 * Tokens are re-synced on every MainActivity launch, which self-heals any
 * missed registration (app updated, Firebase refreshed the token offline…).
 *
 * Concurrency: all mutable local state is guarded by [lock]. Two monotonic
 * counters defeat stale async callbacks:
 *  - `generation` is bumped whenever the token is replaced or local state is
 *    cleared, so an in-flight server sync that started before the change can
 *    detect it has been superseded and discard its result (instead of marking a
 *    dead/replaced token as synced).
 *  - `fetchSeq` tags each Firebase token fetch; a slow, older fetch that resolves
 *    after a newer one is dropped instead of overwriting the fresher token.
 */
object FcmTokenManager {

    private const val TAG = "FcmTokenManager"
    private const val PREFS = "fcm_state"
    private const val KEY_TOKEN = "fcm_token"
    private const val KEY_SYNCED = "fcm_synced"
    private const val KEY_GEN = "fcm_gen"

    private val lock = Any()

    /** Bumped whenever stored state is reset or the token is replaced; mirrored into prefs. */
    @Volatile
    private var generation: Int = 0

    /** Tags each [fetchAndStore] so only the latest fetch may write its result. */
    @Volatile
    private var fetchSeq: Int = 0

    /** True while a server registration call is outstanding (dedupes concurrent retries). */
    @Volatile
    private var syncInFlight: Boolean = false

    private fun prefs(context: Context) =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun storeToken(context: Context, token: String) {
        synchronized(lock) {
            val p = prefs(context)
            val current = p.getString(KEY_TOKEN, null)
            val sameToken = current == token
            // Replacing the token invalidates any in-flight sync for the old one.
            if (!sameToken) {
                generation++
            }
            p.edit()
                .putString(KEY_TOKEN, token)
                // Keep the synced flag only when the token is unchanged; a new token is unsynced.
                .putBoolean(KEY_SYNCED, sameToken && p.getBoolean(KEY_SYNCED, false))
                .putInt(KEY_GEN, generation)
                .apply()
        }
    }

    fun getToken(context: Context): String? =
        prefs(context).getString(KEY_TOKEN, null)

    /** True when the stored token has been acknowledged by the server. */
    fun isSynced(context: Context): Boolean =
        prefs(context).getBoolean(KEY_SYNCED, false)

    /** Fetch the current Firebase token and store it locally. */
    fun fetchAndStore(context: Context, onDone: ((String?) -> Unit)? = null) {
        val mySeq = synchronized(lock) { ++fetchSeq }
        try {
            FirebaseMessaging.getInstance().token
                .addOnCompleteListener(OnCompleteListener { task ->
                    val token = if (task.isSuccessful) task.result else null
                    if (token == null) {
                        Log.w(TAG, "FCM token fetch failed", task.exception)
                    } else {
                        // Drop this result if a newer fetch has been issued since.
                        val isLatest = synchronized(lock) { mySeq == fetchSeq }
                        if (isLatest) {
                            storeToken(context, token)
                        } else {
                            Log.d(TAG, "Discarding stale FCM token fetch result")
                        }
                    }
                    onDone?.invoke(token)
                })
        } catch (e: Exception) {
            // Firebase not initialised (missing google-services.json).
            Log.w(TAG, "Firebase unavailable", e)
            onDone?.invoke(null)
        }
    }

    /** Register the stored token for the signed-in user, if not already synced. */
    fun syncToServer(context: Context) {
        // Reserve the sync slot and snapshot (token, generation) atomically.
        val request = synchronized(lock) {
            val p = prefs(context)
            val t = p.getString(KEY_TOKEN, null)
            if (t == null || p.getBoolean(KEY_SYNCED, false) || syncInFlight) {
                null
            } else {
                syncInFlight = true
                Pair(t, generation)
            }
        } ?: return
        val (token, gen) = request

        val sessionToken = context.getSharedPreferences("auth", Context.MODE_PRIVATE)
            .getString("token", null)
        if (sessionToken.isNullOrEmpty() || sessionToken == "demo_token" || sessionToken == "guest_session") {
            synchronized(lock) { syncInFlight = false }
            return // Not signed in — retry after next login.
        }

        try {
            ApiClient.getService(context).updateFcmToken(fcmToken = token)
                .enqueue(object : Callback<ApiResponse<String>> {
                    override fun onResponse(
                        call: Call<ApiResponse<String>>,
                        response: Response<ApiResponse<String>>
                    ) {
                        synchronized(lock) {
                            syncInFlight = false
                            val p = prefs(context)
                            // Discard if the state changed while the request was in flight
                            // (token replaced, or unregisterAndClear ran).
                            if (generation != gen || p.getString(KEY_TOKEN, null) != token) {
                                Log.d(TAG, "Discarding stale FCM sync result")
                                return@synchronized
                            }
                            val ok = response.isSuccessful && response.body()?.success == true
                            if (ok) {
                                p.edit().putBoolean(KEY_SYNCED, true).apply()
                                Log.d(TAG, "FCM token registered with server")
                            } else {
                                Log.w(TAG, "FCM token registration rejected: ${response.code()}")
                            }
                        }
                    }

                    override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {
                        synchronized(lock) { syncInFlight = false }
                        Log.w(TAG, "FCM token registration failed: ${t.message}")
                    }
                })
        } catch (e: Exception) {
            synchronized(lock) { syncInFlight = false }
            Log.w(TAG, "FCM sync error", e)
        }
    }

    /** Best-effort server-side unregister; clears local state afterwards. */
    fun unregisterAndClear(context: Context) {
        val token = synchronized(lock) {
            val p = prefs(context)
            val t = p.getString(KEY_TOKEN, null)
            // Invalidate any in-flight fetch/sync so their callbacks can't resurrect state.
            generation++
            fetchSeq++
            p.edit().clear().apply()
            t
        }
        if (!token.isNullOrEmpty()) {
            try {
                ApiClient.getService(context).unregisterFcmToken(fcmToken = token)
                    .enqueue(object : Callback<ApiResponse<String>> {
                        override fun onResponse(
                            call: Call<ApiResponse<String>>,
                            response: Response<ApiResponse<String>>
                        ) {}
                        override fun onFailure(call: Call<ApiResponse<String>>, t: Throwable) {}
                    })
            } catch (_: Exception) {}
        }
    }
}
