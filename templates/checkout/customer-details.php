<?php
/**
 * Customer Details Form
 * @package woo-manual-orders/Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $woo_manual_order;
?>
<div class="col-1">
    <?php $woo_manual_order->get_template( 'checkout/form-billing.php', array(
        'checkout' => $checkout,
    ) );
    ?>
</div>

<div class="col-2">
    <?php wc_get_template( 'checkout/form-shipping.php', array( 'checkout' => $checkout ) ); ?>
</div>