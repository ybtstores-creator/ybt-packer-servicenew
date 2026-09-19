jQuery(document).ready(function($){
    // Select All / Deselect All buttons
    $('#select-all-btn').on('click', function(){
        $('.item-checkbox').prop('checked', true);
        updateSelectedCount();
    });
    $('#deselect-all-btn').on('click', function(){
        $('.item-checkbox').prop('checked', false);
        updateSelectedCount();
    });

    // Update count on individual checkbox change
    $(document).on('change', '.item-checkbox', function(){
        updateSelectedCount();
    });

    function updateSelectedCount(){
        var count = $('.item-checkbox:checked').length;
        $('#selected-count').text(count + ' item' + (count !== 1 ? 's' : '') + ' selected');
        $('#submit-packing-btn').prop('disabled', count === 0);
    }

    // Packing request form submission
    $('#packing-request-form').on('submit', function(e){
        e.preventDefault();

        // Check if ybt_ajax is defined
        if (typeof ybt_ajax === 'undefined' || !ybt_ajax.ajax_url) {
            $('#packing-request-alert').html('<div class="alert alert-danger">Configuration error: AJAX URL not found. Please refresh the page.</div>');
            console.error('ybt_ajax object is not defined. Ensure wp_localize_script is called.');
            return;
        }

        var $form = $(this);
        var selectedIds = [];
        $('.item-checkbox:checked').each(function(){
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            $('#packing-request-alert').html('<div class="alert alert-danger">Please select at least one item to pack.</div>');
            return;
        }

        var payload = {
            action: 'ybt_request_packing',
            nonce: $('input[name="ybt_nonce_field"]', $form).val(),
            tracking_ids: selectedIds.join(','),
            shipping_company_name: $('input[name="shipping_company"]', $form).val().trim(),
            shipping_company_address: $('textarea[name="shipping_address"]', $form).val().trim(),
            shipping_company_phone: $('input[name="contact_phone"]', $form).val().trim(),
            shipping_mark: $('input[name="shipping_mark"]', $form).val().trim(),
            instructions: $('input[name="custom_notes"]', $form).val().trim()
        };

        // Basic client validation
        if (!payload.shipping_company_name || !payload.shipping_company_address || !payload.shipping_company_phone) {
            $('#packing-request-alert').html('<div class="alert alert-danger">Please fill in logistics company name, phone, and delivery address.</div>');
            return;
        }

        var $btn = $('#submit-packing-btn');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Submitting...');

        $.post(ybt_ajax.ajax_url, payload, function(response){
            if (response && response.success) {
                var msg = (response.data && response.data.message) ? response.data.message : (response.data || 'Packing request submitted.');
                $('#packing-request-alert').html('<div class="alert alert-success">' + msg + '</div>');
                $form[0].reset();
                $('.item-checkbox').prop('checked', false);
                updateSelectedCount();
            } else {
                var msg = (response && response.data) ? response.data : 'Server error. Try again.';
                $('#packing-request-alert').html('<div class="alert alert-danger">' + msg + '</div>');
            }
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Packing Request');
        }, 'json').fail(function(){
            $('#packing-request-alert').html('<div class="alert alert-danger">Request failed. Check console/network tab.</div>');
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Packing Request');
        });
    });
});
