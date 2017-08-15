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

	private static $endpoint = 'https://api.genoapay.com/v1';
	
	private static $sandbox_endpoint = 'https://sandbox-api.genoapay.com/v1';

	/** @var string API Client ID */
	public static $client_id;

	/** @var string API Client Secret */
	public static $client_secret;

	/** @var bool Sandbox */
	public static $sandbox = false;

	private static $auth_token = false;


	public static function get_endpoint() {
		if ( 'yes' === self::$sandbox ) {
			return self::$sandbox_endpoint;
		} else {
			return self::$endpoint;
		}
	}

	public static function get_auth_token() {
		return self::$auth_token;
	}

	public static function post_token() {
		$response = wp_safe_remote_post(
			self::get_endpoint() . '/token',
			array(
				'method'      => 'POST',
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
				'headers'     => array(
					'Authorization' => 'Basic ' . base64_encode(  self::$client_id . ':' . self::$client_secret ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$auth = json_decode( $response['body'] );
		if( ! empty( $auth->auth_token ) ) {
			self::$auth_token = $auth->auth_token;
		}
	}

	public static function get_configuration() {
		$response = wp_safe_remote_post(
			self::get_endpoint() . '/configuration',
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
			throw new Exception( $response->get_error_message() );
		}

		$configuration = json_decode( $response['body'] );
		if( empty( $configuration->error ) ) {
			return $configuration;
		}
	}

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
			throw new Exception( $response->get_error_message() );
		}

		$sale = json_decode( $response['body'] );

		if( ! empty( $sale->paymentUrl ) ) {
			return $sale->paymentUrl;
		} else {
			return false;
		}
	}

	public static function sale_refund( $request, $order ) {
		$response = wp_safe_remote_post(
			self::get_endpoint() . '/sale'. '/' .  $order->get_transaction_id() .'/refund',
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
		);;

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$refund = json_decode( $response['body'] );

		if( ! empty( $refund->refundId ) ) {
			return $refund->refundId;
		} else {
			return false;
		}
	}

}