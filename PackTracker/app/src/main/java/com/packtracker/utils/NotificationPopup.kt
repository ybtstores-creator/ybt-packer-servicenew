package com.packtracker.utils

import android.app.Activity
import android.animation.ObjectAnimator
import android.animation.ValueAnimator
import android.content.Context
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.view.Gravity
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.animation.DecelerateInterpolator
import android.view.animation.OvershootInterpolator
import android.widget.FrameLayout
import android.widget.ImageButton
import android.widget.TextView
import com.packtracker.R
import com.packtracker.models.NotificationItem
import java.lang.ref.WeakReference

/**
 * Heads-up in-app popup: a banner that slides down over the toolbar when a
 * new notification/message arrives, then auto-dismisses. Tapping it opens
 * the Notifications screen.
 *
 * One banner at a time — a new popup replaces the one on screen. Windows
 * are cleaned up on dismiss so nothing leaks across navigation.
 */
object NotificationPopup {

    private const val AUTO_DISMISS_MS = 9000L
    private const val ENTRY_MS = 360L
    private const val ANIM_MS = 300L
    private const val PULSE_MS = 1000L

    private val handler = Handler(Looper.getMainLooper())
    private var current: PopupHandle? = null
    private var onTap: ((PopupTarget?) -> Unit)? = null

    /**
     * Where a banner tap should land — mirrors the tray child deep links
     * (chat conversation / parcel detail / inbox fallback).
     */
    data class PopupTarget(
        val type: String? = null,
        val trackingNumber: String? = null,
        val chatUserId: Int = 0,
        val chatUserName: String? = null
    )

    /**
     * When true, [GlobalNotificationPoller] skips popping (e.g. the user is
     * already looking at the notifications inbox). Direct [show] calls are
     * never suppressed — an explicit caller knows what it's doing.
     */
    @Volatile
    @JvmStatic
    var suppressed: Boolean = false

    /** Register the action fired when the banner body is tapped. */
    fun setOnTap(action: ((PopupTarget?) -> Unit)?) {
        onTap = action
    }

    /**
     * Show a popup for a new notification.
     *
     * @param activity hosting activity; the banner is added over its content
     * @param title headline, e.g. "Parcel packed"
     * @param body message preview (optional)
     * @param ref tracking number chip line (optional)
     */
    @JvmStatic
    @JvmOverloads
    fun show(
        activity: Activity,
        title: String,
        body: String? = null,
        ref: String? = null,
        target: PopupTarget? = null
    ) {
        if (activity.isFinishing || activity.isDestroyed) return

        // Replace any banner already on screen.
        current?.dismiss()

        val root = activity.findViewById<ViewGroup>(android.R.id.content)
        val popup = LayoutInflater.from(activity)
            .inflate(R.layout.view_notification_popup, root, false) as ViewGroup

        val tvTitle = popup.findViewById<TextView>(R.id.tvPopupTitle)
        val tvBody = popup.findViewById<TextView>(R.id.tvPopupBody)
        val tvRef = popup.findViewById<TextView>(R.id.tvPopupRef)
        val btnClose = popup.findViewById<ImageButton>(R.id.btnPopupClose)

        tvTitle.text = title
        if (body.isNullOrBlank()) {
            tvBody.visibility = View.GONE
        } else {
            tvBody.visibility = View.VISIBLE
            tvBody.text = body
        }
        if (ref.isNullOrBlank()) {
            tvRef.visibility = View.GONE
        } else {
            tvRef.visibility = View.VISIBLE
            tvRef.text = ref
        }

        // Breathing pulse on the status dot so the banner keeps looking
        // alive while it waits. Deliberately slow (1s per half-swing) so it
        // reads as a calm heartbeat, not a flicker. Runs until torn down.
        val dot = popup.findViewById<View>(R.id.viewPopupDot)
        val pulseX = ObjectAnimator.ofFloat(dot, View.SCALE_X, 1f, 1.4f).apply {
            duration = PULSE_MS
            repeatMode = ValueAnimator.REVERSE
            repeatCount = ValueAnimator.INFINITE
            interpolator = DecelerateInterpolator()
        }
        val pulseY = ObjectAnimator.ofFloat(dot, View.SCALE_Y, 1f, 1.4f).apply {
            duration = PULSE_MS
            repeatMode = ValueAnimator.REVERSE
            repeatCount = ValueAnimator.INFINITE
            interpolator = DecelerateInterpolator()
        }

        val handle = PopupHandle(root, popup, pulseX, pulseY)
        current = handle

        val params = popup.layoutParams as FrameLayout.LayoutParams
        params.gravity = Gravity.TOP or Gravity.CENTER_HORIZONTAL
        params.leftMargin = dp(activity, 12)
        params.rightMargin = dp(activity, 12)
        params.topMargin = dp(activity, 8)
        root.addView(popup, params)
        softHaptic(activity)

        // Slide down from under the app bar with a springy overshoot and a
        // subtle scale-up — a clear little "boing" that catches the eye
        // without shouting.
        popup.alpha = 0f
        popup.scaleX = 0.92f
        popup.scaleY = 0.92f
        popup.translationY = -dp(activity, 30).toFloat()
        popup.animate()
            .alpha(1f)
            .translationY(dp(activity, 2).toFloat())
            .scaleX(1f)
            .scaleY(1f)
            .setDuration(ENTRY_MS)
            .setInterpolator(OvershootInterpolator(1.9f))
            .start()

        pulseX.start()
        pulseY.start()

        btnClose.setOnClickListener { handle.dismiss() }
        popup.setOnClickListener {
            handle.dismiss()
            try { onTap?.invoke(target) } catch (_: Exception) {}
        }

        handler.postDelayed({ handle.dismiss() }, AUTO_DISMISS_MS)
    }

