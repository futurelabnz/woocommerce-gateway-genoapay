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

	/** @var bool Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;

	/**
	 * Minimum purchase amount
	 *
	 * @var string
	 */
	protected $minimum_amount;

	/**
	 * Maximum purchase amount
	 *
	 * @var string
	 */
	protected $maximum_amount;

	/**
	 * Genoapay description
	 *
	 * @var string
	 */
	protected $genoapay_description;

	/**
	 * Genoapay support currencies
	 *
	 * @var array
	 */
	protected $genoapay_currencies = array();

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
		$this->order_button_text  = __( 'Proceed to Genoapay', 'wc-genoapay' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->sandbox       = $this->get_option( 'sandbox' );
		$this->debug         = 'yes' === $this->get_option( 'debug', 'no' );
		$this->display_in_modal = 'yes' === $this->get_option( 'display_in_modal' );
		$this->title         = $this->get_option( 'title' );
		$this->client_key 	 = $this->get_option( 'client_key' );
		$this->client_secret = $this->get_option( 'client_secret' );

		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		$this->init_api();

		WooCommerce_Gateway_Genoapay_API_Handler::post_token();

		if ( WooCommerce_Gateway_Genoapay_API_Handler::get_auth_token() ) {
			$genoapay_config = WooCommerce_Gateway_Genoapay_API_Handler::get_configuration();
			$this->minimum_amount = $genoapay_config->minimumAmount;
			$this->maximum_amount = $genoapay_config->maximumAmount;
			$this->genoapay_description = $genoapay_config->description;
			foreach ( $genoapay_config->availability as $availability ) {
				$this->genoapay_currencies[] = $availability->currency;
			}

			if ( ! $this->validate_currency() || $this->validate_min_max_amount() ) {
				$this->log( 'Genoapy gateway disabled: Invalid currency or Order amount not within minimum and maximum number', 'error' );
				$this->enabled = 'no';
			} else {
				include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-ipn-handler.php' );
				new WooCommerce_Gateway_Genoapay_IPN_Handler();
			}
		} else {
			$this->log( 'Genoapy gateway disabled: No auth token', 'error' );
			$this->enabled = 'no';
		}

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		if ( $this->display_in_modal ) {
			wp_dequeue_script( 'wc-checkout' );
			wp_register_script( 'genoapay-script', GENOAPAY_PLUGIN_URL . 'assets/js/genoapay-checkout.js', array( 'jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n' ), GENOAPAY_VERSION );

			$translation_array = array(
					'ajax_url'                  => WC()->ajax_url(),
					'wc_ajax_url'               => WC_AJAX::get_endpoint( "%%endpoint%%" ),
					'update_order_review_nonce' => wp_create_nonce( 'update-order-review' ),
					'apply_coupon_nonce'        => wp_create_nonce( 'apply-coupon' ),
					'remove_coupon_nonce'       => wp_create_nonce( 'remove-coupon' ),
					'option_guest_checkout'     => get_option( 'woocommerce_enable_guest_checkout' ),
					'checkout_url'              => WC_AJAX::get_endpoint( "checkout" ),
					'is_checkout'               => is_page( wc_get_page_id( 'checkout' ) ) && empty( $wp->query_vars['order-pay'] ) && ! isset( $wp->query_vars['order-received'] ) ? 1 : 0,
					'debug_mode'                => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'i18n_checkout_error'       => esc_attr__( 'Error processing checkout. Please try again.', 'woocommerce' ),
				);
			wp_localize_script( 'genoapay-script', 'wc_checkout_params', $translation_array );

			// Enqueued script with localized data.
			wp_enqueue_script( 'genoapay-script' );
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
			'debug' => array(
				'title'       => __( 'Debug log', 'wc-genoapay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'wc-genoapay' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Genoapay events, such as IPN requests, inside %s', 'wc-genoapay' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'genoapay' ) . '</code>' ),
			),
			'display_in_modal' => array(
				'title'       => __( 'Display in modal', 'wc-genoapay' ),
				'label'       => __( 'Enable modal Checkout', 'wc-genoapay' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, open genoapay payment in modal instead of redirect to the page.', 'wc-genoapay' ),
				'default'     => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'wc-genoapay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-genoapay' ),
				'default'     => __( 'Genoapay', 'wc-genoapay' ),
				'desc_tip'    => true,
			),
			'client_key' => array(
				'title' => __( 'Client Key', 'wc-genoapay' ),
				'type' => 'text',
				'description' => __( 'This is the client key that is provided to the merchant and can be obtained from the Merchant account area.', 'wc-genoapay' ),
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
	 * Get gateway icon.
	 * @return string
	 */
	public function get_icon() {
		$icon_html = '';

		$icon_html .= '<img src="' . GENOAPAY_PLUGIN_URL . 'assets/images/genoapay-logo.png' . '" width="100" alt="' . esc_attr__( 'Genoapay', 'wc-genoapay' ) . '" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Init the API class and set the key/secret etc.
	 */
	protected function init_api() {
		include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-api-handler.php' );

		WooCommerce_Gateway_Genoapay_API_Handler::$client_key  = $this->get_option( 'client_key' );
		WooCommerce_Gateway_Genoapay_API_Handler::$client_secret  = $this->get_option( 'client_secret' );
		WooCommerce_Gateway_Genoapay_API_Handler::$sandbox       = $this->get_option( 'sandbox' );
	}

	/**
	 * Show the genoapay description.
	 **/
	public function payment_fields() {
		$description = $this->genoapay_description;
		if ( $description ) {
			echo wp_kses_post( wptexturize( $description ) );
		}
	}

	/**
	 * Process the payment and redirect to payment page
	 *
	 * @param  int $order_id woocommerce order id.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-request.php' );

		$order          = wc_get_order( $order_id );
		$genoapay_request = new WooCommerce_Gateway_Genoapay_Request();

		return array(
			'result'   => 'success',
			'redirect' => $genoapay_request->get_request_url( $order, $this->display_in_modal ),
		);
	}

	/**
	 * Process a refund.
	 *
	 * @param  int    $order_id woocommerce order id.
	 * @param  string $amount refund amount.
	 * @param  string $reason refund reason.
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		include_once( GENOAPAY_PLUGIN_DIR . '/includes/class-woocommerce-gateway-genoapay-request.php' );

		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log( 'Refund Failed: No transaction ID', 'error' );
			return new WP_Error( 'error', __( 'Refund failed: No transaction Token', 'wc-genoapay' ) );
		}

		$genoapay_request = new WooCommerce_Gateway_Genoapay_Request();

		$refund_id = $genoapay_request->request_refund( $order, $amount, $reason );

		if ( $refund_id ) {
			$refund_result = sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'wc-genoapay' ), $amount, $refund_id );
			$this->log( $refund_result );
			$order->add_order_note( $refund_result );
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Can the order be refunded?
	 *
	 * @param  WC_Order $order woocommerce order.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Check if this gateway is enabled and available in the user's currency.
	 *
	 * @return bool
	 */
	public function validate_currency() {
		return in_array( get_woocommerce_currency(), $this->genoapay_currencies, true );
	}

	/**
	 * Ensure minimum and maximum amount is valid.
	 */
	public function validate_min_max_amount() {
		global $product;
		if ( is_checkout() ) {
			return ( ( $this->minimum_amount > 0 && $this->minimum_amount > WC()->cart->get_displayed_subtotal() ) || $this->maximum_amount < WC()->cart->get_displayed_subtotal() );
		} elseif( is_product() ) {
			return ( ( $this->minimum_amount > 0 && $this->minimum_amount > $product->get_price() ) || $this->maximum_amount < $product->get_price() );
		}

	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'genoapay' ) );
		}
	}
}
