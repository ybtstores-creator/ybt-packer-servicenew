<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ybt-packer-service'));
}
?>

<?php wp_enqueue_style('dashicons'); ?>

<div class="wrap">
    <h1><i class="dashicons dashicons-format-chat"></i> Chat with Users</h1>

    <div class="ybt-admin-container">
        <div class="card">
            <div class="card-header">
                <h3>Live Chat</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 15px; height: 500px;">
                    <!-- User List -->
                    <div style="width: 250px; border-right: 1px solid #ddd; padding-right: 15px;">
                        <h4 style="margin-bottom: 10px; font-size: 14px;">Users</h4>
                        <div style="margin-bottom: 10px;">
                            <input type="text" id="user-search" placeholder="Search users..." style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; margin-bottom: 5px;">
                            <button id="show-all-users" class="button button-secondary" style="width: 100%; font-size: 12px;">Show All Users</button>
                        </div>
                        <div id="user-list" style="height: 320px; overflow-y: auto;"></div>
                    </div>

                    <!-- Chat Area -->
                    <div style="flex: 1;">
                        <div id="chat-header" style="padding: 10px; background: #f0f0f0; margin-bottom: 10px; border-radius: 5px; font-weight: bold;">Select a user to chat</div>
                        <div id="chat-messages" style="height: 320px; overflow-y: auto; padding: 15px; margin-bottom: 15px; background: linear-gradient(135deg, #e5ddd5, #d2dbdc);"></div>

                        <div style="background: white; border-radius: 25px; padding: 5px 15px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);" id="chat-input-container">
                            <textarea id="chat-input" placeholder="Type a message..." style="border: none; outline: none; flex: 1; padding: 8px 0; font-size: 14px; resize: none; max-height: 80px;" rows="1" disabled></textarea>
                            <input type="file" id="chat-file" style="display: none;" accept="image/*,.pdf,.webp,.gif">
                            <button id="attach-btn" style="border: none; background: none; color: #666; font-size: 18px; cursor: pointer;">
                                <span class="dashicons dashicons-paperclip"></span>
                            </button>
                            <button id="send-chat" style="border: none; background: #25d366; color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;" disabled>
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </div>
                        <div id="admin-file-preview" style="margin-top: 5px; display: none;">
                            <small class="text-muted" id="admin-file-name"></small>
                            <button id="admin-remove-file" style="border: none; background: none; color: #dc3545; margin-left: 10px; cursor: pointer;">×</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// DOM Safety: Check for ybt_admin object
if (typeof ybt_admin === 'undefined') {
    window.ybt_admin = {
        nonce: '<?php echo esc_js(wp_create_nonce("ybt_admin_nonce")); ?>',
        user_nonce: '<?php echo esc_js(wp_create_nonce("ybt_nonce")); ?>',
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>'
    };
}

const escapeHtml = (u) => u ? String(u).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[s])) : '';

