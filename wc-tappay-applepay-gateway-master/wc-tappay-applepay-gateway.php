<?php

/*
 * Plugin Name: WooCommerce ApplePay LinePay GateWay
 * Description: tappay-applepay for woocommerce 
 * Version: 1.0.1
 * Author: Kevin Chang
 * Author URI: 
 * License: Reserve
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

 define('KC_TAPPAY_APPLEPAY_PLUGIN', plugin_basename( __FILE__ ) );
 define('KC_TAPPAY_APPLEPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
 define('KC_TAPPAY_APPLEPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
 define('KC_TAPPAY_APPLEPAY_PLUGIN_CONTROLLER_DIR', KC_TAPPAY_APPLEPAY_PLUGIN_DIR."controller/");
 define('KC_TAPPAY_APPLEPAY_PLUGIN_ASSETS_DIR', KC_TAPPAY_APPLEPAY_PLUGIN_DIR."assets/");

// Woocommerce Payment Gateway Hook
add_filter('woocommerce_payment_gateways', 'woo_payment_gateway_applepay_hook');
function woo_payment_gateway_applepay_hook($gateways) {
    $gateways[] = 'KCApplePaymentGateway'; /* controller/[class name] */
    return $gateways;
}

//init require
add_action('plugins_loaded', 'init_tappay_applepay_payment');
function init_tappay_applepay_payment() {
    require_once KC_TAPPAY_APPLEPAY_PLUGIN_CONTROLLER_DIR . 'KCApplePaymentGateway.php';
}

// prevent using other payment method in payment page
add_filter('woocommerce_available_payment_gateways', 'tappay_enable_gateway_order_pay_applepay');
function tappay_enable_gateway_order_pay_applepay($available_gateways) {
    global $woocommerce;

    if (is_checkout() && is_wc_endpoint_url('order-pay')) {
        $order = wc_get_order(intval($_GET['order-pay']));
        $testing = $order->get_payment_method();

        if ('tappay_applepay' === $order->get_payment_method()) {

            return array(
                'tappay_applepay' => $available_gateways['tappay_applepay'],
            );
        }
    }
    return $available_gateways;
}




