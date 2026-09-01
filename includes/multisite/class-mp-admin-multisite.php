<?php

class MP_Admin_Multisite {

	const NETWORK_MENU_SLUG = 'network-store-settings';
	const NETWORK_SNAPSHOT_CACHE_KEY = 'mp_network_settings_snapshot_v1';
	const NETWORK_SNAPSHOT_CLEANUP_OPTION = 'mp_network_snapshot_cleanup_settings';
	const NETWORK_SNAPSHOT_CLEANUP_CRON_HOOK = 'mp_network_snapshot_cleanup_event';
	const NETWORK_SNAPSHOT_CLEANUP_RECURRENCE = 'mp_network_snapshot_cleanup_every_custom';

	/**
	 * Refers to a single instance of the class
	 *
	 * @since 1.0
	 * @access private
	 * @var object
	 */
	private static $_instance = null;

	/**
	 * Refers to the current build of the class
	 *
	 * @since 1.0
	 * @access public
	 * @var int
	 */
	var $build = 1;

	/**
	 * Gets the single instance of the class
	 *
	 * @since 1.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new MP_Admin_Multisite();
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0
	 * @access private
	 */
	private function __construct() {
		if ( ! is_plugin_active_for_network( mp_get_plugin_slug() ) ) {
			return;
		}

		if ( is_network_admin() ) {
			add_action( 'init', array( &$this, 'init_metaboxes' ) );
			add_action( 'network_admin_menu', array( &$this, 'add_menu_items' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_styles_scripts' ) );
			add_filter( 'psource_field/after_field', array( &$this, 'display_create_page_button' ), 10, 2 );
			add_action( 'psource_field/print_scripts', array( &$this, 'create_store_page_js' ) );
		}
		add_action( 'wp_ajax_mp_index_products', array( &$this, 'index_products' ) );
		add_action( 'wp_ajax_mp_network_snapshot_controls', array( &$this, 'ajax_network_snapshot_controls' ) );
		add_filter( 'cron_schedules', array( &$this, 'register_network_snapshot_cleanup_schedule' ) );
		add_action( 'init', array( &$this, 'sync_network_snapshot_cleanup_cron' ) );
		add_action( self::NETWORK_SNAPSHOT_CLEANUP_CRON_HOOK, array( &$this, 'cron_clear_network_settings_snapshot_cache' ) );
		if ( mp_get_network_setting( 'global_cart' ) ) {
			add_filter('psource_field/before_get_value', array(&$this, 'global_currency_options'), 10, 4);
		}
		//On blog status change update blog_public status
		add_action( 'activate_blog', array( $this, 'set_blog_public_global_products' ) );
		add_action( 'make_ham_blog', array( $this, 'set_blog_public_global_products' ) );
		add_action( 'unarchive_blog', array( $this, 'set_blog_public_global_products' ) );
		add_action( 'make_undelete_blog', array( $this, 'set_blog_public_global_products' ) );
		add_action( 'activate_blog', array( $this, 'set_blog_public_global_products' ) );

		add_action( 'delete_blog', array( $this, 'unset_blog_public_global_products' ) );
		add_action( 'deactivate_blog', array( $this, 'unset_blog_public_global_products' ) );
		add_action( 'archive_blog', array( $this, 'unset_blog_public_global_products' ) );
		add_action( 'make_spam_blog', array( $this, 'unset_blog_public_global_products' ) );
	}

	/**
	 * Print network_store_page scripts
	 *
	 * When changing the network_store_page value update the product_category and
	 * product_tag slug that is shown before those fields.
	 *
	 * @since 1.0
	 * @access public
	 * @action psource_field/print_scripts/network_store_page
	 */
	public function print_network_store_page_scripts( $field ) {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				$('.mp-create-page-button').on('click', function (e) {
					e.preventDefault();

					var $this = $(this),
						$select = $this.siblings('[name="network_store_page"]');

					$this.addClass('working');

					$.getJSON($this.attr('href'), function (resp) {
						if (resp.success) {
							var selectEl = $select[0];
							var postId = String(resp.data.post_id);
							var optionText = resp.data.select2_value.indexOf('->') > -1 ? resp.data.select2_value.split('->')[1] : resp.data.select2_value;
							if (selectEl.slimSelect) {
								var newData = selectEl.slimSelect.getData().concat({ value: postId, text: optionText });
								selectEl.slimSelect.setData(newData);
								selectEl.slimSelect.setSelected(postId);
							} else {
								$select.val(postId).trigger('change');
							}
							$this.isWorking(false).replaceWith(resp.data.button_html);
							$('.mp-network-store-page-slug').html(resp.data.parent_slug);
						} else {
							alert('<?php _e( 'beim Fehler beim erstellen der Shopseite, bitte versuche es nochmal.', 'mp' ); ?>');
							$this.removeClass('working');
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Enqueue admin styles and scripts
	 *
	 * @since 1.0
	 * @access public
	 * @action admin_enqueue_scripts
	 */
	public function enqueue_styles_scripts() {
		// Styles
		wp_enqueue_style( 'mp-admin', mp_plugin_url( 'includes/admin/ui/css/admin.css' ), array(), MP_VERSION );
		// Scripts
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Initialize metaboxes
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_metaboxes() {
		$this->init_general_settings_metabox();
		$this->init_indexer_metabox();
		$this->init_global_gateway_settings_metabox();
		$this->init_gateway_permissions_metabox();
		$this->init_theme_permissions_metabox();
		$this->init_network_pages();
		$this->init_global_currency_metabox();
		do_action( 'mp_multisite_init_metaboxes' );
	}

	/**
	 * Initialize general settings metabox
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_general_settings_metabox() {
		$metabox = new PSOURCE_Metabox( array(
			'id'               => 'mp-network-settings-general',
			'page_slugs'       => array( 'network-store-settings' ),
			'title'            => __( 'Shopnetzwerk Einstellungen', 'mp' ),
			'site_option_name' => 'mp_network_settings',
			'order'            => 0,
		) );
		$metabox->add_field( 'checkbox', array(
			'name'  => 'main_blog',
			'label' => array( 'text' => __( 'Netzwerk Widgets/Funktionscodes nur auf der Hauptseite?', 'mp' ) ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'  => 'global_cart',
			'label' => array( 'text' => __( 'Den Netzwerkwarenkorb aktivieren?', 'mp' ) ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[hybrid_gateway_routing]',
			'label'   => array( 'text' => __( 'Hybrid-Gateway-Routing aktivieren?', 'mp' ) ),
			'desc'    => __( 'Wenn aktiviert, nutzt ein Warenkorb mit nur einem Subshop dessen lokale Gateways. Bei Multi-Shop-Warenkörben werden weiterhin die Mainshop-Gateways verwendet.', 'mp' ),
			'message' => __( 'Single-Subshop-Kauf nutzt lokale Gateways', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[network_customer_hub]',
			'label'   => array( 'text' => __( 'Zentrale Kundenseite im Mainshop aktivieren?', 'mp' ) ),
			'desc'    => __( 'Aktiviert die optionale Kundenzentrale im Mainshop per Netzwerkseite/Shortcode.', 'mp' ),
			'message' => __( 'Kundenzentrale aktivieren', 'mp' ),
		) );
		if ( function_exists( 'psource_support_insert_ticket' ) && function_exists( 'psource_support_get_ticket_categories' ) ) {
			$priority_options = array();
			if ( function_exists( 'psource_support' ) ) {
				foreach ( (array) psource_support()::$ticket_priority as $priority => $label ) {
					$priority_options[ (string) $priority ] = $label;
				}
			}

			$metabox->add_field( 'checkbox', array(
				'name'    => 'advanced[ps_support_integration]',
				'label'   => array( 'text' => __( 'PS Support mit MarketPress verbinden?', 'mp' ) ),
				'desc'    => __( 'Erstellt bestellbezogene Tickets über PS Support und routet sie an den zuständigen Shop.', 'mp' ),
				'message' => __( 'PS-Support-Integration aktivieren', 'mp' ),
			) );
			$metabox->add_field( 'checkbox', array(
				'name'    => 'advanced[ps_support_customer_button]',
				'label'   => array( 'text' => __( 'Supportbutton in der Kundenzone anzeigen?', 'mp' ) ),
				'desc'    => __( 'Zeigt pro beteiligtem Shop einen sicheren Supportdialog an der Bestellung.', 'mp' ),
				'message' => __( 'Supportbutton anzeigen', 'mp' ),
			) );
			$metabox->add_field( 'checkbox', array(
				'name'    => 'advanced[ps_support_customer_faq_button]',
				'label'   => array( 'text' => __( 'FAQ-Button in der Kundenzone anzeigen?', 'mp' ) ),
				'desc'    => __( 'Verlinkt je beteiligtem Shop dessen von PS Support bereitgestellte FAQ-Seite.', 'mp' ),
				'message' => __( 'FAQ-Button anzeigen', 'mp' ),
			) );
			$metabox->add_field( 'checkbox', array(
				'name'    => 'advanced[ps_support_product_links]',
				'label'   => array( 'text' => __( 'Support und FAQs an Produkten anzeigen?', 'mp' ) ),
				'desc'    => __( 'Zeigt den Shop-Support und eine optional am Produkt ausgewählte FAQ an.', 'mp' ),
				'message' => __( 'Produkt-Supportlinks anzeigen', 'mp' ),
			) );
			if ( ! empty( $priority_options ) ) {
				$metabox->add_field( 'select', array(
					'name'          => 'advanced[ps_support_priority]',
					'label'         => array( 'text' => __( 'Standardpriorität für Bestelltickets', 'mp' ) ),
					'options'       => $priority_options,
					'default_value' => '0',
				) );
			}
		}
		$metabox->add_field( 'select', array(
			'name'    => 'advanced[network_customer_hub_random_products_count]',
			'label'   => array( 'text' => __( 'Anzahl Zufallsprodukte im Kundenhub', 'mp' ) ),
			'desc'    => __( 'Legt fest, wie viele zufaellige Netzwerkprodukte im Kundenhub angezeigt werden.', 'mp' ),
			'options' => array(
				'4' => __( '4 Produkte', 'mp' ),
				'6' => __( '6 Produkte', 'mp' ),
				'8' => __( '8 Produkte', 'mp' ),
			),
			'default_value' => '4',
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[network_customer_hub_random_only_when_cart_empty]',
			'label'   => array( 'text' => __( 'Zufallsprodukte nur bei leerem Warenkorb anzeigen?', 'mp' ) ),
			'desc'    => __( 'Wenn aktiv, wird das Zufallsprodukt-Grid nur eingeblendet, wenn der globale Warenkorb leer ist.', 'mp' ),
			'message' => __( 'Nur bei leerem Warenkorb anzeigen', 'mp' ),
			'default_value' => true,
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[network_withdrawal_management]',
			'label'   => array( 'text' => __( 'Widerrufsmanagement im zentralen Hub aktivieren?', 'mp' ) ),
			'desc'    => __( 'Zeigt Widerrufs-KPIs, Statusverlauf und "Widerruf starten" im zentralen Kundenhub an.', 'mp' ),
			'message' => __( 'Widerrufsmanagement im Hub aktivieren', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[network_shop_performance]',
			'label'   => array( 'text' => __( 'Shopuser Performance-Seite aktivieren?', 'mp' ) ),
			'desc'    => __( 'Erlaubt eine optionale Seite mit Kennzahlen für Shopadmins im Netzwerk.', 'mp' ),
			'message' => __( 'Shopperformance-Seite aktivieren', 'mp' ),
		) );
		$metabox->add_field( 'select', array(
			'name'    => 'advanced[network_shop_presentation_mode]',
			'label'   => array( 'text' => __( 'Navigation im Netzwerk-Marktplatz', 'mp' ) ),
			'desc'    => __( 'Legt fest, ob Produktklicks auf eine zentrale Shop-Profilseite gehen oder direkt ins jeweilige Subshop-Produkt.', 'mp' ),
			'options' => array(
				'direct_product' => __( 'Direkt auf Subshop-Produktseite', 'mp' ),
				'shop_profile'   => __( 'Auf zentrale Shop-Profilseite', 'mp' ),
			),
			'default_value' => 'direct_product',
		) );
		$metabox->add_field( 'select', array(
			'name'    => 'advanced[network_mainshop_floating_cart_mode]',
			'label'   => array( 'text' => __( 'Floating-Warenkorb auf Mainshop-Seiten', 'mp' ) ),
			'desc'    => __( 'Steuert, wo der Floating-Warenkorb auf der Hauptseite angezeigt wird. "Nur Shop-Profilseite" ist für den Netzwerk-Marktplatz-Flow empfohlen.', 'mp' ),
			'options' => array(
				'off'     => __( 'Deaktiviert', 'mp' ),
				'profile' => __( 'Nur Shop-Profilseite', 'mp' ),
				'all'     => __( 'Auf allen Mainshop-Seiten', 'mp' ),
			),
			'default_value' => 'profile',
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[network_shop_profile_reviews]',
			'label'   => array( 'text' => __( 'Bewertungs-Widget im Shop-Profil aktivieren?', 'mp' ) ),
			'desc'    => __( 'Zeigt auf der Shop-Profilseite Sterne-Schnitt und Bewertungsanzahl des ausgewählten Produkts an.', 'mp' ),
			'message' => __( 'Bewertungen im Profil anzeigen', 'mp' ),
		) );
		$metabox->add_field( 'select', array(
			'name'    => 'advanced[network_reviews_policy]',
			'label'   => array( 'text' => __( 'Netzwerk-Policy für Bewertungen', 'mp' ) ),
			'desc'    => __( 'Steuert, wie strikt die Bewertungs-Erweiterung in Subshops vorausgesetzt wird und wie fehlende Aktivierung im UI markiert wird.', 'mp' ),
			'options' => array(
				'off'      => __( 'Deaktiviert (keine Policy-Pruefung)', 'mp' ),
				'advisory' => __( 'Hinweis (fehlende Shops markieren)', 'mp' ),
				'required' => __( 'Pflicht (fehlende Aktivierung klar blockierend markieren)', 'mp' ),
			),
			'default_value' => 'advisory',
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[settlement_enabled]',
			'label'   => array( 'text' => __( 'Auszahlungsfreigabe aktivieren?', 'mp' ) ),
			'desc'    => __( 'Aktiviert die Freigabe-Warteschlange, Zurueckhalten/Freigabe und den Gutschriftstatus der Subshops.', 'mp' ),
			'message' => __( 'Auszahlungsfreigabe aktivieren', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[settlement_auto_release]',
			'label'   => array( 'text' => __( 'Automatische Freigabe nach Regeln?', 'mp' ) ),
			'desc'    => __( 'Wenn aktiv, werden freigabefaehige Positionen nach Ablauf aller Gates automatisch auf freigegeben gesetzt.', 'mp' ),
			'message' => __( 'Auto-Release aktivieren', 'mp' ),
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'advanced[settlement_hold_days]',
			'label'         => array( 'text' => __( 'Hold-Tage', 'mp' ) ),
			'desc'          => __( 'Wartezeit in Tagen bis eine Position ohne Konflikte freigabefaehig wird (Standard: 14).', 'mp' ),
			'default_value' => 14,
			'input_type'    => 'number',
			'style'         => 'width: 100%;',
			'custom'        => array( 'min' => 0, 'step' => 1 ),
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'advanced[settlement_commission_rate]',
			'label'         => array( 'text' => __( 'Netzwerkprovision (%)', 'mp' ) ),
			'desc'          => __( 'Globaler Provisionssatz fuer neue Netzwerkbestellungen.', 'mp' ),
			'default_value' => 0,
			'input_type'    => 'number',
			'style'         => 'width: 100%;',
			'custom'        => array( 'min' => 0, 'max' => 100, 'step' => '0.01' ),
		) );
		$metabox->add_field( 'select', array(
			'name'          => 'advanced[settlement_commission_basis]',
			'label'         => array( 'text' => __( 'Provisionsbasis', 'mp' ) ),
			'options'       => array(
				'product_net'   => __( 'Artikel netto', 'mp' ),
				'product_gross' => __( 'Artikel brutto', 'mp' ),
				'shop_gross'    => __( 'Gesamter Shopanteil inklusive Versand', 'mp' ),
			),
			'default_value' => 'product_net',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'advanced[settlement_commission_tax_rate]',
			'label'         => array( 'text' => __( 'Umsatzsteuer auf Provision (%)', 'mp' ) ),
			'desc'          => __( 'Steuersatz fuer die Abrechnung der Netzwerkprovision.', 'mp' ),
			'default_value' => 0,
			'input_type'    => 'number',
			'style'         => 'width: 100%;',
			'custom'        => array( 'min' => 0, 'max' => 100, 'step' => '0.01' ),
		) );
		// HIER die neue Option:
		$metabox->add_field( 'checkbox', array(
			'name'    => 'advanced[delete_on_uninstall]',
			'label'   => array( 'text' => __( 'Daten beim Deinstallieren löschen?', 'mp' ) ),
			'desc'    => __( 'Wenn aktiviert, werden beim Deinstallieren des Plugins alle MarketPress-Datenbanktabellen und Einstellungen unwiderruflich gelöscht.', 'mp' ),
			'message' => __( 'Ja, alle Daten beim Deinstallieren entfernen', 'mp' ),
			'default_value' => false,
		) );
	}

	/**
	 * Display global currency information
	 *
	 * @since 3.1.3
	 * @access public
	 */
	public function init_global_currency_metabox(){
		if( mp_get_network_setting( 'global_cart' ) ){

			$metabox = new PSOURCE_Metabox( array(
				'id'               => 'mp-global-store-currency',
				'page_slugs'       => array( 'network-store-settings' ),
				'title'            => __( 'Netzwerkwährung', 'mp' ),
				'site_option_name' => 'mp_network_settings',
				'order'            => 0,
			) );

			$currencies	 = mp()->currencies;
			$options	 = array( '' => __( 'Wähle eine Währung', 'mp' ) );

			foreach ( $currencies as $key => $value ) {
				$options[ $key ] = esc_attr( $value[ 0 ] ) . ' - ' . mp_format_currency( $key );
			}

			$metabox->add_field( 'advanced_select', array(
				'name'			 => 'global_currency',
				'placeholder'	 => __( 'Wähle eine Währung', 'mp' ),
				'multiple'		 => false,
				'label'			 => array( 'text' => __( 'Netzwerkwährung', 'mp' ) ),
				'options'		 => $options,
				'width'			 => 'element',
			) );

			$metabox->add_field( 'radio_group', array(
				'name'			 => 'global_curr_symbol_position',
				'label'			 => array( 'text' => __( 'Währungssymbol', 'mp' ) ),
				'default_value'	 => '3',
				'orientation'	 => 'horizontal',
				'options'		 => array(
					'1'	 => '<span class="mp-currency-symbol">' . mp_format_currency( mp_get_network_setting( 'global_currency', 'EUR' ) ) . '</span>100',
					'2'	 => '<span class="mp-currency-symbol">' . mp_format_currency( mp_get_network_setting( 'global_currency', 'EUR' ) ) . '</span> 100',
					'3'	 => '100<span class="mp-currency-symbol">' . mp_format_currency( mp_get_network_setting( 'global_currency', 'EUR' ) ) . '</span>',
					'4'	 => '100 <span class="mp-currency-symbol">' . mp_format_currency( mp_get_network_setting( 'global_currency', 'EUR' ) ) . '</span>',
				)
			) );

			$metabox->add_field( 'radio_group', array(
				'name'			 => 'global_price_format',
				'label'			 => array( 'text' => __( 'Preis Format', 'mp' ) ),
				'default_value'	 => 'eu',
				'orientation'	 => 'horizontal',
				'options'		 => array(
					'en'	 => '1,123.45',
					'eu'	 => '1.123,45',
					'frc'	 => '1 123,45',
					'frd'	 => '1 123.45',
				),
			) );

			$metabox->add_field( 'radio_group', array(
				'name'			 => 'global_curr_decimal',
				'label'			 => array( 'text' => __( 'Dezimalstellen für Preise', 'mp' ) ),
				'default_value'	 => 'on',
				'orientation'	 => 'horizontal',
				'options'		 => array(
					'off'	 => '100',
					'on'	 => '100.00',
				),
			) );

		}
	}

	/**
	 * Fetch currency values
	 */
	public function global_currency_options( $value, $post_id, $raw, $field ){

		$currency_global_options_indexers = array(
			'global_curr_symbol_position',
			'global_price_format',
			'global_curr_decimal'
		);

		return in_array( $field->args['name'], $currency_global_options_indexers ) ? mp_get_network_setting( $field->args['name'] ) : $value;
	}

	/**
	 * Display indexer information
	 */
	public function init_indexer_metabox() {
		$count = MP_Multisite::get_instance()->count();
		//$count='';
		$html = sprintf( __( "%d Produkte wurden im Netzwerk indexiert", "mp" ), $count ) . '<br/><br/>';
		$html .= '<button type="button" class="button mp_index_products">' . __( "Produkte indexieren", "mp" ) . '</button>';
		$html .= '<p class="index-status" style="display: none;">' . __( "Index läuft, bitte warten...", "mp" ) . '</p>';
		$metabox = new PSOURCE_Metabox( array(
			'id'               => 'mp-post-indexer',
			'page_slugs'       => array( 'network-store-settings' ),
			'title'            => __( 'Produkt Indexierung', 'mp' ),
			'desc'             => $html,
			'site_option_name' => '',
			'order'            => 0,
		) );

		add_action( 'admin_footer', array( &$this, 'index_products_script' ) );
	}

	function index_products_script() {
		if ( is_network_admin() ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('.mp_index_products').on('click', function () {
						var that = $(this);
						$.ajax({
							type: 'POST',
							data: {
								action: 'mp_index_products',
								_nonce: '<?php echo wp_create_nonce('mp_index_products') ?>'
							},
							url: ajaxurl,
							beforeSend: function () {
								that.attr('disabled', 'disabled');
								$('.index-status').css('display', 'block');
							},
							success: function (data) {
								that.removeAttr('disabled');
								$('.index-status').text(data.text);
							}
						})
					})
				})
			</script>
			<?php
		}
	}

	public function index_products() {
		if ( ! current_user_can( 'manage_options' ) ) {
			die();
		}

		if ( ! wp_verify_nonce( mp_get_post_value( '_nonce' ), 'mp_index_products' ) ) {
			die();
		}

		$result = MP_Multisite::get_instance()->index_content();

		wp_send_json( array(
			'text' => sprintf( __( "%d Produkte wurden bereits indexiert", "mp" ), $result['count'] )
		) );
	}

	/**
	 * Initialize global gateway metabox
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_global_gateway_settings_metabox() {
		$settings = get_site_option( 'mp_network_settings', array() );
		$settings_changed = false;
		$enabled_gateways = (array) mp_arr_get_value( 'gateways->allowed', $settings, array() );
		if ( empty( $enabled_gateways ) ) {
			$enabled_gateways = array_filter( (array) mp_arr_get_value( 'network_gateways', $settings, array() ) );
			if ( empty( $enabled_gateways ) && ! empty( $settings['global_gateway'] ) ) {
				$enabled_gateways[ sanitize_key( (string) $settings['global_gateway'] ) ] = 1;
			}
			if ( ! empty( $enabled_gateways ) ) {
				if ( ! isset( $settings['gateways'] ) || ! is_array( $settings['gateways'] ) ) {
					$settings['gateways'] = array();
				}
				$settings['gateways']['allowed'] = $enabled_gateways;
				$settings_changed = true;
			}
		}
		if ( array_key_exists( 'network_gateways', $settings ) ) {
			unset( $settings['network_gateways'] );
			$settings_changed = true;
		}
		if ( ! empty( $settings['global_gateway'] ) ) {
			$settings['global_gateway'] = '';
			$settings_changed = true;
		}
		if ( $settings_changed ) {
			update_site_option( 'mp_network_settings', $settings );
		}

		$metabox = new PSOURCE_Metabox( array(
			'id'               => 'mp-network-settings-global-gateway',
			'page_slugs' => array( 'network-store-settings-payments' ),
			'title'            => __( 'Netzwerk Zahlungsgateway', 'mp' ),
			'site_option_name' => 'mp_network_settings',
			'order'            => 0,
		) );

		$gateways = MP_Gateway_API::get_gateways();

		foreach ( $gateways as $code => $gateway ) {
			if ( 'free_orders' === $code ) {
				continue;
			}

			$metabox->add_field( 'checkbox', array(
				'name'    => 'gateways[allowed][' . $code . ']',
				'label'   => array( 'text' => $gateway[1] ),
				'message' => sprintf( __( '%s im Netzwerk-Checkout anbieten', 'mp' ), $gateway[1] ),
			) );
		}
	}

	/**
	 * Initialize gateway permissions metabox
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_gateway_permissions_metabox() {
		$metabox = new PSOURCE_Metabox( array(
			'id'               => 'mp-network-settings-gateway-permissions',
			'page_slugs' => array( 'network-store-settings-payments' ),
			'title'            => __( 'Gateway-Berechtigungen', 'mp' ),
			'site_option_name' => 'mp_network_settings',
			'order'            => 0,
		) );

		$options_permissions = array(
			'full' => __( 'Alle können verwenden', 'mp' ),
			'none' => __( 'Kein Zugriff', 'mp' ),
		);

		/**
		 * Filter the gateway permissions options list
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @param array $options_permissions An array of options.
		 */
		$options_permissions = apply_filters( 'mp_admin_multisite/gateway_permissions_options', $options_permissions );

		$gateways = MP_Gateway_API::get_gateways();

		foreach ( $gateways as $code => $gateway ) {
			if ( $code !== 'free_orders' ) {//we don't need to show free orders gateways since it will be automatically activated if needed
				$metabox->add_field( 'select', array(
					'name'    => 'allowed_gateways[' . $code . ']',
					'label'   => array( 'text' => $gateway[1] ),
					'options' => $options_permissions,
				) );
			}
		}
	}

	/**
	 * Initialize theme permissions metabox
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_theme_permissions_metabox() {
		$metabox = new PSOURCE_Metabox( array(
			'id'               => 'mp-network-settings-theme-permissions',
			'page_slugs'       => array( 'network-store-settings' ),
			'title'            => __( 'Theme Berechtigungen', 'mp' ),
			'site_option_name' => 'mp_network_settings',
			'desc'             => __( 'Festlegen von Theme-Zugriffsberechtigungen für Netzwerkspeicher. Speichere für ein benutzerdefiniertes CSS-Thema Deine CSS-Datei mit dem Header <strong> MarketPress Theme: NAME </strong> im Ordner <strong> /marketpress/ui/themes/ </strong>, damit es in dieser Liste angezeigt wird.', 'mp' ),
			'order'            => 15,
		) );

		$theme_list = mp_get_theme_list();

		$options_permissions = array(
			'full' => __( 'Alle können benutzen', 'mp' ),
			'none' => __( 'Kein Zugriff', 'mp' ),
		);

		/**
		 * Filter the theme permissions options list
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @param array $options_permissions An array of options.
		 */
		$options_permissions = apply_filters( 'mp_admin_multisite/theme_permissions_options', $options_permissions );

		foreach ( $theme_list as $value => $theme ) {
			$metabox->add_field( 'select', array(
				'name'    => 'allowed_themes[' . $value . ']',
				'label'   => array( 'text' => $theme['name'] ),
				'desc'    => $theme['path'],
				'options' => $options_permissions,
			) );
		}
	}

	/**
	 * Add menu items to the network admin menu
	 *
	 * @since 1.0
	 * @access public
	 */
	public function add_menu_items() {
        add_menu_page(
                __( 'PS MarketPress Netzwerk', 'mp' ),
                __( 'PS MarketPress', 'mp' ),
                'manage_network_options',
                self::NETWORK_MENU_SLUG,
                array( &$this, 'network_store_settings' ),
                'dashicons-store',
                58.5
        );

        add_submenu_page(
                self::NETWORK_MENU_SLUG,
                __( 'Shopnetzwerk Einstellungen', 'mp' ),
                __( 'Einstellungen', 'mp' ),
                'manage_network_options',
                self::NETWORK_MENU_SLUG,
                array( &$this, 'network_store_settings' )
        );

        add_submenu_page(
                self::NETWORK_MENU_SLUG,
                __( 'Netzwerk Zahlungen', 'mp' ),
                __( 'Zahlungen', 'mp' ),
                'manage_network_options',
                'network-store-settings-payments',
                array( &$this, 'network_store_settings_payments' )
        );
	}

	/**
	 * Displays the network payment settings.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function network_store_settings_payments() {
        ?>
        <div class="wrap mp-wrap mp-network-settings-modern">

            <h1 class="mp-settings-title">
                <?php esc_html_e( 'Netzwerk Zahlungen', 'mp' ); ?>
            </h1>

            <div class="mp-network-intro">
                <h3><?php esc_html_e( 'Netzwerk-Zahlungsgateways', 'mp' ); ?></h3>
                <p>
					<?php esc_html_e( 'Konfiguriere das Zahlungsgateway für den Netzwerk-Warenkorb.', 'mp' ); ?>
                </p>
            </div>

			<div class="mp-settings">
				<form id="mp-main-form" method="post" action="<?php echo esc_url( add_query_arg( array() ) ); ?>">
					<?php do_action( 'psource_metabox/render_settings_metaboxes' ); ?>
				</form>
			</div>

        </div>
        <?php
	}

	/**
	 * Collect warnings for enabled network features that still miss required pages.
	 *
	 * @return array
	 */
	private function get_missing_network_page_warnings() {
		$warnings = array();
		$required_pages = array();

		if ( 'shop_profile' === sanitize_key( (string) mp_get_network_setting( 'advanced->network_shop_presentation_mode', 'direct_product' ) ) ) {
			$required_pages['network_shop_profile'] = __( 'Die Shop-Profilseite für den Netzwerk-Marktplatz fehlt.', 'mp' );
		}

		if ( (bool) mp_get_network_setting( 'advanced->network_customer_hub', 0 ) ) {
			$required_pages['network_customer_hub'] = __( 'Die zentrale Kundenseite ist aktiviert, aber keine Seite ist zugewiesen.', 'mp' );
		}

		if ( (bool) mp_get_network_setting( 'advanced->network_shop_performance', 0 ) ) {
			$required_pages['network_shop_performance'] = __( 'Die Shopuser-Performance-Seite ist aktiviert, aber keine Seite ist zugewiesen.', 'mp' );
		}


		foreach ( $required_pages as $page_key => $message ) {
			$page_id = (int) mp_get_network_setting( 'pages->' . $page_key, 0 );
			if ( $page_id <= 0 || false === get_post_status( $page_id ) ) {
				$warnings[] = $message;
			}
		}

		return $warnings;
	}

	/**
	 * Displays the network settings form/metaboxes
	 *
	 * @since 1.0
	 * @access public
	 */
	public function network_store_settings() {
		$force_sync = false;
		if ( mp_get_get_value( 'mp_refresh_network_stats', 0 ) ) {
			$nonce = (string) mp_get_get_value( '_mpn', '' );
			if ( $nonce && wp_verify_nonce( $nonce, 'mp_refresh_network_stats' ) ) {
				$force_sync = true;
			}
		}

		$snapshot = $this->get_network_settings_snapshot( $force_sync );
		$cleanup_settings = $this->get_network_snapshot_cleanup_settings();
		$cleanup_next_run = wp_next_scheduled( self::NETWORK_SNAPSHOT_CLEANUP_CRON_HOOK );
		$snapshot_controls_nonce = wp_create_nonce( 'mp_network_snapshot_controls' );
		$missing_page_warnings = $this->get_missing_network_page_warnings();
		$refresh_url = wp_nonce_url(
			add_query_arg(
				array(
					'page' => self::NETWORK_MENU_SLUG,
					'mp_refresh_network_stats' => 1,
				),
				network_admin_url( 'admin.php' )
			),
			'mp_refresh_network_stats',
			'_mpn'
		);
		?>
		<div class="wrap mp-wrap mp-network-settings-modern">
			<div class="icon32"><img src="<?php echo mp_plugin_url( 'ui/images/settings.png' ); ?>"/></div>
			<h2 class="mp-settings-title"><?php _e( 'Shopnetzwerk Einstellungen', 'mp' ); ?></h2>

			<style>
				.mp-network-settings-modern {
					--mp-ui-bg: #f5f8fb;
					--mp-ui-card: #ffffff;
					--mp-ui-border: #d8e3ef;
					--mp-ui-ink: #1e3348;
					--mp-ui-muted: #5b748d;
					--mp-ui-accent: #2f6ca3;
				}

				.mp-network-settings-modern .mp-settings-title {
					color: var(--mp-ui-ink);
					font-size: 28px;
					letter-spacing: .01em;
				}

				.mp-network-settings-modern .mp-network-intro {
					background: linear-gradient(135deg, #e9f3ff 0%, #f8fbff 100%);
					border: 1px solid var(--mp-ui-border);
					border-radius: 14px;
					padding: 16px;
					margin: 14px 0 18px;
				}

				.mp-network-settings-modern .mp-network-intro h3 {
					margin: 0 0 6px;
					font-size: 18px;
					color: var(--mp-ui-ink);
				}

				.mp-network-settings-modern .mp-network-intro p {
					margin: 0;
					color: var(--mp-ui-muted);
				}

				.mp-network-settings-modern .mp-network-warning {
					background: #fff7e8;
					border: 1px solid #f1c27d;
					border-radius: 14px;
					padding: 14px 16px;
					margin: 0 0 18px;
					color: #7a4b00;
				}

				.mp-network-settings-modern .mp-network-warning h3 {
					margin: 0 0 8px;
					font-size: 16px;
					color: #7a4b00;
				}

				.mp-network-settings-modern .mp-network-warning ul {
					margin: 0;
					padding-left: 18px;
				}

				.mp-network-settings-modern .mp-network-stats-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
					gap: 10px;
					margin: 12px 0;
				}

				.mp-network-settings-modern .mp-network-stat-card {
					background: var(--mp-ui-card);
					border: 1px solid var(--mp-ui-border);
					border-radius: 12px;
					padding: 12px;
				}

				.mp-network-settings-modern .mp-network-stat-label {
					display: block;
					font-size: 11px;
					text-transform: uppercase;
					letter-spacing: .05em;
					color: var(--mp-ui-muted);
				}

				.mp-network-settings-modern .mp-network-stat-value {
					display: block;
					margin-top: 6px;
					font-size: 24px;
					font-weight: 700;
					line-height: 1.1;
					color: var(--mp-ui-ink);
				}

				.mp-network-settings-modern .mp-network-meta {
					display: flex;
					align-items: center;
					gap: 10px;
					margin-top: 8px;
					color: var(--mp-ui-muted);
					font-size: 12px;
				}

				.mp-network-settings-modern .mp-network-refresh {
					margin-left: auto;
					text-decoration: none;
					padding: 5px 10px;
					border-radius: 999px;
					border: 1px solid #b9cee3;
					color: var(--mp-ui-accent);
					background: #fff;
				}

				.mp-network-settings-modern .mp-network-gear {
					text-decoration: none;
					padding: 5px 9px;
					border-radius: 999px;
					border: 1px solid #b9cee3;
					color: var(--mp-ui-accent);
					background: #fff;
					font-size: 14px;
				}

				.mp-network-settings-modern .mp-snapshot-options {
					display: none;
					margin-top: 10px;
					padding: 10px;
					border: 1px solid var(--mp-ui-border);
					border-radius: 10px;
					background: #fff;
				}

				.mp-network-settings-modern .mp-snapshot-options.is-open {
					display: block;
				}

				.mp-network-settings-modern .mp-snapshot-options-row {
					display: flex;
					align-items: center;
					gap: 10px;
					flex-wrap: wrap;
					margin-bottom: 8px;
				}

				.mp-network-settings-modern .mp-snapshot-options-row:last-child {
					margin-bottom: 0;
				}

				.mp-network-settings-modern .mp-snapshot-status {
					font-size: 12px;
					color: var(--mp-ui-muted);
				}

				.mp-network-settings-modern .mp-settings .postbox {
					border: 1px solid var(--mp-ui-border);
					border-radius: 12px;
					overflow: hidden;
					box-shadow: 0 2px 8px rgba(23, 52, 78, .06);
				}

				.mp-network-settings-modern .mp-settings .postbox .hndle {
					background: #f7fbff;
					color: var(--mp-ui-ink);
					border-bottom: 1px solid var(--mp-ui-border);
				}

				@media (max-width: 782px) {
					.mp-network-settings-modern .mp-network-meta {
						flex-wrap: wrap;
					}
					.mp-network-settings-modern .mp-network-refresh {
						margin-left: 0;
					}
				}
			</style>

			<section class="mp-network-intro">
				<h3><?php esc_html_e( 'Netzwerk Cockpit', 'mp' ); ?></h3>
				<p><?php esc_html_e( 'Snapshot-basierter Schnellueberblick für Netzwerkbetrieb, Widerrufe und vorbereitetes Support-KPI.', 'mp' ); ?></p>
				<div class="mp-network-stats-grid">
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Aktive Shops', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( $snapshot['active_shops'] ); ?></strong>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Bestellungen gesamt', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( $snapshot['orders_total'] ); ?></strong>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Netzwerkumsatz', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo esc_html( mp_format_currency( mp_get_setting( 'currency' ), $snapshot['revenue_total'] ) ); ?></strong>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Offene Widerrufe', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( $snapshot['withdrawal_open'] ); ?></strong>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Buendel-Checkouts', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( isset( $snapshot['bundle_orders'] ) ? $snapshot['bundle_orders'] : 0 ); ?></strong>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Split-Checkouts', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( isset( $snapshot['split_orders'] ) ? $snapshot['split_orders'] : 0 ); ?></strong>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Auszahlungen offen', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( isset( $snapshot['payout_pending'] ) ? $snapshot['payout_pending'] : 0 ); ?></strong>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Support KPI (Grundgeruest)', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( $snapshot['support_open'] ); ?></strong>
						<span class="mp-network-stat-label"><?php echo esc_html( $snapshot['support_mode_label'] ); ?></span>
					</article>
					<article class="mp-network-stat-card">
						<span class="mp-network-stat-label"><?php esc_html_e( 'Indexierte Produkte', 'mp' ); ?></span>
						<strong class="mp-network-stat-value"><?php echo intval( $snapshot['indexed_products'] ); ?></strong>
					</article>
				</div>
				<div class="mp-network-meta">
					<span><?php echo esc_html( sprintf( __( 'Snapshot aktualisiert: %s', 'mp' ), date_i18n( 'd.m.Y H:i', (int) $snapshot['generated_at'] ) ) ); ?></span>
					<a class="mp-network-refresh" href="<?php echo esc_url( $refresh_url ); ?>"><?php esc_html_e( 'Snapshot aktualisieren', 'mp' ); ?></a>
					<a class="mp-network-gear" href="#" id="mp-snapshot-options-toggle" aria-label="<?php esc_attr_e( 'Snapshot Optionen', 'mp' ); ?>">&#9881;</a>
				</div>
				<div id="mp-snapshot-options" class="mp-snapshot-options" aria-hidden="true">
					<div class="mp-snapshot-options-row">
						<label>
							<input type="checkbox" id="mp-snapshot-auto-clear-enabled" <?php checked( ! empty( $cleanup_settings['enabled'] ) ); ?> />
							<?php esc_html_e( 'Auto-Cache-Loeschung aktivieren', 'mp' ); ?>
						</label>
						<label for="mp-snapshot-auto-clear-interval"><?php esc_html_e( 'Intervall (Minuten)', 'mp' ); ?></label>
						<input type="number" id="mp-snapshot-auto-clear-interval" min="5" max="1440" value="<?php echo intval( $cleanup_settings['interval_minutes'] ); ?>" style="width: 90px;" />
						<button type="button" class="button button-primary" id="mp-snapshot-save-options"><?php esc_html_e( 'Speichern', 'mp' ); ?></button>
						<button type="button" class="button" id="mp-snapshot-clear-cache"><?php esc_html_e( 'Cache jetzt loeschen', 'mp' ); ?></button>
					</div>
					<div class="mp-snapshot-options-row">
						<span class="mp-snapshot-status" id="mp-snapshot-status-text">
							<?php
							if ( ! empty( $cleanup_settings['enabled'] ) && $cleanup_next_run ) {
								echo esc_html( sprintf( __( 'Auto-Clear aktiv, naechster Lauf: %s', 'mp' ), date_i18n( 'd.m.Y H:i', (int) $cleanup_next_run ) ) );
							} else {
								esc_html_e( 'Auto-Clear ist deaktiviert.', 'mp' );
							}
							?>
						</span>
					</div>
				</div>
			</section>

			<script type="text/javascript">
				jQuery(function ($) {
					var $toggle = $('#mp-snapshot-options-toggle');
					var $panel = $('#mp-snapshot-options');
					var $status = $('#mp-snapshot-status-text');

					$toggle.on('click', function (e) {
						e.preventDefault();
						$panel.toggleClass('is-open');
						$panel.attr('aria-hidden', !$panel.hasClass('is-open'));
					});

					function postSnapshotOptions(operation, payload) {
						return $.post(ajaxurl, $.extend({
							action: 'mp_network_snapshot_controls',
							nonce: '<?php echo esc_js( $snapshot_controls_nonce ); ?>',
							operation: operation
						}, payload || {}));
					}

					$('#mp-snapshot-save-options').on('click', function () {
						var enabled = $('#mp-snapshot-auto-clear-enabled').is(':checked') ? 1 : 0;
						var interval = parseInt($('#mp-snapshot-auto-clear-interval').val(), 10) || 60;
						$status.text('<?php echo esc_js( __( 'Speichere Snapshot-Optionen...', 'mp' ) ); ?>');

						postSnapshotOptions('save_settings', {
							enabled: enabled,
							interval_minutes: interval
						}).done(function (resp) {
							if (resp && resp.success && resp.data && resp.data.message) {
								$status.text(resp.data.message);
							} else {
								$status.text('<?php echo esc_js( __( 'Einstellungen gespeichert.', 'mp' ) ); ?>');
							}
						}).fail(function () {
							$status.text('<?php echo esc_js( __( 'Speichern fehlgeschlagen.', 'mp' ) ); ?>');
						});
					});

					$('#mp-snapshot-clear-cache').on('click', function () {
						$status.text('<?php echo esc_js( __( 'Snapshot-Cache wird geloescht...', 'mp' ) ); ?>');
						postSnapshotOptions('clear_cache').done(function (resp) {
							if (resp && resp.success && resp.data && resp.data.message) {
								$status.text(resp.data.message);
								return;
							}
							$status.text('<?php echo esc_js( __( 'Snapshot-Cache geloescht.', 'mp' ) ); ?>');
						}).fail(function () {
							$status.text('<?php echo esc_js( __( 'Cache-Loeschung fehlgeschlagen.', 'mp' ) ); ?>');
						});
					});
				});
			</script>

			<?php if ( ! empty( $missing_page_warnings ) ) : ?>
				<section class="mp-network-warning">
					<h3><?php esc_html_e( 'Fehlende Pflichtseiten erkannt', 'mp' ); ?></h3>
					<ul>
						<?php foreach ( $missing_page_warnings as $warning ) : ?>
							<li><?php echo esc_html( $warning ); ?> <?php esc_html_e( 'Bitte im Bereich Netzwerkmarktplatz Seiten zuweisen oder direkt erstellen.', 'mp' ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<div class="clear"></div>
			<div class="mp-settings">
				<form id="mp-main-form" method="post" action="<?php echo add_query_arg( array() ); ?>">
					<?php
					/**
					 * Render PSOURCE Metabox settings
					 *
					 * @since 1.0
					 */
					do_action( 'psource_metabox/render_settings_metaboxes' );
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Read network settings snapshot data with short caching.
	 *
	 * @param bool $force_sync Force cache refresh.
	 *
	 * @return array
	 */
	private function get_network_settings_snapshot( $force_sync = false ) {
		$cache_key = self::NETWORK_SNAPSHOT_CACHE_KEY;

		if ( ! $force_sync ) {
			$cached = get_site_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$snapshot = $this->build_network_settings_snapshot();
		set_site_transient( $cache_key, $snapshot, 5 * MINUTE_IN_SECONDS );

		return $snapshot;
	}

	/**
	 * Ajax endpoint for snapshot options and manual cache clear.
	 */
	public function ajax_network_snapshot_controls() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'mp' ) ), 403 );
		}

		check_ajax_referer( 'mp_network_snapshot_controls', 'nonce' );

		$operation = sanitize_key( (string) mp_get_post_value( 'operation', '' ) );
		if ( 'clear_cache' === $operation ) {
			$this->clear_network_settings_snapshot_cache();
			wp_send_json_success( array( 'message' => __( 'Snapshot-Cache geloescht.', 'mp' ) ) );
		}

		if ( 'save_settings' === $operation ) {
			$enabled = (bool) intval( mp_get_post_value( 'enabled', 0 ) );
			$interval = intval( mp_get_post_value( 'interval_minutes', 60 ) );
			$interval = max( 5, min( 1440, $interval ) );

			$this->save_network_snapshot_cleanup_settings( $enabled, $interval );
			$this->sync_network_snapshot_cleanup_cron();

			$next = wp_next_scheduled( self::NETWORK_SNAPSHOT_CLEANUP_CRON_HOOK );
			$message = $enabled && $next
				? sprintf( __( 'Gespeichert. Naechste Auto-Loeschung: %s', 'mp' ), date_i18n( 'd.m.Y H:i', (int) $next ) )
				: __( 'Gespeichert. Auto-Clear deaktiviert.', 'mp' );

			wp_send_json_success( array(
				'message' => $message,
				'next_run' => (int) $next,
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Unbekannte Aktion.', 'mp' ) ), 400 );
	}

	/**
	 * Register dynamic cron schedule for snapshot auto cleanup.
	 *
	 * @param array $schedules
	 *
	 * @return array
	 */
	public function register_network_snapshot_cleanup_schedule( $schedules ) {
		$settings = $this->get_network_snapshot_cleanup_settings();
		$minutes = max( 5, min( 1440, intval( $settings['interval_minutes'] ) ) );
		$slug = self::NETWORK_SNAPSHOT_CLEANUP_RECURRENCE;

		$schedules[ $slug ] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			'display'  => sprintf( __( 'MarketPress Snapshot Cleanup (%d Minuten)', 'mp' ), $minutes ),
		);

		// Keep legacy dynamic slugs available so previously planned events can reschedule safely.
		$legacy_schedules = $this->get_legacy_network_snapshot_cleanup_schedule_map();
		foreach ( $legacy_schedules as $legacy_slug => $legacy_interval ) {
			$schedules[ $legacy_slug ] = array(
				'interval' => $legacy_interval,
				'display'  => sprintf( __( 'MarketPress Snapshot Cleanup (Legacy %d Minuten)', 'mp' ), max( 1, intval( $legacy_interval / MINUTE_IN_SECONDS ) ) ),
			);
		}

		return $schedules;
	}

	/**
	 * Keep snapshot cleanup cron in sync with saved settings.
	 */
	public function sync_network_snapshot_cleanup_cron() {
		$settings = $this->get_network_snapshot_cleanup_settings();
		$hook = self::NETWORK_SNAPSHOT_CLEANUP_CRON_HOOK;

		if ( empty( $settings['enabled'] ) ) {
			$this->clear_network_snapshot_cleanup_schedule( $hook );
			return;
		}

		$minutes = max( 5, min( 1440, intval( $settings['interval_minutes'] ) ) );
		$recurrence = $this->get_network_snapshot_cleanup_recurrence( $minutes );
		$next = wp_next_scheduled( $hook );

		if ( ! $next ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, $hook );
			return;
		}

		$current = wp_get_schedule( $hook );
		$needs_reschedule = ( $current !== $recurrence );

		if ( ! $needs_reschedule && function_exists( 'wp_get_scheduled_event' ) ) {
			$event = wp_get_scheduled_event( $hook );
			$target_interval = $minutes * MINUTE_IN_SECONDS;
			if ( $event && isset( $event->interval ) && intval( $event->interval ) !== intval( $target_interval ) ) {
				$needs_reschedule = true;
			}
		}

		if ( $needs_reschedule ) {
			$this->clear_network_snapshot_cleanup_schedule( $hook );
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, $hook );
		}
	}

	/**
	 * Cron callback to clear network snapshot cache.
	 */
	public function cron_clear_network_settings_snapshot_cache() {
		$this->clear_network_settings_snapshot_cache();
	}

	/**
	 * Delete network snapshot transient cache.
	 */
	private function clear_network_settings_snapshot_cache() {
		delete_site_transient( self::NETWORK_SNAPSHOT_CACHE_KEY );
	}

	/**
	 * Get snapshot cleanup settings.
	 *
	 * @return array
	 */
	private function get_network_snapshot_cleanup_settings() {
		$settings = get_site_option( self::NETWORK_SNAPSHOT_CLEANUP_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$enabled = ! empty( $settings['enabled'] );
		$interval = isset( $settings['interval_minutes'] ) ? intval( $settings['interval_minutes'] ) : 60;
		$interval = max( 5, min( 1440, $interval ) );

		return array(
			'enabled' => $enabled,
			'interval_minutes' => $interval,
		);
	}

	/**
	 * Persist snapshot cleanup settings.
	 *
	 * @param bool $enabled
	 * @param int  $interval_minutes
	 */
	private function save_network_snapshot_cleanup_settings( $enabled, $interval_minutes ) {
		update_site_option( self::NETWORK_SNAPSHOT_CLEANUP_OPTION, array(
			'enabled' => (bool) $enabled,
			'interval_minutes' => max( 5, min( 1440, intval( $interval_minutes ) ) ),
		) );
	}

	/**
	 * Build recurrence key from minutes.
	 *
	 * @param int $minutes
	 *
	 * @return string
	 */
	private function get_network_snapshot_cleanup_recurrence( $minutes ) {
		return self::NETWORK_SNAPSHOT_CLEANUP_RECURRENCE;
	}

	/**
	 * Discover legacy dynamic recurrence slugs from scheduled events.
	 *
	 * @return array<string,int>
	 */
	private function get_legacy_network_snapshot_cleanup_schedule_map() {
		$map = array();

		if ( ! function_exists( '_get_cron_array' ) ) {
			return $map;
		}

		$cron = _get_cron_array();
		if ( ! is_array( $cron ) ) {
			return $map;
		}

		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) || empty( $hooks[ self::NETWORK_SNAPSHOT_CLEANUP_CRON_HOOK ] ) ) {
				continue;
			}

			foreach ( (array) $hooks[ self::NETWORK_SNAPSHOT_CLEANUP_CRON_HOOK ] as $event ) {
				if ( ! is_array( $event ) || empty( $event['schedule'] ) ) {
					continue;
				}

				$schedule = (string) $event['schedule'];
				if ( 0 !== strpos( $schedule, 'mp_network_snapshot_cleanup_every_' ) || '_minutes' !== substr( $schedule, -8 ) ) {
					continue;
				}

				$minutes_part = str_replace( array( 'mp_network_snapshot_cleanup_every_', '_minutes' ), '', $schedule );
				$minutes = intval( $minutes_part );
				if ( $minutes < 1 ) {
					continue;
				}

				$map[ $schedule ] = $minutes * MINUTE_IN_SECONDS;
			}
		}

		return $map;
	}

	/**
	 * Unschedule all pending events for a cron hook.
	 *
	 * @param string $hook
	 */
	private function clear_network_snapshot_cleanup_schedule( $hook ) {
		while ( $timestamp = wp_next_scheduled( $hook ) ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	/**
	 * Build snapshot metrics for network settings overview.
	 *
	 * @return array
	 */
	private function build_network_settings_snapshot() {
		global $wpdb;

		$sites            = get_sites( array( 'fields' => 'ids' ) );
		$orders_total     = 0;
		$revenue_total    = 0.0;
		$withdrawal_open  = 0;
		$support_open     = 0;
		$bundle_orders    = 0;
		$split_orders     = 0;
		$payout_pending   = 0;
		$active_shops     = 0;
		$indexed_products = (int) MP_Multisite::get_instance()->count();

		foreach ( (array) $sites as $blog_id ) {
			switch_to_blog( (int) $blog_id );

			$post_counts  = wp_count_posts( 'mp_order' );
			$site_orders  = 0;
			$status_count = is_object( $post_counts ) ? get_object_vars( $post_counts ) : array();
			foreach ( $status_count as $status => $count ) {
				if ( 0 === strpos( (string) $status, 'order_' ) ) {
					$site_orders += (int) $count;
				}
			}

			$orders_total += $site_orders;
			if ( $site_orders > 0 ) {
				$active_shops++;
			}

			$revenue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(pm.meta_value AS DECIMAL(20,4)))
					 FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					 WHERE p.post_type = %s
					 AND p.post_status IN ('order_paid','order_shipped','order_closed')
					 AND pm.meta_key = %s",
					'mp_order',
					'mp_order_total'
				)
			);
			$revenue_total += (float) $revenue;

			$open_query = new WP_Query( array(
				'post_type'      => 'mp_order',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array(
					array(
						'key'     => '_mp_withdrawal_status',
						'value'   => array( 'requested', 'in_review' ),
						'compare' => 'IN',
					),
				),
			) );
			$withdrawal_open += (int) $open_query->found_posts;

			$bundle_query = new WP_Query( array(
				'post_type'      => 'mp_order',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array(
					array(
						'key'     => '_mp_network_multishop_checkout_mode',
						'value'   => 'bundle',
						'compare' => '=',
					),
				),
			) );
			$bundle_orders += (int) $bundle_query->found_posts;

			$split_query = new WP_Query( array(
				'post_type'      => 'mp_order',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array(
					array(
						'key'     => '_mp_network_multishop_checkout_mode',
						'value'   => 'split',
						'compare' => '=',
					),
				),
			) );
			$split_orders += (int) $split_query->found_posts;

			$payout_query = new WP_Query( array(
				'post_type'      => 'mp_order',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array(
					array(
						'key'     => '_mp_network_payout_status',
						'value'   => 'pending',
						'compare' => '=',
					),
				),
			) );
			$payout_pending += (int) $payout_query->found_posts;

			restore_current_blog();
		}

		return array(
			'active_shops'     => $active_shops,
			'orders_total'     => $orders_total,
			'revenue_total'    => $revenue_total,
			'withdrawal_open'  => $withdrawal_open,
			'bundle_orders'    => $bundle_orders,
			'split_orders'     => $split_orders,
			'payout_pending'   => $payout_pending,
			'indexed_products' => $indexed_products,
			'support_open'     => 0,
			'support_mode_label' => __( 'Deaktiviert', 'mp' ),
			'generated_at'     => time(),
		);
	}

