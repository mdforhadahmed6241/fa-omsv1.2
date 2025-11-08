jQuery(document).ready(function($) {
    const ajaxUrl = oms_order_list_data.ajax_url;
    const nonce = oms_order_list_data.nonce;

    $('.oms-send-to-courier-list-btn').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const orderId = $button.data('order-id');
        // **BUG FIX #1**: Get the courier ID directly from the button's data attribute.
        const courierId = $button.data('courier-id');
        const $cell = $button.closest('td');

        if (!courierId) {
            $cell.html('<span class="oms-parcel-id error">Error: No courier specified for this action.</span>');
            return;
        }

        $button.prop('disabled', true).text('Sending...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'oms_ajax_send_to_courier',
                nonce: nonce,
                order_id: orderId,
                courier_id: courierId // Send the specific courier ID from the button
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const html = `
                        <a href="${data.tracking_url}" target="_blank" class="button button-secondary">Track ${data.courier_name}</a>
                        <span class="oms-parcel-id">Parcel ID: ${data.consignment_id}</span>
                    `;
                    $cell.html(html);
                } else {
                    $button.prop('disabled', false).text('Send to Courier');
                    $cell.find('.oms-parcel-id').text('Error: ' + response.data.message).addClass('error');
                }
            },
            error: function() {
                $button.prop('disabled', false).text('Send to Courier');
                $cell.find('.oms-parcel-id').text('Error: AJAX request failed.').addClass('error');
            }
        });
    });
});

