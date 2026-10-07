<?php
/**
 * Admin Data Management — Delete old data to free disk space
 */
if (!defined('ABSPATH')) exit;
$admin_ajax = admin_url('admin-ajax.php');
$rest_base  = rest_url('ybt-admin/v1');
$nonce      = wp_create_nonce('ybt_admin_nonce');
?>
<style>
.ybt-dc-wrap{max-width:1100px;margin:20px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
.ybt-dc-header{background:linear-gradient(135deg,#1e293b 0%,#334155 100%);color:#fff;padding:24px 28px;border-radius:12px;margin-bottom:24px}
.ybt-dc-header h1{margin:0 0 6px;font-size:22px;font-weight:700}
.ybt-dc-header p{margin:0;opacity:.75;font-size:13px}
.ybt-dc-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px}
.ybt-dc-stat{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center}
.ybt-dc-stat .label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
.ybt-dc-stat .value{font-size:28px;font-weight:700;color:#0f172a;margin:4px 0}
.ybt-dc-stat .sub{font-size:11px;color:#94a3b8}
.ybt-dc-section{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:20px;overflow:hidden}
.ybt-dc-section-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;cursor:pointer}
.ybt-dc-section-header h3{margin:0;font-size:15px;color:#1e293b}
.ybt-dc-section-header .badge{background:#fef3c7;color:#92400e;font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600}
.ybt-dc-section-body{padding:20px}
.ybt-dc-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #f8fafc}
.ybt-dc-row:last-child{border-bottom:none}
.ybt-dc-row .icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.ybt-dc-row .info{flex:1}
.ybt-dc-row .info h4{margin:0;font-size:14px;color:#1e293b}
.ybt-dc-row .info p{margin:2px 0 0;font-size:12px;color:#94a3b8}
.ybt-dc-row .controls{display:flex;gap:8px;align-items:center}
.ybt-dc-row select,.ybt-dc-row input{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px}
.ybt-dc-btn{padding:8px 16px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s}
.ybt-dc-btn-preview{background:#e0f2fe;color:#0369a1}
.ybt-dc-btn-preview:hover{background:#bae6fd}
.ybt-dc-btn-delete{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.ybt-dc-btn-delete:hover{background:#fee2e2}
.ybt-dc-btn-delete:disabled{opacity:.5;cursor:not-allowed}
.ybt-dc-btn-refresh{background:#f0fdf4;color:#15803d}
.ybt-dc-btn-refresh:hover{background:#dcfce7}
.ybt-dc-preview{margin-top:10px;padding:12px;background:#f8fafc;border-radius:8px;font-size:12px;display:none}
.ybt-dc-preview.show{display:block}
.ybt-dc-preview .count{font-size:20px;font-weight:700;color:#b91c1c}
.ybt-dc-result{margin-top:10px;padding:12px;border-radius:8px;font-size:13px;display:none}
.ybt-dc-result.show{display:block}
.ybt-dc-result.success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.ybt-dc-result.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.ybt-dc-danger-zone{border-color:#fca5a5 !important;background:#fff5f5 !important}
.ybt-dc-danger-zone .ybt-dc-section-header{border-bottom-color:#fecaca}
.ybt-dc-danger-zone h3{color:#b91c1c !important}
</style>

<div class="ybt-dc-wrap">
  <div class="ybt-dc-header">
    <h1>🗑️ Data Management</h1>
    <p>Preview and delete old data to free up server disk space. Deleted data cannot be recovered.</p>
  </div>

  <!-- AI System Health -->
  <div class="ybt-dc-section" style="margin-bottom:24px;">
    <div class="ybt-dc-section-header" style="cursor:default;">
      <h3>🤖 AI System Diagnostics</h3>
      <div style="display:flex;gap:8px;">
        <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="aiRunDiag()"><i class="fas fa-search"></i> Scan</button>
        <button class="ybt-dc-btn" style="background:#e0e7ff;color:#4338ca;" onclick="aiRunRepair()"><i class="fas fa-wrench"></i> Auto-Repair</button>
        <button class="ybt-dc-btn" style="background:#fef3c7;color:#92400e;" onclick="aiRunReport()"><i class="fas fa-file-lines"></i> Report</button>
      </div>
    </div>
    <div class="ybt-dc-section-body">
      <div id="ai-health-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;"></div>
      <div id="ai-terminal" style="margin-top:16px;background:#0f172a;color:#38bdf8;border-radius:10px;padding:14px;font-family:'Courier New',monospace;font-size:12px;min-height:100px;max-height:250px;overflow-y:auto;white-space:pre-wrap;">[SYSTEM READY] AI diagnostics initialized.
Press a button above to run diagnostics.</div>
    </div>
  </div>

  <!-- Live Stats -->
  <div class="ybt-dc-stats" id="dc-stats">
    <div class="ybt-dc-stat"><div class="label">Loading...</div><div class="value">—</div></div>
  </div>

  <!-- Tracking / Parcels -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>📦 Tracking Records (Parcels)</h3>
      <span class="badge" id="badge-tracking">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#eff6ff;color:#2563eb">📦</div>
        <div class="info">
          <h4>Delete by Age</h4>
          <p>Remove tracking records older than a set number of days</p>
        </div>
        <div class="controls">
          <select id="tracking-age">
            <option value="30">30+ days</option>
            <option value="60">60+ days</option>
            <option value="90">90+ days</option>
            <option value="150" selected>5 months (150 days)</option>
            <option value="180">6 months (180 days)</option>
            <option value="365">1 year+</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('tracking')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('tracking')">Delete</button>
        </div>
      </div>
      <div class="ybt-dc-row">
        <div class="icon" style="background:#fef3c7;color:#d97706">📦</div>
        <div class="info">
          <h4>Delete by Status</h4>
          <p>Remove all parcels with a specific status (e.g. completed, not_received)</p>
        </div>
        <div class="controls">
          <select id="tracking-status">
            <option value="not_received">Not Received</option>
            <option value="received">Received</option>
            <option value="packed">Packed</option>
            <option value="completed">Completed</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('tracking_status')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('tracking_status')">Delete</button>
        </div>
      </div>
      <div id="preview-tracking" class="ybt-dc-preview"></div>
      <div id="result-tracking" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- Chat Messages -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>💬 Chat Messages</h3>
      <span class="badge" id="badge-chat">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#f0fdf4;color:#16a34a">💬</div>
        <div class="info">
          <h4>Delete Old Messages</h4>
          <p>Remove chat messages older than a set number of days</p>
        </div>
        <div class="controls">
          <select id="chat-age">
            <option value="30">30+ days</option>
            <option value="60">60+ days</option>
            <option value="90">90+ days</option>
            <option value="150" selected>5 months (150 days)</option>
            <option value="180">6 months (180 days)</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('chat')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('chat')">Delete</button>
        </div>
      </div>
      <div id="preview-chat" class="ybt-dc-preview"></div>
      <div id="result-chat" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- Activity Logs -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>📝 Activity Logs</h3>
      <span class="badge" id="badge-activity">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#faf5ff;color:#9333ea">📝</div>
        <div class="info">
          <h4>Delete Old Logs</h4>
          <p>Remove activity log entries older than a set number of days</p>
        </div>
        <div class="controls">
          <select id="activity-age">
            <option value="7">7+ days</option>
            <option value="14">14+ days</option>
            <option value="30">30+ days</option>
            <option value="60">60+ days</option>
            <option value="90">90+ days</option>
            <option value="150" selected>5 months (150 days)</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('activity_log')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('activity_log')">Delete</button>
        </div>
      </div>
      <div id="preview-activity_log" class="ybt-dc-preview"></div>
      <div id="result-activity_log" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- Cartons -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>📐 Cartons</h3>
      <span class="badge" id="badge-cartons">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#fef3c7;color:#d97706">📐</div>
        <div class="info">
          <h4>Delete Old Cartons</h4>
          <p>Remove carton records (and their images) older than a set number of days</p>
        </div>
        <div class="controls">
          <select id="cartons-age">
            <option value="30">30+ days</option>
            <option value="60">60+ days</option>
            <option value="90">90+ days</option>
            <option value="150" selected>5 months (150 days)</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('cartons')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('cartons')">Delete</button>
        </div>
      </div>
      <div id="preview-cartons" class="ybt-dc-preview"></div>
      <div id="result-cartons" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- Uploaded Files -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>🖼️ Uploaded Files</h3>
      <span class="badge" id="badge-uploads">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#fef2f2;color:#dc2626">🖼️</div>
        <div class="info">
          <h4>Delete Old YBT Uploads</h4>
          <p>Removes only files referenced by YBT records (parcel images, carton photos, chat attachments, message images) whose records are older than the selected age. Never touches the rest of wp-content/uploads (Media Library, other plugins).</p>
        </div>
        <div class="controls">
          <select id="uploads-age">
            <option value="30">30+ days</option>
            <option value="60">60+ days</option>
            <option value="90">90+ days</option>
            <option value="150" selected>5 months (150 days)</option>
            <option value="180">6 months (180 days)</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('uploads')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('uploads')">Delete</button>
        </div>
      </div>
      <div id="preview-uploads" class="ybt-dc-preview"></div>
      <div id="result-uploads" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- Orphaned Files -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>🧹 Orphaned Files</h3>
      <span class="badge" id="badge-orphans">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#fffbeb;color:#d97706">🧹</div>
        <div class="info">
          <h4>Delete Orphaned YBT Files</h4>
          <p>Scans the uploads root and ybt-cartons for files that NO YBT record references (leftover test uploads, deleted-row leftovers) and older than the selected age. Media Library files and every referenced file are excluded — the scan only ever sees unreferenced files.</p>
        </div>
        <div class="controls">
          <select id="orphans-age">
            <option value="30">30+ days</option>
            <option value="60" selected>60+ days</option>
            <option value="90">90+ days</option>
            <option value="150">5 months (150 days)</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('orphans')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('orphans')">Delete</button>
        </div>
      </div>
      <div id="preview-orphans" class="ybt-dc-preview"></div>
      <div id="result-orphans" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- Notifications Inbox -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>🔔 Notifications Inbox</h3>
      <span class="badge" id="badge-notifications">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#eff6ff;color:#2563eb">🔔</div>
        <div class="info">
          <h4>Delete Old Notifications</h4>
          <p>Remove notification inbox rows older than a set number of days (frees database space)</p>
        </div>
        <div class="controls">
          <select id="notifications-age">
            <option value="30">30+ days</option>
            <option value="60">60+ days</option>
            <option value="90">90+ days</option>
            <option value="150" selected>5 months (150 days)</option>
            <option value="180">6 months (180 days)</option>
          </select>
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('notifications')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('notifications')">Delete</button>
        </div>
      </div>
      <div id="preview-notifications" class="ybt-dc-preview"></div>
      <div id="result-notifications" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- FCM Tokens -->
  <div class="ybt-dc-section">
    <div class="ybt-dc-section-header">
      <h3>📲 FCM Tokens</h3>
      <span class="badge" id="badge-fcm">—</span>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#eff6ff;color:#2563eb">🔔</div>
        <div class="info">
          <h4>Clear All FCM Tokens</h4>
          <p>Remove all push notification tokens (stale tokens accumulate over time)</p>
        </div>
        <div class="controls">
          <button class="ybt-dc-btn ybt-dc-btn-preview" onclick="dcPreview('fcm_tokens')">Preview</button>
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('fcm_tokens')">Delete All</button>
        </div>
      </div>
      <div id="preview-fcm_tokens" class="ybt-dc-preview"></div>
      <div id="result-fcm_tokens" class="ybt-dc-result"></div>
    </div>
  </div>

  <!-- DANGER ZONE -->
  <div class="ybt-dc-section ybt-dc-danger-zone">
    <div class="ybt-dc-section-header">
      <h3>⚠️ Danger Zone — Irreversible Bulk Delete</h3>
    </div>
    <div class="ybt-dc-section-body">
      <div class="ybt-dc-row">
        <div class="icon" style="background:#fef2f2;color:#dc2626">🗑️</div>
        <div class="info">
          <h4>Delete ALL Tracking Records + Cartons</h4>
          <p>Permanently removes every parcel and carton from the database. Use only for full data reset.</p>
        </div>
        <div class="controls">
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('all_parcels')" style="background:#dc2626;color:#fff">Delete All Parcels</button>
        </div>
      </div>
      <div class="ybt-dc-row">
        <div class="icon" style="background:#fef2f2;color:#dc2626">💬</div>
        <div class="info">
          <h4>Delete ALL Chat Messages</h4>
          <p>Permanently removes all support chat history. Customers will see an empty inbox.</p>
        </div>
        <div class="controls">
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('all_chat')" style="background:#dc2626;color:#fff">Delete All Chat</button>
        </div>
      </div>
      <div class="ybt-dc-row">
        <div class="icon" style="background:#fef2f2;color:#dc2626">📝</div>
        <div class="info">
          <h4>Delete ALL Activity Logs</h4>
          <p>Permanently removes the entire audit trail.</p>
        </div>
        <div class="controls">
          <button class="ybt-dc-btn ybt-dc-btn-delete" onclick="dcDelete('all_activity')" style="background:#dc2626;color:#fff">Delete All Logs</button>
        </div>
      </div>
      <div id="result-danger" class="ybt-dc-result"></div>
    </div>
  </div>
</div>

<script>
(function(){
  var REST  = '<?php echo esc_url_raw($rest_base); ?>';
  var WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';

  // ── Auth: use WordPress admin session (no hardcoded password needed) ─
  function getToken(cb){
    // WordPress admin pages already have an authenticated session;
    // pass the WP REST nonce as X-WP-Nonce so require_admin_auth() accepts it.
    cb(WP_NONCE);
  }

  // ── Load stats on page load ────────────────────────────────────────
  function loadStats(refresh){
    getToken(function(tok){
      if(!tok) return;
      var url = REST + '/data/stats' + (refresh ? '?refresh=1' : '');
      jQuery.ajax({
        url: url,
        headers: { 'X-WP-Nonce': tok },
        success: function(r){
          if(!r.success) return;
          var html = '';
          r.data.tables.forEach(function(t){
            var color = t.rows > 0 ? '#0f172a' : '#94a3b8';
            html += '<div class="ybt-dc-stat">'
              + '<div class="label">' + t.label + '</div>'
              + '<div class="value" style="color:'+color+'">'+ t.rows.toLocaleString() +'</div>'
              + '<div class="sub">'+ t.data_kb + ' KB data</div>'
              + '</div>';
          });
          html += '<div class="ybt-dc-stat"><div class="label">Uploads <a href="javascript:void(0)" onclick="loadStats(true)" style="font-size:11px;color:#2563eb;text-decoration:none;" title="Recalculate disk size">🔄 Refresh</a></div>'
            + '<div class="value">'+ r.data.uploads_kb + ' KB</div>'
            + '<div class="sub">'+ r.data.chat_attachments + ' chat attachments</div></div>';
          document.getElementById('dc-stats').innerHTML = html;

          // Update badges
          r.data.tables.forEach(function(t){
            var key = t.table.replace('ybt_','').replace('packing_requests','pr');
            var el = document.getElementById('badge-' + key);
            if(!el && key === 'activity_log') el = document.getElementById('badge-activity');
            if(!el && key === 'fcm_tokens') el = document.getElementById('badge-fcm');
            if(!el && key === 'notifications') el = document.getElementById('badge-notifications');
            if(el) el.textContent = t.rows.toLocaleString() + ' rows';
          });
          var upBadge = document.getElementById('badge-uploads');
          if(upBadge) upBadge.textContent = r.data.uploads_kb + ' KB';
        }
      });
    });
  }
  window.loadStats = loadStats;

  // ── Preview ────────────────────────────────────────────────────────
  window.dcPreview = function(type){
    getToken(function(tok){
      if(!tok) return;
      var params = { type: type };

      if(type === 'tracking'){
        params.older_than_days = parseInt(document.getElementById('tracking-age').value);
      } else if(type === 'tracking_status'){
        params.type = 'tracking';
        params.status = document.getElementById('tracking-status').value;
      } else if(type === 'chat'){
        params.older_than_days = parseInt(document.getElementById('chat-age').value);
      } else if(type === 'activity_log'){
        params.older_than_days = parseInt(document.getElementById('activity-age').value);
      } else if(type === 'cartons'){
        params.older_than_days = parseInt(document.getElementById('cartons-age').value);
      } else if(type === 'uploads'){
        params.older_than_days = parseInt(document.getElementById('uploads-age').value);
      } else if(type === 'orphans'){
        params.older_than_days = parseInt(document.getElementById('orphans-age').value);
      } else if(type === 'notifications'){
        params.older_than_days = parseInt(document.getElementById('notifications-age').value);
      }

      var el = document.getElementById('preview-' + type.replace('tracking_status','tracking'));
      el.classList.add('show');
      el.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Previewing...';

      jQuery.ajax({
        url: REST + '/data/preview',
        method: 'POST',
        headers: { 'X-WP-Nonce': tok, 'Content-Type': 'application/json' },
        data: JSON.stringify(params),
        success: function(r){
          if(!r.success){ el.innerHTML = 'Error: ' + (r.message||'Unknown'); return; }
          var d = r.data;
          var html = '<div class="count">' + d.count.toLocaleString() + ' items would be deleted</div>';
          if(d.size_kb) html += '<div>~' + d.size_kb + ' KB of files would be freed</div>';
          if(d.sample && d.sample.length){
            html += '<br><strong>Sample records:</strong><br>';
            html += '<table style="width:100%;font-size:11px;border-collapse:collapse;margin-top:6px">';
            var keys = Object.keys(d.sample[0]);
            html += '<tr>' + keys.map(function(k){return '<th style="text-align:left;padding:2px 6px;border-bottom:1px solid #ddd">'+k+'</th>';}).join('') + '</tr>';
            d.sample.forEach(function(row){
              html += '<tr>' + keys.map(function(k){return '<td style="padding:2px 6px;border-bottom:1px solid #f1f5f9">'+(row[k]||'')+'</td>';}).join('') + '</tr>';
            });
            html += '</table>';
          }
          el.innerHTML = html;
        },
        error: function(){ el.innerHTML = 'Request failed'; }
      });
    });
  };

  // ── Delete ─────────────────────────────────────────────────────────
  window.dcDelete = function(type){
    var label = type.replace(/_/g,' ');
    if(!confirm('Are you sure you want to DELETE ' + label + '?\n\nThis action CANNOT be undone.')) return;

    var params = { type: type, confirm: true };

    if(type === 'tracking'){
      params.older_than_days = parseInt(document.getElementById('tracking-age').value);
    } else if(type === 'tracking_status'){
      params.type = 'tracking';
      params.status = document.getElementById('tracking-status').value;
    } else if(type === 'chat'){
      params.older_than_days = parseInt(document.getElementById('chat-age').value);
    } else if(type === 'activity_log'){
      params.older_than_days = parseInt(document.getElementById('activity-age').value);
    } else if(type === 'cartons'){
      params.older_than_days = parseInt(document.getElementById('cartons-age').value);
    } else if(type === 'uploads'){
      params.older_than_days = parseInt(document.getElementById('uploads-age').value);
    } else if(type === 'orphans'){
      params.older_than_days = parseInt(document.getElementById('orphans-age').value);
    } else if(type === 'notifications'){
      params.older_than_days = parseInt(document.getElementById('notifications-age').value);
    }

      var resultType = type.replace('tracking_status','tracking');
    var el = document.getElementById('result-' + resultType) || document.getElementById('result-danger');
    el.className = 'ybt-dc-result show';
    el.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting...';

    getToken(function(tok){
      if(!tok){ el.className='ybt-dc-result show error'; el.textContent='Auth failed'; return; }
      jQuery.ajax({
        url: REST + '/data/delete',
        method: 'POST',
        headers: { 'X-WP-Nonce': tok, 'Content-Type': 'application/json' },
        data: JSON.stringify(params),
        success: function(r){
          if(r.success){
            el.className = 'ybt-dc-result show success';
            var freed = r.data.freed_kb > 0 ? ', freed ~' + r.data.freed_kb + ' KB' : '';
            el.innerHTML = '✅ Deleted ' + r.data.deleted.toLocaleString() + ' ' + r.data.type.replace(/_/g,' ') + ' records' + freed;
            loadStats(); // refresh
          } else {
            el.className = 'ybt-dc-result show error';
            el.innerHTML = '❌ ' + (r.message || 'Delete failed');
          }
        },
        error: function(){
          el.className = 'ybt-dc-result show error';
          el.innerHTML = '❌ Request failed';
        }
      });
    });
  };

  // Init
  loadStats();

  // ═══════ AI OPERATOR FUNCTIONS ═══════
  window.aiLog = function(msg) {
    var t = document.getElementById('ai-terminal');
    if (!t) return;
    var now = new Date().toLocaleTimeString();
    t.innerHTML += '\n[' + now + '] ' + msg;
    t.scrollTop = t.scrollHeight;
  };

  window.aiRenderStats = function(d) {
    var g = document.getElementById('ai-health-grid');
    if (!g) return;
    var cards = [
      {label:'Health Score', value: d.health_score+'%', color: d.health_score>80?'#10b981':d.health_score>60?'#f59e0b':'#ef4444'},
      {label:'Unclaimed', value: d.unclaimed_parcels, color:'#3b82f6'},
      {label:'Stale (>14d)', value: d.stale_parcels, color:'#f59e0e'},
      {label:'Missing Photos', value: d.missing_photos, color:'#8b5cf6'},
      {label:'Old Images (5m)', value: d.old_images_5m_count, color:'#ef4444'},
      {label:'DB Size', value: d.db_size_mb+' MB', color:'#06b6d4'}
    ];
    g.innerHTML = cards.map(c => '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;text-align:center;border-top:3px solid '+c.color+';"><div style="font-size:11px;text-transform:uppercase;color:#64748b;letter-spacing:.5px;">'+c.label+'</div><div style="font-size:26px;font-weight:700;color:#0f172a;margin:4px 0;">'+c.value+'</div></div>').join('');
  };

  window.aiRunDiag = function() {
    aiLog('Running full system diagnostic scan...');
    jQuery.ajax({
      url: '<?php echo $rest_base; ?>/ai/diagnostics',
      method: 'GET',
      headers: {'X-WP-Nonce': WP_NONCE},
      success: function(r) {
        if (r.success) {
          var d = r.data;
          aiLog('Scan completed!');
          aiLog('Health Score: ' + d.health_score + '%');
          aiLog('Unclaimed Parcels: ' + d.unclaimed_parcels);
          aiLog('Stale Warehouse (>14d): ' + d.stale_parcels);
          aiLog('Missing Photos: ' + d.missing_photos);
          aiLog('Old Images (5m): ' + d.old_images_5m_count);
          aiLog('DB Size: ' + d.db_size_mb + ' MB');
          aiRenderStats(d);
        } else {
          aiLog('Error: ' + (r.message || 'Failed'));
        }
      },
      error: function() { aiLog('Request failed — using AJAX fallback'); aiRunDiagAjax(); }
    });
  };

  window.aiRunDiagAjax = function() {
    jQuery.post('<?php echo $admin_ajax; ?>', {
      action: 'ybt_ai_run_diagnostics',
      nonce: '<?php echo wp_create_nonce('ybt_nonce'); ?>'
    }, function(r) {
      if (r.success) {
        aiLog('Scan completed via AJAX!');
        aiLog('Health Score: ' + r.data.health_score + '%');
        aiLog('Unclaimed: ' + r.data.unclaimed_parcels);
        aiLog('Stale: ' + r.data.stale_parcels);
        aiRenderStats(r.data);
      } else {
        aiLog('Error: ' + (r.data || 'Failed'));
      }
    });
  };

  window.aiRunRepair = function() {
    if (!confirm('Run auto-repair? This will fix orphaned records and optimize tables.')) return;
    aiLog('Executing auto-repair...');
    jQuery.post('<?php echo $admin_ajax; ?>', {
      action: 'ybt_ai_auto_repair',
      nonce: '<?php echo wp_create_nonce('ybt_nonce'); ?>'
    }, function(r) {
      if (r.success) {
        aiLog('Repair complete: ' + r.data.message);
        (r.data.actions || []).forEach(function(a) { aiLog('  → ' + a); });
      } else {
        aiLog('Repair failed: ' + (r.data || 'Unknown'));
      }
    });
  };

  window.aiRunReport = function() {
    aiLog('Generating report...');
    jQuery.post('<?php echo $admin_ajax; ?>', {
      action: 'ybt_ai_generate_report',
      nonce: '<?php echo wp_create_nonce('ybt_nonce'); ?>'
    }, function(r) {
      if (r.success) {
        aiLog(r.data.report.replace(/\n/g, '\n  '));
      } else {
        aiLog('Report failed: ' + (r.data || 'Unknown'));
      }
    });
  };
})();
</script>
