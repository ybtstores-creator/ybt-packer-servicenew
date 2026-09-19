/**
 * Admin Process Packing Page JavaScript
 */
jQuery(document).ready(function($) {
    const selections = new Set(JSON.parse(localStorage.getItem('ybt_selected_items') || '[]'));

    // Update selection indicator
    function updateSelectionIndicator() {
        const count = selections.size;
        if (count > 0) {
            $('#selection-indicator').show();
            $('#selection-count').text(count);
        } else {
            $('#selection-indicator').hide();
        }
    }

    // Restore selections on page load
    selections.forEach(id => {
        $(`.item-checkbox[value="${id}"]`).prop('checked', true);
    });
    updateSelectionIndicator();

    // Handle checkbox changes
    $(document).on('change', '.item-checkbox', function() {
        const id = $(this).val();
        if ($(this).is(':checked')) {
            selections.add(id);
        } else {
            selections.delete(id);
        }
        localStorage.setItem('ybt_selected_items', JSON.stringify([...selections]));
        updateSelectionIndicator();
    });

    // User group header toggle
    $(document).on('click', '.user-group-header', function() {
        $(this).toggleClass('collapsed');
        $(this).next('.user-group-content').slideToggle(200);
        $(this).find('.toggle-icon').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Select all in user group
    $(document).on('change', '.user-select-all', function() {
        const user = $(this).data('user');
        const checked = $(this).is(':checked');
        $(`.item-checkbox[data-user="${user}"]`).prop('checked', checked).trigger('change');
    });

    // Select/Deselect all on page
    $('#select-all-page-items').on('click', function() {
        $('.item-checkbox:visible').prop('checked', true).trigger('change');
    });

    $('#deselect-all-page-items').on('click', function() {
        $('.item-checkbox:visible').prop('checked', false).trigger('change');
    });

    // Clear all selections
    $('#clear-all-selections').on('click', function() {
        if (confirm(ybt_admin.strings.confirm_clear_selections)) {
            selections.clear();
            localStorage.removeItem('ybt_selected_items');
            $('.item-checkbox').prop('checked', false);
            updateSelectionIndicator();
        }
    });

    // View selected items
    $('#view-selected-items').on('click', function() {
        if (selections.size === 0) return;

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_tracking_details_batch',
                nonce: ybt_admin.nonce,
                tracking_ids: [...selections].join(',')
            },
            success: function(response) {
                if (response.success) {
                    let html = '<ul class="list-group">';
                    response.data.forEach(item => {
                        const trackingNum = $('<div>').text(item.tracking_number).html();
                        const username = $('<div>').text(item.username).html();
                        const status = $('<div>').text(item.status).html();
                        html += `<li class="list-group-item"><strong>${trackingNum}</strong> - ${username} (${status})</li>`;
                    });
                    html += '</ul>';
                    $('#selected-items-list').html(html);
                    $('#view-selected-modal').fadeIn();
                }
            },
            error: function() {
                alert('Failed to load selected items');
            }
        });
    });

    // Update item count
    $(document).on('change', '.item-count-input', function() {
        const id = $(this).data('id');
        const value = $(this).val();

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_update_item_details',
                nonce: ybt_admin.nonce,
                tracking_id: id,
                field: 'item_count',
                value: value
            },
            error: function() {
                console.error('Failed to update item count');
            }
        });
    });

    // Update complete checkbox
    $(document).on('change', '.complete-checkbox', function() {
        const id = $(this).data('id');
        const value = $(this).is(':checked') ? 1 : 0;

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_update_item_details',
                nonce: ybt_admin.nonce,
                tracking_id: id,
                field: 'is_complete',
                value: value
            }
        });
    });

    // Mark as packed
    $(document).on('click', '.mark-packed', function() {
        const id = $(this).data('id');

        if (!confirm(ybt_admin.strings.confirm_mark_packed)) return;

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_update_status',
                nonce: ybt_admin.nonce,
                tracking_id: id,
                status: 'packed'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Upload images
    $(document).on('click', '.upload-packing-images', function() {
        const id = $(this).data('id');
        $('#packing-upload-tracking-id').val(id);
        $('#packing-image-upload-modal').fadeIn();
    });

    $('#packing-image-upload-form').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('action', 'ybt_upload_image');
        formData.append('nonce', ybt_admin.nonce);
        formData.append('tracking_id', $('#packing-upload-tracking-id').val());

        const files = $('#packing-upload-images')[0].files;
        for (let i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // View images
    $(document).on('click', '.view-images', function() {
        const images = $(this).data('images').split(',');
        let html = '';
        images.forEach(img => {
            const imgUrl = $('<div>').text(img.trim()).html();
            html += `<img src="${imgUrl}" style="max-width: 100%; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">`;
        });
        $('#packing-image-gallery').html(html);
        $('#packing-image-viewer-modal').fadeIn();
    });

    // Attach cartons (single item)
    $(document).on('click', '.attach-cartons', function() {
        const id = $(this).data('id');
        const batch = $(this).data('batch');

        $('#attach-batch-id').val(batch || id);
        $('#attach-batch-label').text(batch || `Item #${id}`);
        $('#attach-cartons-modal').fadeIn();
    });

    // Generate carton fields
    $('#generate-carton-fields').on('click', function() {
        const count = parseInt($('#attach-cartons-count').val());
        let html = '';

        for (let i = 1; i <= count; i++) {
            html += `
                <div class="carton-field mb-3 p-3 border rounded">
                    <h6>${ybt_admin.strings.carton} ${i}</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <label>${ybt_admin.strings.weight_kg}</label>
                            <input type="number" step="0.01" name="carton_weight[]" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>${ybt_admin.strings.amount}</label>
                            <input type="number" step="0.01" name="carton_payment[]" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>${ybt_admin.strings.currency}</label>
                            <select name="carton_currency[]" class="form-control">
                                <option value="CNY">CNY (¥)</option>
                                <option value="NGN">NGN (₦)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label>${ybt_admin.strings.images}</label>
                        <input type="file" name="carton_image_${i}[]" class="form-control" multiple accept="image/*">
                    </div>
                </div>
            `;
        }

        $('#carton-fields-container').html(html);
    });

    // Submit cartons
    $('#attach-cartons-form').on('submit', function(e) {
        e.preventDefault();

        const batchId = $('#attach-batch-id').val();
        const cartonData = [];
        const formData = new FormData();

        formData.append('action', 'ybt_attach_cartons_to_batch_v2');
        formData.append('nonce', ybt_admin.nonce);
        formData.append('batch_id', batchId);

        // Collect carton data from generated fields
        $('#carton-fields-container .carton-field').each(function(index) {
            const weight = $(this).find('input[name="carton_weight[]"]').val() || 0;
            const amount = $(this).find('input[name="carton_payment[]"]').val() || 0;
            const currency = $(this).find('select[name="carton_currency[]"]').val() || 'CNY';
            const imageInput = $(this).find(`input[name="carton_image_${index + 1}[]"]`)[0];

            cartonData.push({
                weight: parseFloat(weight),
                amount: parseFloat(amount),
                currency: currency
            });

            // Add images to FormData
            if (imageInput && imageInput.files && imageInput.files.length > 0) {
                for (let i = 0; i < imageInput.files.length; i++) {
                    formData.append(`carton_image_${index}[]`, imageInput.files[i]);
                }
            }
        });

        if (cartonData.length === 0) {
            alert('Please generate carton fields first');
            return;
        }

        formData.append('carton_data', JSON.stringify(cartonData));

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data || 'Error attaching cartons');
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    });

    // Attach cartons to selected
    $('#attach-cartons-selected').on('click', function() {
        if (selections.size === 0) {
            alert(ybt_admin.strings.no_items_selected);
            return;
        }

        $('#attach-selected-ids').val([...selections].join(','));
        $('#attach-selected-count').text(selections.size);
        $('#attach-cartons-selected-modal').fadeIn();
    });

    // Generate selected carton fields
    $('#generate-selected-carton-fields').on('click', function() {
        const count = parseInt($('#attach-selected-cartons-count').val());
        let html = '';

        for (let i = 1; i <= count; i++) {
            html += `
                <div class="carton-field mb-3 p-3 border rounded">
                    <h6>${ybt_admin.strings.carton} ${i}</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <label>${ybt_admin.strings.weight_kg}</label>
                            <input type="number" step="0.01" name="carton_weight[]" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>${ybt_admin.strings.amount}</label>
                            <input type="number" step="0.01" name="carton_payment[]" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>${ybt_admin.strings.currency}</label>
                            <select name="carton_currency[]" class="form-control">
                                <option value="CNY">CNY (¥)</option>
                                <option value="NGN">NGN (₦)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label>${ybt_admin.strings.images}</label>
                        <input type="file" name="carton_image_${i}[]" class="form-control" multiple accept="image/*">
                    </div>
                </div>
            `;
        }

        $('#selected-carton-fields-container').html(html);
    });

    // Submit selected cartons
    $('#attach-cartons-selected-form').on('submit', function(e) {
        e.preventDefault();

        const trackingIds = $('#attach-selected-ids').val();
        const cartonData = [];
        const formData = new FormData();

        formData.append('action', 'ybt_attach_cartons_to_ids_v2');
        formData.append('nonce', ybt_admin.nonce);
        formData.append('tracking_ids', trackingIds);

        // Collect carton data from generated fields
        $('#selected-carton-fields-container .carton-field').each(function(index) {
            const weight = $(this).find('input[name="carton_weight[]"]').val() || 0;
            const amount = $(this).find('input[name="carton_payment[]"]').val() || 0;
            const currency = $(this).find('select[name="carton_currency[]"]').val() || 'CNY';
            const imageInput = $(this).find(`input[name="carton_image_${index + 1}[]"]`)[0];

            cartonData.push({
                weight: parseFloat(weight),
                amount: parseFloat(amount),
                currency: currency
            });

            // Add images to FormData
            if (imageInput && imageInput.files && imageInput.files.length > 0) {
                for (let i = 0; i < imageInput.files.length; i++) {
                    formData.append(`carton_image_${index}[]`, imageInput.files[i]);
                }
            }
        });

        if (cartonData.length === 0) {
            alert('Please generate carton fields first');
            return;
        }

        formData.append('carton_data', JSON.stringify(cartonData));

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    selections.clear();
                    localStorage.removeItem('ybt_selected_items');
                    location.reload();
                } else {
                    alert(response.data || 'Error attaching cartons');
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    });

    // Bulk complete selected
    $('#bulk-complete-selected').on('click', function() {
        if (selections.size === 0) {
            alert(ybt_admin.strings.no_items_selected);
            return;
        }

        if (!confirm(ybt_admin.strings.confirm_bulk_complete)) return;

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_bulk_update_status',
                nonce: ybt_admin.nonce,
                tracking_ids: [...selections].join(','),
                status: 'completed'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    selections.clear();
                    localStorage.removeItem('ybt_selected_items');
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // View carton details
    $(document).on('click', '.view-carton-details', function() {
        const batchId = $(this).data('batch-id');

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_carton_details',
                nonce: ybt_admin.nonce,
                batch_id: batchId
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>#</th><th>Weight</th><th>Payment</th><th>Images</th></tr></thead><tbody>';
                    response.data.forEach(carton => {
                        const images = carton.carton_image ? carton.carton_image.split(',') : [];
                        const imgUrl = images.length > 0 ? $('<div>').text(images[0]).html() : '';
                        let imgHtml = images.length > 0 ? `<a href="${imgUrl}" target="_blank">${ybt_admin.strings.download}</a>` : ybt_admin.strings.no_images;
                        html += `<tr>
                            <td>${carton.carton_number}</td>
                            <td>${carton.weight_kg} kg</td>
                            <td>${carton.payment_currency} ${carton.payment_amount}</td>
                            <td>${imgHtml}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                    $('#cartons-container').html(html);
                } else {
                    $('#cartons-container').html('<p>No carton details found.</p>');
                }
                $('#carton-modal').fadeIn();
            }
        });
    });

    // View tracking details
    $(document).on('click', '.view-tracking-details', function() {
        const trackingId = $(this).data('id');

        $('#tracking-details-content').html(`
            <div class="loading-spinner" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                <p class="text-muted mt-2">Loading details...</p>
            </div>
        `);
        $('#view-tracking-details-modal').fadeIn();

        $.ajax({
            url: ybt_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ybt_get_tracking_details',
                nonce: ybt_admin.nonce,
                tracking_id: trackingId
            },
            success: function(response) {
                if (response.success && response.data) {
                    const item = response.data;
                    const images = item.images ? item.images.split(',') : [];

                    let html = `
                        <div class="tracking-details-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div class="detail-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                <h4 style="margin-top: 0; color: var(--primary-color);"><i class="fas fa-barcode me-2"></i>Tracking Information</h4>
                                <div style="margin-bottom: 10px;">
                                    <strong>Tracking Number:</strong><br>
                                    <span style="font-size: 1.2em; font-weight: bold; color: #1e293b;">${$('<div>').text(item.tracking_number).html()}</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Status:</strong><br>
                                    <span class="badge-glass">${$('<div>').text(item.status).html()}</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Shipping Method:</strong><br>
                                    <span>${item.shipping_method === 'air' ? '✈️ Air' : '🚢 Sea'}</span>
                                </div>
                            </div>

                            <div class="detail-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid var(--neo-gold);">
                                <h4 style="margin-top: 0; color: var(--neo-gold);"><i class="fas fa-user me-2"></i>User Information</h4>
                                <div style="margin-bottom: 10px;">
                                    <strong>Username:</strong><br>
                                    <span>${$('<div>').text(item.username).html()}</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>User ID:</strong><br>
                                    <span>${$('<div>').text(item.user_id).html()}</span>
                                </div>
                            </div>

                            <div class="detail-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #60a5fa; grid-column: 1 / -1;">
                                <h4 style="margin-top: 0; color: #60a5fa;"><i class="fas fa-align-left me-2"></i>Description</h4>
                                <p style="margin: 0; padding: 15px; background: white; border-radius: 6px; border: 1px solid #e5e7eb;">${item.description ? $('<div>').text(item.description).html() : '<em class="text-muted">No description provided</em>'}</p>
                            </div>

                            <div class="detail-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981;">
                                <h4 style="margin-top: 0; color: #10b981;"><i class="fas fa-box me-2"></i>Item Details</h4>
                                <div style="margin-bottom: 10px;">
                                    <strong>Item Count:</strong><br>
                                    <span>${$('<div>').text(item.item_count || '1').html()}</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Weight:</strong><br>
                                    <span>${item.weight ? item.weight + ' kg' : 'Not specified'}</span>
                                </div>
                            </div>

                            <div class="detail-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #a78bfa;">
                                <h4 style="margin-top: 0; color: #a78bfa;"><i class="fas fa-calendar me-2"></i>Dates</h4>
                                <div style="margin-bottom: 10px;">
                                    <strong>Date Added:</strong><br>
                                    <span>${item.date_added ? new Date(item.date_added).toLocaleDateString() : 'N/A'}</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Date Received:</strong><br>
                                    <span>${item.date_received ? new Date(item.date_received).toLocaleDateString() : 'Not received'}</span>
                                </div>
                            </div>
                        </div>
                    `;

                    if (images.length > 0) {
                        html += `
                            <div class="detail-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #ec4899; margin-top: 20px;">
                                <h4 style="margin-top: 0; color: #ec4899;"><i class="fas fa-images me-2"></i>Images (${images.length})</h4>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 15px;">
                        `;

                        images.forEach((img, index) => {
                            const safeImg = $('<div>').text(img.trim()).html();
                            html += `
                                <div style="position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                    <img src="${safeImg}" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" onclick="openImageFullscreen('${safeImg}')" alt="Image ${index + 1}">
                                </div>
                            `;
                        });

                        html += `
                                </div>
                            </div>
                        `;
                    }

                    html += '</div>';
                    $('#tracking-details-content').html(html);
                } else {
                    $('#tracking-details-content').html('<div class="alert alert-warning">No details found for this tracking number.</div>');
                }
            },
            error: function() {
                $('#tracking-details-content').html('<div class="alert alert-danger">Failed to load tracking details. Please try again.</div>');
            }
        });
    });

    // Close modals
    $(document).on('click', '.close-modal, .ybt-modal-overlay', function(e) {
        if (e.target === this) {
            $('.ybt-modal-overlay').fadeOut();
        }
    });

    // Image fullscreen
    window.openImageFullscreen = function(url) {
        window.open(url, '_blank');
    };
});
