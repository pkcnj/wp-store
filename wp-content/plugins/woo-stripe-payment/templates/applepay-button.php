<?php
/**
 * @version 3.3.5
 * @package Stripe/Templates
 */
?>
<button class="apple-pay-button <?php echo $style ?>"
        style="<?php echo '-apple-pay-button-style: ' . $button_type . '; -apple-pay-button-type:' . apply_filters( 'wc_stripe_applepay_button_type', $type ) ?>"></button>