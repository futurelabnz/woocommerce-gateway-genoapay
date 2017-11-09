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

	/**
	 * Constructor for the IPN Handler.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_woocommerce_gateway_genoapay', array( $this, 'check_response' ) );
		add_action( 'valid-genoapay-standard-ipn-request', array( $this, 'valid_response' ) );
	}

	/**
	 * Check for IPN Response from Genoapay.
	 */
	public function check_response() {
		WooCommerce_Gateway_Genoapay::log( 'Checking IPN response is valid' );
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

	/**
	 * Valid response from genoapay and match order status with woocommerce
	 *
	 * @param  array $request query string from Genoapay.
	 */
	function valid_response( $request ) {
		if ( ! empty( $request['result'] ) && ! empty( $request['reference'] ) ) {

			$reference  = explode( '-', $request['reference'] );
			$order_id = $reference[1];

			$order = wc_get_order( $order_id );

			WooCommerce_Gateway_Genoapay::log( 'Found order #' . $order->get_id() );
			WooCommerce_Gateway_Genoapay::log( 'Payment status: ' . $request['result'] );

			if ( $order ) {
				$return_url = $order->get_checkout_order_received_url();
			} else {
				$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
			}

			if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) === 'yes' ) {
				$return_url = str_replace( 'http:', 'https:', $return_url );
			}

			$order_received = false;

			if ( 'COMPLETED' === $request['result'] ) {
				$order->payment_complete( $request['token'] );
				$order_received = true;
			} else {
				if ( 'Payment failed' === $request['result'] || 'Account closed' === $request['result'] ) {
					$order->update_status( 'failed' );
					$order_received = true;
				} else {
					$order_received = false;
				}
			}

			if ( $order_received ) {
				$redirect_url = $return_url;
			} else {
				$redirect_url = esc_url_raw( $order->get_cancel_order_url_raw() );
			}

			if( ! $request['display-in-modal'] ) {
				wp_safe_redirect( $redirect_url );
			} else { ?>
				<script type="text/javascript">
					window.top.location.href = '<?php echo $redirect_url; ?>';
				</script>
			<?php
			}
		}// End if().
		exit();
	}

}