jQuery(document).ready(function($) {
    let selectedUserId = null;
    let lastChatMessageCount = 0;
    let sending = false;
    let showingAllUsers = false;

    // Request notification permission
    if ('Notification' in window && Notification.permission !== 'granted') {
        Notification.requestPermission();
    }

    loadUsers();

    $('#show-all-users').click(function() {
        if (showingAllUsers) {
            showingAllUsers = false;
            $(this).text('Show All Users').removeClass('button-primary').addClass('button-secondary');
            loadUsers();
        } else {
            showingAllUsers = true;
            $(this).text('Show Active Chats').removeClass('button-secondary').addClass('button-primary');
            loadAllUsers();
        }
    });

    // Search functionality
    $('#user-search').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.user-item').each(function() {
            const username = String($(this).data('username')).toLowerCase();
            if (username.includes(search)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#send-chat').click(sendMessage);
    $('#chat-input').keypress(function(e) {
        if (e.which == 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize textarea
    $('#chat-input').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // File attachment handling
    $('#attach-btn').click(function() {
        $('#chat-file').click();
    });

    $('#chat-file').change(function() {
        const file = this.files[0];
        if (file) {
            $('#admin-file-name').text(file.name);
            $('#admin-file-preview').show();
        }
    });

    $('#admin-remove-file').click(function() {
        $('#chat-file').val('');
        $('#admin-file-preview').hide();
    });

    function loadUsers() {
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_chat_users',
                nonce: ybt_admin.user_nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    let html = '';
                    response.data.forEach(function(user) {
                        const isActive = selectedUserId == user.user_id;
                        const safeUsername = escapeHtml(user.username);
                        const msgCount = parseInt(user.message_count || 0);
                        const unreadCount = parseInt(user.unread_count || 0);

                        html += `
                            <div class="user-item ${isActive ? 'active' : ''} ${unreadCount > 0 ? 'has-unread' : ''}" data-user-id="${parseInt(user.user_id)}" data-username="${safeUsername}">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="font-weight: bold;">${safeUsername}</div>
                                    ${unreadCount > 0 ? `<span class="unread-badge">${unreadCount}</span>` : ''}
                                </div>
                                <div style="font-size: 11px; opacity: 0.8;">${msgCount} messages</div>
                            </div>`;
                    });
                    $('#user-list').html(html || '<p class="p-2 text-muted">No active chats.</p>');
                    if (selectedUserId) loadUserChat(selectedUserId);
                }
            },
            error: function() { console.error('Failed to load users.'); }
        });
    }

    function loadAllUsers() {
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_all_users',
                nonce: ybt_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    let html = '';
                    response.data.forEach(function(user) {
                        const isActive = selectedUserId == user.ID;
                        const safeLogin = escapeHtml(user.user_login);
                        html += `
                            <div class="user-item ${isActive ? 'active' : ''}" data-user-id="${parseInt(user.ID)}" data-username="${safeLogin}">
                                <div style="font-weight: bold;">${safeLogin}</div>
                                <div style="font-size: 12px; opacity: 0.8;">Start new chat</div>
                            </div>`;
                    });
                    $('#user-list').html(html);
                }
            }
        });
    }

    $(document).on('click', '.user-item', function() {
        selectedUserId = parseInt($(this).data('user-id'));
        const username = $(this).data('username');

        if (!selectedUserId || isNaN(selectedUserId)) return;

        $('.user-item').removeClass('active');
        $(this).addClass('active');

        $('#chat-header').text('Chat with ' + username);
        $('#chat-input, #send-chat').prop('disabled', false);

        loadUserChat(selectedUserId);
    });

    function sendMessage() {
        if (!selectedUserId || sending) return;

        const message = $('#chat-input').val().trim();
        const fileInput = $('#chat-file')[0];

        if (!message && !fileInput.files.length) return;

        const formData = new FormData();
        formData.append('action', 'ybt_send_chat');
        formData.append('nonce', ybt_admin.user_nonce);
        formData.append('message', message);
        formData.append('target_user_id', selectedUserId);

        if (fileInput.files.length) {
            formData.append('chat_file', fileInput.files[0]);
        }

        sending = true;
        $('#send-chat').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                sending = false;
                $('#send-chat').prop('disabled', false).html('<span class="dashicons dashicons-arrow-right-alt2"></span>');
                if (response.success) {
                    $('#chat-input').val('').css('height', 'auto');
                    $('#chat-file').val('');
                    $('#admin-file-preview').hide();
                    loadUserChat(selectedUserId);
                } else {
                    alert('Error: ' + (response.data || 'Failed to send message.'));
                }
            },
            error: function() {
                sending = false;
                $('#send-chat').prop('disabled', false).html('<span class="dashicons dashicons-arrow-right-alt2"></span>');
                alert('A server error occurred. Please try again.');
            }
        });
    }

    function loadUserChat(userId) {
        if (!userId) return;
        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_user_chat',
                nonce: ybt_admin.user_nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Notifications
                    if (lastChatMessageCount > 0 && response.data.length > lastChatMessageCount) {
                        const latestMsg = response.data[response.data.length - 1];
                        if (latestMsg.is_admin == 0 && Notification.permission === 'granted') {
                             new Notification('New Message', {
                                body: 'From ' + latestMsg.username + ': ' + (latestMsg.message || 'Sent an attachment'),
                                icon: '<?php echo esc_url(YBT_PLUGIN_URL); ?>assets/favicon.ico'
                            });
                        }
                    }
                    lastChatMessageCount = response.data.length;

                    let html = '';
                    response.data.forEach(function(msg) {
                        const isAdmin = msg.is_admin == 1;
                        const time = new Date(msg.date_created).toLocaleString();
                        const safeMessage = escapeHtml(msg.message);
                        const senderName = isAdmin ? 'Admin' : (msg.username || 'User');

                        html += `
                            <div style="margin-bottom: 12px; ${isAdmin ? 'text-align: right;' : ''}">
                                <div style="display: inline-block; max-width: 80%; padding: 10px 15px; border-radius: 18px; font-size: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); ${isAdmin ? 'background:#25d366; color:#fff; border-bottom-right-radius:4px;' : 'background:#fff; color:#333; border-bottom-left-radius:4px;'}">
                                    <div style="font-size: 11px; opacity: 0.8; margin-bottom: 4px;">${escapeHtml(senderName)}</div>
                                    <div style="word-break: break-word;">${safeMessage}</div>
                                    ${msg.attachment ? renderAttachment(msg.attachment) : ''}
                                    <div style="font-size: 10px; opacity: 0.7; margin-top: 4px; text-align: right;">${time}</div>
                                </div>
                            </div>`;
                    });
                    $('#chat-messages').html(html).scrollTop($('#chat-messages')[0].scrollHeight);
                }
            }
        });
    }

    function renderAttachment(url) {
        if (!url || typeof url !== 'string') return '';
        const trimmed = url.trim();
        // Whitelist safe URL protocols: http, https, or relative paths
        if (!/^https?:\/\//i.test(trimmed) && !trimmed.startsWith('/')) {
            return '';
        }
        const safeUrl = encodeURI(trimmed).replace(/["'<>\\]/g, function(c) {
            return '&#' + c.charCodeAt(0) + ';';
        });
        const isImage = /\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(trimmed);
        if (isImage) {
            return `<div style="margin-top: 8px;"><a href="${safeUrl}" target="_blank" rel="noopener noreferrer"><img src="${safeUrl}" style="max-width: 100%; border-radius: 8px; cursor: pointer;" alt="Attachment"></a></div>`;
        }
        const rawFileName = trimmed.split('?')[0].split('#')[0].split('/').pop() || 'attachment';
        const safeFileName = escapeHtml(decodeURIComponent(rawFileName));
        return `<div style="margin-top: 8px;"><a href="${safeUrl}" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: underline; font-size: 12px;">📎 ${safeFileName}</a></div>`;
    }
});
</script>

<style>
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card-header {
    background: linear-gradient(135deg, #228B22, #DAA520);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 15px 20px;
}

.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
}

.user-item {
    padding: 10px;
    border: 1px solid #ddd;
    margin-bottom: 5px;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.2s;
}

.user-item:hover {
    background: #f0f0f0;
}

.user-item.active {
    background: #007cba;
    color: white;
}

.unread-badge {
    background: #dc3545;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: bold;
}

.user-item.has-unread {
    border-left: 4px solid #dc3545;
}
</style>
