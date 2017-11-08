<?php
/**
 * Genoapay main class.
 *
 * Class Genoapay
 *
 * @package Genoapay
 */

if ( ! class_exists( 'Genoapay' ) ) {

	/**
	 * Main Genoapay Class
	 *
	 * @class Genoapay
	 * @version 1.0
	 */
	class Genoapay {

		/**
		 * Initiated class
		 *
		 * @var boolean
		 */
		private static $initiated = false;

		/**
		 * Include required files
		 */
		public static function init() {

			if ( ! self::$initiated ) {
				self::init_hooks();
			}
		}

		/**
		 * Initializes WordPress hooks
		 */
		private static function init_hooks() {
			self::$initiated = true;

			add_action( 'woocommerce_single_product_summary', array( 'Genoapay', 'woocommerce_template_genoapay_details' ), 11 );
			add_action( 'wp_enqueue_scripts', array( 'Genoapay', 'genoapay_scripts' ) );
		}

		/**
		 * Genoapay payment details on single product summary
		 */
		public static function woocommerce_template_genoapay_details() {
			global $product;
			$genoapay_gateway = new WooCommerce_Gateway_Genoapay();
			if ( $genoapay_gateway->validate_currency() && ! $genoapay_gateway->validate_min_max_amount() ) :
			?>
				<div class="genoapay-product-payment-details">
					<div class="genoapay-message">or 10 Interest free payments from $<?php echo number_format( (float) self::round_up( $product->get_price() / 10, 2), 2, '.', ''); ?></div>
					<div class="genoapay-logo">
						<img width="100" src="<?php echo GENOAPAY_PLUGIN_URL . 'assets/images/genoapay-logo.png';?>" alt="Genoapay" itemprop="logo">
						<a href="https://www.genoapay.com/" target="_blank"><i>Learn More</i></a>
					</div>
				</div>
			<?php
			endif;
		}

		/**
		 * Alwasy round up to 2 decimal places
		 * @param  Integer $value    
		 * @param  Integer $precision
		 * @return string
		 */
		public static function round_up ( $value, $precision ) { 
		    $pow = pow ( 10, $precision ); 
		    return ( ceil ( $pow * $value ) + ceil ( $pow * $value - ceil ( $pow * $value ) ) ) / $pow; 
		}

		/**
		 * Enqueue Scripts
		 */
		public static function genoapay_scripts() {
			wp_enqueue_style( 'genoapay-style', GENOAPAY_PLUGIN_URL . 'assets/css/genoapay.css', false, GENOAPAY_VERSION );
		}

	}
}// End if().
