package com.packtracker.models

import com.google.gson.annotations.SerializedName

/**
 * Edit history for a single tracking number — which admin made each edit,
 * when, what changed (old -> new), and from which source/IP.
 *
 * Backed by the WordPress plugin's ybt_tracking_edits audit table via the
 * `ybt_mobile_admin_tracking_history` endpoint (JWT admin auth required).
 */
data class TrackingEditHistory(
    val tracking_id: Int? = null,
    val tracking_number: String? = null,
    @SerializedName("last_edited_by") val lastEditedBy: Int? = null,
    @SerializedName("last_edited_by_name") val lastEditedByName: String? = null,
    @SerializedName("last_edited_at") val lastEditedAt: String? = null,
    @SerializedName("edit_count") val editCount: Int? = null,
    val total: Int? = null,
    val entries: List<TrackingEditEntry>? = null
)

/**
 * One recorded edit in the audit trail. The server sends `changes` as an
 * already-decoded JSON array of field-change objects.
 */
data class TrackingEditEntry(
    val id: Long? = null,
    val tracking_id: Int? = null,
    val tracking_number: String? = null,
    val admin_id: Int? = null,
    val admin_name: String? = null,
    val action: String? = null,
    val summary: String? = null,
    val changes: List<TrackingFieldChange>? = null,
    val ip_address: String? = null,
    val source: String? = null,
    val date_created: String? = null
)

/**
 * A single field change inside an edit entry: field label plus old and new
 * display values (already humanized server-side, e.g. "not_received" ->
 * "Not Received"). Typed loosely because numeric values can arrive as JSON
 * numbers depending on the column type.
 */
data class TrackingFieldChange(
    val field: String? = null,
    val label: String? = null,
    val from: Any? = null,
    val to: Any? = null
) {
    val fromText: String get() = stringify(from) ?: "—"
    val toText: String get() = stringify(to) ?: "—"

    private fun stringify(value: Any?): String? {
        if (value == null) return null
        if (value is String) return value.ifBlank { null }
        return value.toString()
    }
}
