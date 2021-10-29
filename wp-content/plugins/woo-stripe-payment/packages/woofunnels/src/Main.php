<?php


namespace PaymentPlugins\WooFunnels\Stripe;


class Main {

	public static function init() {
		if ( self::enabled() ) {
			new PaymentGateways();
		}
	}

	private static function enabled() {
		return function_exists( 'WFOCU_Core' );
	}
}