<?php
/**
 * WooCommerce_Gateway_Genoapay class.
 *
 * @extends WC_Payment_Gateway
 * @package WooCommerce Payment Genoapay gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for Genoapay payment gateway
 */
class WooCommerce_Gateway_Genoapay extends WC_Payment_Gateway {

	protected $minimum_amount;

	protected $maximum_amount;

	protected $genoapay_description;

	public $genoapay_currencies = array();

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = 'genoapay';
		$this->has_fields         = true;
		$this->method_title       = __( 'Genoapay', 'wc-genoapay' );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->sandbox       = $this->get_option( 'sandbox' );
		$this->title       		= $this->get_option( 'title' );
		$this->client_id 	= $this->get_option( 'client_id' );
		$this->client_secret 	= $this->get_option( 'client_secret' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-api-handler.php' );

		WooCommerce_Gateway_Genoapay_API_Handler::$client_id  = $this->get_option( 'client_id' );
		WooCommerce_Gateway_Genoapay_API_Handler::$client_secret  = $this->get_option( 'client_secret' );
		WooCommerce_Gateway_Genoapay_API_Handler::$sandbox       = $this->get_option( 'sandbox' );

		WooCommerce_Gateway_Genoapay_API_Handler::post_token();

		if( WooCommerce_Gateway_Genoapay_API_Handler::get_auth_token() ) {
			$genoapay_config = WooCommerce_Gateway_Genoapay_API_Handler::get_configuration();
			$this->minimum_amount = $genoapay_config->minimumAmount;
			$this->maximum_amount = $genoapay_config->maximumAmount;
			$this->genoapay_description = $genoapay_config->description;
			foreach( $genoapay_config->availability as $availability ) {
				$this->genoapay_currencies[] = $availability->currency;
			}

			if ( ! $this->validate_currency() || $this->validate_min_max_amount() ) {
				$this->enabled = 'no';
			} else {
				include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-ipn-handler.php' );
				new WooCommerce_Gateway_Genoapay_IPN_Handler();
			}

		} else {
			$this->enabled = 'no';
		}
	}


	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters( 'wc_qcard_form_fields', array(

			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'wc-genoapay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Genoapay', 'wc-genoapay' ),
				'default' => 'yes',
			),
			'sandbox' => array(
				'title' => __( 'Sandbox', 'wc-genoapay' ),
				'label' => __( 'Enable Genoapay sandbox', 'wc-genoapay' ),
				'type' => 'checkbox',
				'description' => __( 'Place the payment gateway in development mode.', 'wc-genoapay' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'wc-genoapay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-genoapay' ),
				'default'     => __( 'Genoapay', 'wc-genoapay' ),
				'desc_tip'    => true,
			),
			'client_id' => array(
				'title' => __( 'Client ID', 'wc-genoapay' ),
				'type' => 'text',
				'description' => __( 'This is the client id that is provided to the merchant and can be obtained from the Merchant account area.', 'wc-genoapay' ),
				'default' => '',
			),
			'client_secret' => array(
				'title' => __( 'Client Secret', 'wc-genoapay' ),
				'type' => 'text',
				'description' => __( 'This is the merchants secret key that is provided to the merchant. This value can be regenerated from the Merchants account.', 'wc-genoapay' ),
				'default' => '',
			),
		) );
	}

	/**
	 * Show the description from Genoapay.
	 **/
	public function payment_fields() {
		if ( $description = $this->genoapay_description ) {
			echo wpautop( wptexturize( $description ) );
		}
	}

	/**
	 * Process the payment and return the result.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-request.php' );

		$order          = wc_get_order( $order_id );
		$genoapay_request = new WooCommerce_Gateway_Genoapay_Request();

		return array(
			'result'   => 'success',
			'redirect' => $genoapay_request->get_request_url( $order ),
		);
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-request.php' );

		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			return new WP_Error( 'error', __( 'Refund failed: No transaction Token', 'wc-genoapay' ) );
		}

		$genoapay_request = new WooCommerce_Gateway_Genoapay_Request();

		$refund_id = $genoapay_request->request_refund( $order, $amount, $reason );

		if( $refund_id ) {
			$order->add_order_note( sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'wc-genoapay' ), $amount, $refund_id ) );
			return true;
		} else {
			return false;
		}
	}

	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Check if this gateway is enabled and available in the user's currency.
	 *
	 * @return bool
	 */
	public function validate_currency() {
		return in_array( get_woocommerce_currency(), $this->genoapay_currencies );
	}

	/**
	 * Ensure minimum and maximum amount is valid.
	 */
	public function validate_min_max_amount() {
		if ( is_checkout() ) {
			return ( ( $this->minimum_amount > 0 && $this->minimum_amount > WC()->cart->get_displayed_subtotal() ) || $this->maximum_amount < WC()->cart->get_displayed_subtotal() );
		}

	}
}
