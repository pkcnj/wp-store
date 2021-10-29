<?php


namespace PaymentPlugins\Blocks\Stripe\Payments;


class KlarnaPayment extends AbstractStripeLocalPayment {

	protected $name = 'stripe_klarna';

	public function get_payment_method_script_handles() {
		wp_enqueue_script( 'wc-stripe-klarna', 'https://x.klarnacdn.net/kp/lib/v1/api.js', array(), null );

		return parent::get_payment_method_script_handles();
	}

	public function get_payment_method_data() {
		return wp_parse_args( array(
			'categories'     => $this->payment_method->get_payment_categories(),
			'requiredParams' => $this->payment_method->get_required_parameters()
		), parent::get_payment_method_data() );
	}
}