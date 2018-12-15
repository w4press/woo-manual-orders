<?php
/**
 * Checkout Shortcode
 *
 * Used on the checkout page, the checkout shortcode displays the checkout process.
 *
 * @package Woo-Manual-Order/Shortcodes/Checkout
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode Creating class.
 */
class Woo_Manual_Order_Shortcode_Checkout {
    /**
	 * Get the shortcode content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function get( $atts ) {
		
		return Woo_Manual_Order_Shortcodes::shortcode_wrapper( array( __CLASS__, 'output' ), $atts );
	}
	
	/**
	 * Output the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public static function output( $atts ) {
		global $wp;

		self::checkout();
	}

	/**
	 * Show the checkout.
	 */
	private static function checkout() {
        global $woo_manual_order;

		// Check cart contents for errors.
		do_action( 'woocommerce_check_cart_items' );

		// Calc totals.
		WC()->cart->calculate_totals();

		// Get checkout object.
		$checkout = WC()->checkout();

		if ( empty( $_POST ) && wc_notice_count( 'error' ) > 0 ) { // WPCS: input var ok, CSRF ok.

			wc_get_template( 'checkout/cart-errors.php', array( 'checkout' => $checkout ) );

		} else {

			$non_js_checkout = ! empty( $_POST['woocommerce_checkout_update_totals'] ); // WPCS: input var ok, CSRF ok.

			if ( wc_notice_count( 'error' ) === 0 && $non_js_checkout ) {
				wc_add_notice( __( 'The order totals have been updated. Please confirm your order by pressing the "Place order" button at the bottom of the page.', 'woo-manual-order' ) );
			}

			$woo_manual_order->get_template( 'checkout/form-checkout.php', array( 'checkout' => $checkout ) );

		}
	}
	
}