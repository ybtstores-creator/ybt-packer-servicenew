<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<div class="alert alert-warning">Please login to access support chat.</div>';
    return;
}

$current_user = wp_get_current_user();

// ====== DASHBOARD SETTINGS ======
add_action('wp_head', function() {
    echo '<style>
        #wpadminbar { display: none !important; }
        body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }
        html { margin-top: 0 !important; }
    </style>';
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: blob: https:; connect-src \'self\' https:;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);
?>

<style>
/* ================================================================
   SUPPORT DESK — page-specific layer (consignment-note system)
   JS contract untouched: #ybt-chat-messages, #ybt-chat-form,
   #btn-attach, #chat-file-input, #chat-message-input, #btn-send,
   #file-preview, #filename, #remove-file.
   ================================================================ */
.ybt-support{
	margin:clamp(16px,3vw,30px) auto;
	max-width:900px;
}
.ybt-support .ybt-deskhead{
	display:flex;
	align-items:flex-end;
	justify-content:space-between;
	gap:16px;
	flex-wrap:wrap;
	margin-bottom:20px;
}
.ybt-support .ybt-deskhead h2{
	font-family:var(--display);
	font-weight:400;
	font-size:clamp(1.25rem,2.6vw,1.6rem);
	margin:0;
}
.ybt-support .ybt-deskhead .ybt-sub{
	margin:4px 0 0;
	color:#3d4a42;
	font-size:14px;
}
.ybt-support .ybt-livechip{
	display:inline-flex;align-items:center;gap:8px;
	font-family:var(--mono);
	font-size:11px;font-weight:600;
	letter-spacing:.08em;text-transform:uppercase;
	color:var(--emerald-deep);
	background:var(--paper);
	border:1.5px solid var(--emerald);
	border-radius:6px;
	padding:8px 12px;
	white-space:nowrap;
}
.ybt-support .ybt-livechip .ybt-livedot{
	width:8px;height:8px;border-radius:50%;
	background:var(--emerald);
	box-shadow:0 0 0 3px rgba(16,185,129,.18);
}
/* the chat window itself: panel chrome in-system */
.ybt-support .ybt-chat-container{
	border:1.5px solid var(--ink) !important;
	border-radius:12px !important;
	box-shadow:6px 6px 0 rgba(15,31,23,.12) !important;
	background:var(--paper) !important;
	overflow:hidden;
}
/* window head: kraft strip with mono desk label */
.ybt-support .ybt-chat-header{
	display:flex !important;
	align-items:center;
	justify-content:space-between;
	gap:12px;
	background:var(--kraft) !important;
	background-image:repeating-linear-gradient(90deg, rgba(60,42,10,.03) 0 1px, transparent 1px 26px) !important;
	color:var(--ink) !important;
	border-bottom:2px dashed var(--kraft-line) !important;
	padding:14px 18px !important;
}
.ybt-support .ybt-chat-header .ybt-deskgroup{ display:flex; align-items:center; gap:12px; }
.ybt-support .ybt-chat-header .ybt-deskgroup small{ display:block; margin-top:3px; }
.ybt-support .ybt-chat-header .ybt-deskid{
	width:42px;height:42px;flex:0 0 42px;
	display:flex;align-items:center;justify-content:center;
	border-radius:50%;
	background:var(--ink);
	color:var(--gold);
	border:2px solid var(--gold);
}
.ybt-support .ybt-chat-header h5{
	font-family:var(--display);
	font-weight:400;
	font-size:15px;
	color:var(--ink) !important;
	margin:0;
}
.ybt-support .ybt-chat-header small{
	font-family:var(--mono);
	font-size:11px;font-weight:600;
	letter-spacing:.08em;text-transform:uppercase;
	color:#5c5543 !important;
}
.ybt-support .ybt-chat-header small i{ color:var(--gold-deep) !important; }
.ybt-support .ybt-chat-header a.ybt-backlink{
	font-family:var(--mono);
	font-size:11.5px;font-weight:600;
	letter-spacing:.08em;text-transform:uppercase;
	color:var(--emerald-deep);
	text-decoration:underline;
}
.ybt-support .ybt-chat-header a.ybt-backlink:hover{ color:var(--emerald); }
/* messages ledger */
.ybt-support .ybt-chat-messages{
	background:#fbf8f1 !important;
	border-bottom:2px dashed var(--kraft-line);
}
.ybt-support .ybt-chat-empty i{ color:var(--kraft-deep) !important; opacity:1 !important; }
.ybt-support .ybt-chat-empty h4{ font-family:var(--sans); font-weight:700; font-size:16px; color:var(--ink) !important; }
/* input bar on paper */
.ybt-support .ybt-chat-input-area{ background:var(--paper) !important; }
.ybt-support .ybt-chat-input-area form > div{ display:flex; align-items:center; gap:10px; }
.ybt-support #chat-message-input{ flex:1 1 auto; min-width:0; }
.ybt-support #chat-message-input{
	background:#fbf8f1 !important;
	border:1.5px solid var(--kraft-deep) !important;
	border-radius:8px !important;
}
.ybt-support #chat-message-input:focus{
	border-color:var(--emerald) !important;
	box-shadow:0 0 0 3px rgba(16,185,129,.22) !important;
}
.ybt-support #btn-attach{
	width:42px;height:42px;flex:0 0 42px;
	display:inline-flex;align-items:center;justify-content:center;
	border-radius:50%;
	border:1.5px solid var(--kraft-deep) !important;
	background:var(--kraft) !important;
	color:var(--ink) !important;
}
.ybt-support #btn-send{ background:var(--gold) !important; color:var(--ink) !important; border-radius:10px !important; }
.ybt-support #btn-send:hover{ background:#dfbc48 !important; transform:translateY(-1px); }
.ybt-support #file-preview{ background:var(--gold-tint) !important; border:1px solid var(--gold); border-radius:8px; }
.ybt-support #file-preview #filename{ font-family:var(--mono); font-size:12.5px; }
@media (max-width:620px){
	.ybt-support .ybt-chat-header a.ybt-backlink{ display:none; }
}
</style>

