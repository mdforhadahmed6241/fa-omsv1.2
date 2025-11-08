jQuery(document).ready(function($) {
    const data = oms_return_data;
    const ajaxUrl = data.ajax_url;
    const nonce = data.nonce;
    const scanNonce = data.scan_nonce;
    const successSound = document.getElementById('oms-audio-success');
    const errorSound = document.getElementById('oms-audio-error');

    // --- Summary Date Filter Logic ---
    const filterSelect = $('#filter-select');
    const startDateInput = $('#start_date');
    const endDateInput = $('#end_date');

    function toggleCustomRange() {
        if (filterSelect.val() === 'custom') {
            startDateInput.show();
            endDateInput.show();
            startDateInput.prev('label').show();
            endDateInput.prev('label').show();
        } else {
            startDateInput.hide();
            endDateInput.hide();
            startDateInput.prev('label').hide();
            endDateInput.prev('label').hide();
        }
    }

    filterSelect.on('change', toggleCustomRange);
    toggleCustomRange(); // Initial state check

    // --- Return List Button Action ---
    $('.oms-update-return-btn').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const orderId = $button.data('order-id');
        const newStatus = $button.data('status');
        const oldText = $button.text();
        const listTab = $button.closest('.oms-tab-content').find('.nav-tab-active').data('list-tab');

        $button.prop('disabled', true).text(newStatus == 1 ? 'Receiving...' : 'Updating...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'oms_ajax_update_return_status',
                nonce: nonce,
                order_id: orderId,
                receive_status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    if (newStatus == 1 && listTab === 'not-received') {
                         // If moving from Not Received to Received, remove the row for a dynamic feel
                         $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                             // Simple success message on the top (optional)
                             $('.wrap > h1').after('<div class="notice notice-success is-dismissible oms-notice"><p>Order #' + orderId + ' successfully marked as Received.</p></div>');
                        });
                    } else {
                        // For other moves (e.g., in Received tab or marked back to Not Received) refresh the page
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (response.data.message || 'Could not update status.'));
                    $button.prop('disabled', false).text(oldText);
                }
            },
            error: function() {
                alert('An AJAX error occurred.');
                $button.prop('disabled', false).text(oldText);
            }
        });
    });

    // --- Return Scanner Logic (Summary Tab) ---
    $('#return-scan-input').on('keypress', function (e) {
        if (e.which === 13) { // Enter key pressed
            e.preventDefault();
            const $input = $(this);
            const orderNumber = $input.val().trim();
            const $feedbackEl = $('#return-scan-feedback');
            const $logTableBody = $('#return-scan-log');

            if (!orderNumber) { return; }

            $input.prop('disabled', true);
            $feedbackEl.removeClass('success error warning').addClass('loading').text('Processing...').show();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oms_ajax_return_scan',
                    nonce: scanNonce,
                    order_number: orderNumber,
                },
                success: function (response) {
                    if (response.success) {
                        const { status, message, order_id, order_number } = response.data;
                        let feedbackClass = status === 'success' ? 'success' : 'warning'; 
                        let logClass = status === 'success' ? 'success' : 'skipped';
                        
                        if (status === 'success') {
                            if(successSound) successSound.play();
                            // Optional: Update Not Received count/card dynamically without full reload
                        } else {
                            if(errorSound) errorSound.play();
                        }
                        
                        $feedbackEl.removeClass('loading').addClass(feedbackClass).text(message);
                        addReturnLogRow($logTableBody, order_id, order_number, message, logClass);

                    } else {
                        // AJAX call succeeded but WP returned an error
                        if(errorSound) errorSound.play();
                        $feedbackEl.removeClass('loading').addClass('error').text(response.data.message);
                        addReturnLogRow($logTableBody, '#', orderNumber, response.data.message, 'error');
                    }
                },
                error: function () {
                    if(errorSound) errorSound.play();
                    $feedbackEl.removeClass('loading').addClass('error').text('An unknown AJAX error occurred.');
                    addReturnLogRow($logTableBody, '#', orderNumber, 'AJAX Error', 'error');
                },
                complete: function () {
                    $input.prop('disabled', false).val('').focus();
                    setTimeout(() => $feedbackEl.fadeOut(), 4000);
                }
            });
        }
    });
    
    function addReturnLogRow($tableBody, orderId, orderNumber, result, statusClass) {
        const currentTime = new Date().toLocaleTimeString();
        const newRow = `
            <tr class="oms-log-row--${statusClass}">
                <td>${currentTime}</td>
                <td><a href="/wp-admin/admin.php?page=oms-order-details&order_id=${orderId}" target="_blank">#${orderNumber}</a></td>
                <td>${result}</td>
            </tr>
        `;
        $tableBody.prepend(newRow);

        // Keep the log to a reasonable size (e.g., 5 items)
        if ($tableBody.children('tr').length > 5) {
            $tableBody.children('tr:last').remove();
        }
    }
});