	/**
	 * Pages for network cart (marketplace,marketplace/categories etc)
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_network_pages() {
		$metabox = new PSOURCE_Metabox( array(
			'id'               => 'mp-settings-network-pages-slugs',
			'page_slugs'       => array( 'network-store-settings' ),
			'title'            => __( 'Netzwerkmarktplatz Seiten', 'mp' ),
			'site_option_name' => 'mp_network_settings',
			'order'            => 2
		) );

		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[network_store_page]',
			'label'       => array( 'text' => __( 'Marktplatz', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[network_categories]',
			'label'       => array( 'text' => __( 'Netzwerkartikel Kategorien', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[network_tags]',
			'label'       => array( 'text' => __( 'Shopnetzwerk Tags', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[network_customer_hub]',
			'label'       => array( 'text' => __( 'Zentrale Kundenseite', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Optional: Wähle eine Seite', 'mp' ),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[network_shop_performance]',
			'label'       => array( 'text' => __( 'Shopuser Performance', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Optional: Wähle eine Seite', 'mp' ),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[network_shop_profile]',
			'label'       => array( 'text' => __( 'Shop-Profilseite (Mainshop)', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Optional: Wähle eine Seite', 'mp' ),
		) );
	}

	/**
	 * Display "create page" button next to a given field
	 *
	 * @since 1.0
	 * @access public
	 * filter psource_field/after_field
	 */
	public function display_create_page_button( $html, $field ) {
		switch ( $field->args['original_name'] ) {
			case 'pages[network_store_page]' :
				$type = 'network_store_page';
				break;

			case 'pages[network_categories]' :
				$type = 'network_categories';
				break;

			case 'pages[network_tags]' :
				$type = 'network_tags';
				break;

			case 'pages[network_customer_hub]' :
				$type = 'network_customer_hub';
				break;

			case 'pages[network_shop_performance]' :
				$type = 'network_shop_performance';
				break;

			case 'pages[network_shop_profile]' :
				$type = 'network_shop_profile';
				break;

		}

		if ( isset( $type ) ) {
			if ( ( $post_id = mp_get_network_setting( "pages->$type" ) ) && get_post_status( $post_id ) !== false ) {
				return '<a target="_blank" class="button mp-edit-page-button" href="' . add_query_arg( array(
					'post'   => $post_id,
					'action' => 'edit',
				), get_admin_url( null, 'post.php' ) ) . '">' . __( 'Seite bearbeiten', 'mp' ) . '</a>';
			} else {
				return '<a class="button mp-create-page-button" href="' . wp_nonce_url( get_admin_url( null, 'admin-ajax.php?action=mp_create_store_page&type=' . $type ), 'mp_create_store_page' ) . '">' . __( 'Seite erstellen', 'mp' ) . '</a>';
			}
		}

		return $html;
	}