<div class="ybt-dashboard-enhanced ybt-support">

	<!-- desk head -->
	<div class="ybt-deskhead">
		<div>
			<p class="ybd-eyebrow">Support desk &middot; Live channel</p>
			<h2>Customer support</h2>
			<p class="ybt-sub">Direct message channel with the YBT Packing Service warehouse team.</p>
		</div>
		<span class="ybt-livechip"><span class="ybt-livedot"></span>Live support online</span>
	</div>

	<div class="ybt-chat-container">
		<div class="ybt-chat-main">
			<div class="ybt-chat-header">
				<div class="ybt-deskgroup">
					<div class="ybt-deskid"><i class="fas fa-user-shield"></i></div>
					<div>
						<h5 class="mb-0">YBT Warehouse Desk</h5>
						<small><i class="fas fa-circle me-1" style="font-size:8px;"></i>Mon - Sat (8am - 10pm GMT)</small>
					</div>
				</div>
				<a href="<?php echo home_url('/dashboard/'); ?>" class="ybt-backlink"><i class="fas fa-arrow-left"></i> Dashboard</a>
			</div>

			<div class="ybt-chat-messages p-4" id="ybt-chat-messages" style="min-height: 380px; max-height: 480px; overflow-y: auto;">
				<div class="ybt-chat-empty text-center py-5 text-muted">
					<i class="fas fa-comments fa-3x mb-3"></i>
					<h4 class="fw-bold">Start a conversation</h4>
					<p class="small max-w-sm mx-auto">Have questions about parcel dimensions, shipping quotes, or consolidation status? Send us a message!</p>
				</div>
			</div>

			<div class="ybt-chat-input-area p-3">
				<div id="file-preview" class="ybt-file-preview mb-2 p-2 d-flex align-items-center justify-content-between" style="display:none;">
					<span class="small fw-semibold"><i class="fas fa-file-image me-2"></i><span id="filename"></span></span>
					<button type="button" class="btn btn-sm btn-outline-danger rounded-circle py-0 px-2" id="remove-file">&times;</button>
				</div>
				<form id="ybt-chat-form" enctype="multipart/form-data">
					<div class="d-flex align-items-center gap-2">
						<button type="button" class="btn rounded-circle border p-2" id="btn-attach" title="Attach Photo">
							<i class="fas fa-paperclip fs-5"></i>
						</button>
						<input type="file" id="chat-file-input" name="chat_file" accept="image/*" style="display:none;">
						<textarea class="form-control border-0" name="message" id="chat-message-input" placeholder="Type your message here..." rows="1" style="resize:none;"></textarea>
						<button type="submit" class="btn rounded-circle d-flex align-items-center justify-content-center" id="btn-send" style="width: 46px; height: 46px;">
							<i class="fas fa-paper-plane fs-6"></i>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
