<?php

/*
  MarketPress Stripe Gateway Plugin
  Author - Aaron Edwards, Marko Miljus
 */


if ( file_exists( __DIR__ . '/../../../vendor/autoload.php' ) ) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

class MP_Gateway_Stripe extends MP_Gateway_API {

	//build
	var $build					 = 2;
	//private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
	var $plugin_name				 = 'stripe';
	//name of your gateway, for the admin side.
	var $admin_name				 = '';
	//public name of your gateway, for lists and such.
	var $public_name				 = '';
	//url for an image for your checkout method. Displayed on checkout form if set
	var $method_img_url			 = '';
	//url for an submit button image for your checkout method. Displayed on checkout form if set
	var $method_button_img_url	 = '';
	//whether or not ssl is needed for checkout page
	var $force_ssl;
	//always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
	var $ipn_url;
	//whether if this is the only enabled gateway it can skip the payment_form step
	var $skip_form				 = false;
	//api vars
	var $publishable_key, $secret_key, $currency;

	/**
	 * Gateway currencies
	 *
	 * @since 3.0
	 * @access public
	 * @var array
	 */
	var $currencies = array();

	/**
	 * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
	 */
	function on_creation() {
		//set names here to be able to translate
		$this->admin_name	 = __( 'Stripe', 'mp' );
		$this->public_name	 = __( 'Kreditkarte', 'mp' );

		$this->publishable_key	 = $this->get_setting( 'api_credentials->publishable_key' );
		$this->secret_key		 = $this->get_setting( 'api_credentials->secret_key' );
		$this->force_ssl		 = (bool) $this->get_setting( 'is_ssl' );
		$this->currency			 = $this->get_setting( 'currency', 'USD' );

		$this->currencies = array(
			"AED"	 => __( 'AED - United Arab Emirates Dirham', 'mp' ),
			"AFN"	 => __( 'AFN - Afghan Afghani*', 'mp' ),
			"ALL"	 => __( 'ALL - Albanian Lek', 'mp' ),
			"AMD"	 => __( 'AMD - Armenian Dram', 'mp' ),
			"ANG"	 => __( 'ANG - Netherlands Antillean Gulden', 'mp' ),
			"AOA"	 => __( 'AOA - Angolan Kwanza*', 'mp' ),
			"ARS"	 => __( 'ARS - Argentine Peso*', 'mp' ),
			"AUD"	 => __( 'AUD - Australian Dollar*', 'mp' ),
			"AWG"	 => __( 'AWG - Aruban Florin', 'mp' ),
			"AZN"	 => __( 'AZN - Azerbaijani Manat', 'mp' ),
			"BAM"	 => __( 'BAM - Bosnia & Herzegovina Convertible Mark', 'mp' ),
			"BBD"	 => __( 'BBD - Barbadian Dollar', 'mp' ),
			"BDT"	 => __( 'BDT - Bangladeshi Taka', 'mp' ),
			"BGN"	 => __( 'BGN - Bulgarian Lev', 'mp' ),
			"BIF"	 => __( 'BIF - Burundian Franc', 'mp' ),
			"BMD"	 => __( 'BMD - Bermudian Dollar', 'mp' ),
			"BND"	 => __( 'BND - Brunei Dollar', 'mp' ),
			"BOB"	 => __( 'BOB - Bolivian Boliviano*', 'mp' ),
			"BRL"	 => __( 'BRL - Brazilian Real*', 'mp' ),
			"BSD"	 => __( 'BSD - Bahamian Dollar', 'mp' ),
			"BWP"	 => __( 'BWP - Botswana Pula', 'mp' ),
			"BZD"	 => __( 'BZD - Belize Dollar', 'mp' ),
			"CAD"	 => __( 'CAD - Canadian Dollar*', 'mp' ),
			"CDF"	 => __( 'CDF - Congolese Franc', 'mp' ),
			"CHF"	 => __( 'CHF - Swiss Franc', 'mp' ),
			"CLP"	 => __( 'CLP - Chilean Peso*', 'mp' ),
			"CNY"	 => __( 'CNY - Chinese Renminbi Yuan', 'mp' ),
			"COP"	 => __( 'COP - Colombian Peso*', 'mp' ),
			"CRC"	 => __( 'CRC - Costa Rican Colón*', 'mp' ),
			"CVE"	 => __( 'CVE - Cape Verdean Escudo*', 'mp' ),
			"CZK"	 => __( 'CZK - Czech Koruna*', 'mp' ),
			"DJF"	 => __( 'DJF - Djiboutian Franc*', 'mp' ),
			"DKK"	 => __( 'DKK - Danish Krone', 'mp' ),
			"DOP"	 => __( 'DOP - Dominican Peso', 'mp' ),
			"DZD"	 => __( 'DZD - Algerian Dinar', 'mp' ),
			"EEK"	 => __( 'EEK - Estonian Kroon*', 'mp' ),
			"EGP"	 => __( 'EGP - Egyptian Pound', 'mp' ),
			"ETB"	 => __( 'ETB - Ethiopian Birr', 'mp' ),
			"EUR"	 => __( 'EUR - Euro', 'mp' ),
			"FJD"	 => __( 'FJD - Fijian Dollar', 'mp' ),
			"FKP"	 => __( 'FKP - Falkland Islands Pound*', 'mp' ),
			"GBP"	 => __( 'GBP - British Pound', 'mp' ),
			"GEL"	 => __( 'GEL - Georgian Lari', 'mp' ),
			"GIP"	 => __( 'GIP - Gibraltar Pound', 'mp' ),
			"GMD"	 => __( 'GMD - Gambian Dalasi', 'mp' ),
			"GNF"	 => __( 'GNF - Guinean Franc*', 'mp' ),
			"GTQ"	 => __( 'GTQ - Guatemalan Quetzal*', 'mp' ),
			"GYD"	 => __( 'GYD - Guyanese Dollar', 'mp' ),
			"HKD"	 => __( 'HKD - Hong Kong Dollar', 'mp' ),
			"HNL"	 => __( 'HNL - Honduran Lempira*', 'mp' ),
			"HRK"	 => __( 'HRK - Croatian Kuna', 'mp' ),
			"HTG"	 => __( 'HTG - Haitian Gourde', 'mp' ),
			"HUF"	 => __( 'HUF - Hungarian Forint', 'mp' ),
			"IDR"	 => __( 'IDR - Indonesian Rupiah', 'mp' ),
			"ILS"	 => __( 'ILS - Israeli New Sheqel', 'mp' ),
			"INR"	 => __( 'INR - Indian Rupee*', 'mp' ),
			"ISK"	 => __( 'ISK - Icelandic Króna', 'mp' ),
			"JMD"	 => __( 'JMD - Jamaican Dollar', 'mp' ),
			"JPY"	 => __( 'JPY - Japanese Yen', 'mp' ),
			"KES"	 => __( 'KES - Kenyan Shilling', 'mp' ),
			"KGS"	 => __( 'KGS - Kyrgyzstani Som', 'mp' ),
			"KHR"	 => __( 'KHR - Cambodian Riel', 'mp' ),
			"KMF"	 => __( 'KMF - Comorian Franc', 'mp' ),
			"KRW"	 => __( 'KRW - South Korean Won', 'mp' ),
			"KYD"	 => __( 'KYD - Cayman Islands Dollar', 'mp' ),
			"KZT"	 => __( 'KZT - Kazakhstani Tenge', 'mp' ),
			"LAK"	 => __( 'LAK - Lao Kip*', 'mp' ),
			"LBP"	 => __( 'LBP - Lebanese Pound', 'mp' ),
			"LKR"	 => __( 'LKR - Sri Lankan Rupee', 'mp' ),
			"LRD"	 => __( 'LRD - Liberian Dollar', 'mp' ),
			"LSL"	 => __( 'LSL - Lesotho Loti', 'mp' ),
			"LTL"	 => __( 'LTL - Lithuanian Litas', 'mp' ),
			"LVL"	 => __( 'LVL - Latvian Lats', 'mp' ),
			"MAD"	 => __( 'MAD - Moroccan Dirham', 'mp' ),
			"MDL"	 => __( 'MDL - Moldovan Leu', 'mp' ),
			"MGA"	 => __( 'MGA - Malagasy Ariary', 'mp' ),
			"MKD"	 => __( 'MKD - Macedonian Denar', 'mp' ),
			"MNT"	 => __( 'MNT - Mongolian Tögrög', 'mp' ),
			"MOP"	 => __( 'MOP - Macanese Pataca', 'mp' ),
			"MRO"	 => __( 'MRO - Mauritanian Ouguiya', 'mp' ),
			"MUR"	 => __( 'MUR - Mauritian Rupee*', 'mp' ),
			"MVR"	 => __( 'MVR - Maldivian Rufiyaa', 'mp' ),
			"MWK"	 => __( 'MWK - Malawian Kwacha', 'mp' ),
			"MXN"	 => __( 'MXN - Mexican Peso*', 'mp' ),
			"MYR"	 => __( 'MYR - Malaysian Ringgit', 'mp' ),
			"MZN"	 => __( 'MZN - Mozambican Metical', 'mp' ),
			"NAD"	 => __( 'NAD - Namibian Dollar', 'mp' ),
			"NGN"	 => __( 'NGN - Nigerian Naira', 'mp' ),
			"NIO"	 => __( 'NIO - Nicaraguan Córdoba*', 'mp' ),
			"NOK"	 => __( 'NOK - Norwegian Krone', 'mp' ),
			"NPR"	 => __( 'NPR - Nepalese Rupee', 'mp' ),
			"NZD"	 => __( 'NZD - New Zealand Dollar', 'mp' ),
			"PAB"	 => __( 'PAB - Panamanian Balboa*', 'mp' ),
			"PEN"	 => __( 'PEN - Peruvian Nuevo Sol*', 'mp' ),
			"PGK"	 => __( 'PGK - Papua New Guinean Kina', 'mp' ),
			"PHP"	 => __( 'PHP - Philippine Peso', 'mp' ),
			"PKR"	 => __( 'PKR - Pakistani Rupee', 'mp' ),
			"PLN"	 => __( 'PLN - Polish Złoty', 'mp' ),
			"PYG"	 => __( 'PYG - Paraguayan Guaraní*', 'mp' ),
			"QAR"	 => __( 'QAR - Qatari Riyal', 'mp' ),
			"RON"	 => __( 'RON - Romanian Leu', 'mp' ),
			"RSD"	 => __( 'RSD - Serbian Dinar', 'mp' ),
			"RUB"	 => __( 'RUB - Russian Ruble', 'mp' ),
			"RWF"	 => __( 'RWF - Rwandan Franc', 'mp' ),
			"SAR"	 => __( 'SAR - Saudi Riyal', 'mp' ),
			"SBD"	 => __( 'SBD - Solomon Islands Dollar', 'mp' ),
			"SCR"	 => __( 'SCR - Seychellois Rupee', 'mp' ),
			"SEK"	 => __( 'SEK - Swedish Krona', 'mp' ),
			"SGD"	 => __( 'SGD - Singapore Dollar', 'mp' ),
			"SHP"	 => __( 'SHP - Saint Helenian Pound*', 'mp' ),
			"SLL"	 => __( 'SLL - Sierra Leonean Leone', 'mp' ),
			"SOS"	 => __( 'SOS - Somali Shilling', 'mp' ),
			"SRD"	 => __( 'SRD - Surinamese Dollar*', 'mp' ),
			"STD"	 => __( 'STD - São Tomé and Príncipe Dobra', 'mp' ),
			"SVC"	 => __( 'SVC - Salvadoran Colón*', 'mp' ),
			"SZL"	 => __( 'SZL - Swazi Lilangeni', 'mp' ),
			"THB"	 => __( 'THB - Thai Baht', 'mp' ),
			"TJS"	 => __( 'TJS - Tajikistani Somoni', 'mp' ),
			"TOP"	 => __( 'TOP - Tongan Paʻanga', 'mp' ),
			"TRY"	 => __( 'TRY - Turkish Lira', 'mp' ),
			"TTD"	 => __( 'TTD - Trinidad and Tobago Dollar', 'mp' ),
			"TWD"	 => __( 'TWD - New Taiwan Dollar', 'mp' ),
			"TZS"	 => __( 'TZS - Tanzanian Shilling', 'mp' ),
			"UAH"	 => __( 'UAH - Ukrainian Hryvnia', 'mp' ),
			"UGX"	 => __( 'UGX - Ugandan Shilling', 'mp' ),
			"USD"	 => __( 'USD - United States Dollar', 'mp' ),
			"UYI"	 => __( 'UYI - Uruguayan Peso*', 'mp' ),
			"UZS"	 => __( 'UZS - Uzbekistani Som', 'mp' ),
			"VEF"	 => __( 'VEF - Venezuelan Bolívar*', 'mp' ),
			"VND"	 => __( 'VND - Vietnamese Đồng', 'mp' ),
			"VUV"	 => __( 'VUV - Vanuatu Vatu', 'mp' ),
			"WST"	 => __( 'WST - Samoan Tala', 'mp' ),
			"XAF"	 => __( 'XAF - Central African Cfa Franc', 'mp' ),
			"XCD"	 => __( 'XCD - East Caribbean Dollar', 'mp' ),
			"XOF"	 => __( 'XOF - West African Cfa Franc*', 'mp' ),
			"XPF"	 => __( 'XPF - Cfp Franc*', 'mp' ),
			"YER"	 => __( 'YER - Yemeni Rial', 'mp' ),
			"ZAR"	 => __( 'ZAR - South African Rand', 'mp' ),
			"ZMW"	 => __( 'ZMW - Zambian Kwacha', 'mp' ),
		);

		// IPN-Handler mit höchster Priorität registrieren (vor anderen Actions)
		// Hook temporär deaktiviert wegen Session-Problemen
		// add_action( 'wp', array( &$this, 'process_ipn_return' ), 5 );
	}


