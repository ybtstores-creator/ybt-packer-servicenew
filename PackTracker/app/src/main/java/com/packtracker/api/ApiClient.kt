package com.packtracker.api

import android.content.Context
import android.util.Log
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object ApiClient {

    private const val TAG = "PackTracker"
    private const val BASE_URL = "http://10.0.2.2:8080/"
    private var service: ApiService? = null

    fun getService(context: Context): ApiService {
        if (service == null) {
            Log.d(TAG, "Creating new API service")

            val loggingInterceptor = HttpLoggingInterceptor { message ->
                Log.d(TAG, "HTTP: $message")
            }.apply {
                level = HttpLoggingInterceptor.Level.NONE
            }

            val clientBuilder = OkHttpClient.Builder()
                .connectTimeout(30, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .addInterceptor { chain ->
                    // Read token from prefs on EVERY request (not cached at creation)
                    val prefs = context.getSharedPreferences("auth", Context.MODE_PRIVATE)
                    val token = prefs.getString("token", null)
                    val original = chain.request()
                    val requestBuilder = original.newBuilder()
                        .method(original.method, original.body)
                    if (!token.isNullOrEmpty() && token != "demo_token" && token != "guest_session") {
                        requestBuilder.header("Authorization", "Bearer $token")
                    }
                    Log.d(TAG, "Request: ${original.url.encodedPath}")
                    chain.proceed(requestBuilder.build())
                }
                .addInterceptor(loggingInterceptor)

            val retrofit = Retrofit.Builder()
                .baseUrl(BASE_URL)
                .client(clientBuilder.build())
                .addConverterFactory(GsonConverterFactory.create())
                .build()

            service = retrofit.create(ApiService::class.java)
            Log.d(TAG, "API service created")
        }
        return service!!
    }

    fun clearToken(context: Context) {
        context.getSharedPreferences("auth", Context.MODE_PRIVATE).edit().remove("token").apply()
        service = null
    }

    /** Call after login to rebuild with new token */
    fun rebuild() {
        service = null
    }
}
