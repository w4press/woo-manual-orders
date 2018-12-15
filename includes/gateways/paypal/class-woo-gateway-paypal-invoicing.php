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
class Woo_Gateway_Paypal_Invoicing extends Woo_Gateway_Invoicing {

    var $remote;

    /**
	 * Constructor for the gateway.
	 */
	public function __construct() {

        $this->id			= 'paypal_invoicing';
        $this->has_fields 	= true;
		$this->method_title = __( 'Paypal Invoicing', 'woo-manual-order' );
		$this->method_description = __( 'PayPal makes it easy to send professional, customized invoices to your customers. In a few clicks, customers can pay you securely, and you typically receive your money in just minutes.', 'woo-manual-order' );
        
        $this->supports = array('refunds');

        parent::__construct();

        if( 'yes' == $this->enabled ) {	
            $this->remote = Woo_Paypal_Invoicing_Remote::instance( $this->settings );
        }
    }
    
    /**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'woo-manual-order' ),
				'label'       => __( 'Enable Paypal Invoice', 'woo-manual-order' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'              => array(
				'title'       => __( 'Title', 'woo-manual-order' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-manual-order' ),
				'default'     => __( 'Paypal Invoice', 'woo-manual-order' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'woo-manual-order' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woo-manual-order' ),
				'default'     => __( 'Send invoice via Paypal.', 'woo-manual-order' ),
				'desc_tip'    => true,
			),
			'debug'                 => array(
                'title'       => __( 'Debug log', 'woo-manual-order' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'woo-manual-order' ),
                'default'     => 'no',
                /* translators: %s: URL */
                'description' => sprintf( __( 'Log PayPal events, inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woo-manual-order' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'paypal' ) . '</code>' ),
            ),
            'merchant_email'          => array(
                'title'       => __( 'Merchant email', 'woo-manual-order' ),
                'type'        => 'text',
                'description' => __( 'The merchant email address. This email must be listed in the merchant\'s PayPal profile.', 'woo-manual-order' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __( 'Require', 'woo-manual-order' ),
            ),
            'trans_mode'              => array(
                'title' => __('Transaction Mode', 'woo-manual-order'),
                'type' => 'select',
                'options' => array('live' => 'Live', 'sandbox' => 'Sandbox'),
                'default' => 'live',
                /* translators: %s: URL */
                'description' => sprintf( __( 'PayPal sandbox can be used to test payments. Sign up for a <a href="%s">developer account</a>.', 'woo-manual-order' ), 'https://developer.paypal.com/' ),
            ),
            
            'client_id'          => array(
                'title'       => __( 'Live Client ID', 'woo-manual-order' ),
                'type'        => 'text',
                'description' => __( 'Get your API credentials from PayPal.', 'woo-manual-order' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __( 'Require', 'woo-manual-order' ),
            ),
            'client_secret'          => array(
                'title'       => __( 'Live Client Secret', 'woo-manual-order' ),
                'type'        => 'password',
                'description' => __( 'Get your API credentials from PayPal.', 'woo-manual-order' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __( 'Require', 'woo-manual-order' ),
            ),

            'sandbox_client_id'  => array(
                'title'       => __( 'Sandbox Client ID', 'woo-manual-order' ),
                'type'        => 'text',
                'description' => __( 'Get your API credentials from PayPal.', 'woo-manual-order' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __( 'Require', 'woo-manual-order' ),
            ),
            'sandbox_client_secret'  => array(
                'title'       => __( 'Sandbox Client Secret', 'woo-manual-order' ),
                'type'        => 'password',
                'description' => __( 'Get your API credentials from PayPal.', 'woo-manual-order' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __( 'Require', 'woo-manual-order' ),
            ),
            'terms'        => array(
				'title'       => __( 'The general terms', 'woo-manual-order' ),
				'type'        => 'textarea',
				'description' => __( 'The general terms of the invoice. Maximum length: 4000', 'woo-manual-order' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
    }
    /**
     * Admin option
     */
    function admin_options(){
        parent::admin_options();
        ?>
        <script>
        jQuery(document).ready( function($){
            jQuery( '#woocommerce_paypal_invoicing_trans_mode' ).change(function(e){
                var pf = '#woocommerce_paypal_invoicing_';
                if ($(this).val() == 'sandbox') {
                    $( pf+'sandbox_client_id').closest('tr').show();
                    $( pf+'sandbox_client_secret').closest('tr').show();
                    
                    $( pf+'client_id').closest('tr').hide();
                    $( pf+'client_secret').closest('tr').hide();

                } else {
                    $( pf+'sandbox_client_id').closest('tr').hide();
                    $( pf+'sandbox_client_secret').closest('tr').hide();
                    
                    $( pf+'client_id').closest('tr').show();
                    $( pf+'client_secret').closest('tr').show();

                }
            }).change();
        });
        </script>
        <?php
        
    }
    
    /**
     * Process the payment
     */
	function process_payment($order_id) {
        global $woocommerce, $woo_manual_order;

        $order = wc_get_order( $order_id );

        try {

            if( $response = $this->remote->send_invoice( $order ) ){
                
                $this->add_logs( 'Send invoice response: ' . print_r($response, true) );

                if( is_wp_error( $response ) ) {
                    throw new Exception($response->get_error_message());
                }

                if( empty($response['id'] ) || empty($response['result'] ) ) {
                    throw new Exception( __('Can not send invoice', 'woo-manual-order') );
                }

                if( 'Accepted' !== $response['result']['message'] ) {
                    throw new Exception( __('Send invoice error', 'woo-manual-order') );
                }

                $inv_id = $response['id']; // invoice id

                $order->payment_complete( $inv_id );

                // Remove cart.
                WC()->cart->empty_cart();

                $this->add_logs( 'Payment completed with invoice id: ' . $inv_id );

                // Return thankyou redirect.
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order ),
                );

            } else {
                throw new Exception( 'Can not send invoice via paypal' );
            }

            // Add any log if exists
            $this->add_logs($this->remote->get_messages());

        } catch( Exception $ex ) {
            $message = __("Error processing checkout", 'woo-manual-order' );
            $this->add_logs( $ex->getMessage() );
        }
    }

    /**
	 * Process refund transaction
	 */
	public function process_refund($order_id, $amount = null, $reason = '') {

        $order = new WC_Order($order_id);
        
		try {
			$this->add_logs( 'Process refund' );

            $this->add_logs( ' Refund Id: ' . $order->get_transaction_id() );
            $this->add_logs( ' Amount: ' . $amount );
            $this->add_logs( ' Reason: ' . $reason );

            if( $response = $this->remote->refund($order, $amount, $reason)){

                if( ! empty( $response['name'] )) {
                    throw new Exception( $response['message'] );
                }

                $order->add_order_note(
                    sprintf(__('%s: refunded %.2f from charge "%s"', 'woo-manual-order'),
                        $this->method_title, $amount, $order->get_transaction_id()
                    )
                );

                $this->add_logs( 'Refund completed'  );

                return true;

            }

            $this->add_logs( ' Can not refund transaction'  );

            return false;

		} catch (Exception $e) {

            $this->add_logs( $e->getMessage()  );

			$order->add_order_note(
				sprintf(__('%s: refund of charge "%s" failed with message: "%s"', 'woo-manual-order'),
					$this->method_title,
					$order->get_transaction_id(),
					$e->getMessage()
				)
			);
			return new WP_Error('paypal_invoicing_refund_error', $e->getMessage());
		}
	}

    
}