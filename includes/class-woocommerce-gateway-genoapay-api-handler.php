<?php
/**
 * WooCommerce_Gateway_Genoapay_API_Handler class.
 *
 * @package WooCommerce Payment Genoapay gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Refunds and other API requests.
 */
class WooCommerce_Gateway_Genoapay_API_Handler {

	/**
	 * Production endpoint
	 *
	 * @var string
	 */
	private static $endpoint = 'https://api.genoapay.com/v1';

	/**
	 * Sandbox endpoint
	 *
	 * @var string
	 */
	private static $sandbox_endpoint = 'https://sandbox-api.genoapay.com/v1';

	/**
	 * API Client Key
	 *
	 * @var string
	 */
	public static $client_key;

	/**
	 * API Client Secret
	 *
	 * @var string
	 */
	public static $client_secret;

	/**
	 * Enable/disbale sandbox mode
	 *
	 * @var boolean
	 */
	public static $sandbox = false;

	/**
	 * Auth token from retrieved from API
	 *
	 * @var string
	 */
	private static $auth_token = false;


	/**
	 * Get API endpoint by sandbox mode
	 *
	 * @return string API endpoint
	 */
	public static function get_endpoint() {
		if ( 'yes' === self::$sandbox ) {
			return self::$sandbox_endpoint;
		} else {
			return self::$endpoint;
		}
	}

	/**
	 * Get auth token
	 *
	 * @return string auth token
	 */
	public static function get_auth_token() {
		return self::$auth_token;
	}

	/**
	 * Authenticate the merchant server and obtain the auth_token used to validate the source of the api requests.
	 *
	 * @throws Exception Thrown on failure.
	 */
	public static function post_token() {
		$response = wp_safe_remote_post(
			self::get_endpoint() . '/token',
			array(
				'method'      => 'POST',
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
				'headers'     => array(
					'Authorization' => 'Basic ' . base64_encode( self::$client_key . ':' . self::$client_secret ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WooCommerce_Gateway_Genoapay::log( 'Token Failed: ' . $response->get_error_message(), 'error' );
			throw new Exception( $response->get_error_message() );
		}

		$auth = json_decode( $response['body'] );
		if ( ! empty( $auth->auth_token ) ) {
			WooCommerce_Gateway_Genoapay::log( 'successful authentication' );
			self::$auth_token = $auth->auth_token;
		}
	}

	/**
	 * Updates the client server with the latest configuration from the Genoapay system.
	 *
	 * @param  array $query_arg query args
	 * @return array response from API
	 * @throws Exception Thrown on failure.
	 */
	public static function get_configuration( $query_arg = array() ) {
		$config_endpoint = add_query_arg( $query_arg, self::get_endpoint() . '/configuration' );

		$response = wp_safe_remote_post(
			$config_endpoint,
			array(
				'method'      => 'GET',
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
				'headers'     => array(
					'Authorization' => 'Bearer ' . self::$auth_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WooCommerce_Gateway_Genoapay::log( 'Configuration Failed: ' . $response->get_error_message(), 'error' );
			throw new Exception( $response->get_error_message() );
		}

		$configuration = json_decode( $response['body'] );
		if ( empty( $configuration->error ) ) {
			WooCommerce_Gateway_Genoapay::log( 'Configuration Result: ' . wc_print_r( $response['body'], true ) );
			return $configuration;
		}
	}

	/**
	 * Creates a sale in the Genoapay system and returns the token to use when the user is redirected to complete the payment.
	 *
	 * @param  array $request request body.
	 * @return string payment url
	 * @throws Exception Thrown on failure.
	 */
	public static function post_sale( $request ) {
		$response = wp_safe_remote_post(
			self::get_endpoint() . '/sale',
			array(
				'method'      => 'POST',
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
				'body'        => $request,
				'headers'     => array(
					'Authorization' => 'Bearer ' . self::$auth_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WooCommerce_Gateway_Genoapay::log( 'Sale Failed: ' . $response->get_error_message(), 'error' );
			throw new Exception( $response->get_error_message() );
		}

		$sale = json_decode( $response['body'] );

		if ( ! empty( $sale->paymentUrl ) ) {
			WooCommerce_Gateway_Genoapay::log( 'Sale Result: ' . wc_print_r( $response['body'], true ) );
			return $sale->paymentUrl;
		} else {
			WooCommerce_Gateway_Genoapay::log( 'Sale Failed: ' . wc_print_r( $response['body'], true ), 'error' );
			return false;
		}
	}

	/**
	 * Request a refund in the Genoapay system.
	 *
	 * @param  array    $request request body.
	 * @param  WC_Order $order   woocommerce order.
	 * @return string refund id
	 * @throws Exception Thrown on failure.
	 */
	public static function sale_refund( $request, $order ) {
		$response = wp_safe_remote_post(
			self::get_endpoint() . sprintf( '/sale/%s/refund', $order->get_transaction_id() ),
			array(
				'method'      => 'POST',
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
				'body'        => $request,
				'headers'     => array(
					'Authorization' => 'Bearer ' . self::$auth_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WooCommerce_Gateway_Genoapay::log( 'Refund Failed: ' . $response->get_error_message(), 'error' );
			throw new Exception( $response->get_error_message() );
		}

		$refund = json_decode( $response['body'] );

		if ( ! empty( $refund->refundId ) ) {
			WooCommerce_Gateway_Genoapay::log( 'Refund Result: ' . wc_print_r( $response['body'], true ) );
			return $refund->refundId;
		} else {
			WooCommerce_Gateway_Genoapay::log( 'Refund Failed: ' . wc_print_r( $response['body'], true ), 'error' );
			return false;
		}
	}

}
