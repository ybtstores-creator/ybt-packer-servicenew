<?php
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in()) return;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
?>
<div id="ybt-floating-chat">
<button id="ybt-chat-toggle" class="ybt-chat-btn" aria-label="Open chat">
<span class="ybt-chat-icon" aria-hidden="true">💬</span>
<span class="ybt-chat-badge" style="display:none;" aria-live="polite">0</span>
</button>
<div id="ybt-chat-window" style="display:none;" role="dialog" aria-labelledby="chat-title">
<div class="ybt-chat-header">
<h4 class="ybt-chat-title" id="chat-title">
<span class="ybt-chat-title-icon" aria-hidden="true">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
</svg>
</span>
<span>Chat with Admin</span>
</h4>
<button id="ybt-chat-close" aria-label="Close chat">&times;</button>
</div>
<div id="ybt-chat-messages" aria-live="polite"></div>
<div class="ybt-chat-input-area">
<input type="file" id="ybt-chat-file" accept="image/*,.pdf" style="display:none;" aria-label="Attach file">
<button id="ybt-chat-attach" title="Attach image" aria-label="Attach file">📎</button>
<input type="text" id="ybt-chat-input" placeholder="Type a message..." aria-label="Chat message">
<button id="ybt-chat-send" aria-label="Send message">Send</button>
</div>
</div>
</div>
<style>
/* Security-hardened styles with proper scoping */
#ybt-floating-chat { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
.ybt-chat-btn { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #228B22, #32CD32); border: none; color: white; cursor: pointer; box-shadow: 0 4px 12px rgba(34,139,34,0.4); display: flex; align-items: center; justify-content: center; position: relative; transition: transform 0.2s; }
.ybt-chat-btn:hover { transform: scale(1.1); }
.ybt-chat-badge { position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; }
#ybt-chat-window { position: fixed; bottom: 90px; right: 20px; width: 350px; height: 500px; background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
.ybt-chat-header { background: linear-gradient(135deg, #228B22, #32CD32); color: white; padding: 15px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
.ybt-chat-header h4 { margin: 0; font-size: 16px; }
#ybt-chat-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; }
#ybt-chat-messages { flex: 1; overflow-y: auto; padding: 15px; background: #f8f9fa; }
.ybt-chat-msg { margin-bottom: 12px; display: flex !important; flex-direction: column !important; width: 100%; }
.ybt-chat-msg.admin { align-items: flex-end !important; }
.ybt-chat-msg.user { align-items: flex-start !important; }
.ybt-msg-bubble { max-width: 70%; padding: 10px 14px; border-radius: 18px; word-wrap: break-word; }
.ybt-chat-msg.admin .ybt-msg-bubble { background: #25d366; color: #ffffff; border: 1px solid #25d366; }
.ybt-chat-msg.user .ybt-msg-bubble { background: #0d6efd; color: #ffffff; border: 1px solid #0d6efd; }
.ybt-msg-bubble img { max-width: 100%; border-radius: 8px; margin-top: 5px; }
.ybt-msg-time { font-size: 11px; color: #999; margin-top: 4px; }
.ybt-msg-sender { font-size: 12px; color: #666; margin-bottom: 4px; }
.ybt-chat-input-area { display: flex; padding: 12px; border-top: 1px solid #e0e0e0; background: white; border-radius: 0 0 12px 12px; }
#ybt-chat-attach { background: #f8f9fa; border: 1px solid #ddd; border-radius: 50%; width: 36px; height: 36px; cursor: pointer; font-size: 18px; margin-right: 8px; }
#ybt-chat-input { flex: 1; border: 1px solid #ddd; border-radius: 20px; padding: 8px 15px; outline: none; }
#ybt-chat-send { margin-left: 8px; background: #228B22; color: white; border: none; border-radius: 20px; padding: 8px 20px; cursor: pointer; }
#ybt-chat-send:hover { background: #1a6b1a; }
@media (max-width: 768px) {
#ybt-chat-window { width: calc(100vw - 40px); height: 70vh; bottom: 90px; right: 20px; }
}
</style>
<script>
jQuery(document).ready(function($) {
// SECURITY FIX: HTML escaping function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

let chatOpen = false;
let lastMessageId = 0;
let selectedFile = null;
const ybtNonce = (typeof ybt_ajax !== 'undefined' && ybt_ajax.nonce) ? ybt_ajax.nonce : '<?php echo esc_js(wp_create_nonce('ybt_nonce')); ?>';
const ajaxUrl = (typeof ybt_ajax !== 'undefined' && ybt_ajax.ajax_url) ? ybt_ajax.ajax_url : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
const currentUserId = <?php echo intval($user_id); ?>;

$('#ybt-chat-toggle').click(function() {
    chatOpen = !chatOpen;
    $('#ybt-chat-window').toggle();
    if (chatOpen) {
        loadMessages();
        $('.ybt-chat-badge').hide().attr('aria-live', 'off');
    }
});

$('#ybt-chat-close').click(function() {
    chatOpen = false;
    $('#ybt-chat-window').hide();
});

$('#ybt-chat-send').click(sendMessage);
$('#ybt-chat-input').keypress(function(e) {
    if (e.which === 13 && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

$('#ybt-chat-attach').click(function() {
    $('#ybt-chat-file').click();
});

$('#ybt-chat-file').change(function() {
    if (this.files && this.files[0]) {
        // SECURITY FIX: Validate file type
        const file = this.files[0];
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!validTypes.includes(file.type)) {
            alert('Invalid file type. Only images and PDFs allowed.');
            this.value = '';
            return;
        }
        // SECURITY FIX: Validate file size (max 5MB)
        if (file.size > 5242880) {
            alert('File size exceeds 5MB limit.');
            this.value = '';
            return;
        }
        selectedFile = file;
    }
});

function sendMessage() {
    const msg = $('#ybt-chat-input').val().trim();
    if (!msg && !selectedFile) return;

    // SECURITY FIX: Sanitize message content
    const sanitizedMsg = msg.replace(/[<>]/g, '');
    if (sanitizedMsg.length > 1000) {
        alert('Message too long (max 1000 characters)');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'ybt_send_chat');
    formData.append('nonce', ybtNonce);
    formData.append('message', sanitizedMsg);
    if (selectedFile) {
        formData.append('chat_file', selectedFile);
    }

    $('#ybt-chat-send').prop('disabled', true).text('Sending...');

    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            $('#ybt-chat-send').prop('disabled', false).text('Send');
            if (response.success) {
                $('#ybt-chat-input').val('');
                selectedFile = null;
                $('#ybt-chat-file').val('');
                loadMessages();
            } else {
                alert('Error: ' + escapeHtml(response.data || 'Failed to send message'));
            }
        },
        error: function(xhr) {
            $('#ybt-chat-send').prop('disabled', false).text('Send');
            alert('Network error. Please try again.');
        }
    });
}

function loadMessages() {
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
            action: 'ybt_get_user_chat',
            nonce: ybtNonce,
            user_id: currentUserId
        },
        success: function(response) {
            if (response.success) {
                displayMessages(response.data);
            }
        }
    });
}

function displayMessages(messages) {
    const container = $('#ybt-chat-messages');
    container.empty();

    messages.forEach(msg => {
        const isAdmin = msg.is_admin == 1;
        const msgClass = isAdmin ? 'admin' : 'user';
        const time = new Date(msg.date_created).toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'});
        const senderName = isAdmin ? 'Admin' : (msg.username || 'You');
        const safeSender = escapeHtml(senderName);

        // SECURITY FIX: Escape message content and validate image URLs
        let content = escapeHtml(msg.message);
        if (msg.attachment) {
            const isImage = /\.(jpg|jpeg|png|webp|gif)$/i.test(msg.attachment);
            if (isImage) {
                // Validate URL before displaying
                try {
                    const url = new URL(msg.attachment, window.location.href);
                    if (url.protocol === 'http:' || url.protocol === 'https:') {
                        content += `<br><img src="${escapeHtml(msg.attachment)}" alt="Image" style="max-width:100%;border-radius:8px;margin-top:5px;">`;
                    }
                } catch (e) {
                    console.error('Invalid image URL:', e);
                }
            } else {
                const fileName = msg.attachment.split('/').pop();
                const safeName = escapeHtml(fileName);
                content += `<br><a href="${escapeHtml(msg.attachment)}" target="_blank" rel="noopener noreferrer" style="color:#0d6efd;text-decoration:underline;">📎 ${safeName}</a>`;
            }
        }

        container.append(`
        <div class="ybt-chat-msg ${msgClass}">
            <div class="ybt-msg-sender">${safeSender}</div>
            <div class="ybt-msg-bubble">${content}</div>
            <div class="ybt-msg-time">${time}</div>
        </div>
        `);
    });

    container.scrollTop(container[0].scrollHeight);
}

// Poll for new messages every 10 seconds
setInterval(function() {
    if (chatOpen) {
        loadMessages();
    } else {
        // Check for unread messages
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'ybt_get_user_chat',
                nonce: ybtNonce,
                user_id: currentUserId
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    const lastMsg = response.data[response.data.length - 1];
                    if (lastMsg.is_admin == 1 && lastMsg.id > lastMessageId) {
                        $('.ybt-chat-badge').text('1').show().attr('aria-live', 'assertive');
                        lastMessageId = lastMsg.id;
                    }
                }
            }
        });
    }
}, 10000);
});
</script>
