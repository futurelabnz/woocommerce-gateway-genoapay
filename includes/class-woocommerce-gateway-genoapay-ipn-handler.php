<?php
/**
 * WooCommerce_Gateway_Genoapay_IPN_Handler class.
 *
 * @package WooCommerce Payment Genoapay gateway
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles responses from Genoapay IPN.
 */
class WooCommerce_Gateway_Genoapay_IPN_Handler {

	public function __construct() {
		add_action( 'woocommerce_api_woocommerce_gateway_genoapay', array( $this, 'check_response' ) );
		add_action( 'valid-genoapay-standard-ipn-request', array( $this, 'valid_response' ) );
	}

	public function check_response() {

		if ( isset( $_REQUEST['_wpnonce'], $_REQUEST['reference'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'genoapay_' . sanitize_text_field( wp_unslash( $_REQUEST['reference'] ) ) ) ) {

			$_REQUEST = wp_unslash( $_REQUEST ); // Input var okay.

			// @codingStandardsIgnoreStart
			do_action( 'valid-genoapay-standard-ipn-request', $_REQUEST );
			// @codingStandardsIgnoreEnd
			exit;
		}

		wp_die( 'Genoapay IPN Request Failure', 'Genoapay IPN', array(
			'response' => 500,
		) );
	}

	function valid_response( $request ) {
		if ( ! empty( $request['result'] ) && ! empty( $request['reference'] ) ) {

			$reference  = explode( '-', $request['reference'] );
			$order_id = $reference[1];

			$order = wc_get_order( $order_id );

			if ( $order ) {
				$return_url = $order->get_checkout_order_received_url();
			} else {
				$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
			}

			if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) === 'yes' ) {
				$return_url = str_replace( 'http:', 'https:', $return_url );
			}

			$order_received = false;

			switch ( $request['result'] ) {
				case 'COMPLETED':
					$order->payment_complete( $request['token'] );
					$order_received = true;
					break;
				default:
					$order_received = false;
			}

			if ( $order_received ) {
				wp_safe_redirect( $return_url );
			} else {
				wp_safe_redirect( esc_url_raw( $order->get_cancel_order_url_raw() ) );
			}
		}// End if().
		exit();
	}

}