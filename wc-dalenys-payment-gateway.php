<?php

namespace Dalenys;

use WC_Payment_Gateways;

/**
 * Plugin Name: Payment Gateway through Dalenys
 * Description: Enable your customer to pay for your products through Dalenys.
 * Author: OnePix
 * Author URI: https://onepix.net
 * Version: 1.0.1
 * Text Domain: wc-dalenys-payment-gateway
 * Domain Path: /languages
 */
class Main {
	private static Main $instance;

	private function __construct() {
		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_dalenys_gateway_class' ] );
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );

		// WooFunnels support
		add_filter( 'wfocu_wc_get_supported_gateways', [ $this, 'wfocu_wc_get_supported_gateways' ], 10 );
		add_action( 'wc_ajax_wfocu_front_handle_dalenys_payments', [ $this, 'wfocu_process_charge' ], 10 );
	}

	public static function getPluginSource( $path = false ): string {
		if ( $path === true ) {
			return plugin_dir_path( __FILE__ );
		} else {
			return plugin_dir_url( __FILE__ );
		}
	}

	public static function getInstance(): Main {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function add_dalenys_gateway_class( $gateways ): array {
		$gateways[] = 'WC_Dalenys_Gateway';

		return $gateways;
	}

	public function plugins_loaded() {
		require_once 'includes/class-wc-dalenys-payment-gateway.php';
	}

	public function rest_api_init() {
		register_rest_route( 'dalenys/v1', '/webhook/', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'wc_dalenys_webhook' ],
			'permission_callback' => '__return_true'
		] );
	}

	public function wc_dalenys_webhook( $request ) {

		$payment_gateway_id = 'dalenys';
		$payment_gateways   = WC_Payment_Gateways::instance();
		$payment_gateway    = $payment_gateways->payment_gateways()[ $payment_gateway_id ];
		$payment_gateway->lets_log( '111' );
		$dalenys_response_data = $request->get_params();

		$payment_gateway->lets_log( [
			'response_params' => $dalenys_response_data
		] );

		$order_id = $dalenys_response_data['ORDERID'];
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				if ( $dalenys_response_data['EXECCODE'] == '0000' ) {
					$meta_input = [];
					foreach ( $dalenys_response_data as $key => $value ) {
						$meta_input[ 'dalenys_' . strtolower( $key ) ] = $value;
					}
					if ( $meta_input ) {
						$order_metas = [
							'ID'         => (int) $order_id,
							'meta_input' => $meta_input,
						];
						wp_update_post( wp_slash( $order_metas ) );
					}
					$order->add_order_note( __( 'Order is paid via Dalenys.', 'wc-dalenys-payment-gateway' ), 1 );
					$order->payment_complete( $dalenys_response_data['TRANSACTIONID'] );
					$thank_you_page = $order->get_checkout_order_received_url();
					if ( function_exists( 'WFOCU_Core' ) && WFOCU_Core()->data->is_funnel_exists() ) {
						$get_offer      = WFOCU_Core()->offers->get_the_first_offer();
						$thank_you_page = WFOCU_Core()->public->get_the_upsell_url( $get_offer );
					}
					wp_safe_redirect( $thank_you_page );
				} else {
					$response_message = $dalenys_response_data['MESSAGE'];
					wp_redirect( add_query_arg( 'dalenysResponseMessage', $response_message, wc_get_checkout_url() ) );
				}
			}
		}
		echo 'OK';
		die;
	}

	public function wfocu_wc_get_supported_gateways( $gateways ) {
		$gateways['dalenys'] = 'WFOCU_Gateway_Integration_Dalenys';

		return $gateways;
	}

	public function wfocu_process_charge() {
		if ( class_exists( 'WFOCU_Gateways' ) ) {
			WFOCU_Gateways::get_instance()->get_integration( 'dalenys' )->process_charge();
		}
	}
}

$GLOBALS['Dalenys\Main'] = Main::getInstance();
