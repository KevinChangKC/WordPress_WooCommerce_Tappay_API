jQuery(function() {
    var wc_tappay = {
        initialize: function () {
            // setup direct method api
            TPDirect.setupSDK(parseInt(tappay_params.app_id), tappay_params.app_key, tappay_params.server_type);
            console.log('initialize');
            
        },

        requestToken: function () {
            TPDirect.linePay.getPrime(function (result) {
                if (0 !== result.status) {
                    console.log('Error with getPrime');
                    return;
                }

                console.log('got prime: ' + result.prime);

                // got prime, send to backend
                wc_tappay.processPayment(result.prime);

            });

            return false;
        },

        processPayment: function (prime) {
            var checkoutForm = jQuery('form.woocommerce-checkout');
            checkoutForm.find('#tappay_prime').val(prime);
            checkoutForm.off('checkout_place_order', wc_tappay.requestToken);
            checkoutForm.submit();
            checkoutForm.on('checkout_place_order', wc_tappay.requestToken);
        },

    };

    // override default place order button
    jQuery('body').on('change', function (e) {
        wc_tappay.initialize();
        console.log('body');

        if ('checked' === jQuery('#payment_method_tappay_linepay').attr('checked')) {
            jQuery('form.woocommerce-checkout').on('checkout_place_order', wc_tappay.requestToken);
            console.log('check');
        } else {
            jQuery('form.woocommerce-checkout').off('checkout_place_order', wc_tappay.requestToken);
        }
    });
});
