jQuery(function() {
    var wc_tappay_applepay = {
        initialize: function () {
  
            // setup direct method api
            console.log('applepay initialize, SDK setup....');
            TPDirect.setupSDK(parseInt(tappay_params.app_id), tappay_params.app_key, tappay_params.server_type);
            
        },

        requestToken: function () {

            console.log('applepay checking broswer avaliable....');
            var check = TPDirect.paymentRequestApi.checkAvailability();
            
            if( check == false)
            {
                console.log('false , broswer is not avaliable for payment....');
                return false;
            }
            console.log('checking success!');
            console.log('setting payment request....');

            TPDirect.paymentRequestApi.getPrime(function (result) {
                if (0 !== result.status) {
                    console.log('Error with getPrime');
                    return;
                }

                console.log('got prime: ' + result.prime);

                // got prime, send to backend
                //wc_tappay_applepay.processPayment(result.prime);

            });

            var paymentRequest = {
                supportedNetworks: ['AMEX', 'JCB', 'MASTERCARD', 'VISA'],
                supportedMethods: ['apple_pay'],
            
                displayItems: [{
                    label: 'TapPay',
                    amount: {
                        currency: 'TWD',
                        value: '1.00'
                    },
                    isAmountPending: false
                }],
                total: {
                    label: '付給 TapPay',
                    amount: {
                        currency: 'TWD',
                        value: '1.00'
                    },
                    isAmountPending: false,
                    isShowTotalAmount: true

                },
                shippingOptions: [{
                        id: "standard",
                        label: "Ground Shipping (2 days)",
                        detail: 'Estimated delivery time: 2 days',
                        amount: {
                            currency: "TWD",
                            value: "5.00"
                        }
                    },
                    {
                        id: "drone",
                        label: "Drone Express (2 hours)",
                        detail: 'Estimated delivery time: 2 hours',
                        amount: {
                            currency: "TWD",
                            value: "25.00"
                        }
                    },
                ],
                options: {
                    requestPayerEmail: false,
                    requestPayerName: false,
                    requestPayerPhone: false,
                    requestShipping: false,
                    shippingType: 'shipping',
                }
            }


            TPDirect.paymentRequestApi.setupApplePay({
                merchantIdentifier: 'APMEnIcTUZYYB2HQv7Zl',
                countryCode: 'TW'
            });

            console.log(paymentRequest);

            TPDirect.paymentRequestApi.setupPaymentRequest(paymentRequest, function (result) {
                if (!result.browserSupportPaymentRequest) {
                    console.log('瀏覽器不支援 PaymentRequest')
                    console.log(result);
                    return
                }
                if (result.canMakePaymentWithActiveCard === true) {
                    console.log('該裝置有支援的卡片可以付款')
                } else {
                    console.log('該裝置沒有支援的卡片可以付款')
                }
            })

            // TPDirect.paymentRequestApi.getPrime(function (result) {
            //     if (0 !== result.status) {
            //         console.log('Error with getPrime');
            //         return;
            //     }

            //     console.log('got prime: ' + result.prime);

            //     // got prime, send to backend
            //     wc_tappay_applepay.processPayment(result.prime);

            // });

            wc_tappay_applepay.processPayment(result.prime);

            return false;
        },

        processPayment: function (prime) {
            var checkoutForm = jQuery('form.woocommerce-checkout');
            checkoutForm.find('#tappay_prime').val(prime);
            checkoutForm.off('checkout_place_order', wc_tappay_applepay.requestToken);
            checkoutForm.submit();
            checkoutForm.on('checkout_place_order', wc_tappay_applepay.requestToken);
        },

    };

    // override default place order button
    jQuery('body').on('change', function (e) {
        wc_tappay_applepay.initialize();

        var applepay_paymentbox_show = jQuery('.payment_method_tappay_applepay .payment_box').css('display');

        if( applepay_paymentbox_show != 'none'){
            console.log('Choose apple payment');
            jQuery('form.woocommerce-checkout').on('checkout_place_order', wc_tappay_applepay.requestToken);
            jQuery('#place_order').val('使用 Apple Pay 付款');

        } else {
            jQuery('form.woocommerce-checkout').off('checkout_place_order', wc_tappay_applepay.requestToken);
        }
    
    });
});
