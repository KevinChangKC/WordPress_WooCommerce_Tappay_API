<?php


/**
 * KCJKOPaymentGateway Class.
 */

class KCApplePaymentGateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'tappay_applepay';
        $this->has_fields = false;
        $this->method_title = 'Tappay Gateway for applepay';
        $this->method_description = 'Tappay Gateway for applepay using';
        $this->supports = array(
            'products'
        );
        $this->order_button_text = '使用 ApplePay 付款';

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

        // Apply Script Needed
        add_action('wp_enqueue_scripts', 
            array($this, 'payment_scripts')
        );

        // Webhook func
        add_action('woocommerce_api_' . $this->webhook_name(),
            array($this, 'webhook')
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
        wp_register_script('woocommerce_tappay_applepay', plugins_url('tappay_applepay.js', __FILE__), array('jquery', 'tappay_js'), '3.9', true);

        //Send data to javascript
        wp_localize_script('woocommerce_tappay_applepay', 'tappay_params', array(
            'method' => 'applepay',
            'app_id' => $this->app_id,
            'app_key' => $this->app_key,
            'server_type' => ('no' === $this->enabled)? 'production':'sandbox',
        ));

        wp_enqueue_script('woocommerce_tappay_applepay');
    }

    public function init_form_fields()
    {        
         
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable TapPay Gateway for ApplePay',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'ApplePay',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => '使用applypay付款，power by tappay',
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
                'default'     => 'APMEnIcTUZYYB2HQv7Zl',
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

            // Send Prime and Order info to Tappay
            $response = $this->_payByPrime($order, $order_id, $_POST['tappay_prime']);

            // Get Result 
            $result = json_decode($response['body'], true);

            // Get Payment URL 
            $pay_url = $result['payment_url'];
        
            // get data & process stock
            if(!is_wp_error($response)) {

                // success
                if (0 === $result['status']) {
                    
                    if(!isset($pay_url))
                        return;
                        
                    
                    return array(
                        'result' => 'success',
                        'redirect' => $pay_url,                        
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
    { 

        global $woocommerce, $post;

        echo $post;

        $order_get = new WC_Order($post->ID);
        $rec_trade_id = $post->rec_trade_id;

        $this -> console_log('rec_trade_id :');
        $this -> console_log($rec_trade_id);

        $order_id = $order_get->get_order_number();
        
        // using for refund
        $msg = 'Payment completed, trade_id = ' . $result['rec_trade_id'];
        
        $order = wc_get_order($order_id);            
        
        $order->add_order_note($msg);

        // save rec_trade_id
        $order->payment_complete($result['rec_trade_id']);

        // // process stock
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();
        exit;


    }

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
    
    private function webhook_name() {
        return $this->id;
    }

    private function _payByPrime($order, $order_id, $prime) {
        // prepare data send to gateway
        $detailText = array();

        $order_key = $order->get_order_key();

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
                'frontend_redirect_url' => get_site_url() . "/checkout/order-received/{$order_id}/?key={$order_key}",
                'backend_notify_url' => get_site_url() . "/?wc-api=" . $this->webhook_name(),
            ),
            'cardholder'=> array(
                'phone_number' => $order->get_billing_phone(),
                'name' => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'zip_code' => $order->get_billing_postcode(),
                'address' => strip_tags($order->get_formatted_billing_address()),
            ),
        );

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
