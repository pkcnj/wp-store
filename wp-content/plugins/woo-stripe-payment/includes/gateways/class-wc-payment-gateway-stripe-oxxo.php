<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WC_Payment_Gateway_Stripe_Local_Payment' ) ) {
	return;
}

/**
 *
 * @package Stripe/Gateways
 * @author PaymentPlugins
 *
 */
class WC_Payment_Gateway_Stripe_OXXO extends WC_Payment_Gateway_Stripe_Local_Payment {

	protected $payment_method_type = 'oxxo';

	public $synchronous = false;

	use WC_Stripe_Local_Payment_Intent_Trait;

	public function __construct() {
		$this->local_payment_type = 'oxxo';
		$this->currencies         = array( 'MXN' );
		$this->countries          = array( 'MX' );
		$this->id                 = 'stripe_oxxo';
		$this->tab_title          = __( 'OXXO', 'woo-stripe-payment' );
		$this->method_title       = __( 'OXXO', 'woo-stripe-payment' );
		$this->method_description = __( 'OXXO gateway that integrates with your Stripe account.', 'woo-stripe-payment' );
		$this->icon               = stripe_wc()->assets_url( 'img/oxxo.svg' );
		parent::__construct();
	}
}
