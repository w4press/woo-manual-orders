<?php
/** 
 * Plugin Name: Woo Manual Orders
 * Plugin URI: https://github.com/w4press/woo-manual-orders
 * Description: Woocommerce Manual Orders is a plugin that extends WooCommerce, allow you to quick create order as a customer. Requires WC 3.0.0+
 * Version: 1.0.3
 * Author: w4press <w4press@gmail.com>
 * Author URI: https://w4press.com
 * Requires at least: 3.8
 * Tested up to: 4.9.8
 * WC requires at least: 3.0.0
 * WC tested up to: 3.5.x
 * 
 * @package   Woo-Manual-Order
 * @author    w4press <w4press@gmail.com>
 * @category  Orders
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'WC_MANUAL_ORDER_EXTENSION_SLUG' , 'woo-manual-order' );
define( 'WC_MANUAL_ORDER_EXTENSION_VERSION' , '1.0.3' );
define( 'WC_MANUAL_ORDER_REQUIRED_VERSION' , '3.0.0' );

/**
 * WooCommerce fallback notice.
 *
 * @since 1.0.0
 * @return string
 */
function woocommerce_manual_order_missing_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Manual Orders requires WooCommerce to be installed and active. You can download %s here.', 'woo-manual-order' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

add_action( 'woocommerce_loaded', 'woocommerce_manual_order_init' );

function woocommerce_manual_order_init(){

	if ( ! class_exists( 'Woocommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_manual_order_missing_notice' );
		return;
	}
	
	// Include the main WooCommerce class.
	if ( ! class_exists( 'Woo_Manual_Order_Ctrl' ) ) {
		require_once dirname( __FILE__ ) . '/includes/class-woo-manual-order-ctrl.php';
	}

	$GLOBALS['woo_manual_order'] = Woo_Manual_Order_Ctrl::instance( __FILE__ );
}
