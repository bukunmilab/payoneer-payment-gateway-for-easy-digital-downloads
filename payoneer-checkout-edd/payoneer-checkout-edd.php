<?php
/**
 * Plugin Name: Payoneer Checkout for Easy Digital Downloads
 * Plugin URI: https://loquisoft.com
 * Description: Custom PSP plugin to integrate Payoneer Checkout with Easy Digital Downloads (EDD). This plugin supports hosted and native modes, recurring subscriptions, license keys, transactions, refunds, partial refunds, chargebacks, 3DS flows, multi-currency and trials, plus live/test API switching.
 * Version: 1.1.0
 * Author: Loquisoft
 * Author URI: https://loquisoft.com
 * License: GPLv2 or later
 * Text Domain: payoneer-checkout-edd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin path and URL constants.
if ( ! defined( 'PCE_PLUGIN_DIR' ) ) {
    define( 'PCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'PCE_PLUGIN_URL' ) ) {
    define( 'PCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Include required files.
require_once PCE_PLUGIN_DIR . 'includes/class-payoneer-api.php';
require_once PCE_PLUGIN_DIR . 'includes/class-payoneer-notifications.php';
require_once PCE_PLUGIN_DIR . 'includes/class-payoneer-admin.php';
require_once PCE_PLUGIN_DIR . 'includes/class-payoneer-edd-integration.php';

// Initialize the gateway integration (if EDD is active).
function pce_init_gateway_integration() {
    if ( class_exists( 'Easy_Digital_Downloads' ) ) {
        new Payoneer_EDD_Integration();
    }
}
add_action( 'plugins_loaded', 'pce_init_gateway_integration' );

// Initialize admin settings for Payoneer in EDD.
function pce_init_admin_settings() {
    if ( is_admin() ) {
        new Payoneer_Admin();
    }
}
add_action( 'plugins_loaded', 'pce_init_admin_settings' );

// Handle notifications (webhook endpoint).
function pce_handle_notifications() {
    if ( isset( $_GET['pce_notification'] ) ) {
        $payoneer_notifications = new Payoneer_Notifications();
        $payoneer_notifications->process_notification();
        exit;
    }
}
add_action( 'init', 'pce_handle_notifications' );
