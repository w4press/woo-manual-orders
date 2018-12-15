<?php
/**
 * Class Woo_Gateway_Paypal_Invoicing file.
 *
 * @package WooCommerce\Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Paypal Invoicing Gateway.
 *
 * Provides a Paypal Invoicing Payment Gateway.
 *
 * @class       Woo_Gateway_Paypal_Invoicing
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     Woo_Manual_Orders/Classes/Payment
 */
class Woo_Gateway_Invoicing extends WC_Payment_Gateway {
    /**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		if( !isset($this->supports) ) $this->supports = array();

		$this->supports = array_merge( $this->supports, array(
			'products',
			'invoicing',
        ));
        
        // Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		foreach ( $this->settings as $setting_key => $setting ) {
			$this->$setting_key = $setting;
		}

		// Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
        global $woo_manual_order;
        $is_available = ( 'yes' === $this->enabled );
        if( ! is_manual_order_page() ) {
            return false;
        }
		return $is_available;
    }

    /**
	 * Adds debug messages to the page as a WC message/error, and / or to the WC Error log
	 *
	 * @since 1.0
	 * @param array $errors error messages to add
	 */
	public function add_logs( $errors, $display = false ) {
		global $woo_manual_order;
		
		if ($this->debug != 'yes') return;

		// do nothing when debug mode is off
		if ( empty( $errors ) )
			return;

		$message = implode( ', ', ( is_array( $errors ) ) ? $errors : array( $errors ) );

		// add debug message to checkout page
		$woo_manual_order->log( $message, $this->id );

		if( $display ) {
            $this->add_message( $message );
        }
	}
	
	/**
	 * Show messages
	 */
	public function add_message( $message ) {
		global $woo_manual_order;
		
		// do nothing when debug mode is off
		if ( empty( $message ) )
			return;

		$message = implode( ', ', ( is_array( $message ) ) ? $message : array( $message ) );

		// add debug message to checkout page
		$woo_manual_order->add_message( $message );			
	}
}