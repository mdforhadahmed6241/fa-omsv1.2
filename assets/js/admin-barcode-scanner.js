jQuery(function ($) {
    const ajaxUrl = oms_barcode_scanner_data.ajax_url;
    const nonce = oms_barcode_scanner_data.nonce;
    const successSound = document.getElementById('oms-audio-success');
    const errorSound = document.getElementById('oms-audio-error');

    // --- Tab Functionality ---
    const tabs = $('.nav-tab-wrapper .nav-tab');
    const tabContents = $('.oms-tab-content');

    tabs.on('click', function (e) {
        e.preventDefault();
        tabs.removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        const target = $(this).attr('href');
        tabContents.removeClass('active').hide();
        $(target).addClass('active').show();
        $(target).find('.oms-barcode-input').focus();
    });

    // Initial focus
    $('.oms-tab-content.active .oms-barcode-input').focus();

    // --- Barcode Scanning Logic ---
    $('.oms-barcode-input').on('keypress', function (e) {
        if (e.which === 13) { // Enter key pressed
            e.preventDefault();
            const $input = $(this);
            const orderNumber = $input.val().trim();
            const targetStatus = $input.data('target-status');
            const $feedbackEl = $('#feedback-' + targetStatus);
            const $logTableBody = $('#log-' + targetStatus);

            if (!orderNumber) {
                return;
            }

            $input.prop('disabled', true);
            $feedbackEl.removeClass('success error warning').addClass('loading').text('Processing...').show();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oms_ajax_update_status_from_scan',
                    nonce: nonce,
                    order_number: orderNumber,
                    target_status: targetStatus
                },
                success: function (response) {
                    if (response.success) {
                        const { status, message, order_id, order_number, previous_status_name } = response.data;
                        let feedbackClass = 'warning'; // Default for skipped
                        let logClass = 'skipped';
                        
                        if (status === 'success') {
                            feedbackClass = 'success';
                            logClass = 'success';
                            if(successSound) successSound.play();
                        } else if(status === 'skipped') {
                            if(errorSound) errorSound.play();
                        }
                        
                        $feedbackEl.removeClass('loading').addClass(feedbackClass).text(message);
                        addLogRow($logTableBody, order_id, order_number, message, previous_status_name, logClass);

                    } else {
                        // AJAX call succeeded but WP returned an error
                        if(errorSound) errorSound.play();
                        $feedbackEl.removeClass('loading').addClass('error').text(response.data.message);
                        addLogRow($logTableBody, '#', orderNumber, response.data.message, 'N/A', 'error');
                    }
                },
                error: function () {
                    if(errorSound) errorSound.play();
                    $feedbackEl.removeClass('loading').addClass('error').text('An unknown AJAX error occurred.');
                    addLogRow($logTableBody, '#', orderNumber, 'AJAX Error', 'N/A', 'error');
                },
                complete: function () {
                    $input.prop('disabled', false).val('').focus();
                    setTimeout(() => $feedbackEl.fadeOut(), 4000);
                }
            });
        }
    });

    function addLogRow($tableBody, orderId, orderNumber, result, prevStatus, statusClass) {
        const currentTime = new Date().toLocaleTimeString();
        const newRow = `
            <tr class="oms-log-row--${statusClass}">
                <td>${currentTime}</td>
                <td><a href="/wp-admin/admin.php?page=oms-order-details&order_id=${orderId}" target="_blank">#${orderNumber}</a></td>
                <td>${result}</td>
                <td>${prevStatus}</td>
            </tr>
        `;
        $tableBody.prepend(newRow);

        // Keep the log to a reasonable size
        if ($tableBody.children('tr').length > 20) {
            $tableBody.children('tr:last').remove();
        }
    }
});

