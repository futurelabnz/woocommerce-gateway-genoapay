<?php
/**
 * Plugin Name: WooCommerce Payment Genoapay gateway
 * Plugin URI: https://www.futurelab.co.nz
 * Description: A payment gateway for Genoapay
 * Version: 1.0
 * Author: FutureLab
 * Author URI: https://www.futurelab.co.nz
 * Text Domain: wc-genoapay
 *
 * @package WooCommerce Payment Genoapay gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check if WooCommerce is active
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	return;
}


define( 'GENOAPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GENOAPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'woocommerce_genoapay_init' );

/**
 * Genoapay payment gateway init
 */
function woocommerce_genoapay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once( GENOAPAY_PLUGIN_DIR . 'includes/class-woocommerce-gateway-genoapay.php' );

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_genoapay_add_gateway' );
}

/**
 * Add Genoapay gateway to woocommerce
 *
 * @param  array $methods WC Payment Methods.
 * @return array
 */
function woocommerce_genoapay_add_gateway( $methods ) {
	$methods[] = 'WooCommerce_Gateway_Genoapay';
	return $methods;
}
