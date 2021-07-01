<?php

class KCLinePaymentGateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'tappay_linepay';
        $this->has_fields = false;
        $this->method_title = 'Tappay Gateway for linepay';
        $this->method_description = 'Tappay Gateway for linepay using';
        $this->supports = array(
            'products'
        );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        if (empty($this->title)) {
            $this->title = $this->method_title;
        }

        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = $this->get_option('testmode');

        if ('no' === $this->testmode) {
            // production mode
            $this->app_key = $this->get_option('app_key');
            $this->app_id = $this->get_option('app_id');
            $this->partner_key = $this->get_option('partner_key');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->endpoint = 'https://prod.tappaysdk.com/tpc/';
        } else {
            // test mode
            $this->app_key = $this->get_option('test_app_key');
            $this->app_id = $this->get_option('test_app_id');
            $this->partner_key = $this->get_option('test_partner_key');
            $this->merchant_id = $this->get_option('test_merchant_id');
            $this->endpoint = 'https://sandbox.tappaysdk.com/tpc/';
        }

        // Update options
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );

        add_action('wp_enqueue_scripts', 
            array($this, 'payment_scripts')
        );

        add_action('woocommerce_api_tappay', 
            array($this, 'webhook')
        );
    }

    public function init_form_fields()
    {        
        
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable TapPay Gateway for LinePay',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'TapPay_LinePay',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Use TapPay LinePay',
            ),
            'testmode' => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in test mode using test API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_app_key' => array(
                'title'       => 'Test APP Key',
                'type'        => 'text',
                'default'     => 'app_U3EvZKw4fQAn1AO0yQtSO1lwEP9IDLitynMcC4QZ1OSx3se5uCbwXVVfdpek',
            ),
            'test_app_id' => array(
                'title'       => 'Test APP ID',
                'type'        => 'text',
                'default'     => '19667',
            ),
            'test_partner_key' => array(
                'title'       => 'Test Partner Key',
                'type'        => 'text',
                'default'     => 'partner_cq9ld7jfmgAyoagoecpDYHDyJf6TGGQCaA4IZaDTmyrfzNofCkIv9Bza',
            ),
            'test_merchant_id' => array(
                'title'       => 'Test Merchant ID',
                'type'        => 'text',
                'default'     => 'WOOMdow_LINEPAY',
            ),
            'app_key' => array(
                'title'       => 'APP Key',
                'type'        => 'text',
            ),
            'app_id' => array(
                'title'       => 'APP ID',
                'type'        => 'text',
            ),
            'partner_key' => array(
                'title'       => 'Partner Key',
                'type'        => 'text',
            ),
            'merchant_id' => array(
                'title'       => 'Merchant ID',
                'type'        => 'text',
            ),

        );
    }

    public function payment_scripts() {
        
        if ('no' === $this->enabled) {
            return;
        }

        // only enqueue script in following conditions
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        if (!$this->testmode && !is_ssl()) {
            return;
        }


        // enqueue scripts
        wp_enqueue_script('tappay_js', 'https://js.tappaysdk.com/tpdirect/v5.5.1');

        wp_register_script('woocommerce_tappay_linepay', plugins_url('tappay_linepay.js', __FILE__), array('jquery', 'tappay_js'), '20210426', true);

        //Send data to javascript
        wp_localize_script('woocommerce_tappay_linepay', 'tappay_params', array(
            'method' => 'linepay',
            'app_id' => $this->app_id,
            'app_key' => $this->app_key,
            'server_type' => ('no' === $this->enabled)? 'production':'sandbox',
        ));

        wp_enqueue_script('woocommerce_tappay_linepay');
    }


    public function process_payment($order_id)
    {
        global $woocommerce;
        
        // Wordpress Security Nonce
        $nonceVerified = wp_verify_nonce($_POST['_wc_tappay_nonce'], 'wc_tappay');

        // nonce was generated in 12 hours ago
        if (1 === $nonceVerified) {
            // nonce verified
            $order = wc_get_order($order_id);

            if (!$order) {
                wc_add_notice('Invalid order', 'error');
                return;
            }

            $response = $this->_payByPrime($order, $_POST['tappay_prime']);
            $result = json_decode($response['body'], true);
            $this -> console_log($result);
            $this -> console_log($this->get_return_url($order));

            //add_action('wp_enqueue_scripts', 'redierct_script');

            $pay_url = $result['payment_url'];
        
            // get data & process stock
            if(!is_wp_error($response)) {
                $result = json_decode($response['body'], true);
                // success
                if (0 === $result['status']) {
                    
                    //using for refund
                    //$msg = 'Payment completed, trade_id = ' . $result['rec_trade_id'];
                    
                    //$order->add_order_note($msg);

                    // save rec_trade_id
                    //$order->payment_complete($result['rec_trade_id']);

                    // process stock
                    //$order->reduce_order_stock();
                    //$woocommerce->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $pay_url,
                        //'redirect' => $this->get_return_url($order),
                    );
                } else {
                    // failed payment
                    $msg = 'Payment failed, message: ' . $result['msg'];
                    $order->add_order_note($msg);
                    wc_add_notice($msg, 'error');
                    return;
                }
            } else {
                // connection error
                wc_add_notice('Connection error', 'error');
                return;
            }

        } else {
            // nonce not verified
            wc_add_notice('Invalid nonce', 'error');
            return;
        }
    }
    
    public function webhook()
    { }

        
    // public function redierct_script(){

    //     console_log('redierct_script');

    //     wp_enqueue_script('tappay_js', 'https://js.tappaysdk.com/tpdirect/v5.5.1');

    //     wp_register_script('woocommerce_tappay_linepay_redirect', plugins_url('tappay_linepay_redirect.js', __FILE__), array('jquery', 'tappay_js'), '202104287', true);

    //     //Send data to javascript
    //     wp_localize_script('woocommerce_tappay_linepay_redirect', 'tappay_params', array(
    //         'url' => 'https://sandbox-redirect.tappaysdk.com/redirect/fecc16f8cbac32e32f41c7fdea72f173bb70f8e941a79f194b4e3e1fa3631bb8',
    //     ));

    //     wp_enqueue_script('woocommerce_tappay_linepay_redirect');

    // }

    public function console_log($data)
    {
        if (is_array($data) || is_object($data))
        {
            echo("<script>console.log('".json_encode($data)."');</script>");        
        }
        else
        {
            echo("<script>console.log('".$data."');</script>");
        }
    }   


    private function _payByPrime($order, $prime) {
        // prepare data send to gateway
        $detailText = array();

        $this -> console_log('_payByPrime go');

        foreach ($order->get_items() as $itemKey => $item) {
            $detailText[] = $item->get_name() . ' x' . $item->get_quantity() . PHP_EOL;
        }

        $amount = $order->get_total();

        if ('TWD' === $order->get_currency()) {
            $amount = intval($amount);
        }

        $postData = array(
            'prime' => $prime,
            'partner_key' => $this->partner_key,
            'merchant_id'=> $this->merchant_id,
            'details' => implode(PHP_EOL, $detailText),
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'order_number' => $order->get_order_number(),
            'result_url' => array(
                'frontend_redirect_url' => 'https://www.google.com/',
                'backend_notify_url' => 'https://www.google.com/',
            ),
            'cardholder'=> array(
                'phone_number' => $order->get_billing_phone(),
                'name' => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'zip_code' => $order->get_billing_postcode(),
                'address' => strip_tags($order->get_formatted_billing_address()),
            ),
        );

        $this -> console_log($postData);

        // send data
        $response = wp_remote_post(
            $this->endpoint . 'payment/pay-by-prime',
            array(
                'method' => 'POST',
                'timeout' => 30,
                'headers' => array(
                    'content-type' => 'application/json',
                    'x-api-key' => $this->partner_key,
                ),
                'body' => json_encode($postData),
                    )
        );
        
        return $response;
    }

}