	/**
	 * Print scripts for creating store page
	 *
	 * @since 1.0
	 * @access public
	 * @action psource_field/print_scripts
	 */
	public function create_store_page_js( $field ) {
		static $script_printed = false;

		$valid_fields = array(
			'pages[network_store_page]',
			'pages[network_categories]',
			'pages[network_tags]',
			'pages[network_customer_hub]',
			'pages[network_shop_performance]',
			'pages[network_shop_profile]',
		);

		if ( ! in_array( $field->args['original_name'], $valid_fields, true ) ) {
			return;
		}

		if ( $script_printed ) {
			return;
		}

		$script_printed = true;
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				$(document).off('click.mpCreatePage', '.mp-create-page-button').on('click.mpCreatePage', '.mp-create-page-button', function (e) {
					e.preventDefault();

					var $this = $(this),
						$select = $this.siblings('[name^="pages"]');

					if ($this.data('mpCreating')) {
						return;
					}

					$this.data('mpCreating', true);

					$this.addClass('working');

					$.getJSON($this.attr('href'), function (resp) {
						if (resp.success) {
							var selectEl = $select[0];
							var postId = String(resp.data.post_id);
							var optionText = resp.data.select2_value.indexOf('->') > -1 ? resp.data.select2_value.split('->')[1] : resp.data.select2_value;
							if (selectEl.slimSelect) {
								var newData = selectEl.slimSelect.getData().concat({ value: postId, text: optionText });
								selectEl.slimSelect.setData(newData);
								selectEl.slimSelect.setSelected(postId);
							} else {
								$select.val(postId).trigger('change');
							}
							$this.removeData('mpCreating');
							$this.isWorking(false).replaceWith(resp.data.button_html);
						} else {
							alert('<?php _e( 'Fehler beim erstellen der Seite, versuch es bitte noch einmal.', 'mp' ); ?>');
							$this.removeData('mpCreating');
							$this.isWorking(false);
						}
					}).fail(function () {
						$this.removeData('mpCreating');
						$this.isWorking(false);
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Catch deprecated functions
	 */
	public function __call( $method, $args ) {
		switch ( $method ) {
			case 'is_main_site' :
				_deprecated_function( $method, '3.0', 'mp_is_main_site' );

				return call_user_func_array( 'mp_is_main_site', $args );
				break;

			default :
				trigger_error( 'Error! MP_Admin_Multisite doesn\'t have a ' . $method . ' method.', E_USER_ERROR );
				break;
		}
	}

	/**
	 * Update blog_public state to 1 on blog status change
	 *
	 * @since 3.1.2
	 * @access public
	 *
	 */
	public function set_blog_public_global_products( $blog_id ){

		if( is_integer( $blog_id ) ){

			global $wpdb;

			$global_products_table = "{$wpdb->base_prefix}mp_products";
			$global_term_relationships_table = "{$wpdb->base_prefix}mp_term_relationships";

			$wpdb->update( $global_products_table,
				array(
					'blog_public' => 1
				),
				array(
					'blog_id' => $blog_id
				),
				array( '%d' ),
				array( '%d' )
			);

			$wpdb->update( $global_term_relationships_table,
				array(
					'public' => 1
				),
				array(
					'blog_id' => $blog_id
				),
				array( '%d' ),
				array( '%d' )
			);

		}

	}

	/**
	 * Update blog_public state to 0 on blog status change
	 */
	public function unset_blog_public_global_products( $blog_id ){

		if( is_integer( $blog_id ) ){

			global $wpdb;

			$global_products_table = "{$wpdb->base_prefix}mp_products";
			$global_term_relationships_table = "{$wpdb->base_prefix}mp_term_relationships";

			$wpdb->update( $global_products_table,
				array(
					'blog_public' => 0
				),
				array(
					'blog_id' => $blog_id
				),
				array( '%d' ),
				array( '%d' )
			);

			$wpdb->update( $global_term_relationships_table,
				array(
					'public' => 0
				),
				array(
					'blog_id' => $blog_id
				),
				array( '%d' ),
				array( '%d' )
			);

		}

	}

}

$GLOBALS['mp_wpmu'] = MP_Admin_Multisite::get_instance();