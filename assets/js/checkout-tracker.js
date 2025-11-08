jQuery(function ($) {
    // Only run on the checkout page
    if (!$('body').hasClass('woocommerce-checkout')) {
        return;
    }

    let checkoutTimeout;

    function captureCheckoutData() {
        const phone = $('#billing_phone').val();

        // Only proceed if a phone number is present
        if (!phone || phone.length < 5) {
            return;
        }

        const customerData = {
            billing_first_name: $('#billing_first_name').val(),
            billing_last_name: $('#billing_last_name').val(),
            billing_address_1: $('#billing_address_1').val(),
            order_comments: $('#order_comments').val(),
        };

        $.ajax({
            url: woocommerce_params.ajax_url.replace('%%endpoint%%', 'oms_capture_incomplete_order'),
            type: 'POST',
            data: {
                action: 'oms_capture_incomplete_order',
                phone: phone,
                customer_data: JSON.stringify(customerData)
            },
            success: function (response) {
                // console.log('Incomplete order data captured.');
            },
            error: function(error) {
                // console.error('Failed to capture incomplete order data.');
            }
        });
    }

    // **UPDATED**: Changed 'blur' to 'keyup blur' for faster capture
    $(document.body).on('keyup blur', '#billing_phone, #billing_first_name, #billing_last_name, #billing_address_1, #order_comments', function () {
        clearTimeout(checkoutTimeout);
        checkoutTimeout = setTimeout(captureCheckoutData, 500); // Debounce to avoid rapid firing
    });
});

