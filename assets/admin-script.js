// YBT Admin Script
jQuery(document).ready(function ($) {
    console.log('YBT Admin Script Loaded');

    // 1. Tab Switching Logic
    $('.ybt-tab-link').on('click', function (e) {
        e.preventDefault();

        // Get target and validate it's an anchor
        var target = $(this).attr('href');
        
        // Security check: only process if it's a valid anchor (starts with #)
        if (!target || !target.startsWith('#')) {
            console.warn('Invalid tab target:', target);
            return;
        }
        
        // Check if target element exists
        if ($(target).length === 0) {
            console.warn('Tab target element not found:', target);
            return;
        }

        // Visual Update
        $('.ybt-tab-link').removeClass('nav-tab-active').css('border-bottom', '1px solid transparent');
        $(this).addClass('nav-tab-active').css('border-bottom', '1px solid white');

        // Content Update
        $('.ybt-tab-content').hide();
        $(target).fadeIn(200);
    });

    // 2. Export Button Handlers (Placeholders)
    $('#generate-pdf').on('click', function (e) {
        e.preventDefault();
        alert('PDF Generation functionality would be triggered here.');
    });

    $('#quick-export').on('click', function (e) {
        e.preventDefault();
        alert('CSV Export functionality would be triggered here.');
    });

    // 3. Attach Items Button using Admin AJAX
    $('#attach-cartons-selected').on('click', function (e) {
        e.preventDefault();
        // Check for selected checkboxes in the main table (if implemented)
        var selected = [];
        $('input[name="tracking_ids[]"]:checked').each(function () {
            selected.push($(this).val());
        });

        if (selected.length === 0) {
            alert('Please select at least one item from the list below.');
            return;
        }

        // Proceed to attach (mock)
        console.log('Attaching cartons for: ', selected);
        alert('Attach Cartons Modal would open for ' + selected.length + ' items.');
    });
});