	function enqueue_scripts() {
		// Keine Scripts mehr nötig - Stripe Checkout übernimmt alles
		return;
	}

	/**
	 * Return fields you need to add to the top of the payment screen, like your credit card info fields
	 *
	 * @param array $cart. Contains the cart contents for the current blog, global cart if mp()->global_cart is true
	 * @param array $shipping_info. Contains shipping info and email in case you need it
	 */
	function payment_form($cart, $shipping_info) {
		// Kein Formular nötig - Weiterleitung zu Stripe Checkout erfolgt beim Submit
		$content = '<p>' . __('Sie werden zu Stripe weitergeleitet, um Ihre Zahlung sicher abzuschließen.', 'mp') . '</p>';
		return $content;
	}

	/**
	 * Initialize the settings metabox
	 *
	 * @since 3.0
	 * @access public
	 */
	public function init_settings_metabox() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => $this->generate_metabox_id(),
			'page_slugs'	 => array( 'store-settings-payments', 'store-settings_page_store-settings-payments' ),
			'title'			 => sprintf( __( '%s Settings', 'mp' ), $this->admin_name ),
			'option_name'	 => 'mp_settings',
			'desc'			 => __( 'Stripe ermöglicht es Ihnen, Kreditkartenzahlungen sicher zu akzeptieren. Kunden werden zu Stripe weitergeleitet, um ihre Zahlung abzuschließen, und dann zurück zu Ihrer Website geleitet. Sie benötigen kein eigenes Händlerkonto. Stripe verarbeitet alle Zahlungen sicher und überweist das Geld direkt auf Ihr Bankkonto.', 'mp' ),
			'conditional'	 => array(
				'name'	 => 'gateways[allowed][' . $this->plugin_name . ']',
				'value'	 => 1,
				'action' => 'show',
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'	 => $this->get_field_name( 'is_ssl' ),
			'label'	 => array( 'text' => __( 'Force SSL?', 'mp' ) ),
			'desc'	 => __( 'When in live mode Stripe recommends you have an SSL certificate setup for the site where the checkout form will be displayed.', 'mp' ),
		) );
		$creds	 = $metabox->add_field( 'complex', array(
			'name'	 => $this->get_field_name( 'api_credentials' ),
			'label'	 => array( 'text' => __( 'API Credentials', 'mp' ) ),
			'desc'	 => __( 'You must login to Stripe to <a target="_blank" href="https://manage.stripe.com/#account/apikeys">get your API credentials</a>. You can enter your test credentials, then live ones when ready.', 'mp' ),
		) );

		if ( $creds instanceof PSOURCE_Field ) {
			$creds->add_field( 'text', array(
				'name'		 => 'secret_key',
				'label'		 => array( 'text' => __( 'Secret Key', 'mp' ) ),
				'validation' => array(
					'required' => true,
				),
			) );
			$creds->add_field( 'text', array(
				'name'		 => 'publishable_key',
				'label'		 => array( 'text' => __( 'Publishable Key', 'mp' ) ),
				'validation' => array(
					'required' => true,
				),
			) );
		}

		$metabox->add_field( 'advanced_select', array(
			'name'			 => $this->get_field_name( 'currency' ),
			'label'			 => array( 'text' => __( 'Currency', 'mp' ) ),
			'multiple'		 => false,
			'width'			 => 'element',
			'options'		 => $this->currencies,
			'default_value'	 => mp_get_setting( 'currency' ),
			'desc'			 => __( 'Selecting a currency other than that used for your store may cause problems at checkout.', 'mp' ),
		) );
	}

	public function process_payment( $cart, $billing_info, $shipping_info ) {
		// Stripe initialisieren
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once mp_plugin_dir( 'vendor/autoload.php' );
		}
		\Stripe\Stripe::setApiKey( $this->secret_key );

		// Payment Info vorbereiten (wird nach erfolgreicher Zahlung aktualisiert)
		$payment_info = array(
			'gateway_public_name'  => $this->public_name,
			'gateway_private_name' => $this->admin_name,
			'gateway_plugin_name'  => $this->plugin_name,
			'method'               => __( 'Stripe Checkout', 'mp' ),
			'status'               => array( time() => __( 'Ausstehend', 'mp' ) ),
			'total'                => $cart->total(),
			'currency'             => $this->currency,
		);

		// Bestellung erstellen
		$order = new MP_Order();
		$saved_order_id = $order->save( array(
			'cart'          => $cart,
			'payment_info'  => $payment_info,
			'billing_info'  => $billing_info,
			'shipping_info' => $shipping_info,
			'paid'          => false,
		) );
		
		if ( ! $saved_order_id ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array(
					'errors' => array(
						'general' => __( 'Fehler beim Erstellen der Bestellung.', 'mp' )
					)
				) );
			} else {
				wp_die( __( 'Fehler beim Erstellen der Bestellung.', 'mp' ) );
			}
		}

		// Order neu laden um alle Eigenschaften zu aktualisieren
		$order = new MP_Order( $saved_order_id );
		$order_id = $order->get_id(); // Dies ist der post_name (Order-Key)
		
		// Line Items für Stripe Checkout vorbereiten
		$line_items = [];
		$items = $cart->get_items_as_objects();
		
		foreach ( $items as $item ) {
			$line_items[] = [
				'price_data' => [
					'currency'     => strtolower( $this->currency ),
					'product_data' => [
						'name' => $item->title(),
					],
					'unit_amount'  => $this->config_amount( $item->get_price() ),
				],
				'quantity'   => $item->qty,
			];
		}

		// Fallback: Falls keine Items gefunden, verwende Gesamtbetrag
		if ( empty( $line_items ) ) {
			error_log( 'Stripe: Keine Line Items gefunden, verwende Gesamtbetrag als Fallback' );
			$line_items[] = [
				'price_data' => [
					'currency'     => strtolower( $this->currency ),
					'product_data' => [
						'name' => __( 'Bestellung', 'mp' ) . ' #' . $order_id,
					],
					'unit_amount'  => $this->config_amount( $cart->total() ),
				],
				'quantity'   => 1,
			];
		} else {
			// Versandkosten hinzufügen, falls vorhanden
			$shipping_total = $cart->shipping_total( false );
			if ( $shipping_total > 0 ) {
				$line_items[] = [
					'price_data' => [
						'currency'     => strtolower( $this->currency ),
						'product_data' => [
							'name' => __( 'Versand', 'mp' ),
						],
						'unit_amount'  => $this->config_amount( $shipping_total ),
					],
					'quantity'   => 1,
				];
			}

			// Steuern hinzufügen, falls vorhanden
			$tax_total = $cart->tax_total( false );
			if ( $tax_total > 0 ) {
				$line_items[] = [
					'price_data' => [
						'currency'     => strtolower( $this->currency ),
						'product_data' => [
							'name' => __( 'Steuern', 'mp' ),
						],
						'unit_amount'  => $this->config_amount( $tax_total ),
					],
					'quantity'   => 1,
				];
			}
		}

		try {
			// Stripe Checkout Session erstellen
			$session = \Stripe\Checkout\Session::create([
				'payment_method_types' => ['card'],
				'line_items'           => $line_items,
				'mode'                 => 'payment',
				'success_url'          => $this->get_return_url( $order ),
				'cancel_url'           => mp_store_page_url( 'checkout', false ),
				'customer_email'       => $billing_info['email'],
				'metadata'             => [
					'order_id' => $order_id,
				],
			]);

			// Session ID in Order speichern (mit Post-ID!)
			update_post_meta( $order->ID, '_stripe_checkout_session_id', $session->id );

			// Session-ID für später speichern (wird von process_ipn_return() abgerufen)
			// Verwende die Order-Key (post_name) als Index
			$pending_orders = get_transient( 'stripe_pending_orders' );
			if ( ! is_array( $pending_orders ) ) {
				$pending_orders = [];
			}
			$pending_orders[ $order_id ] = $session->id;
		set_transient( 'stripe_pending_orders', $pending_orders, DAY_IN_SECONDS ); // 24 Stunden
			error_log( 'Stripe: Bestellung erstellt - Order-ID: ' . $order_id . ', Post-ID: ' . $order->ID . ', Session-ID: ' . $session->id );
			error_log( 'Stripe: Transient gespeichert. Pending Orders: ' . implode( ', ', array_keys( $pending_orders ) ) );

			// Bei AJAX: JSON-Antwort mit Redirect-URL
			if ( wp_doing_ajax() ) {
				wp_send_json_success( array(
					'redirect_url' => $session->url
				) );
			} else {
				// Bei normalem Request: Direkter Redirect
				wp_redirect( $session->url );
				exit;
			}

		} catch ( \Exception $e ) {
			error_log( 'Stripe Checkout Fehler: ' . $e->getMessage() );
			
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array(
					'errors' => array(
						'general' => sprintf( 
							__( 'Stripe-Fehler: %s', 'mp' ), 
							$e->getMessage() 
						)
					)
				) );
			} else {
				mp_checkout()->add_error( 
					sprintf( 
						__( 'Stripe-Fehler: %s', 'mp' ), 
						$e->getMessage() 
					) 
				);
				wp_redirect( mp_checkout_step_url( 'checkout' ) );
				exit;
			}
		}
	}


	public function get_return_url( $order ) {
		if ( ! $order || ! method_exists( $order, 'tracking_url' ) ) {
			// Fallback, falls wirklich nichts da ist
			return home_url( '/shop' );
		}
		// liefert z.B. /shop/bestellstatus/{order_key}/
		return $order->tracking_url( false );
	}

	/**
	 * INS and payment return
	 */
	function process_ipn_return() {
		error_log( '=== Stripe IPN AUFGERUFEN ===' );
		error_log( 'REQUEST_URI: ' . ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : 'N/A' ) );
		
		// Versuche erst Order-ID über WordPress Query Var zu extrahieren
		$order_id = get_query_var( 'mp_order_id' );
		error_log( 'Stripe IPN: get_query_var("mp_order_id") = ' . ( $order_id ? $order_id : 'LEER' ) );
		
		// Fallback: Order-ID aus URL extrahieren (falls query_var nicht funktioniert)
		if ( ! $order_id && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = sanitize_text_field( $_SERVER['REQUEST_URI'] );
			// URL-Pattern: /shop/bestellstatus/{order_id}/
			preg_match( '#/bestellstatus/([a-zA-Z0-9]+)/?(\?|$)#', $uri, $matches );
			if ( ! empty( $matches[1] ) ) {
				$order_id = $matches[1];
				error_log( 'Stripe IPN: Order-ID aus Regex extrahiert: ' . $order_id );
			}
		}

		// Nur verarbeiten wenn Order-ID gefunden
		if ( ! $order_id ) {
			error_log( 'Stripe IPN: KEINE Order-ID gefunden, skip' );
			return; // Keine Order-ID gefunden
		}

		error_log( 'Stripe IPN: Verarbeite Order-ID: ' . $order_id );

		// Prüfen ob diese Order gerade bezahlt wurde
		$pending_orders = get_transient( 'stripe_pending_orders' );
		error_log( 'Stripe IPN: Transient stripe_pending_orders: ' . ( $pending_orders ? implode( ', ', array_keys( $pending_orders ) ) : 'LEER' ) );
		
		if ( ! is_array( $pending_orders ) ) {
			error_log( 'Stripe IPN: Keine pending orders im Transient gefunden' );
			return;
		}

		if ( ! isset( $pending_orders[ $order_id ] ) ) {
			error_log( 'Stripe IPN: Order ' . $order_id . ' nicht in pending orders. Verfügbar: ' . implode( ', ', array_keys( $pending_orders ) ) );
			return; // Diese Order ist nicht pending
		}

		// Order laden und prüfen ob sie bereits bezahlt ist
		$order = new MP_Order( $order_id );
		
		error_log( 'Stripe IPN: Order existiert: ' . ( $order->exists() ? 'ja' : 'nein' ) . ', Status: ' . ( $order->exists() ? $order->post_status : 'N/A' ) );

		if ( ! $order->exists() ) {
			error_log( 'Stripe IPN: Order ' . $order_id . ' existiert nicht!' );
			return;
		}

		if ( $order->post_status === 'order_paid' ) {
			error_log( 'Stripe IPN: Order ' . $order_id . ' ist bereits als "paid" markiert' );
			unset( $pending_orders[ $order_id ] );
			set_transient( 'stripe_pending_orders', $pending_orders, DAY_IN_SECONDS ); // 24 Stunden
			return;
		}

		// Stripe Session-ID aus Transient holen
		$session_id = $pending_orders[ $order_id ];
		error_log( 'Stripe IPN: Session-ID: ' . $session_id );

		// Stripe initialisieren
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once mp_plugin_dir( 'vendor/autoload.php' );
		}
		\Stripe\Stripe::setApiKey( $this->secret_key );

		try {
			// Session von Stripe abrufen
			$session = \Stripe\Checkout\Session::retrieve( $session_id );

			error_log( 'Stripe IPN: Session Status: ' . $session->payment_status );

			if ( $session->payment_status === 'paid' ) {
				error_log( 'Stripe IPN: Payment Status ist PAID' );
				
				// Payment Intent Details abrufen
				$payment_intent = \Stripe\PaymentIntent::retrieve( $session->payment_intent );

				// Betrag korrekt berechnen
				$amount = $session->amount_total;
				if ( ! in_array( strtoupper( $session->currency ), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'] ) ) {
					$amount = $amount / 100;
				}

				// Payment Info aktualisieren
				$payment_info = array(
					'gateway_public_name'  => $this->public_name,
					'gateway_private_name' => $this->admin_name,
					'gateway_plugin_name'  => $this->plugin_name,
					'method'               => __( 'Stripe Checkout', 'mp' ),
					'transaction_id'       => $session->payment_intent,
					'status'               => array( time() => __( 'Bezahlt', 'mp' ) ),
					'total'                => $amount,
					'currency'             => strtoupper( $session->currency ),
					'stripe_session_id'    => $session_id,
				);

				// Bestellung als bezahlt markieren
				error_log( 'Stripe IPN: Speichere Payment Info für Order #' . $order->ID );
				update_post_meta( $order->ID, 'mp_payment_info', $payment_info );
				error_log( 'Stripe IPN: Payment Info gespeichert. Prüfe Datenbank...' );
				$saved_payment_info = get_post_meta( $order->ID, 'mp_payment_info', true );
				error_log( 'Stripe IPN: Payment Info aus DB: ' . print_r( $saved_payment_info, true ) );
				
				error_log( 'Stripe IPN: Vor Status-Änderung - Status ist: ' . $order->post_status );
				$order->change_status( 'order_paid', true );
				error_log( 'Stripe IPN: Nach change_status() - Status sollte sein: order_paid' );
				
				// Neu laden um zu prüfen
				$order_check = new MP_Order( $order_id );
				error_log( 'Stripe IPN: Nach Neu-Laden - Status ist: ' . $order_check->post_status );

				error_log( 'Stripe: Zahlung erfolgreich für Bestellung ' . $order->get_id() . ' (ID #' . $order->ID . '), Betrag: ' . $amount . ' ' . strtoupper( $session->currency ) );
				
				// Warenkorb leeren
				mp_cart()->empty_cart();
				
				// Cache leeren
				wp_cache_delete( $order->get_id(), 'mp_order' );
				wp_cache_delete( $order->ID, 'mp_order' );
				clean_post_cache( $order->ID );
				
				// Order aus pending entfernen
				unset( $pending_orders[ $order_id ] );
				set_transient( 'stripe_pending_orders', $pending_orders, HOUR_IN_SECONDS );
				
				error_log( 'Stripe IPN: Verarbeitung abgeschlossen für Order ' . $order_id );
			} else {
				error_log( 'Stripe IPN: Unerwarteter Status: ' . $session->payment_status );
			}
		} catch ( \Exception $e ) {
			error_log( 'Stripe IPN Fehler: ' . $e->getMessage() );
		}
	}

	function print_checkout_scripts() {
		// Intentionally left blank
	}

	/**
	* If zero decimal curreny selected stripe don't need to multiply by 100 to get cents.
	* Source: https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
	*/
	function config_amount( $total = false ){

		if( ! $total ) return 0;
		
		$zero_decimal_currencies = array(
			'BIF',
			'CLP',
			'DJF',
			'GNF',
			'JPY',
			'KMF',
			'KRW',
			'MGA',
			'PYG',
			'RWF',
			'VND',
			'VUV',
			'XAF',
			'XOF',
			'XPF'
		);

		return in_array( $this->currency, $zero_decimal_currencies ) ? $total : round( $total * 100 );

	}

}