    /** Convenience: popup from an inbox item, with its deep-link target. */
    fun show(activity: Activity, item: NotificationItem) {
        show(
            activity,
            title = item.title?.ifBlank { null } ?: "New notification",
            body = item.body,
            ref = item.tracking_number,
            target = targetFor(item.type, item.tracking_number)
        )
    }

    /** Target for the typed push data payload (sender ids ride along for chat). */
    fun targetFor(
        type: String?,
        trackingNumber: String?,
        chatUserId: Int = 0,
        chatUserName: String? = null
    ): PopupTarget? {
        if (type == "chat_message" && chatUserId > 0) {
            return PopupTarget(type, chatUserId = chatUserId, chatUserName = chatUserName)
        }
        if (type == "tracking_update" && !trackingNumber.isNullOrBlank()) {
            return PopupTarget(type, trackingNumber = trackingNumber)
        }
        return null
    }

    private fun dp(activity: Activity, value: Int): Int =
        (value * activity.resources.displayMetrics.density).toInt()

    /**
     * Short, soft haptic tick on banner arrival — a gentle "tap" so a push
     * is felt even when the banner is off-screen or the user is mid-gesture.
     * Never throws: emulators without a vibrator and odd vendor services are
     * silently ignored.
     */
    private fun softHaptic(activity: Activity) {
        try {
            val vibrator: Vibrator? = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val manager = activity.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
                manager?.defaultVibrator
            } else {
                @Suppress("DEPRECATION")
                activity.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
            }
            if (vibrator == null || !vibrator.hasVibrator()) return
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                // 35 ms at low-ish amplitude: noticeable, never jarring.
                vibrator.vibrate(VibrationEffect.createOneShot(35, 90))
            } else {
                @Suppress("DEPRECATION")
                vibrator.vibrate(35)
            }
        } catch (_: Exception) {
            // Haptics are best-effort only.
        }
    }

    /** Owns one banner: idempotent dismiss with slide-up-out animation. */
    private class PopupHandle(
        root: ViewGroup,
        popup: View,
        pulseX: ObjectAnimator?,
        pulseY: ObjectAnimator?
    ) {
        private val rootRef = WeakReference(root)
        private val popupRef = WeakReference(popup)
        private val pulseXRef = WeakReference<ObjectAnimator?>(pulseX)
        private val pulseYRef = WeakReference<ObjectAnimator?>(pulseY)
        @Volatile private var dismissed = false

        fun dismiss() {
            if (dismissed) return
            dismissed = true
            if (current === this) current = null
            // Stop the dot pulse before animating out.
            pulseXRef.get()?.cancel()
            pulseYRef.get()?.cancel()
            val popup = popupRef.get() ?: return
            val root = rootRef.get()
            popup.animate()
                .alpha(0f)
                .translationY(-popup.height.toFloat().coerceAtLeast(1f))
                .scaleX(0.94f)
                .scaleY(0.94f)
                .setDuration(ANIM_MS)
                .withEndAction {
                    (popup.parent as? ViewGroup)?.removeView(popup)
                }
                .start()
            // Safety net in case the animation never completes (e.g. activity
            // finishes mid-animation).
            root?.postDelayed({
                (popup.parent as? ViewGroup)?.removeView(popup)
            }, ANIM_MS + 200)
        }
    }
}
