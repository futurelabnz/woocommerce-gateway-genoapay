<?php
/**
 * WooCommerce_Gateway_Genoapay_Request class.
 *
 * @package WooCommerce Payment gateway Qcard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for dealing with a Genoapay payment request
 */
class WooCommerce_Gateway_Genoapay_Request {

	/**
	 * Get the Genoapay request URL for an order.
	 *
	 * @param  WC_Order $order woocommerce order.
	 * @return string
	 */
	public function get_request_url( $order ) {
		WooCommerce_Gateway_Genoapay_API_Handler::post_token();

		if ( WooCommerce_Gateway_Genoapay_API_Handler::get_auth_token() ) {

			$transaction_id = uniqid( 'ID' );
			$transaction_id = $transaction_id . '-' . $order->get_id();

			$nonce = wp_create_nonce( 'genoapay_' . $transaction_id );

			$return_url = add_query_arg( array(
							'wc-api' => 'WooCommerce_Gateway_Genoapay',
							'_wpnonce' => $nonce,
			), home_url( '/' ) );

			$line_items_desc = array();
			$line_items_price = array();
			if ( count( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					$product = $item->get_product();
					if ( $item->is_type( 'line_item' ) && ( $product ) ) {
						$line_items_desc[] = $item->get_quantity() . ' x ' . $product->get_name();
						$line_items_price[] = $item->get_total();
					}
				}
			}

			$sale = array(
				'customer' => array(
					'mobileNumber' => $order->get_billing_phone(),
					'firstName' => $order->get_billing_first_name(),
					'surname' => $order->get_billing_last_name(),
					'email' => $order->get_billing_email(),
					'address' => array(
						'addressLine1' => $order->get_billing_address_1(),
						'addressLine2' => $order->get_billing_address_2(),
						'suburb' => '',
						'cityTown' => $order->get_billing_city(),
						'state' => $order->get_billing_state(),
						'postcode' => $order->get_billing_postcode(),
						'countryCode' => $order->get_billing_country(),
					),
				),
				'product' => array(
					'description' => implode( ', ',$line_items_desc ),
					'price' => array_sum( $line_items_price ),
					'currencyCode' => $order->get_currency(),
					'reference' => $transaction_id,
				),
				'returnUrls' => array(
					'successUrl' => $return_url,
					'failUrl' => $return_url,
					'callbackUrl' => $return_url,
				),
			);

			WooCommerce_Gateway_Genoapay::log( 'Genoapay Request Args for sale ' . $order->get_order_number() . ': ' . wc_print_r( $sale, true ) );

			$sale_json = stripslashes( wp_json_encode( $sale ) );

			$signature = $this->request_signature( $sale_json );

			$request = array(
				'sale'         => $sale_json,
				'signature'    => $signature,
			);

			return WooCommerce_Gateway_Genoapay_API_Handler::post_sale( $request );
		} else {
			return false;
		}// End if().
	}


	/**
	 * Process refund request for an order
	 *
	 * @param  WC_Order $order woocommerce order.
	 * @param  string   $amount refund amount.
	 * @param  string   $reason refund reason.
	 * @return string refund id
	 */
	public function request_refund( $order, $amount, $reason ) {
		WooCommerce_Gateway_Genoapay_API_Handler::post_token();

		if ( WooCommerce_Gateway_Genoapay_API_Handler::get_auth_token() ) {

			$refund = array(
				'amount' => $amount,
				'reason' => $reason,
				'currencyCode' => $order->get_currency(),
				'reference' => $order->get_id(),
			);

			$refund_json = stripslashes( wp_json_encode( $refund ) );

			$signature = $this->request_signature( $refund_json );

			$request = array(
				'refund'         => $refund_json,
				'signature'    => $signature,
			);

			WooCommerce_Gateway_Genoapay::log( 'Genoapay Request Args for refund ' . $order->get_order_number() . ': ' . wc_print_r( $refund, true ) );

			return WooCommerce_Gateway_Genoapay_API_Handler::sale_refund( $request, $order );
		} else {
			return false;
		}
	}

	/**
	 * Strip out the json formatting and compute the HMAC using SHA256 digest algorithm
	 *
	 * @param  string $json json string.
	 * @return string
	 */
	public function request_signature( $json ) {
		// Strip out the json formatting, leaving only the name and values.
		$replacements = array(
			'":{' => '',
			'":"' => '',
			'":' => '',
			'{"' => '',
			'},"' => '',
			'","' => '',
			',"' => '',
			',' => '',
			'"' => '',
			'{' => '',
			'}' => '',
			' ' => '',
		);
		$signature = str_replace( array_keys( $replacements ), $replacements, $json );

		// Encode to Base64 format.
		$signature = base64_encode( $signature );

		// Compute the HMAC using SHA256 digest algorithm.
		return hash_hmac( 'sha256', $signature, WooCommerce_Gateway_Genoapay_API_Handler::$client_secret );
	}
}