//register payment gateway plugin
mp_register_gateway_plugin( 'MP_Gateway_Stripe', 'stripe', __( 'Stripe', 'mp' ) );

/**
 * Verifiziert Stripe-Zahlung für eine Order
 * Wird aufgerufen wenn die Bestellstatus-Seite geladen wird
 */
function mp_stripe_verify_payment( $order ) {
	if ( ! $order || ! $order->exists() ) {
		return;
	}
	
	// Nur für Orders mit Status "received" (noch nicht bezahlt)
	if ( $order->post_status !== 'order_received' ) {
		return;
	}
	
	$order_id = $order->get_id();
	
	// Prüfe ob diese Order in der Stripe Transient ist
	$pending_orders = get_transient( 'stripe_pending_orders' );
	if ( ! is_array( $pending_orders ) || ! isset( $pending_orders[ $order_id ] ) ) {
		return; // Keine Stripe-Zahlung pending
	}
	
	// Hole Session-ID
	$session_id = $pending_orders[ $order_id ];
	
	error_log( 'Stripe Verify: Prüfe Zahlung für Order ' . $order_id . ', Session: ' . $session_id );
	
	// Hole Stripe Gateway Settings
	$gateways = MP_Gateway_API::get_active_gateways();
	if ( ! isset( $gateways['stripe'] ) ) {
		error_log( 'Stripe Verify: Stripe Gateway nicht aktiv!' );
		return;
	}
	
	$gateway = $gateways['stripe'];
	$secret_key = $gateway->get_setting( 'api_credentials->secret_key' );
	
	if ( ! $secret_key ) {
		error_log( 'Stripe Verify: Secret Key nicht gefunden!' );
		return;
	}
	
	if ( ! class_exists( '\Stripe\Stripe' ) ) {
		require_once mp_plugin_dir( 'vendor/autoload.php' );
	}
	\Stripe\Stripe::setApiKey( $secret_key );
	
	try {
		// Session von Stripe abrufen
		$session = \Stripe\Checkout\Session::retrieve( $session_id );
		
		error_log( 'Stripe Verify: Session Status: ' . $session->payment_status );
		
		if ( $session->payment_status === 'paid' ) {
			// Betrag berechnen
			$amount = $session->amount_total;
			if ( ! in_array( strtoupper( $session->currency ), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'] ) ) {
				$amount = $amount / 100;
			}
			
			// Payment Info erstellen
			$payment_info = array(
				'gateway_public_name'  => $gateway->public_name,
				'gateway_private_name' => $gateway->admin_name,
				'gateway_plugin_name'  => $gateway->plugin_name,
				'method'               => __( 'Stripe Checkout', 'mp' ),
				'transaction_id'       => $session->payment_intent,
				'status'               => array( time() => __( 'Bezahlt', 'mp' ) ),
				'total'                => $amount,
				'currency'             => strtoupper( $session->currency ),
				'stripe_session_id'    => $session_id,
			);
			
			// Payment Info speichern
			update_post_meta( $order->ID, 'mp_payment_info', $payment_info );
			
			// Status ändern
			$order->change_status( 'order_paid', true );
			
			error_log( 'Stripe Verify: Zahlung bestätigt für Order ' . $order_id . ', Betrag: ' . $amount . ' ' . strtoupper( $session->currency ) );
			
			// Order aus pending entfernen
			unset( $pending_orders[ $order_id ] );
			set_transient( 'stripe_pending_orders', $pending_orders, DAY_IN_SECONDS );
			
			// Cache leeren
			clean_post_cache( $order->ID );
			
			// Warenkorb leeren
			mp_cart()->empty_cart();
		}
	} catch ( \Exception $e ) {
		error_log( 'Stripe Verify Fehler: ' . $e->getMessage() );
	}
}
