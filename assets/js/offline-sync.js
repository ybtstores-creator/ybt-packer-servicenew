/**
 * YBT Packer Mobile App - Offline Sync Service
 *
 * This script handles offline data storage and automatic sync when connectivity is restored.
 * Include this after the main app JS and after authService is initialized.
 */

(function () {
    'use strict';

    // Configuration - Get from wp_localize_script or use defaults
    const SYNC_STORAGE_KEY = 'ybt_offline_queue';
    const IS_ONLINE_KEY = 'ybt_is_online';
    // Use WordPress AJAX URL - try ybtApiConfig first, then fallback
    // Note: action param is added in syncQueue(); do NOT bake it in here.
    const AJAX_BASE_URL = (window.ybtApiConfig && window.ybtApiConfig.ajax_url)
        ? window.ybtApiConfig.ajax_url
        : (window.ybtApiUrl || '');

    // State
    let isOnline = navigator.onLine;
    let isSyncing = false; // single sync-in-progress flag

    /**
     * Queue an operation to be synced when online
     * @param {string} type - Operation type
     * @param {object} data - Operation data
     * @param {string} localId - Optional local ID
     * @param {FileList|File[]} files - Optional files to upload
     */
    async function queueOperation(type, data, localId, files) {
        // Convert files to base64 if present
        if (files && files.length > 0) {
            const base64Images = [];
            for (let i = 0; i < files.length; i++) {
                try {
                    const base64 = await fileToBase64(files[i]);
                    base64Images.push(base64);
                } catch (e) {
                    console.error('[YBT Sync] Error converting file to base64:', e);
                }
            }
            if (base64Images.length > 0) {
                data.images_base64 = base64Images;
            }
        }

        const queue = getQueue();
        const newItem = {
            type: type,
            data: data,
            local_id: localId || generateLocalId(),
            timestamp: new Date().toISOString()
        };

        // Check storage before adding
        const testQueue = [...queue, newItem];
        const testSize = JSON.stringify(testQueue).length;
        const maxSize = 5 * 1024 * 1024; // 5MB limit

        if (testSize > maxSize) {
            throw new Error('Storage full! Please sync or delete old items. Current: ' +
                Math.round(testSize / 1024) + 'KB, Max: ' + Math.round(maxSize / 1024) + 'KB');
        }

        queue.push(newItem);
        saveQueue(queue);
        console.log('[YBT Sync] Operation queued:', type, 'Size:', Math.round(testSize / 1024) + 'KB');
        return queue[queue.length - 1].local_id;
    }

    /**
     * Compress and convert File to base64 string
     */
    async function fileToBase64(file) {
        // Compress image first to save space
        const compressed = await compressImage(file);

        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(compressed);
        });
    }

    /**
     * Compress image to max 1200px width and 92% quality
     * Preserves PNG transparency by detecting format
     * Reduces file size by ~50-60% while maintaining good quality
     */
    function compressImage(file, forOffline = true) {
        return new Promise((resolve) => {
            // If not an image, return as-is
            if (!file.type.startsWith('image/')) {
                resolve(file);
                return;
            }

            // Detect if PNG with transparency
            const isPNG = file.type === 'image/png';
            const outputType = isPNG ? 'image/png' : 'image/jpeg';
            const quality = isPNG ? 0.95 : 0.92;

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    // Only resize if image is very large (max 1200px width)
                    const maxWidth = forOffline ? 1200 : 1920;
                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Use appropriate format and quality
                    canvas.toBlob((blob) => {
                        resolve(new File([blob], file.name, {
                            type: outputType,
                            lastModified: Date.now()
                        }));
                    }, outputType, quality);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    /**
     * Get the current queue from localStorage
     */
    function getQueue() {
        try {
            const data = localStorage.getItem(SYNC_STORAGE_KEY);
            return data ? JSON.parse(data) : [];
        } catch (e) {
            console.error('[YBT Sync] Error reading queue:', e);
            return [];
        }
    }

    /**
     * Save queue to localStorage
     */
    function saveQueue(queue) {
        try {
            localStorage.setItem(SYNC_STORAGE_KEY, JSON.stringify(queue));
        } catch (e) {
            console.error('[YBT Sync] Error saving queue:', e);
        }
    }

    /**
     * Clear the sync queue
     */
    function clearQueue() {
        localStorage.removeItem(SYNC_STORAGE_KEY);
    }

    /**
     * Remove specific items from queue after successful sync
     */
    function removeFromQueue(localIds) {
        const queue = getQueue();
        const remaining = queue.filter(item => !localIds.includes(item.local_id));
        saveQueue(remaining);
    }

    /**
     * Generate a unique local ID
     */
    function generateLocalId() {
        return 'local_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Sync all queued operations to the server
     */
    async function syncQueue() {
        if (!isOnline || isSyncing) {
            console.log('[YBT Sync] Skipping sync: offline or already syncing');
            return { success: false, reason: 'offline_or_syncing' };
        }

        const queue = getQueue();
        if (queue.length === 0) {
            console.log('[YBT Sync] No operations to sync');
            return { success: true, processed: 0 };
        }

        // Check if user is authenticated
        const token = localStorage.getItem('ybt_token') || localStorage.getItem('ybt_mobile_token');
        if (!token) {
            console.log('[YBT Sync] User not authenticated, cannot sync');
            return { success: false, reason: 'not_authenticated' };
        }

        isSyncing = true;
        updateOnlineIndicator();
        console.log('[YBT Sync] Starting sync of', queue.length, 'operations...');

        try {
            // Build WordPress AJAX URL — append action as query param only (not in FormData)
            const syncUrl = AJAX_BASE_URL + (AJAX_BASE_URL.includes('?') ? '&' : '?') + 'action=ybt_mobile_sync';

            const formData = new FormData();
            formData.append('operations', JSON.stringify(queue));
            formData.append('security', (window.ybtApiConfig && window.ybtApiConfig.nonce) || '');

            const response = await fetch(syncUrl, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token },
                body: formData
            });

            if (!response.ok) {
                throw new Error('Server returned ' + response.status);
            }

            const result = await response.json();

            if (result.success) {
                const syncedIds = queue.map(item => item.local_id);
                removeFromQueue(syncedIds);
                console.log('[YBT Sync] Successfully synced', result.data.processed, 'operations');
                if (window.ybtApp && typeof window.ybtApp.onSyncComplete === 'function') {
                    window.ybtApp.onSyncComplete(result.data);
                }
                return { success: true, data: result.data };
            } else {
                console.error('[YBT Sync] Sync failed:', result.data || result.message);
                return { success: false, reason: result.data || result.message };
            }
        } catch (error) {
            console.error('[YBT Sync] Sync error:', error);
            return { success: false, reason: error.message };
        } finally {
            isSyncing = false; // always reset — single flag
            updateOnlineIndicator();
        }
    }

    /**
     * Get current queue status
     */
    function getQueueStatus() {
        const queue = getQueue();
        return {
            pending: queue.length,
            isOnline: isOnline,
            isSyncing: isSyncing
        };
    }

    /**
     * Update the online/offline indicator in the UI
     */
    function updateOnlineIndicator() {
        const indicator = document.getElementById('ybt-network-status');
        if (indicator) {
            if (!isOnline) {
                indicator.className = 'offline';
                indicator.innerHTML = '<span class="ybt-status-icon">&#9898;</span> Offline - Changes will sync when connected';
            } else if (isSyncing) {
                indicator.className = 'syncing';
                indicator.innerHTML = '<span class="ybt-status-icon">&#8635;</span> Syncing...';
            } else {
                const queue = getQueue();
                if (queue.length > 0) {
                    indicator.className = 'pending';
                    indicator.innerHTML = '<span class="ybt-status-icon">&#9711;</span> ' + queue.length + ' pending change' + (queue.length > 1 ? 's' : '');
                } else {
                    indicator.className = 'online';
                    indicator.innerHTML = '<span class="ybt-status-icon">&#9899;</span> Online';
                }
            }
        }

        // Update document title with pending count
        if (!isOnline) {
            const queue = getQueue();
            if (queue.length > 0 && !document.title.includes('(')) {
                document.title = '(' + queue.length + ') ' + document.title;
            }
        } else {
            document.title = document.title.replace(/^\(\d+\)\s*/, '');
        }
    }

    /**
     * Create and insert the network status indicator UI
     */
    function createNetworkIndicator() {
        if (document.getElementById('ybt-network-status')) return;

        const indicator = document.createElement('div');
        indicator.id = 'ybt-network-status';
        indicator.className = 'ybt-network-status';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 20px;
            border-radius: 25px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            max-width: 90%;
            text-align: center;
        `;

        document.body.appendChild(indicator);
        updateOnlineIndicator();
    }

    /**
     * Add CSS styles for the network indicator
     */
    function addStyles() {
        if (document.getElementById('ybt-sync-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'ybt-sync-styles';
        styles.textContent = `
            .ybt-network-status {
                background: #fff;
                color: #333;
            }
            .ybt-network-status.offline {
                background: #ff6b6b;
                color: #fff;
            }
            .ybt-network-status.syncing {
                background: #ffd43b;
                color: #333;
            }
            .ybt-network-status.pending {
                background: #74b9ff;
                color: #fff;
            }
            .ybt-network-status.online {
                background: #10b981;
                color: #fff;
            }
            .ybt-status-icon {
                margin-right: 6px;
            }
            @keyframes ybt-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .ybt-network-status.syncing .ybt-status-icon {
                display: inline-block;
                animation: ybt-spin 1s linear infinite;
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Initialize the sync service
     */
    function init() {
        console.log('[YBT Sync] Initializing offline sync service...');

        // Add styles
        addStyles();

        // Create UI indicator
        createNetworkIndicator();

        // Listen for online/offline events
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        // Initial status update
        updateOnlineIndicator();

        // If online, try to sync any pending operations
        if (isOnline) {
            // Delay slightly to ensure app is fully loaded
            setTimeout(() => syncQueue(), 2000);
        }

        console.log('[YBT Sync] Service initialized. Online:', isOnline);
    }

    /**
     * Handle network coming back online
     */
    function handleOnline() {
        console.log('[YBT Sync] Network is online!');
        isOnline = true;
        localStorage.setItem(IS_ONLINE_KEY, 'true');
        updateOnlineIndicator();

        // Auto-sync when back online
        setTimeout(() => syncQueue(), 1000);
    }

    /**
     * Handle network going offline
     */
    function handleOffline() {
        console.log('[YBT Sync] Network is offline');
        isOnline = false;
        localStorage.setItem(IS_ONLINE_KEY, 'false');
        updateOnlineIndicator();
    }

    // Check initial online state
    isOnline = navigator.onLine;
    localStorage.setItem(IS_ONLINE_KEY, isOnline ? 'true' : 'false');

    // Expose public API
    window.ybtSyncService = {
        queueOperation: queueOperation,
        syncNow: syncQueue,
        getStatus: getQueueStatus,
        clearQueue: clearQueue,
        init: init,
        isOnline: function () { return isOnline; }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
