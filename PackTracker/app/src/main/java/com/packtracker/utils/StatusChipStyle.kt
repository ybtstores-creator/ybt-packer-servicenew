package com.packtracker.utils

import android.graphics.drawable.GradientDrawable
import android.widget.TextView
import androidx.core.content.ContextCompat
import com.packtracker.R

/**
 * Presentation-only helper: renders a parcel status the way the WEB SITE does
 * (ybt-consignment.css .ybd-chip-*): tinted background + dark text for
 * pending states, solid fill + white text for done states, hairline border.
 *
 * No logic lives here - callers keep their own status strings and data flow;
 * this only changes how the badge LOOKS. Chip hues are the canonical web
 * palette and stay fixed across night mode (same policy the previous solid
 * chips used, because their labels keep fixed contrast on the fill).
 */
object StatusChipStyle {

    // (background, text, border) - packed/completed solid, the rest tinted,
    // exactly like the web chips.
    private fun triple(status: String?): Triple<Int, Int, Int> = when (status) {
        "received" ->
            Triple(R.color.emerald_50, R.color.emerald_700, R.color.emerald_500)
        "packing_in_progress" ->
            Triple(R.color.gold_100, R.color.gold_700, R.color.gold_500)
        "packed" ->
            Triple(R.color.emerald_500, R.color.white, R.color.emerald_500)
        "completed" ->
            Triple(R.color.emerald_700, R.color.white, R.color.emerald_700)
        else -> // not_received / unknown: kraft-paper neutral
            Triple(R.color.chip_neutral_bg, R.color.chip_neutral_fg, R.color.kraft_deep)
    }

    fun bind(textView: TextView, status: String?) {
        val ctx = textView.context
        val (bg, fg, border) = triple(status)
        val density = ctx.resources.displayMetrics.density
        val drawable = ContextCompat.getDrawable(ctx, R.drawable.bg_status_badge)!!
            .mutate() as GradientDrawable
        drawable.setColor(ContextCompat.getColor(ctx, bg))
        drawable.setStroke(
            (1f * density).toInt().coerceAtLeast(1),
            ContextCompat.getColor(ctx, border))
        textView.background = drawable
        textView.setTextColor(ContextCompat.getColor(ctx, fg))
    }
}
