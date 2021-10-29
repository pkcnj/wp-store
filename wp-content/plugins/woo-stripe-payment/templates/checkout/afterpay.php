<?php
/**
 * @version 3.3.4
 *
 * @var WC_Payment_Gateway_Stripe_Local_Payment $gateway
 */
?>
<div id="wc_stripe_local_payment_<?php echo $gateway->id ?>" data-active="<?php echo $gateway->is_local_payment_available() ?>">
    <div class="wc-stripe-afterpay__offsite">
        <img src="<?php echo stripe_wc()->assets_url( 'img/offsite.svg' ) ?>"/>
        <p><?php printf( __( 'After clicking "%s", you will be redirected to Afterpay to complete your purchase securely.', 'woo-stripe-payment' ), $gateway->order_button_text ) ?></p>
    </div>
</div>