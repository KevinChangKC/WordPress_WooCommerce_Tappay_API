<?php
/*
 * Plugin Name: TapPay Gateway LinePay
 * Description: tappay-linepay for woocommerce 
 * Version: 1.0.1
 * Author: Kevin Chang
 * Author URI: 
 * License: Reserve
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// define('KC_TAPPAY_LINEPAY_PLUGIN', plugin_basename( __FILE__ ) );
// define('KC_TAPPAY_LINEPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
// define('KC_TAPPAY_LINEPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
// define('KC_TAPPAY_LINEPAY_PLUGIN_CONTROLLER_DIR', KC_TAPPAY_LINEPAY_PLUGIN."controller/");
// define('KC_TAPPAY_LINEPAY_PLUGIN_ASSETS_DIR', KC_TAPPAY_LINEPAY_PLUGIN."assets/");

// Woocommerce Payment Gateway Hook
add_filter('woocommerce_payment_gateways', 'woo_payment_gateway_hook');
function woo_payment_gateway_hook($gateways) {
    $gateways[] = 'KCLinePaymentGateway'; /* lib/[class name] */
    return $gateways;
}

//init require
add_action('plugins_loaded', 'init_kc_tappay_linepay_payment');
function init_kc_tappay_linepay_payment() {
    require_once 'controller/KCLinePaymentGateway.php';
}


// prevent using other payment method in payment page
add_filter('woocommerce_available_payment_gateways', 'tappay_enable_gateway_order_pay_linepay');
function tappay_enable_gateway_order_pay_linepay($available_gateways) {
    global $woocommerce;

    if (is_checkout() && is_wc_endpoint_url('order-pay')) {
        $order = wc_get_order(intval($_GET['order-pay']));
        if ('tappay_linepay' === $order->get_payment_method()) {
            return array(
                'tappay_linepay' => $available_gateways['tappay_linepay'],
            );
        }
    }
    return $available_gateways;
}

