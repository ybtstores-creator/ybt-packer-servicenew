package com.packtracker.fragments

import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.util.TypedValue
import android.view.Gravity
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.HorizontalScrollView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast
import com.google.android.material.bottomsheet.BottomSheetDialogFragment
import com.packtracker.api.ApiClient
import com.packtracker.models.ApiResponse
import com.packtracker.models.TrackingEditEntry
import com.packtracker.models.TrackingEditHistory
import com.packtracker.models.TrackingFieldChange
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

/**
 * Bottom sheet showing the edit history of one tracking number: which admin
 * made each edit, when, what changed (old -> new), and from which source/IP.
 *
 * Built entirely in code (no XML layout) so it works with any res setup.
 * Backed by `ybt_mobile_admin_tracking_history` (JWT admin auth).
 *
 * Usage:
 *   EditHistoryDialogFragment.newInstance(trackingId, trackingNumber)
 *       .show(childFragmentManager, "edit_history")
 */
class EditHistoryDialogFragment : BottomSheetDialogFragment() {

    companion object {
        private const val ARG_TRACKING_ID = "tracking_id"
        private const val ARG_TRACKING_NUMBER = "tracking_number"

        fun newInstance(trackingId: Int, trackingNumber: String?): EditHistoryDialogFragment {
            return EditHistoryDialogFragment().apply {
                arguments = Bundle().apply {
                    putInt(ARG_TRACKING_ID, trackingId)
                    putString(ARG_TRACKING_NUMBER, trackingNumber)
                }
            }
        }
    }

    private var trackingId = 0
    private var trackingNumber: String? = null

    private lateinit var contentContainer: LinearLayout
    private var tvMeta: TextView? = null
    private var listContainer: LinearLayout? = null
    private var loadingView: View? = null

    // Palette
    private val colorPrimary = 0xFF2563EB.toInt()
    private val colorTextDark = 0xFF1E293B.toInt()
    private val colorTextMuted = 0xFF64748B.toInt()
    private val colorTextFaint = 0xFF94A3B8.toInt()
    private val colorBgCard = 0xFFF8FAFC.toInt()
    private val colorBgBadge = 0xFFEFF6FF.toInt()
    private val colorRed = 0xFFB91C1C.toInt()
    private val colorGreen = 0xFF15803D.toInt()
    private val colorBorder = 0xFFE2E8F0.toInt()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        trackingId = arguments?.getInt(ARG_TRACKING_ID, 0) ?: 0
        trackingNumber = arguments?.getString(ARG_TRACKING_NUMBER)
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        val ctx = requireContext()
        val density = ctx.resources.displayMetrics.density

        fun dp(v: Int) = (v * density).toInt()