if (typeof ybt_ajax === 'undefined') {
    window.ybt_ajax = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

jQuery(document).ready(function($) {
    const chatContainer = $('#ybt-chat-messages');
    const chatForm = $('#ybt-chat-form');
    const fileInput = $('#chat-file-input');

    let lastMessageCount = 0;
    let currentScrollPos = 0;

    function saveScrollPosition() {
        currentScrollPos = chatContainer.scrollTop();
    }

    function restoreScrollPosition() {
        chatContainer.scrollTop(currentScrollPos);
    }

    function scrollToBottom() {
        chatContainer.scrollTop(chatContainer[0].scrollHeight);
    }

    $('#btn-attach').click(function() {
        fileInput.click();
    });

    fileInput.change(function() {
        if (this.files && this.files[0]) {
            $('#filename').text(this.files[0].name);
            $('#file-preview').fadeIn();
        }
    });

    $('#remove-file').click(function() {
        fileInput.val('');
        $('#file-preview').fadeOut();
    });

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function sanitizeImageUrl(url) {
        if (!url) return '';
        url = String(url).trim();
        if (/^(https?:\/\/|\/)/i.test(url)) {
            return url.replace(/"/g, '&quot;');
        }
        return '';
    }

    function loadMessages(forceScroll = false) {
        saveScrollPosition();

        $.ajax({
            url: ybt_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_chat_messages',
                nonce: ybt_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const messages = response.data;
                    let html = '';

                    if (messages.length === 0) {
                        html = `
                            <div class="ybt-chat-empty text-center py-5 text-muted">
                                <i class="fas fa-comments fa-3x mb-3 text-success opacity-50"></i>
                                <h4 class="fw-bold text-dark">Start a Conversation</h4>
                                <p class="small text-muted max-w-sm mx-auto">Send a message to our support team</p>
                            </div>
                        `;
                    } else {
                        messages.forEach(msg => {
                            const isUser = msg.sender_type === 'user';
                            const messageClass = isUser ? 'text-end' : 'text-start';
                            const bubbleClass = isUser ? 'bg-success text-white' : 'bg-white text-dark shadow-sm border';

                            html += `
                                <div class="mb-3 ${messageClass}">
                                    <div class="d-inline-block p-3 rounded-4 max-w-lg ${bubbleClass}">
                                        ${msg.message ? `<div>${escapeHtml(msg.message)}</div>` : ''}
                                        ${msg.attachment_url ? `
                                            <div class="mt-2">
                                                <img src="${sanitizeImageUrl(msg.attachment_url)}" class="rounded-3" style="max-width: 240px; max-height: 240px; cursor: pointer;" onclick="window.open('${sanitizeImageUrl(msg.attachment_url)}')">
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="small text-muted mt-1 px-1" style="font-size: 0.75rem;">
                                        ${escapeHtml(msg.sender_name || (isUser ? 'You' : 'Support'))} • ${escapeHtml(msg.created_at || '')}
                                    </div>
                                </div>
                            `;
                        });
                    }

                    chatContainer.html(html);

                    if (forceScroll || messages.length > lastMessageCount) {
                        scrollToBottom();
                    } else {
                        restoreScrollPosition();
                    }

                    lastMessageCount = messages.length;
                }
            }
        });
    }

    loadMessages(true);
    setInterval(loadMessages, 5000);

    chatForm.submit(function(e) {
        e.preventDefault();

        const messageInput = $('#chat-message-input');
        const message = messageInput.val().trim();
        const file = fileInput[0].files[0];

        if (!message && !file) return;

        const formData = new FormData(this);
        formData.append('action', 'ybt_send_chat_message');
        formData.append('nonce', ybt_ajax.nonce);

        const btnSend = $('#btn-send');
        btnSend.prop('disabled', true);

        $.ajax({
            url: ybt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    messageInput.val('');
                    fileInput.val('');
                    $('#file-preview').fadeOut();
                    loadMessages(true);
                } else {
                    alert(response.data || 'Failed to send message');
                }
            },
            error: function() {
                alert('Network error. Failed to send message.');
            },
            complete: function() {
                btnSend.prop('disabled', false);
            }
        });
    });
});
</script>
