<?php
add_action( 'wp_ajax_mp_update_currency', array( 'MP_Store_Settings_General', 'ajax_mp_update_currency' ) );

class MP_Store_Settings_General {

	/**
	 * Refers to a single instance of the class
	 *
	 * @since 1.0
	 * @access private
	 * @var object
	 */
	private static $_instance = null;

	/**
	 * Gets the single instance of the class
	 *
	 * @since 1.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new MP_Store_Settings_General();
		}
		return self::$_instance;
	}

	/**
	 * Gets an updated currency symbol based upon a given currency code
	 *
	 * @since 1.0
	 * @access public
	 * @action wp_ajax_mp_update_currency
	 */
	public static function ajax_mp_update_currency() {
		if ( check_ajax_referer( 'mp_update_currency', 'nonce', false ) ) {
			$currency = mp_format_currency( mp_get_get_value( 'currency' ) );
			wp_send_json_success( $currency );
		}

		wp_send_json_error();
	}

	/**
	 * Constructor function
	 *
	 * @since 1.0
	 * @access private
	 */
	private function __construct() {
		add_action( 'psource_field/print_scripts/base_country', array( &$this, 'update_states_dropdown' ) );
		add_action( 'psource_field/print_scripts/currency', array( &$this, 'update_currency_symbol' ) );
		add_action( 'psource_metabox/after_settings_metabox_saved', array( &$this, 'update_product_post_type' ) );
		add_action( 'init', array( &$this, 'init_metaboxes' ) );

		add_filter( 'psource_field/format_value/tax[rate]', array( &$this, 'format_tax_rate_value' ), 10, 2 );
		add_filter( 'psource_field/sanitize_for_db/tax[rate]', array( &$this, 'save_tax_rate_value' ), 10, 3 );

		foreach ( mp()->provinces['CA'] as $key => $value ) {
			add_filter( 'psource_field/format_value/tax[canada_rate][' . $key . ']', array( &$this, 'format_tax_rate_value' ), 10, 2 );
			add_filter( 'psource_field/sanitize_for_db/tax[canada_rate][' . $key . ']', array( &$this, 'save_tax_rate_value' ), 10, 3 );
		}
	}

	/**
	 * Initialize metaboxes
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_metaboxes() {
		$this->init_legal_settings(); 
		$this->init_withdrawal_settings();
		$this->init_location_settings();
		$this->init_tax_settings();
		if( ! is_multisite() || ! mp_cart()->is_global ) $this->init_currency_settings();
		$this->init_digital_settings();
		$this->init_download_settings();
		$this->init_misc_settings();
		$this->init_advanced_settings();
	}

	/**
	 * Update the product post type
	 *
	 * @since 1.0
	 * @access public
	 * @action psource_metabox/settings_metabox_saved
	 * @uses $wpdb
	 */
	public function update_product_post_type( $metabox ) {
		global $wpdb;

		if ( $metabox->args[ 'id' ] != 'mp-settings-general-advanced-settings' ) {
			return;
		}

		$new_product_post_type = mp_get_setting( 'product_post_type' );
		$old_product_post_type = $new_product_post_type == 'mp_product' ? 'product' : 'mp_product';

		// Check if there is at least 1 product with the old post type
		$check = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_type = '{$old_product_post_type}'", ARRAY_A );
		if ( null === $check ) {
			return;
		}

		$wpdb->update( $wpdb->posts, array( 'post_type' => $new_product_post_type ), array( 'post_type' => $old_product_post_type ) );
		update_option( 'mp_flush_rewrites', 1 );
	}

	/**
	 * Formats the tax rate value from decimal to percentage
	 *
	 * @since 1.0
	 * @access public
	 * @filter psource_field/get_value
	 * @return string
	 */
	public function format_tax_rate_value( $value, $field ) {
		$value = (float) $value;
		// Wenn der Wert größer als 1 ist, wurde vermutlich ein Prozentwert gespeichert (z.B. 19)
		if ( $value > 1 ) {
			$value = $value / 100;
		}
		return $value * 100;
	}