        val root = ScrollView(ctx).apply {
            layoutParams = ViewGroup.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            )
            isFillViewport = true
        }

        contentContainer = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(20), dp(16), dp(20), dp(24))
        }
        root.addView(contentContainer)

        // Header: title + tracking number + summary line (filled in after load)
        val tvTitle = TextView(ctx).apply {
            text = "\uD83D\uDD52 Edit History" // 🕓
            setTextColor(colorTextDark)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 18f)
            setTypeface(typeface, Typeface.BOLD)
        }
        contentContainer.addView(tvTitle)

        val tvTracking = TextView(ctx).apply {
            text = trackingNumber?.let { "Tracking #$it" } ?: "Tracking #${trackingId.takeIf { id -> id > 0 } ?: "—"}"
            setTextColor(colorPrimary)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 14f)
            setTypeface(typeface, Typeface.BOLD)
            setPadding(0, dp(2), 0, dp(2))
        }
        contentContainer.addView(tvTracking)

        tvMeta = TextView(ctx).apply {
            text = "Loading…"
            setTextColor(colorTextMuted)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 12f)
            setPadding(0, dp(2), 0, 0)
        }
        contentContainer.addView(tvMeta)

        // Entry list
        listContainer = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(0, dp(10), 0, 0)
        }
        contentContainer.addView(listContainer)

        // Loading spinner
        loadingView = ProgressBar(ctx).apply {
            setPadding(0, dp(24), 0, dp(24))
            layoutParams = LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { gravity = Gravity.CENTER_HORIZONTAL }
        }
        contentContainer.addView(loadingView)

        loadHistory()
        return root
    }

    private fun loadHistory() {
        if (!isAdded || trackingId <= 0) {
            showError("Invalid tracking reference.")
            return
        }
        val ctx = context ?: return

        ApiClient.getService(ctx).getTrackingEditHistory(trackingId = trackingId)
            .enqueue(object : Callback<ApiResponse<TrackingEditHistory>> {
                override fun onResponse(
                    call: Call<ApiResponse<TrackingEditHistory>>,
                    response: Response<ApiResponse<TrackingEditHistory>>
                ) {
                    if (!isAdded) return
                    val body = response.body()
                    if (response.isSuccessful && body?.success == true && body.data != null) {
                        render(body.data)
                    } else {
                        showError(body?.message ?: "Could not load history (${response.code()})")
                    }
                }

                override fun onFailure(call: Call<ApiResponse<TrackingEditHistory>>, t: Throwable) {
                    if (!isAdded) return
                    showError("Network error: ${t.message ?: "unknown"}")
                }
            })
    }

    private fun showError(message: String) {
        if (!isAdded) return
        loadingView?.visibility = View.GONE
        try {
            Toast.makeText(context, message, Toast.LENGTH_SHORT).show()
        } catch (_: Exception) {}
        dismissAllowingStateLoss()
    }

    private fun render(history: TrackingEditHistory) {
        if (!isAdded) return
        val ctx = requireContext()
        val density = ctx.resources.displayMetrics.density
        fun dp(v: Int) = (v * density).toInt()

        loadingView?.visibility = View.GONE

        // Summary meta line
        val metaLine = buildString {
            append(history.tracking_number ?: "")
            if (!history.lastEditedByName.isNullOrBlank()) {
                append(" · last: ").append(history.lastEditedByName)
            }
            if ((history.editCount ?: 0) > 0) {
                append(" · ").append(history.editCount).append(" edit")
                if (history.editCount != 1) append("s")
            }
        }.ifBlank { "No edits recorded" }

        tvMeta?.text = metaLine.trim()

        val list = listContainer ?: return
        list.removeAllViews()

        val entries = history.entries.orEmpty()
        if (entries.isEmpty()) {
            list.addView(TextView(ctx).apply {
                text = "No edits recorded yet."
                setTextColor(colorTextMuted)
                setTextSize(TypedValue.COMPLEX_UNIT_SP, 13f)
                setPadding(0, dp(8), 0, dp(8))
            })
            return
        }

        for (entry in entries) {
            list.addView(buildEntryCard(ctx, entry, ::dp))
        }
    }

    private fun buildEntryCard(
        ctx: android.content.Context,
        entry: TrackingEditEntry,
        dp: (Int) -> Int
    ): View {
        val card = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            background = GradientDrawable().apply {
                cornerRadius = dp(10).toFloat()
                setColor(colorBgCard)
                setStroke(dp(1), colorBorder)
            }
            setPadding(dp(12), dp(10), dp(12), dp(10))
            layoutParams = LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { bottomMargin = dp(10) }
        }

        // Row 1: admin name + date
        val row1 = LinearLayout(ctx).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
        }
        val tvAdmin = TextView(ctx).apply {
            text = "\uD83D\uDEE1 ${entry.admin_name ?: "System"}" // 🛡
            setTextColor(colorTextDark)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 14f)
            setTypeface(typeface, Typeface.BOLD)
            layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)
        }
        row1.addView(tvAdmin)

        val tvWhen = TextView(ctx).apply {
            text = entry.date_created ?: ""
            setTextColor(colorTextMuted)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
        }
        row1.addView(tvWhen)
        card.addView(row1)

        // Row 2: action badge + summary
        val row2 = LinearLayout(ctx).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(0, dp(4), 0, 0)
        }
        val actionLabel = (entry.action ?: "edit")
            .replace('_', ' ')
            .split(' ')
            .joinToString(" ") { w -> w.replaceFirstChar { c -> c.uppercase() } }
        val tvAction = TextView(ctx).apply {
            text = actionLabel
            setTextColor(colorPrimary)
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
            setTypeface(typeface, Typeface.BOLD)
            background = GradientDrawable().apply {
                cornerRadius = dp(20).toFloat()
                setColor(colorBgBadge)
            }
            setPadding(dp(8), dp(2), dp(8), dp(2))
        }
        row2.addView(tvAction)

        if (!entry.summary.isNullOrBlank()) {
            row2.addView(TextView(ctx).apply {
                text = entry.summary
                setTextColor(colorTextMuted)
                setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
                maxLines = 2
                ellipsize = android.text.TextUtils.TruncateAt.END
                layoutParams = LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT,
                    ViewGroup.LayoutParams.WRAP_CONTENT
                ).apply { marginStart = dp(6) }
            })
        }
        card.addView(row2)

        // Row 3: field changes table (old -> new)
        val changes = entry.changes.orEmpty()
        if (changes.isNotEmpty()) {
            card.addView(buildChangesTable(ctx, changes, dp))
        }

        // Row 4: source / IP
        val footerBits = mutableListOf<String>()
        if (!entry.source.isNullOrBlank()) footerBits.add("via ${entry.source}")
        if (!entry.ip_address.isNullOrBlank()) footerBits.add("IP ${entry.ip_address}")
        if (footerBits.isNotEmpty()) {
            card.addView(TextView(ctx).apply {
                text = footerBits.joinToString(" · ")
                setTextColor(colorTextFaint)
                setTextSize(TypedValue.COMPLEX_UNIT_SP, 10f)
                setPadding(0, dp(6), 0, 0)
            })
        }

        return card
    }

    private fun buildChangesTable(
        ctx: android.content.Context,
        changes: List<TrackingFieldChange>,
        dp: (Int) -> Int
    ): View {
        val scroll = HorizontalScrollView(ctx).apply {
            layoutParams = LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ).apply { topMargin = dp(8) }
            isHorizontalScrollBarEnabled = false
        }

        val table = LinearLayout(ctx).apply { orientation = LinearLayout.VERTICAL }

        for (change in changes) {
            val row = LinearLayout(ctx).apply {
                orientation = LinearLayout.HORIZONTAL
                gravity = Gravity.CENTER_VERTICAL
                setPadding(0, dp(2), 0, dp(2))
            }

            val tvLabel = TextView(ctx).apply {
                text = (change.label ?: change.field ?: "Field")
                setTextColor(colorTextMuted)
                setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
                layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 0.32f)
            }
            row.addView(tvLabel)

            val tvFrom = TextView(ctx).apply {
                text = change.fromText
                setTextColor(colorRed)
                paintFlags = paintFlags or android.graphics.Paint.STRIKE_THRU_TEXT_FLAG
                setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
                maxLines = 2
                layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 0.34f)
            }
            row.addView(tvFrom)

            val tvArrow = TextView(ctx).apply {
                text = "→"
                setTextColor(colorTextFaint)
                setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
                layoutParams = LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT,
                    ViewGroup.LayoutParams.WRAP_CONTENT
                ).apply { marginStart = dp(4); marginEnd = dp(4) }
            }
            row.addView(tvArrow)

            val tvTo = TextView(ctx).apply {
                text = change.toText
                setTextColor(colorGreen)
                setTypeface(typeface, Typeface.BOLD)
                setTextSize(TypedValue.COMPLEX_UNIT_SP, 11f)
                maxLines = 2
                layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 0.34f)
            }
            row.addView(tvTo)

            table.addView(row)
        }

        scroll.addView(table)
        return scroll
    }
}
