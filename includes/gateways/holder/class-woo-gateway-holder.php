<?php
/**
 * Class Woo_Gateway_Holder file.
 *
 * @package WooCommerce\Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Hold Order Gateway.
 *
 * Provides a Hold Order Payment Gateway.
 *
 * @class       Woo_Gateway_Holder
 * @extends     Woo_Gateway_Invoicing
 * @version     1.0.0
 * @package     Woo-Manual-Order/Classes/Payment
 */
class Woo_Gateway_Holder extends Woo_Gateway_Invoicing {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = 'holder';
		$this->icon               = apply_filters( 'woocommerce_holder_icon', '' );
		$this->method_title       = __( 'Hold Order', 'woo-manual-order' );
		$this->method_description = __( 'Have your customers pay with cash (or by other means) upon delivery.', 'woo-manual-order' );
		$this->has_fields         = false;

        parent::__construct();
        
        add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'complete_order_status' ), 10, 2 );
	}

    /**
	 * Change payment complete order status to completed for Manual Holde orders.
	 *
	 * @since  1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function complete_order_status($status, $order = false){
		if ( $order && 'holder' === $order->get_payment_method() ) {
			$status = 'on-hold';
		}
		return $status;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'woo-manual-order' ),
				'label'       => __( 'Enable holder payment', 'woo-manual-order' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'              => array(
				'title'       => __( 'Title', 'woo-manual-order' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-manual-order' ),
				'default'     => __( 'Holder Order', 'woo-manual-order' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'woo-manual-order' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woo-manual-order' ),
				'default'     => __( 'Hold order for process later.', 'woo-manual-order' ),
				'desc_tip'    => true,
			),
			
		);
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
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			// Mark as processing or on-hold (payment won't be taken until delivery).
			$order->update_status( 'on-hold', __( 'Payment to be hold.', 'woo-manual-order' ) );
		} else {
			$order->payment_complete();
		}

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
