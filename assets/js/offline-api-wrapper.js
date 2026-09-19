/**
 * YBT Packer Mobile App - Offline API Wrapper
 *
 * This wrapper intercepts API calls and queues them for later sync when offline.
 * Include this AFTER offline-sync.js and configure with your API endpoint.
 */

(function () {
    'use strict';

    // Configuration - Use WordPress AJAX URL from wp_localize_script
    const API_BASE_URL = (window.ybtApiConfig && window.ybtApiConfig.ajax_url)
        ? window.ybtApiConfig.ajax_url
        : (window.ybtApiUrl || '');
    const AUTH_TOKEN_KEY = 'ybt_token';

    /**
     * Make an API call with offline support
     *
     * @param {string} endpoint - API endpoint (e.g., 'ybt_mobile_add_tracking')
     * @param {object} data - Form data to send
     * @param {string} operationType - Type of operation for sync queue
     * @param {boolean} requireAuth - Whether this endpoint requires authentication
     * @param {FileList|File[]} files - Optional files to upload
     * @returns {Promise} - API response
     */
    async function offlineApiCall(endpoint, data, operationType, requireAuth = true, files = null) {
        const isOnline = navigator.onLine;

        // If offline and this is a write operation, queue it
        if (!isOnline && operationType) {
            console.log('[YBT API] Offline - queueing operation:', operationType);
            return queueOfflineOperation(operationType, data, files);
        }

        // Online - make the API call normally (NO compression)
        return makeApiCall(endpoint, data, requireAuth, files);
    }

    /**
     * Queue an operation for later sync
     */
    async function queueOfflineOperation(type, data, files) {
        const localId = await window.ybtSyncService.queueOperation(type, data, null, files);

        // Return a promise that resolves with a "queued" response
        return Promise.resolve({
            success: true,
            queued: true,
            local_id: localId,
            message: 'Operation saved offline. Will sync when connected.'
        });
    }

    /**
     * Make the actual API call
     */
    async function makeApiCall(endpoint, data, requireAuth = true, files = null) {
        const formData = new FormData();

        // Add data fields
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                formData.append(key, data[key]);
            }
        }

        // Add files if present
        if (files && files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                formData.append('images[]', files[i]);
            }
        }

        // Add action for WordPress AJAX (only once)
        formData.append('action', endpoint);

        // Build URL - WordPress AJAX doesn't need action in query string
        let url = API_BASE_URL;

        const headers = {};

        // Add auth token if required
        if (requireAuth) {
            const token = localStorage.getItem(AUTH_TOKEN_KEY) || localStorage.getItem('ybt_mobile_token');
            if (token) {
                headers['Authorization'] = 'Bearer ' + token;
            }
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: formData
        });

        if (!response.ok) {
            throw new Error('API request failed: ' + response.status);
        }

        return await response.json();
    }

    // Pre-configured API methods for common operations

    /**
     * Claim a parcel (works offline)
     */
    window.ybtApi = {
        claimParcel: function (trackingNumber) {
            return offlineApiCall(
                'ybt_mobile_claim_parcel',
                { tracking_number: trackingNumber },
                'claim_parcel'
            );
        },

        /**
         * Add tracking number (works offline)
         */
        addTracking: function (trackingNumber, description, shippingMethod, itemCount) {
            return offlineApiCall(
                'ybt_mobile_add_tracking',
                {
                    tracking_number: trackingNumber,
                    description: description || '',
                    shipping_method: shippingMethod || 'sea',
                    item_count: itemCount || 1
                },
                'add_tracking'
            );
        },

        /**
         * Send chat message (works offline)
         */
        sendChat: function (message) {
            return offlineApiCall(
                'ybt_mobile_send_chat',
                { message: message },
                'send_chat'
            );
        },

        /**
         * Admin: Scan barcode (works offline)
         */
        scanBarcode: function (barcode) {
            return offlineApiCall(
                'ybt_mobile_scan_barcode',
                { barcode: barcode },
                'scan_barcode'  // BUG FIX: was 'scan_barcel' (typo) — scans were silently dropped

            );
        },

        /**
         * Admin: Update item status (works offline)
         */
        updateStatus: function (trackingId, status) {
            return offlineApiCall(
                'ybt_mobile_admin_update_status',
                {
                    tracking_id: trackingId,
                    status: status
                },
                'update_status'
            );
        },

        /**
         * Upload images (works offline)
         */
        uploadImage: function (trackingId, files) {
            return offlineApiCall(
                'ybt_upload_image',
                { tracking_id: trackingId },
                'upload_image',
                true,
                files
            );
        },

        /**
         * Force sync now
         */
        syncNow: function () {
            return window.ybtSyncService.syncNow();
        },

        /**
         * Get sync status
         */
        getSyncStatus: function () {
            return window.ybtSyncService.getStatus();
        },

        /**
         * Regular online-only API call
         */
        get: function (endpoint, data) {
            return makeApiCall(endpoint, data, true);
        },

        /**
         * Check if online
         */
        isOnline: function () {
            return navigator.onLine;
        }
    };

    console.log('[YBT API] Offline API wrapper initialized');

})();
