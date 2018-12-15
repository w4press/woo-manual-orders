<?php
/**
 * Sales By Product Reporting
 *
 * @package WooCommerce/Admin/Reporting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Report_Sales_By_Manual_Order
 *
 * @author      w4press
 * @category    Admin
 * @version     1.0.2
 */
class WC_Report_Sales_By_Manual_Order extends WC_Report_Sales_By_Date {
    /**
	 * Constructor.
	 */
	public function __construct() {
		
		add_filter( 'woocommerce_reports_get_order_report_data_args', array( $this, 'get_order_report_data_args' ) );
	}

	/**
	 * Hook to add filter by manual order
	 */
	public function get_order_report_data_args($args=array()){

		if( ! isset( $args['where_meta'] ) ){
			$args['where_meta'] = array();
		}
		$args['where_meta']['relation'] = 'AND';
		$args['where_meta'][] = array(
			'meta_key'   => '_manual_order',
			'meta_value' => 'true',
			'operator'   => '=',
		);

		return $args;
	}
}