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
class WC_Payment_Gateway_Stripe_Boleto extends WC_Payment_Gateway_Stripe_Local_Payment {

	protected $payment_method_type = 'boleto';

	public $synchronous = false;

	use WC_Stripe_Local_Payment_Intent_Trait;

	public function __construct() {
		$this->local_payment_type = 'boleto';
		$this->currencies         = array( 'BRL' );
		$this->countries          = $this->limited_countries = array( 'BR' );
		$this->id                 = 'stripe_boleto';
		$this->tab_title          = __( 'Boleto', 'woo-stripe-payment' );
		$this->method_title       = __( 'Boleto', 'woo-stripe-payment' );
		$this->method_description = __( 'Boleto gateway that integrates with your Stripe account.', 'woo-stripe-payment' );
		$this->icon               = stripe_wc()->assets_url( 'img/boleto.svg' );
		parent::__construct();
		$this->template_name = 'boleto.php';
	}

	public function validate_fields() {
		$regex = '/^(\w{3}\.){2}\w{3}-\w{2}$|^(\w{11}|\w{14})$|^\w{2}\.\w{3}\.\w{3}\/\w{4}-\w{2}$/';
		if ( empty( $_POST['wc_stripe_boleto_tax_id'] ) || ! preg_match_all( $regex, $_POST['wc_stripe_boleto_tax_id'] ) ) {
			wc_add_notice( __( 'Please enter a valid CPF / CNPJ', 'woo-stripe-payment' ), 'error' );
		}
	}
}
