<?php
/**
 * Class ConvertAction
 *
 * @package WPDesk\FS\TableRate\ShippingMethod\Convert
 */

namespace WPDesk\FS\TableRate\ShippingMethod\Convert;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WPDesk\FS\TableRate\ShippingMethodSingle;
use WPDesk_Flexible_Shipping;

/**
 * Action for convert Group Shipping Method.
 */
class ConvertAction implements Hookable {
	const AJAX_ACTION = 'flexible_shipping_process_converting';
	const AJAX_NONCE = 'flexible_shipping_process_converting';

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_run_convert' ) );
	}

	/**
	 * Process convert.
	 */
	public function ajax_run_convert() {
		check_ajax_referer( self::AJAX_NONCE );

		$instance_id = filter_input( INPUT_GET, 'instance_id', FILTER_VALIDATE_INT );

		if ( ! $instance_id ) {
			return;
		}

		/** @var WPDesk_Flexible_Shipping $shipping_method */
		$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		if ( ! $shipping_method instanceof WPDesk_Flexible_Shipping ) {
			return;
		}

		/** @var WC_Shipping_Zone $zone */
		$zone = WC_Shipping_Zones::get_zone_by( 'instance_id', $instance_id );

		$shipping_methods = $shipping_method->get_shipping_methods();

		// Shipping method orders.
		$shipping_method_order = $this->get_shipping_method_order( $instance_id );
		$this->update_shipping_methods_order( $zone->get_id(), $shipping_method_order, count( $shipping_methods ) );

		foreach ( $shipping_methods as $single_shipping_method ) {
			$single_shipping_method_instance_id = $this->add_new_shipping_method_to_zone( $zone, $single_shipping_method, $shipping_method );

			$method_status = $shipping_method->is_enabled() ? isset( $single_shipping_method['method_enabled'] ) && 'yes' === $single_shipping_method['method_enabled'] : false;

			$this->set_shipping_method_status( $single_shipping_method_instance_id, $method_status, $zone );
			$this->update_shipping_method_field( $single_shipping_method_instance_id, 'method_order', ++ $shipping_method_order );

			do_action( 'flexible-shipping/group-method/converted-single-method', $single_shipping_method_instance_id, $single_shipping_method );
		}

		// Turn off current Flexible Shipping Group Method.
		$this->set_shipping_method_status( $instance_id, false, $zone );
		$shipping_method->set_as_converted();

		do_action( 'flexible-shipping/group-method/converted-method', $shipping_method );

		wp_redirect( $this->get_redirect_url_success_convert( $zone->get_id() ) );
		die();
	}

	/**
	 * @param WC_Shipping_Zone         $zone                         .
	 * @param array                    $old_shipping_method_settings .
	 * @param WPDesk_Flexible_Shipping $old_shipping_method          .
	 *
	 * @return int
	 */
	private function add_new_shipping_method_to_zone( WC_Shipping_Zone $zone, array $old_shipping_method_settings, WPDesk_Flexible_Shipping $old_shipping_method ) {
		$instance_id = $zone->add_shipping_method( ShippingMethodSingle::SHIPPING_METHOD_ID );

		/** @var ShippingMethodSingle $shipping_method . */
		$shipping_method     = WC_Shipping_Zones::get_shipping_method( $instance_id );
		$shipping_method_key = $shipping_method->get_instance_option_key();

		if ( ! is_array( $old_shipping_method_settings ) ) {
			$old_shipping_method_settings = array();
		}

		$old_shipping_method_settings['tax_status'] = $old_shipping_method->get_instance_option( 'tax_status' );

		// Add default options.
		update_option( $shipping_method_key, $old_shipping_method_settings );

		return $instance_id;
	}

	/**
	 * @param int              $instance_id .
	 * @param bool             $status      .
	 * @param WC_Shipping_Zone $zone        .
	 */
	private function set_shipping_method_status( $instance_id, $status, $zone ) {
		$status = $status ? 1 : 0;

		if ( $this->update_shipping_method_field( $instance_id, 'is_enabled', $status ) ) {
			$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

			do_action( 'woocommerce_shipping_zone_method_status_toggled', $shipping_method->instance_id, $shipping_method->id, $zone->get_id(), $status );
		}
	}

	/**
	 * @param int    $instance_id .
	 * @param string $key         .
	 * @param string $value       .
	 *
	 * @return bool|int
	 */
	private function update_shipping_method_field( $instance_id, $key, $value ) {
		global $wpdb;

		return $wpdb->update( "{$wpdb->prefix}woocommerce_shipping_zone_methods", array( $key => $value ), array( 'instance_id' => absint( $instance_id ) ) );
	}

	/**
	 * @param int $zone_id                    .
	 * @param int $method_order               .
	 * @param int $number_of_shipping_methods .
	 */
	private function update_shipping_methods_order( $zone_id, $method_order, $number_of_shipping_methods ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}woocommerce_shipping_zone_methods` SET `method_order` = `method_order`+%d WHERE `zone_id` = %d AND `method_order` > %d", $number_of_shipping_methods, $zone_id, $method_order ) );
	}

	/**
	 * @param int $instance_id .
	 *
	 * @return int
	 */
	private function get_shipping_method_order( $instance_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT `method_order` FROM `{$wpdb->prefix}woocommerce_shipping_zone_methods` WHERE `instance_id` = %d ORDER BY `method_order` ASC", $instance_id ) );
	}

	/**
	 * @param int $zone_id .
	 *
	 * @return string
	 */
	private function get_redirect_url_success_convert( $zone_id ) {
		return add_query_arg(
			array(
				'page'      => 'wc-settings',
				'tab'       => 'shipping',
				'converted' => '1',
				'zone_id'   => $zone_id,
			),
			admin_url( 'admin.php' )
		);
	}
}