	/**
	 * Formats the tax rate value from percentage to decimal prior to saving to db
	 *
	 * @since 1.0
	 * @access public
	 * @filter psource_field/sanitize_for_db
	 * @return string
	 */
	public function save_tax_rate_value( $value, $post_id, $field ) {
		// Wenn der Wert größer als 1 ist, wurde vermutlich ein Prozentwert eingegeben (z.B. 19 für 19%)
		if ( $value > 1 ) {
			return $value / 100;
		}
		// Wenn der Wert zwischen 0 und 1 ist, ist es schon ein Dezimalwert (z.B. 0.19)
		return (float) $value;
	}

	/**
	 * Prints javascript for updating the currency symbol when user updates the currency value
	 *
	 * @since 1.0
	 * @access public
	 * @action psource_field/print_scripts/currency
	 */
	public function update_currency_symbol( $field ) {
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				var $currency = $( 'select[name="currency"]' );

				$currency.on( 'change', function( e ) {
					var data = [
						{
							"name": "currency",
							"value": $(this).val()
						}, {
							"name": "action",
							"value": "mp_update_currency"
						}, {
							"name": "nonce",
							"value": "<?php echo wp_create_nonce( 'mp_update_currency' ); ?>"
						}
					];

					$currency.prop( 'disabled', true ).isWorking( true );

					$.get( ajaxurl, $.param( data ) ).done( function( resp ) {
						$currency.prop( 'disabled', false ).isWorking( false );

						if ( resp.success ) {
							$( '.mp-currency-symbol' ).html( resp.data );
						}
					} );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Prints javascript for updating the base_province dropdown when user updates the base_country value
	 *
	 * @since 1.0
	 * @access public
	 * @action psource_field/print_scripts/base_country
	 */
	public function update_states_dropdown( $field ) {
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				var $country = $( 'select[name="base_country"]' ),
					$state = $( 'select[name="base_province"]' );

				$country.on( 'change', function() {
					var data = {
						country: $country.val(),
						action: "mp_update_states_dropdown"
					};

					$country.prop( 'disabled', true ).isWorking( true );
					$state.prop( 'disabled', true );

					$.post( ajaxurl, data ).done( function( resp ) {
						$country.prop( 'disabled', false ).isWorking( false );
						$state.prop( 'disabled', false );

						if ( resp.success ) {
							$state.html( resp.data.states );
							$state.trigger( 'change' );
						}
					} );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Init advanced settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_advanced_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => 'mp-settings-general-advanced-settings',
			'page_slugs'	 => array( 'store-settings-general' ),
			'title'			 => __( 'Erweiterte Einstellungen', 'mp' ),
			'option_name'	 => 'mp_settings',
		) );

		$metabox->add_field( 'radio_group', array(
			'name'			 => 'product_post_type',
			'label'			 => array( 'text' => __( 'Produkt-Post-Typ ändern', 'mp' ) ),
			'desc'		 => __( 'Wenn du Konflikte mit anderen E-Commerce-Plugins hast, ändere diese Einstellung. Dies ändert den internen Post-Typ aller deiner Produkte. <strong>Bitte beachte, dass das Ändern dieser Option 3rd-Party-Themes oder -Plugins beeinträchtigen kann.</strong>', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
			'default_value'	 => 'product',
			'orientation'	 => 'horizontal',
			'options'		 => array(
				'product'	 => __( 'Produkt (standard)', 'mp' ),
				'mp_product'	 => 'mp_product',
			),
		) );

		// Uninstall
		if ( ! is_multisite() || is_network_admin() ) {
			$metabox->add_field( 'checkbox', array(
				'name'    => 'advanced[delete_on_uninstall]',
				'label'   => array( 'text' => __( 'Daten beim Deinstallieren löschen?', 'mp' ) ),
				'desc'    => __( 'Wenn aktiviert, werden beim Deinstallieren des Plugins alle MarketPress-Datenbanktabellen und Einstellungen unwiderruflich gelöscht.', 'mp' ),
				'message' => __( 'Ja, alle Daten beim Deinstallieren entfernen', 'mp' ),
				'default_value' => false,
			) );
		}
	}

	/**
	 * Init download settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_download_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => 'mp-settings-general-downloads',
			'page_slugs'	 => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'			 => __( 'Download Einstellungen', 'mp' ),
			'option_name'	 => 'mp_settings',
		) );
		$metabox->add_field( 'text', array(
			'name'		 => 'max_downloads',
			'label'		 => array( 'text' => __( 'Maximale Downloads', 'mp' ) ),
			'desc'		 => __( 'Wie oft darf ein Kunde eine Datei herunterladen, die er gekauft hat? (Es ist am besten, dies höher als eins zu setzen, falls es Probleme beim Herunterladen gibt)', 'mp' ),
			'style'		 => 'width:50px;',
			'validation' => array(
				'required'	 => true,
				'digits'	 => true,
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'	 => 'use_alt_download_method',
			'label'	 => array( 'text' => __( 'Alternative Download-Methode verwenden?', 'mp' ) ),
			'desc'	 => __( 'Wenn du Probleme beim Herunterladen großer Dateien hast und mit deinem Hosting-Provider zusammengearbeitet hast, um deine Speicherlimits zu erhöhen, versuche dies zu aktivieren - beachte jedoch, dass es nicht so sicher ist!', 'mp' ),
		) );
	}

	/**
	 * Init misc settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_misc_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => 'mp-settings-general-misc',
			'page_slugs'	 => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'			 => __( 'Verschiedene Einstellungen', 'mp' ),
			'option_name'	 => 'mp_settings',
		) );
		$metabox->add_field( 'text', array(
			'name'		 => 'inventory_threshhold',
			'label'		 => array( 'text' => __( 'Lagerwarnschwelle', 'mp' ) ),
			'desc'		 => __( 'Bei welchem niedrigen Lagerbestand möchten Sie für Produkte, für die Sie die Bestandsverfolgung aktiviert haben, gewarnt werden?', 'mp' ),
			'style'		 => 'width:50px;',
			'validation' => array(
				'required'	 => true,
				'digits'	 => true,
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'inventory_remove',
			'label'		 => array( 'text' => __( 'Ausverkaufte Produkte ausblenden?', 'mp' ) ),
			'desc'		 => __( 'Dies setzt das Produkt auf Entwurf, wenn der Lagerbestand aller Varianten aufgebraucht ist.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'force_login',
			'label'		 => array( 'text' => __( 'Login erzwingen?', 'mp' ) ),
			'desc'		 => __( 'Ob Kunden registriert und eingeloggt sein müssen, um den Checkout abzuschließen. (Nicht empfohlen: Das Aktivieren kann die Konversionen senken)', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'disable_cart',
			'label'		 => array( 'text' => __( 'Warenkorb deaktivieren?', 'mp' ) ),
			'desc'		 => __( 'Diese Option verwandelt MarketPress eher in ein Produktlisten-Plugin, deaktiviert Einkaufswagen, Checkout und Bestellverwaltung. Dies ist nützlich, wenn Sie einfach nur Artikel auflisten möchten, die Sie irgendwo anders kaufen können, und optional die "Jetzt kaufen"-Schaltflächen auf eine externe Website verlinken. Einige Beispiele sind ein Autohaus oder das Verlinken zu Songs/Alben in iTunes oder das Verlinken zu Produkten auf einer anderen Website mit Ihren eigenen Affiliate-Links.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'       => 'show_orders',
			'label'      => array( 'text' => __( 'Admin-Bestellseite anzeigen?', 'mp' ) ),
			'desc'		 => __( 'Wenn deaktiviert, wird Ihre Admin-Bestellseite ausgeblendet', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
			'conditional' => array(
				'name'   => 'disable_cart',
				'value'  => '1',
				'action' => 'show',
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'disable_minicart',
			'label'		 => array( 'text' => __( 'Mini-Warenkorb deaktivieren?', 'mp' ) ),
			'desc'		 => __( 'Diese Option blendet den schwebenden Mini-Warenkorb in der oberen rechten Ecke aus.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'          => 'show_product_image',
			'label'         => array( 'text' => __( 'Produktbild im Mini-Warenkorb anzeigen?', 'mp' ) ),
			'desc'          => __( 'Möchtest Du das Produktbild im schwebenden Mini-Warenkorb anzeigen?', 'mp' ),
			'message'       => __( 'Ja', 'mp' ),
			'default_value' => true,
		) );
		$metabox->add_field( 'checkbox', array(
			'name'          => 'show_product_qty',
			'label'         => array( 'text' => __( 'Produktmenge im Mini-Warenkorb anzeigen?', 'mp' ) ),
			'desc'          => __( 'Möchtest Du die Produktmenge im schwebenden Mini-Warenkorb anzeigen?', 'mp' ),
			'message'       => __( 'Ja', 'mp' ),
			'default_value' => true,
		) );
		$metabox->add_field( 'checkbox', array(
			'name'          => 'show_product_price',
			'label'         => array( 'text' => __( 'Produktpreis im Mini-Warenkorb anzeigen?', 'mp' ) ),
			'desc'          => __( 'Möchtest Du den Produktpreis im schwebenden Mini-Warenkorb anzeigen?', 'mp' ),
			'message'       => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'ga_ecommerce',
			'label'			 => array( 'text' => __( 'Google Analytics Ecommerce Tracking', 'mp' ) ),
			'desc'			 => __( 'Wenn Du bereits Google Analytics für Deine Webseite verwendest, kannst Du detaillierte E-Commerce-Informationen verfolgen, indem Du diese Einstellung aktivierst. Wähle, ob Du den neuen asynchronen oder den alten Tracking-Code verwenden möchtest. Bevor Google Analytics E-Commerce-Aktivitäten für Deine Website melden kann, musst Du das E-Commerce-Tracking auf der Profilseite Deiner Website aktivieren. Beachte auch, dass einige Gateways die Empfangsseite nicht zuverlässig anzeigen, sodass die Verfolgung in diesen Fällen möglicherweise nicht genau ist. Es wird empfohlen, das PayPal-Gateway für die genauesten Daten zu verwenden. <a target="_blank" href="http://analytics.blogspot.com/2009/05/how-to-use-ecommerce-tracking-in-google.html">Weitere Informationen &raquo;</a>', 'mp' ),
			'default_value'	 => 'none',
			'orientation'	 => 'horizontal',
			'options'		 => array(
				'none'		 => __( 'Keine', 'mp' ),
				'new'		 => __( 'Neu', 'mp' ),
				'old'		 => __( 'Alt', 'mp' ),
				'universal'	 => __( 'Universal', 'mp' ),
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'special_instructions',
			'label'		 => array( 'text' => __( 'Spezielle Anweisungen anzeigen?', 'mp' ) ),
			'desc'		 => __( 'Wenn dieses Feld aktiviert ist, wird auf der Versand-Checkout-Seite ein Textfeld angezeigt, in das Benutzer spezielle Anweisungen für ihre Bestellung eingeben können. Nützlich für Produktpersonalisierung usw.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
	}

	/**
	 * Init currency settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_currency_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => 'mp-settings-general-currency',
			'page_slugs'	 => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'			 => __( 'Währungseinstellungen', 'mp' ),
			'option_name'	 => 'mp_settings',
		) );

		$currencies	 = mp()->currencies;
		$options	 = array( '' => __( 'Wähle eine Währung', 'mp' ) );

		foreach ( $currencies as $key => $value ) {
			$options[ $key ] = esc_attr( $value[ 0 ] ) . ' - ' . mp_format_currency( $key );
		}

		$metabox->add_field( 'advanced_select', array(
			'name'			 => 'currency',
			'placeholder'	 => __( 'Wähle eine Währung', 'mp' ),
			'multiple'		 => false,
			'label'			 => array( 'text' => __( 'Ladenwährung', 'mp' ) ),
			'options'		 => $options,
			'width'			 => 'element',
		) );
		
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'curr_symbol_position',
			'label'			 => array( 'text' => __( 'Position des Währungssymbols', 'mp' ) ),
			'default_value'	 => '1',
			'orientation'	 => 'horizontal',
			'options'		 => array(
				'1'	 => '<span class="mp-currency-symbol">' . mp_format_currency( mp_get_setting( 'currency', 'EUR' ) ) . '</span>100',
				'2'	 => '<span class="mp-currency-symbol">' . mp_format_currency( mp_get_setting( 'currency', 'EUR' ) ) . '</span> 100',
				'3'	 => '100<span class="mp-currency-symbol">' . mp_format_currency( mp_get_setting( 'currency', 'EUR' ) ) . '</span>',
				'4'	 => '100 <span class="mp-currency-symbol">' . mp_format_currency( mp_get_setting( 'currency', 'EUR' ) ) . '</span>',
			),
		) );
		
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'price_format',
			'label'			 => array( 'text' => __( 'Preisformat', 'mp' ) ),
			'default_value'	 => 'en',
			'orientation'	 => 'horizontal',
			'options'		 => array(
				'en'	 => '1,123.45',
				'eu'	 => '1.123,45',
				'frc'	 => '1 123,45',
				'frd'	 => '1 123.45',
			),
		) );
		
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'curr_decimal',
			'label'			 => array( 'text' => __( 'Dezimalstellen in Preisen anzeigen', 'mp' ) ),
			'default_value'	 => '1',
			'orientation'	 => 'horizontal',
			'options'		 => array(
				'0'	 => '100',
				'1'	 => '100.00',
			),
		) );
	}

	/**
	 * Init tax settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_tax_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => 'mp-settings-general-tax',
			'page_slugs'	 => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'			 => __( 'Steuer-Einstellungen', 'mp' ),
			'option_name'	 => 'mp_settings',
		) );
		$metabox->add_field( 'text', array(
			'name'			 => 'tax[rate]',
			'label'			 => array( 'text' => __( 'Steuersatz', 'mp' ) ),
			'after_field'	 => '%',
			'style'			 => 'width:75px',
			'default_value'=> 0.19, // <-- Standardwert als Dezimalzahl (19%)
			'validation'	 => array(
				'number' => true,
			),
			/*'conditional'	 => array(
				'name'	 => 'base_country',
				'value'	 => 'CA',
				'action' => 'hide',
			),*/
		) );

		// Create field for each canadian province
		foreach ( mp()->provinces['CA'] as $key => $label ) {
			$metabox->add_field( 'text', array(
				'name'			 => 'tax[canada_rate][' . $key . ']',
				'desc'			 => '<a target="_blank" href="http://en.wikipedia.org/wiki/Sales_taxes_in_Canada">' . __( 'Current Rates', 'mp' ) . '</a>',
				'label'			 => array( 'text' => sprintf( __( '%s Tax Rate', 'mp' ), $label ) ),
				'custom'		 => array( 'style' => 'width:75px' ),
				'after_field'	 => '%',
				'conditional'	 => array(
					'name'	 => 'base_country',
					'value'	 => 'CA',
					'action' => 'show',
				),
			) );
		}

		$metabox->add_field( 'text', array(
			'name'	 => 'tax[label]',
			'label'	 => array( 'text' => __( 'Steuerbezeichnung', 'mp' ) ),
			'style'	 => 'width:300px',
			'desc'	 => __( 'Die Bezeichnung, die für die Steuerposition im Warenkorb angezeigt wird. Steuern, MwSt., GST usw.', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'tax[tax_shipping]',
			'label'		 => array( 'text' => __( 'Steuer auf Versandkosten anwenden?', 'mp' ) ),
			'desc'		 => __( 'Bitte beachte die örtlichen Steuergesetze. In den meisten Gebieten wird Steuer auf Versandkosten erhoben.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'tax[tax_inclusive]',
			'label'		 => array( 'text' => __( 'Preise inklusive Steuer eingeben?', 'mp' ) ),
			'desc'		 => __( 'Wenn diese Option aktiviert ist, kannst Du alle Preise inklusive Steuer eingeben und anzeigen, während die Steuer insgesamt als Position im Warenkorb aufgeführt wird. Bitte beachte die örtlichen Steuergesetze.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'tax[include_tax]',
			'label'		 => array( 'text' => __( 'Preis + Steuer anzeigen?', 'mp' ) ),
			'desc'		 => __( 'Wenn diese Option aktiviert ist, wird Preis + Steuer angezeigt, z.B. wenn Dein Preis 100 und Deine Steuer 20 beträgt, wird Dein Preis 120 sein', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'tax[tax_label]',
			'label'		 => array( 'text' => __( 'Steuerbezeichnung anzeigen?', 'mp' ) ),
			'desc'		 => __( 'Wenn diese Option aktiviert ist, wird die Bezeichnung `exkl. Steuer` oder `inkl. Steuer` nach dem Preis angezeigt.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'tax[tax_digital]',
			'label'		 => array( 'text' => __( 'Steuer auf digitale Produkte anwenden?', 'mp' ) ),
			'desc'		 => __( 'Bitte beachte die örtlichen Steuergesetze. Wenn diese Option aktiviert ist und der Warenkorb nur herunterladbare Produkte enthält, werden die Steuersätze für Deinen Basisstandort verwendet.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		/*
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'tax[tax_based]',
			'label'			 => array( 'text' => __( 'Steuer basierend auf?', 'mp' ) ),
			'default_value'	 => 'store_tax',
			'orientation'	 => 'horizontal',
			'options'		 => array(
				'store_tax'	 => __( 'Steuer basierend auf dem Standort des Geschäfts anwenden', 'mp' ),
				'user_tax'	 => __( 'Steuer basierend auf dem Standort des Kunden anwenden', 'mp' ),
			),
			'conditional' => array(
				'name'   => 'tax[tax_digital]',
				'value'  => '1',
				'action' => 'show',
			),
		) );
		*/
	}
	
	/**
	 * Init digital products settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_digital_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => 'mp-settings-general-digital',
			'page_slugs'	 => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'			 => __( 'Digitale Einstellungen', 'mp' ),
			'option_name'	 => 'mp_settings',
		) );
		
		$metabox->add_field( 'checkbox', array(
			'name'		 => 'download_order_limit',
			'label'		 => array( 'text' => __( 'Limitiere digitale Produkte pro Bestellung?', 'mp' ) ),
			'desc'		 => __( 'Dies verhindert, dass mehrere Exemplare desselben herunterladbaren Produkts in den Warenkorb gelegt werden.', 'mp' ),
			'message'	 => __( 'Ja', 'mp' ),
		) );
		
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'details_collection',
			'label'			 => array( 'text' => __( 'Benutzerinformationen sammeln', 'mp' ) ),
			'default_value'	 => 'contact',
			'orientation'	 => 'horizontal',
			'options'		 => array(
				'full'		 => __( 'Vollständige Rechnungsinformationen', 'mp' ),
				'contact'		 => __( 'Nur Kontaktdaten', 'mp' ),
			),
		) );
		
	}

	/**
	 * Init location settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_location_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'			 => 'mp-settings-general-location',
			'page_slugs'	 => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'			 => __( 'Standorteinstellungen', 'mp' ),
			'option_name'	 => 'mp_settings',
		) );
		$metabox->add_field( 'advanced_select', array(
			'name'			 => 'base_country',
			'placeholder'	 => __( 'Wähle ein Land', 'mp' ),
			'multiple'		 => false,
			'label'			 => array( 'text' => __( 'Basisland', 'mp' ) ),
			'options'		 => array( '' => __( 'Wähle ein Land', 'mp' ) ) + mp_countries(),
			'width'			 => 'element',
			'default_value'  => 'DE', // Deutschland als Standard
			'validation'	 => array(
				'required' => true,
			),
		) );

		$countries_with_states = array();
		foreach ( mp_countries() as $code => $country ) {
			if( property_exists( mp(), $code.'_provinces' ) ) {
				$countries_with_states[] = $code;
			}
		}
		$states = mp_get_states( mp_get_setting( 'base_country' ) );
		$metabox->add_field( 'advanced_select', array(
			'name'			 => 'base_province',
			'placeholder'	 => __( 'Wähle ein Bundesland/Provinz/Region', 'mp' ),
			'multiple'		 => false,
			'label'			 => array( 'text' => __( 'Basis Bundesland/Provinz/Region', 'mp' ) ),
			'options'		 => $states,
			'width'			 => 'element',
			'conditional'	 => array(
				'name'	 => 'base_country',
				'value'	 => $countries_with_states,
				'action' => 'show',
			),
			'validation'	 => array(
				'required' => true,
			),
		) );

		$countries_without_postcode = array_keys( mp()->countries_no_postcode );
		$metabox->add_field( 'text', array(
			'name'			 => 'base_zip',
			'label'			 => array( 'text' => __( 'Standort Postleitzahl', 'mp' ) ),
			'style'			 => 'width:150px;',
			'custom'		 => array(
				'minlength' => 3,
			),
			'conditional'	 => array(
				'name'	 => 'base_country',
				'value'	 => $countries_without_postcode,
				'action' => 'hide',
			),
			'validation'	 => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'text', array(
			'name'		 => 'zip_label',
			'label'		 => array( 'text' => __( 'Postleitzahl/PLZ Bezeichnung', 'mp' ) ),
			'custom'	 => array(
				'style' => 'width:300px',
			),
			'validation' => array(
				'required' => true,
			),
		) );
	}

	public function init_legal_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'           => 'mp-settings-general-legal',
			'page_slugs'   => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'        => __( 'Rechtliche Informationen', 'mp' ),
			'option_name'  => 'mp_settings',
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'legal[company_name]',
			'label' => array( 'text' => __( 'Firmenname', 'mp' ) ),
			'validation' => array( 'required' => true ),
		) );
		$metabox->add_field( 'textarea', array(
			'name'  => 'legal[company_address]',
			'label' => array( 'text' => __( 'Firmenadresse', 'mp' ) ),
			'validation' => array( 'required' => true ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'legal[vat_id]',
			'label' => array( 'text' => __( 'USt-IdNr.', 'mp' ) ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'legal[tax_number]',
			'label' => array( 'text' => __( 'Steuernummer', 'mp' ) ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'legal[small_business]',
			'label'   => array( 'text' => __( 'Kleinunternehmerregelung (§19 UStG)', 'mp' ) ),
			'message' => __( 'Ich bin Kleinunternehmer gemäß §19 UStG (keine Umsatzsteuer ausgewiesen)', 'mp' ),
		) );
		$metabox->add_field( 'textarea', array(
			'name'  => 'legal[custom_note]',
			'label' => array( 'text' => __( 'Benutzerdefinierte rechtliche Hinweise', 'mp' ) ),
			'desc'  => __( 'Optional: Füge eine benutzerdefinierte Notiz für Deine Rechnungen hinzu (z.B. Kleinunternehmerregelung)', 'mp' ),
			'default_value' => __( 'Ich bin Kleinunternehmer gemäß §19 UStG (keine Umsatzsteuer ausgewiesen)', 'mp' ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'legal[invoice_prefix]',
			'label' => array( 'text' => __( 'Rechnungsnummer Präfix', 'mp' ) ),
			'desc'  => __( 'Optional: Ein Präfix für alle Rechnungsnummern, z.B. "RE-" oder "2025-".', 'mp' ),
		) );
		$metabox->add_field( 'select', array(
			'name'    => 'legal[invoice_date_format]',
			'label'   => array( 'text' => __( 'Datumsformat in Rechnungsnummer', 'mp' ) ),
			'desc'    => __( 'Wähle, ob und wie das Datum in der Rechnungsnummer erscheinen soll.', 'mp' ),
			'options' => array(
				''           => __( 'Kein Datum', 'mp' ),
				'Y'          => __( 'Jahr (z.B. 2025)', 'mp' ),
				'Ym'         => __( 'JahrMonat (z.B. 202505)', 'mp' ),
				'Ymd'        => __( 'JahrMonatTag (z.B. 20250527)', 'mp' ),
				'dmY'        => __( 'TagMonatJahr (z.B. 27052025)', 'mp' ),
			),
			'default_value' => '',
		) );

	}

	/**
	 * Init withdrawal settings
	 *
	 * @since 1.0.9
	 * @access public
	 */
	public function init_withdrawal_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-general-withdrawal',
			'page_slugs'  => array( 'store-settings', 'toplevel_page_store-settings' ),
			'title'       => __( 'Widerruf & Kundenzone', 'mp' ),
			'option_name' => 'mp_settings',
		) );

		$metabox->add_field( 'checkbox', array(
			'name'    => 'withdrawal[enabled]',
			'label'   => array( 'text' => __( 'Digitalen Widerruf aktivieren?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
			'default_value' => 1,
		) );

		$metabox->add_field( 'textarea', array(
			'name'  => 'withdrawal[policy_text]',
			'label' => array( 'text' => __( 'Widerrufsbelehrung (Kundenzone)', 'mp' ) ),
			'desc'  => __( 'Dieser Text wird in der Kundenzone auf der Bestellstatus-Seite angezeigt.', 'mp' ),
			'custom' => array( 'rows' => 6 ),
			'default_value' => __( 'Du kannst Deinen Widerruf hier digital erklaeren. Waehle Positionen, Grund und sende direkt ab. Danach siehst Du jederzeit den Status.', 'mp' ),
		) );

		$metabox->add_field( 'textarea', array(
			'name'  => 'withdrawal[reason_options]',
			'label' => array( 'text' => __( 'Widerrufsgruende (Auswahlliste)', 'mp' ) ),
			'desc'  => __( 'Eine Zeile pro Grund. Optional im Format code|Bezeichnung. Beispiel: defect|Artikel ist defekt.', 'mp' ),
			'custom' => array( 'rows' => 7 ),
			'default_value' => "defect|Artikel ist beschaedigt oder fehlerhaft\nnot_as_described|Artikel entspricht nicht der Beschreibung\nwrong_item|Falscher Artikel geliefert\ndelay|Lieferung kam zu spaet\nother|Anderer rechtlicher Widerrufsgrund",
		) );

		$metabox->add_field( 'checkbox', array(
			'name'    => 'withdrawal[allow_custom_reason]',
			'label'   => array( 'text' => __( 'Zusaetzliche Begruendung erlauben?', 'mp' ) ),
			'message' => __( 'Optionales Textfeld fuer genauere Begruendung anzeigen', 'mp' ),
			'default_value' => 1,
		) );

		$metabox->add_field( 'text', array(
			'name'  => 'withdrawal[max_reason_length]',
			'label' => array( 'text' => __( 'Maximale Laenge Begruendung', 'mp' ) ),
			'desc'  => __( 'Empfohlen: 300 Zeichen.', 'mp' ),
			'default_value' => 300,
		) );

		$metabox->add_field( 'text', array(
			'name'  => 'email[withdrawal_confirmation][subject]',
			'label' => array( 'text' => __( 'E-Mail-Betreff: Widerrufsbestätigung', 'mp' ) ),
			'default_value' => __( 'Eingangsbestätigung Widerruf (ORDERID)', 'mp' ),
		) );

		$metabox->add_field( 'textarea', array(
			'name'  => 'email[withdrawal_confirmation][text]',
			'label' => array( 'text' => __( 'E-Mail-Text: Widerrufsbestätigung', 'mp' ) ),
			'custom' => array( 'rows' => 8 ),
			'default_value' => __( "Hallo CUSTOMERNAME,\n\nwir bestätigen den Eingang Deines Widerrufs zur Bestellung ORDERID.\n\nBetroffene Positionen:\nWITHDRAWALITEMS\n\nWir bearbeiten Dein Anliegen so schnell wie möglich.", 'mp' ),
		) );
	}
}

MP_Store_Settings_General::get_instance();
