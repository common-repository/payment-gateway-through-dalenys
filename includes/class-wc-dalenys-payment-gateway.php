<?php

use Dalenys\Main;

class WC_Dalenys_Gateway extends WC_Payment_Gateway {

	public string $api_key_id;
	public string $api_key;
	public string $identifier;
	public string $api_secret_key;
	public string $display_mode;
	public string $test_mode;
	public string $url;
	public bool $logging;

	public function __construct() {
		$this->id                 = 'dalenys';
		$this->method_title       = esc_html__( 'Dalenys Payment Gateway', 'wc-dalenys-payment-gateway' );
		$this->method_description = esc_html__( 'Allow your customers to pay for your products through Dalenys.', 'wc-dalenys-payment-gateway' ) . '<br>
															Redirect URL after 3D secure authentication: <strong>' . get_home_url() . '/wp-json/dalenys/v1/webhook/</strong><br>
															You can set it in your account settings and you will be able to retrieve the transaction final result.';
		$this->supports           = [
			'products',
		];

		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		$this->title          = $this->get_option( 'title' );
		$this->icon           = Main::getPluginSource() . 'assets/images/dalenys_navy.svg';
		$this->description    = $this->get_option( 'description' );
		$this->api_key_id     = $this->get_option( 'api_key_id' );
		$this->api_key        = $this->get_option( 'api_key' );
		$this->identifier     = $this->get_option( 'identifier' );
		$this->api_secret_key = $this->get_option( 'api_secret_key' );
		$this->display_mode   = $this->get_option( 'display_mode' );
		$this->test_mode      = 'yes' === $this->get_option( 'test_mode' );
		$this->url            = $this->test_mode ? 'https://secure-test.dalenys.com/front/service/rest/process' : 'https://secure.dalenys.com/front/service/rest/process';
		$this->logging        = $this->get_option( 'logging' );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_action( 'wp_footer', [ $this, 'dalenys_3ds_form_wrap' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'show_payment_error' ] );

	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled'     => [
				'title'       => esc_html__( 'Enable/Disable', 'wc-dalenys-payment-gateway' ),
				'label'       => esc_html__( 'Enable Dalenys Payment Gateway', 'wc-dalenys-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'title'       => [
				'title'       => esc_html__( 'Title', 'wc-dalenys-payment-gateway' ),
				'type'        => 'text',
				'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'wc-dalenys-payment-gateway' ),
				'default'     => esc_html__( 'Pay with Dalenys', 'wc-dalenys-payment-gateway' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => esc_html__( 'Description', 'wc-dalenys-payment-gateway' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'wc-dalenys-payment-gateway' ),
				'default'     => esc_html__( 'Pay with your credit card via Dalenys payment gateway.', 'wc-dalenys-payment-gateway' ),
			],
			'test_mode'   => [
				'title'       => esc_html__( 'Test Mode', 'wc-dalenys-payment-gateway' ),
				'label'       => esc_html__( 'Enable/Disable', 'wc-dalenys-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes'
			],
			'api_key_id'  => [
				'title'   => esc_html__( 'API Key ID', 'wc-dalenys-payment-gateway' ),
				'type'    => 'text',
				'default' => '',
			],

			'api_key' => [
				'title'   => esc_html__( 'API Key', 'wc-dalenys-payment-gateway' ),
				'type'    => 'text',
				'default' => '',
			],

			'identifier' => [
				'title'   => esc_html__( 'Identifier', 'wc-dalenys-payment-gateway' ),
				'type'    => 'text',
				'default' => '',
			],

			'api_secret_key' => [
				'title'   => esc_html__( 'API Secret key', 'wc-dalenys-payment-gateway' ),
				'type'    => 'text',
				'default' => '',
			],

			'display_mode' => [
				'title'       => esc_html__( '3DS Requestor Challenge Indicator', 'wc-dalenys-payment-gateway' ),
				'description' => __( '<strong>sca</strong> - ask for a strong authentication;<br>
										 <strong>frictionless</strong> - ask for a frictionless authentication;<br>
										 <strong>nopref</strong> - default value when absent, the decision will be made by Dalenys;<br>
										 <strong>scamandate</strong> - strong authentication required by regulation.', 'wc-dalenys-payment-gateway' ),
				'type'        => 'select',
				'options'     => [
					'sca'          => 'sca',
					'frictionless' => 'frictionless',
					'nopref'       => 'nopref',
					'scamandate'   => 'scamandate',
				],
			],

			'logging' => [
				'title'       => esc_html__( 'Enable logging', 'wc-dalenys-payment-gateway' ),
				'label'       => esc_html__( 'Enable/Disable', 'wc-dalenys-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
		];
	}

	public function payment_fields() {
		echo '<p>' . esc_html( $this->description ) . '</p>';
		do_action( 'woocommerce_credit_card_form_start', $this->id );
		require_once Main::getPluginSource( true ) . 'template-parts/credit-card-fields.php';
		do_action( 'woocommerce_credit_card_form_end', $this->id );
	}

	public function payment_scripts() {
		if ( ! is_checkout() || 'no' === $this->enabled ) {
			return;
		}
		wp_enqueue_script(
			'dalenys-hosted-fields',
			Main::getPluginSource() . 'assets/js/hosted-fields.min.js',
			[ 'jquery' ],
			filemtime( Main::getPluginSource( true ) . 'assets/js/hosted-fields.min.js' )
		);
		wp_enqueue_script(
			'dalenys-scripts',
			Main::getPluginSource() . 'assets/js/scripts.js',
			[ 'jquery', 'dalenys-hosted-fields' ],
			filemtime( Main::getPluginSource( true ) . 'assets/js/scripts.js' )
		);
		wp_localize_script( 'dalenys-scripts', 'DalenysAjax', [
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'api_key_id' => $this->api_key_id,
			'api_key'    => $this->api_key
		] );
		wp_enqueue_style(
			'dalenys-styles',
			Main::getPluginSource() . 'assets/css/styles.css',
			[],
			filemtime( Main::getPluginSource() . 'assets/css/styles.css' )
		);
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$token = sanitize_text_field( $_POST['hf-token'] );
		update_post_meta( $order_id, 'dalenys_token', $token );

		$card_brand = sanitize_text_field( $_POST['selected-brand'] );
		update_post_meta( $order_id, 'dalenys_selected_brand', $card_brand );

		$cardholder = sanitize_text_field( $_POST['dalenys-holder'] );
		update_post_meta( $order_id, 'dalenys_cardholder', $cardholder );

		$total          = $order->get_total() * 100;
		$thank_you_page = $this->get_return_url( $order );

		$url = $this->url;

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
		} else {
			$user_id = sanitize_text_field( $_POST['billing_first_name'] ) . sanitize_text_field( $_POST['billing_last_name'] );
		}

		$identifier      = $this->identifier;
		$order           = wc_get_order( $order_id );
		$billing_address = $order->get_billing_address_1();
		if ( $order->get_billing_address_2() ) {
			$billing_address .= ' ' . $order->get_billing_address_2();
		}
		$billing_city       = $order->get_billing_city();
		$billing_postcode   = $order->get_billing_postcode();
		$billing_country    = $order->get_billing_country();
		$billing_phone      = $order->get_billing_phone();
		$billing_phone      = str_replace( ' ', '', $billing_phone );
		$billing_phone      = str_replace( '-', '', $billing_phone );
		$billing_phone      = str_replace( '(', '', $billing_phone );
		$billing_phone      = str_replace( ')', '', $billing_phone );
		$phone_first_symbol = substr( $billing_phone, 0, 1 );
		if ( $phone_first_symbol != '+' ) {
			$billing_phone = '+' . $billing_phone;
		}
		$shipping_address = ( $order->get_shipping_address_1() ) ? $order->get_shipping_address_1() : $billing_address;
		if ( $order->get_shipping_address_1() && $order->get_shipping_address_2() ) {
			$shipping_address .= ' ' . $order->get_shipping_address_2();
		}
		$shipping_city     = ( $order->get_shipping_city() ) ? $order->get_shipping_city() : $billing_city;
		$shipping_postcode = ( $order->get_shipping_postcode() ) ? $order->get_shipping_postcode() : $billing_postcode;
		$shipping_country  = ( $order->get_shipping_country() ) ? $order->get_shipping_country() : $billing_country;

		$payment_params = [
			'AMOUNT'                       => $total,
			'CARDFULLNAME'                 => $cardholder,
			'CLIENTEMAIL'                  => sanitize_email( $_POST['billing_email'] ),
			'CLIENTIDENT'                  => $user_id,
			'CLIENTIP'                     => WC_Geolocation::get_ip_address(),
			'CLIENTREFERRER'               => $thank_you_page,
			'CLIENTUSERAGENT'              => wc_get_user_agent(),
			'DESCRIPTION'                  => 'Order #' . $order_id,
			'HFTOKEN'                      => $token,
			'IDENTIFIER'                   => $identifier,
			'OPERATIONTYPE'                => 'payment',
			'ORDERID'                      => $order_id,
			'VERSION'                      => '3.0',
			'3DSECUREAUTHENTICATIONAMOUNT' => $total,
			'3DSECUREPREFERENCE'           => $this->display_mode,
			'BILLINGADDRESS'               => $billing_address,
			'BILLINGCITY'                  => $billing_city,
			'BILLINGCOUNTRY'               => $billing_country,
			'BILLINGPOSTALCODE'            => $billing_postcode,
			'MOBILEPHONE'                  => $billing_phone,
			'3DSECUREDISPLAYMODE'          => 'raw',
			'CREATEALIAS'                  => true,
			'ALIASMODE'                    => 'subscription',
			'SHIPTOADDRESSTYPE'            => 'billing',
			'SHIPTOADDRESS'                => $shipping_address,
			'SHIPTOCITY'                   => $shipping_city,
			'SHIPTOCOUNTRY'                => $shipping_country,
			'SHIPTOPOSTALCODE'             => $shipping_postcode,
		];

		$hash_string            = $this->get_hash_string( $payment_params );
		$hash                   = hash( 'sha256', $hash_string );
		$payment_params['HASH'] = $hash;

		$payment_data = [
			'method' => 'payment',
			'params' => $payment_params,
		];

		$this->lets_log( [
			'request_params' => $payment_data
		] );

		$payment_request = wp_remote_post( $url, [
			'timeout' => 20,
			'body'    => $payment_data,
		] );

		$response = json_decode( $payment_request['body'], true );

		$this->lets_log( [
			'response' => $response
		] );

		if ( $response['EXECCODE'] === "0000" ) {

			if ( isset( $response['SCHEMETRANSACTIONID'] ) ) {
				update_post_meta( $order_id, 'dalenys_SCHEMETRANSACTIONID', $response['SCHEMETRANSACTIONID'] );
			}
			$meta_input = [];
			foreach ( $response as $key => $value ) {
				$meta_input[ 'dalenys_' . strtolower( $key ) ] = $value;
			}
			if ( $meta_input ) {
				$order_metas = [
					'ID'         => $order_id,
					'meta_input' => $meta_input,
				];
				wp_update_post( wp_slash( $order_metas ) );
			}
			$order->add_order_note( esc_html__( 'Order paid via Dalenys.', 'wc-dalenys-payment-gateway' ), 1 );
			$order->payment_complete( $response['TRANSACTIONID'] );
			WC()->cart->empty_cart();
			wp_send_json( [
				'url'      => $thank_you_page,
				'response' => $response
			] );

		} elseif ( $response['EXECCODE'] === "0001" ) {

			$redirect_url    = $response['REDIRECTURL'];
			$redirect_params = $response['REDIRECTPOSTPARAMS'];
			$params          = [];
			parse_str( $redirect_params, $params );
			$html = $this->get_3ds_form_html( $redirect_url, $params );

			wp_send_json( [
				'result' => 'redirect',
				'html'   => $html,
			] );

		} else {

			wp_send_json( [
				'result'   => 'failure',
				'messages' => '<div class="woocommerce-error">' . esc_html( $response['MESSAGE'] ) . '</div>',
				'response' => $response
			] );

		}
	}

	public function get_hash_string( $data ): string {
		$secret      = html_entity_decode( $this->api_secret_key );
		$hash_string = $secret;
		if ( is_array( $data ) && ! empty( $data ) ) {
			ksort( $data );
			foreach ( $data as $key => $value ) {
				$hash_string .= $key . '=' . $value . $secret;
			}
		}

		return $hash_string;
	}

	public function lets_log( $data ) {
		if ( $this->logging == 'yes' ) {
			wc_get_logger()->debug( print_r( $data, true ), [ 'source' => $this->id ] );
		}
	}

	private function get_3ds_form_html( $url, $params ): string {
		$html = '<form action="' . esc_url( $url ) . '" method="POST">';
		foreach ( $params as $key => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		$html .= '<input type="submit" value="Submit">';
		$html .= '</form>';

		return $html;
	}

	public function dalenys_3ds_form_wrap() {
		echo '<div id="dalenys-3ds-form-wrap" style="display: none!important;"></div>';
	}

	public function show_payment_error() {
		if ( isset( $_REQUEST['dalenysResponseMessage'] ) ) {
			$error_message = sanitize_text_field( $_REQUEST['dalenysResponseMessage'] );
			wc_add_notice( esc_html__( $error_message, 'wc-dalenys-payment-gateway' ), 'error' );
		}
	}
}
