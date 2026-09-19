package com.packtracker.utils

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.FileOutputStream

/**
 * Compresses images before they are uploaded to the server to reduce
 * upload time, bandwidth and disk space on the server.
 *
 * Strategy:
 *  1. Decode the image with RGB_565 (halves memory, no alpha needed for photos)
 *  2. Downscale so the longest edge is at most [MAX_DIMENSION] px
 *  3. Re-encode as JPEG with adaptive quality until under [TARGET_MAX_BYTES]
 */
object ImageCompressor {

    /** Longest allowed edge in pixels — plenty for parcel evidence photos. */
    private const val MAX_DIMENSION = 1280

    /** Target upper bound for the compressed file (~200 KB). */
    private const val TARGET_MAX_BYTES = 200 * 1024

    /** JPEG quality where the adaptive loop starts. */
    private const val START_QUALITY = 85

    /** Never go below this quality — image must stay readable. */
    private const val MIN_QUALITY = 50

    /**
     * Compress the image at [uri] and write the result to a new temp JPEG file.
     * Returns the compressed file, or null when the source cannot be decoded
     * (caller should fall back to uploading the original bytes).
     */
    fun compress(context: Context, uri: Uri): File? {
        return try {
            val bitmap = decodeScaledBitmap(context, uri) ?: return null
            val rotated = applyExifRotation(context, uri, bitmap)
            val bytes = encodeToTargetSize(rotated)
            val out = File.createTempFile("cmp_", ".jpg", context.cacheDir)
            FileOutputStream(out).use { it.write(bytes) }
            if (rotated !== bitmap) bitmap.recycle()
            out
        } catch (_: Exception) {
            null
        }
    }

    /** Decode with an inSampleSize so we never hold a full 12 MP bitmap in memory. */
    private fun decodeScaledBitmap(context: Context, uri: Uri): Bitmap? {
        val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        context.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bounds) }
            ?: return null

        var sample = 1
        while (bounds.outWidth / (sample * 2) >= MAX_DIMENSION ||
            bounds.outHeight / (sample * 2) >= MAX_DIMENSION
        ) {
            sample *= 2
        }

        val opts = BitmapFactory.Options().apply {
            inSampleSize = sample
            inPreferredConfig = Bitmap.Config.RGB_565
        }
        return context.contentResolver.openInputStream(uri)?.use {
            BitmapFactory.decodeStream(it, null, opts)
        }
    }

    /** Respect the camera's orientation flag so photos are never upside-down. */
    private fun applyExifRotation(context: Context, uri: Uri, bitmap: Bitmap): Bitmap {
        val rotation = try {
            context.contentResolver.openInputStream(uri)?.use { stream ->
                when (ExifInterface(stream).getAttributeInt(
                    ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL
                )) {
                    ExifInterface.ORIENTATION_ROTATE_90 -> 90f
                    ExifInterface.ORIENTATION_ROTATE_180 -> 180f
                    ExifInterface.ORIENTATION_ROTATE_270 -> 270f
                    else -> 0f
                }
            } ?: 0f
        } catch (_: Exception) {
            0f
        }
        if (rotation == 0f) return bitmap
        val matrix = Matrix().apply { postRotate(rotation) }
        return Bitmap.createBitmap(bitmap, 0, 0, bitmap.width, bitmap.height, matrix, true)
    }

    /** Re-encode as JPEG, stepping quality down until we fit the target size. */
    private fun encodeToTargetSize(bitmap: Bitmap): ByteArray {
        var quality = START_QUALITY
        var bytes = encode(bitmap, quality)
        while (bytes.size > TARGET_MAX_BYTES && quality > MIN_QUALITY) {
            quality -= 10
            bytes = encode(bitmap, quality)
        }
        return bytes
    }

    private fun encode(bitmap: Bitmap, quality: Int): ByteArray =
        ByteArrayOutputStream().also { bitmap.compress(Bitmap.CompressFormat.JPEG, quality, it) }.toByteArray()
}
