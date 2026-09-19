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
    echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;">';
}, 1);

// Load modern navbar
require_once YBT_PLUGIN_PATH . 'templates/components/dashboard-navbar-modern.php';
render_dashboard_navbar($current_user);

wp_enqueue_style('ybt-chat-style', YBT_PLUGIN_URL . 'assets/chat-style.css', array(), YBT_VERSION);
?>

<div class="ybt-dashboard-wrapper animate-fade-in-up">
    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-headset" style="color: #10b981;"></i> Customer Support
                </h2>
                <p class="text-muted small mb-0">Direct live message channel with YBT Packer warehouse support team</p>
            </div>
            <div class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2 fs-6" style="border: 1px solid rgba(16, 185, 129, 0.25);">
                <i class="fas fa-circle text-success me-2" style="font-size: 8px;"></i>Live Support Online
            </div>
        </div>

        <div class="ybt-chat-container glass-card glass-card-green" style="border-radius: 20px; overflow: hidden; background: #ffffff;">
            <div class="ybt-chat-main">
                <div class="ybt-chat-header border-bottom p-3.5 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #047857 0%, #10b981 100%); color: #fff;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 44px; height: 44px; font-size: 1.2rem;">
                            <i class="fas fa-user-shield" style="color: #10b981;"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-white fs-6">YBT Warehouse Desk</h5>
                            <small class="text-white-50"><i class="fas fa-circle me-1" style="color: #d4af37; font-size: 8px;"></i>Mon - Sat (8am - 10pm GMT)</small>
                        </div>
                    </div>
                    <a href="<?php echo home_url('/dashboard/'); ?>" class="btn btn-sm btn-outline-light rounded-pill px-3">
                        <i class="fas fa-arrow-left me-1"></i> Dashboard
                    </a>
                </div>

                <div class="ybt-chat-messages p-4" id="ybt-chat-messages" style="min-height: 380px; max-height: 480px; overflow-y: auto; background: #f8fafc;">
                    <div class="ybt-chat-empty text-center py-5 text-muted">
                        <i class="fas fa-comments fa-3x mb-3 text-success opacity-50"></i>
                        <h4 class="fw-bold text-dark">Start a Conversation</h4>
                        <p class="small text-muted max-w-sm mx-auto">Have questions about parcel dimensions, shipping quotes, or consolidation status? Send us a message!</p>
                    </div>
                </div>

                <div class="ybt-chat-input-area p-3 border-top bg-white">
                    <div id="file-preview" class="ybt-file-preview mb-2 p-2 bg-light rounded-3 d-flex align-items-center justify-content-between" style="display:none;">
                        <span class="small text-dark fw-semibold"><i class="fas fa-file-image me-2 text-success"></i><span id="filename"></span></span>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle py-0 px-2" id="remove-file">×</button>
                    </div>
                    <form id="ybt-chat-form" enctype="multipart/form-data">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-light rounded-circle border p-2 text-secondary" id="btn-attach" title="Attach Photo">
                                <i class="fas fa-paperclip fs-5"></i>
                            </button>
                            <input type="file" id="chat-file-input" name="chat_file" accept="image/*" style="display:none;">
                            <textarea class="form-control form-control-premium border-0" name="message" id="chat-message-input" placeholder="Type your message here..." rows="1" style="resize:none; background: #f1f5f9;"></textarea>
                            <button type="submit" class="btn btn-premium-primary rounded-circle p-2.5 d-flex align-items-center justify-content-center shadow-sm" id="btn-send" style="width: 46px; height: 46px;">
                                <i class="fas fa-paper-plane fs-6"></i>
                            </button>
                        </div>
                    </form>
                </div>
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
