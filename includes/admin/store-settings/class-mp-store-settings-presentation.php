<?php

class MP_Store_Settings_Presentation {

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
			self::$_instance = new MP_Store_Settings_Presentation();
		}

		return self::$_instance;
	}

	/**
	 * Constructor function
	 *
	 * @since 1.0
	 * @access private
	 */
	private function __construct() {
		add_filter( 'psource_field/after_field', array( &$this, 'display_create_page_button' ), 10, 2 );
		add_action( 'psource_field/print_scripts', array( &$this, 'create_store_page_js' ) );

		if ( mp_get_get_value( 'page' ) == 'store-settings-presentation' ) {
			add_action( 'init', array( &$this, 'init_metaboxes' ) );
			add_action( 'psource_metabox/after_settings_metabox_saved', array( &$this, 'link_store_pages' ) );
		}
	}

	/**
	 *
	 * @param $psource_metabox
	 */
	public function link_store_pages( $psource_metabox ) {
		if ( $psource_metabox->args['id'] == 'mp-settings-presentation-pages-slugs' ) {
			$pages = mp_get_post_value( 'pages' );
			foreach ( $pages as $type => $page ) {
				MP_Pages_Admin::get_instance()->save_store_page_value( $type, $page, false );
			}
		}
	}

	/**
	 * Print scripts for creating store page
	 *
	 * @since 1.0
	 * @access public
	 * @action psource_field/print_scripts
	 */
	public function create_store_page_js( $field ) {
		if ( $field->args['original_name'] !== 'pages[store]' ) {
			return;
		}
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function () {
			document.querySelectorAll('.mp-create-page-button').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					btn.classList.add('working');
					fetch(btn.getAttribute('href'))
						.then(response => response.json())
						.then(resp => {
							if (resp.success) {
								// Dropdown aktualisieren (SlimSelect oder native)
								var select = btn.parentNode.querySelector('[name^="pages"]');
								if (select) {
									select.value = resp.data.post_id;
									if (window.SlimSelect && select.slim) {
										select.slim.set(resp.data.post_id);
									} else {
										// Native select: Trigger change event
										var event = new Event('change', { bubbles: true });
										select.dispatchEvent(event);
									}
								}
								btn.classList.remove('working');
								btn.outerHTML = resp.data.button_html;
							} else {
								alert('<?php _e( 'Beim Erstellen der Store-Seite ist ein Fehler aufgetreten. Bitte versuche es erneut.', 'mp' ); ?>');
								btn.classList.remove('working');
							}
						});
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Initialize metaboxes
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_metaboxes() {
		$this->init_general_settings();
		$this->init_product_page_settings();
		$this->init_button_text_settings();
		$this->init_related_product_settings();
		$this->init_product_list_settings();
		$this->init_store_pages_slugs_settings();
		$this->init_miscellaneous_settings();
	}

	/**
	 * Gets the appropriate image size label for a given size.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string $size The image size.
	 *
	 * @return string
	 */
	public function get_image_size_label( $size ) {
		$width  = get_option( "{$size}_size_w" );
		$height = get_option( "{$size}_size_h" );
		$crop   = get_option( "{$size}_crop" );

		return "{$width} x {$height} (" . ( ( $crop ) ? __( 'beschnitten', 'mp' ) : __( 'nicht beschnitten', 'mp' ) ) . ')';
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
			case 'pages[store]' :
				$type = 'store';
				break;

			case 'pages[products]' :
				$type = 'products';
				break;

			case 'pages[cart]' :
				$type = 'cart';
				break;

			case 'pages[checkout]' :
				$type = 'checkout';
				break;

			case 'pages[order_status]' :
				$type = 'order_status';
				break;
		}

		if ( isset( $type ) ) {
			if ( ( $post_id = mp_get_setting( "pages->$type" ) ) && get_post_status( $post_id ) !== false ) {
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
	 * Init the store page/slugs settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_store_pages_slugs_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-presentation-pages-slugs',
			'page_slugs'  => array( 'store-settings-presentation', 'store-settings_page_store-settings-presentation' ),
			'title'       => __( 'Shop-Seiten', 'mp' ),
			'option_name' => 'mp_settings',
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[store]',
			'label'       => array( 'text' => __( 'Shop-Basis', 'mp' ) ),
			'desc'        => __( 'Diese Seite wird als Basis für deinen Shop verwendet.', 'mp' ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[products]',
			'label'       => array( 'text' => __( 'Produktliste', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[cart]',
			'label'       => array( 'text' => __( 'Warenkorb', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[checkout]',
			'label'       => array( 'text' => __( 'Kasse', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'post_select', array(
			'name'        => 'pages[order_status]',
			'label'       => array( 'text' => __( 'Bestellstatus', 'mp' ) ),
			'query'       => array( 'post_type' => 'page', 'orderby' => 'title', 'order' => 'ASC' ),
			'placeholder' => __( 'Wähle eine Seite', 'mp' ),
			'validation'  => array(
				'required' => true,
			),
		) );
	}

	/**
	 * Init the product list settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_product_list_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-presentation-product-list',
			'page_slugs'  => array( 'store-settings-presentation', 'store-settings_page_store-settings-presentation' ),
			'title'       => __( 'Produktliste/Grid Einstellungen', 'mp' ),
			'desc'        => __( 'Einstellungen zur Anzeige von Produktlisten/Grids.', 'mp' ),
			'option_name' => 'mp_settings',
		) );
		$metabox->add_field( 'radio_group', array(
			'name'    => 'list_view',
			'label'   => array( 'text' => __( 'Produktlayout', 'mp' ) ),
			'options' => array(
				'list' => __( 'Als Liste anzeigen', 'mp' ),
				'grid' => __( 'Als Grid anzeigen', 'mp' ),
			),
			'default_value' => 'list',
		) );
		$metabox->add_field( 'radio_group', array(
			'name'          => 'per_row',
			'label'         => array( 'text' => __( 'Wie viele Produkte pro Reihe?', 'mp' ) ),
			'desc'          => __( 'Lege die Anzahl der Produkte fest, die in einer Grid-Reihe angezeigt werden, um dein Theme optimal anzupassen', 'mp' ),
			'default_value' => 3,
			'options'       => array(
				1 => __( 'Eins', 'mp' ),
				2 => __( 'Zwei', 'mp' ),
				3 => __( 'Drei', 'mp' ),
				4 => __( 'Vier', 'mp' ),
			),
			'conditional'   => array(
				'name'   => 'list_view',
				'value'  => 'grid',
				'action' => 'show',
			),
		) );
		$metabox->add_field( 'radio_group', array(
			'name'    => 'list_button_type',
			'label'   => array( 'text' => __( 'In den Warenkorb Aktion', 'mp' ) ),
			'desc'    => __( 'MarketPress unterstützt zwei "Flows" zum Hinzufügen von Produkten zum Warenkorb. Nach dem Hinzufügen eines Produkts zu ihrem Warenkorb können zwei Dinge passieren:', 'mp' ),
			'options' => array(
				'addcart' => __( 'Auf der aktuellen Produktseite bleiben', 'mp' ),
				'buynow'  => __( 'Zur Warenkorbseite für sofortigen Checkout weiterleiten', 'mp' ),
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'show_thumbnail',
			'label'   => array( 'text' => __( 'Produktbild anzeigen?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
		) );

		$metabox->add_field( 'checkbox', array(
			'name'    => 'show_thumbnail_placeholder',
			'label'   => array( 'text' => __( 'Standard-Platzhalterbild anzeigen, wenn kein Produktbild verfügbar ist?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
		) );

		$metabox->add_field( 'file', array(
			'name'        => 'thumbnail_placeholder',
			'label'       => array( 'text' => __( 'Wähle ein Standard-Platzhalterbild aus, wenn kein Produktbild verfügbar ist (wenn leer, wird das eingebaute Bild des Plugins verwendet)', 'mp' ) ),
			'message'     => __( 'Ja', 'mp' ),
			'conditional' => array(
				'name'   => 'show_thumbnail_placeholder',
				'value'  => '1',
				'action' => 'show',
			),
		) );

		$metabox->add_field( 'select', array(
			'name'        => 'list_img_size',
			'label'       => array( 'text' => __( 'Bildgröße', 'mp' ) ),
			'options'     => array(
				'thumbnail' => sprintf( __( 'Thumbnail - %s', 'mp' ), $this->get_image_size_label( 'thumbnail' ) ),
				'medium'    => sprintf( __( 'Mittel - %s', 'mp' ), $this->get_image_size_label( 'medium' ) ),
				'large'     => sprintf( __( 'Groß - %s', 'mp' ), $this->get_image_size_label( 'large' ) ),
				'custom'    => __( 'Benutzerdefiniert', 'mp' ),
			),
			'conditional' => array(
				'name'   => 'show_thumbnail',
				'value'  => '1',
				'action' => 'show',
			),
		) );
		$custom_size = $metabox->add_field( 'complex', array(
			'name'        => 'list_img_size_custom',
			'label'       => array( 'text' => __( 'Benutzerdefinierte Bildgröße', 'mp' ) ),
			'conditional' => array(
				'operator' => 'AND',
				'action'   => 'show',
				array(
					'name'  => 'show_thumbnail',
					'value' => '1',
				),
				array(
					'name'  => 'list_img_size',
					'value' => 'custom',
				)
			),
		) );

		if ( $custom_size instanceof PSOURCE_Field ) {
			$custom_size->add_field( 'text', array(
				'name'       => 'width',
				'label'      => array( 'text' => __( 'Breite', 'mp' ) ),
				'validation' => array(
					'required' => true,
					'digits'   => true,
					'min'      => 0,
				),
			) );
			$custom_size->add_field( 'text', array(
				'name'       => 'height',
				'label'      => array( 'text' => __( 'Höhe', 'mp' ) ),
				'validation' => array(
					'required' => true,
					'digits'   => true,
					'min'      => 0,
				),
			) );
		}

		$metabox->add_field( 'radio_group', array(
			'name'        => 'image_alignment_list',
			'label'       => array( 'text' => __( 'Bildausrichtung', 'mp' ) ),
			'options'     => array(
				//'alignnone'		 => __( 'None', 'mp' ),
				//'aligncenter'	 => __( 'Center', 'mp' ),
				'alignleft'  => __( 'Links', 'mp' ),
				'alignright' => __( 'Rechts', 'mp' ),
			),
			'default_value' => 'alignleft',
			'conditional' => array(
				'operator' => 'AND',
				'action'   => 'show',
				array(
					'name'  => 'show_thumbnail',
					'value' => '1',
				),
				array(
					'name'  => 'list_view',
					'value' => 'list',
				),
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'show_excerpts',
			'label'   => array( 'text' => __( 'Auszüge anzeigen?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'excerpts_length',
			'label'         => array( 'text' => __( 'Länge der Auszüge', 'mp' ) ),
			'conditional'   => array(
				'name'   => 'show_excerpts',
				'value'  => '1',
				'action' => 'show',
			),
			'validation'    => array(
				'required' => true,
				'digits'   => 1,
			),
			'default_value' => 55
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'paginate',
			'label'   => array( 'text' => __( 'Produkte paginieren?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'text', array(
			'name'        => 'per_page',
			'label'       => array( 'text' => __( 'Produkte pro Seite', 'mp' ) ),
			'conditional' => array(
				'name'   => 'paginate',
				'value'  => '1',
				'action' => 'show',
			),
			'validation'  => array(
				'required' => true,
				'digits'   => 1,
			),
		) );
		$metabox->add_field( 'select', array(
			'name'    => 'order_by',
			'label'   => array( 'text' => __( 'Produkte sortieren nach', 'mp' ) ),
			'options' => array(
				'title'  => __( 'Produktname', 'mp' ),
				'date'   => __( 'Veröffentlichungsdatum', 'mp' ),
				'ID'     => __( 'Produkt-ID', 'mp' ),
				'author' => __( 'Produktautor', 'mp' ),
				'sales'  => __( 'Anzahl der Verkäufe', 'mp' ),
				'price'  => __( 'Produktpreis', 'mp' ),
				'rand'   => __( 'Zufällig', 'mp' ),
			),
		) );
		$metabox->add_field( 'radio_group', array(
			'name'    => 'order',
			'label'   => array( 'text' => __( 'Sortierreihenfolge', 'mp' ) ),
			'options' => array(
				'DESC' => __( 'Absteigend', 'mp' ),
				'ASC'  => __( 'Aufsteigend', 'mp' ),
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'hide_products_filter',
			'label'   => array( 'text' => __( 'Produktefilter ausblenden?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
			'desc'    => __( 'Wenn aktiviert, können Benutzer Produkte nicht nach Kategorie filtern und/oder nach Veröffentlichungsdatum/Name/Preis sortieren.', 'mp' ),
			'default_value' => 0
		) );
	}

	public function init_miscellaneous_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-miscellaneous-product-list',
			'page_slugs'  => array( 'store-settings-presentation', 'store-settings_page_store-settings-presentation' ),
			'title'       => __( 'Verschiedene Einstellungen', 'mp' ),
			'desc'        => __( '', 'mp' ),
			'option_name' => 'mp_settings',
		) );

		$metabox->add_field( 'text', array(
			'name'          => 'per_page_order_history',
			'label'         => array( 'text' => __( 'Bestellstatuseinträge pro Seite', 'mp' ) ),
			'default_value' => get_option( 'posts_per_page' ),
			'validation'    => array(
				'required' => true,
				'digits'   => 1,
			),
		) );
	}

	/**
	 * Init the related product settings
	 *
	 * @since 1.0
	 * @access public
	 */
	/**
	 * Configurable labels for each buy-button state.
	 *
	 * @since 1.0.1
	 * @access public
	 */
	public function init_button_text_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-presentation-button-texts',
			'page_slugs'  => array( 'store-settings-presentation', 'store-settings_page_store-settings-presentation' ),
			'title'       => __( 'Kaufen-Button Texte', 'mp' ),
			'desc'        => __( 'Passe den Text des Kaufen-Buttons für jeden Produktzustand an. Lasse ein Feld leer, um den internen Standardtext zu verwenden.', 'mp' ),
			'option_name' => 'mp_settings',
		) );

		// --- Standard-Zustände ---
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_addcart',
			'label'         => array( 'text' => __( 'In den Warenkorb', 'mp' ) ),
			'desc'          => __( 'Button-Text im Modus „Auf der Produktseite bleiben". Standard: „In den Warenkorb"', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_buynow',
			'label'         => array( 'text' => __( 'Jetzt Kaufen', 'mp' ) ),
			'desc'          => __( 'Button-Text im Modus „Sofort zum Warenkorb weiterleiten". Standard: „Jetzt kaufen"', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_choose_options',
			'label'         => array( 'text' => __( 'Optionen wählen', 'mp' ) ),
			'desc'          => __( 'Button-Text für Produkte mit Variationen (z.B. Größe, Farbe). Standard: „Wähle Optionen"', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_out_of_stock',
			'label'         => array( 'text' => __( 'Nicht vorrätig', 'mp' ) ),
			'desc'          => __( 'Anzeigetext wenn ein Produkt nicht auf Lager ist. Standard: „Ausverkauft"', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_external',
			'label'         => array( 'text' => __( 'Externer Produkt-Link', 'mp' ) ),
			'desc'          => __( 'Link-Text für externe Produkte (öffnen eine externe URL). Standard: „Zum Anbieter »"', 'mp' ),
			'default_value' => '',
		) );

		// --- Download-Produkte (Typ: Digital) ---
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_download_addcart',
			'label'         => array( 'text' => __( 'Bezahlter Download – „In den Warenkorb"-Modus', 'mp' ) ),
			'desc'          => __( 'Button-Text für digitale Produkte (Typ: Digital, Preis > 0) im Addcart-Modus. Standard: „Kaufen &amp; Herunterladen"', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_download_buynow',
			'label'         => array( 'text' => __( 'Bezahlter Download – „Jetzt Kaufen"-Modus', 'mp' ) ),
			'desc'          => __( 'Button-Text für digitale Produkte (Typ: Digital, Preis > 0) im Buynow-Modus. Standard: „Kaufen &amp; Herunterladen"', 'mp' ),
			'default_value' => '',
		) );

		// --- Gratis-Produkte ---
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_free',
			'label'         => array( 'text' => __( 'Gratis-Produkt (nicht digital, Preis 0)', 'mp' ) ),
			'desc'          => __( 'Button-Text für physische/virtuelle Produkte mit Preis 0. Standard: „Kostenlos"', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'btn_text_free_download',
			'label'         => array( 'text' => __( 'Gratis-Download (digital, Preis 0)', 'mp' ) ),
			'desc'          => __( 'Button-Text für digitale Produkte mit Preis 0, z.B. „Jetzt herunterladen" oder „Gratis downloaden". Standard: „Kostenloser Download"', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'price_text_free',
			'label'         => array( 'text' => __( 'Preistext bei 0,00', 'mp' ) ),
			'desc'          => __( 'Text für die Produkt-Preisanzeige bei Preis 0 (z.B. „Kostenlos", „Free", „Demo"). Leer lassen für Standard-Formatierung als Währung.', 'mp' ),
			'default_value' => '',
		) );
		$metabox->add_field( 'text', array(
			'name'          => 'price_text_free_shipping',
			'label'         => array( 'text' => __( 'Versandtext bei 0,00', 'mp' ) ),
			'desc'          => __( 'Text für die Versandanzeige bei Versandkosten 0 (z.B. „Kostenlos“, „Versand gratis"). Leer lassen für Standard-Formatierung als Währung.', 'mp' ),
			'default_value' => __( 'Kostenloser Versand', 'mp' ),
		) );
	}

	public function init_related_product_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-presentation-product-related',
			'page_slugs'  => array( 'store-settings-presentation', 'store-settings_page_store-settings-presentation' ),
			'title'       => __( 'Einstellungen für verwandte Produkte', 'mp' ),
			'option_name' => 'mp_settings',
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'related_products[show]',
			'label'   => array( 'text' => __( 'Verwandte Produkte anzeigen?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'text', array(
			'name'        => 'related_products[show_limit]',
			'label'       => array( 'text' => __( 'Limit für verwandte Produkte', 'mp' ) ),
			'conditional' => array(
				'name'   => 'related_products[show]',
				'value'  => '1',
				'action' => 'show',
			),
			'validation'  => array(
				'required' => true,
				'digits'   => 1,
			),
		) );
		$metabox->add_field( 'select', array(
			'name'        => 'related_products[relate_by]',
			'label'       => array( 'text' => __( 'Verwandte Produkte nach', 'mp' ) ),
			'options'     => array(
				'both'     => __( 'Kategorie &amp; Tags', 'mp' ),
				'category' => __( 'Nur Kategorie', 'mp' ),
				'tags'     => __( 'Nur Tags', 'mp' ),
			),
			'conditional' => array(
				'name'   => 'related_products[show]',
				'value'  => '1',
				'action' => 'show',
			),
		) );
		$metabox->add_field( 'radio_group', array(
			'name'        => 'related_products[view]',
			'label'       => array( 'text' => __( 'Layout für verwandte Produkte', 'mp' ) ),
			'message'     => __( 'Ja', 'mp' ),
			'options'     => array(
				'list' => __( 'Als Liste anzeigen', 'mp' ),
				'grid' => __( 'Als Raster anzeigen', 'mp' ),
			),
			'default_value' => 'list',
			'conditional' => array(
				'name'   => 'related_products[show]',
				'value'  => '1',
				'action' => 'show',
			),
		) );

		$metabox->add_field( 'radio_group', array(
			'name'          => 'related_products[per_row]',
			'label'         => array( 'text' => __( 'Wie viele Produkte pro Reihe?', 'mp' ) ),
			'desc'          => __( 'Legen Sie die Anzahl der Produkte fest, die in einer Rasterreihe angezeigt werden, um Ihr Theme optimal anzupassen', 'mp' ),
			'default_value' => 3,
			'options'       => array(
				1 => __( 'Eins', 'mp' ),
				2 => __( 'Zwei', 'mp' ),
				3 => __( 'Drei', 'mp' ),
				4 => __( 'Vier', 'mp' ),
			),
			'conditional'   => array(
				'name'   => 'related_products[view]',
				'value'  => 'grid',
				'action' => 'show',
			),
		) );
	}

	/**
	 * Init the general settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_product_page_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-presentation-product-page',
			'page_slugs'  => array( 'store-settings-presentation', 'store-settings_page_store-settings-presentation' ),
			'title'       => __( 'Produktseiten-Einstellungen', 'mp' ),
			'desc'        => __( 'Einstellungen zur Anzeige einzelner Produktseiten.', 'mp' ),
			'option_name' => 'mp_settings',
		) );
		$metabox->add_field( 'radio_group', array(
			'name'    => 'product_button_type',
			'label'   => array( 'text' => __( 'Aktion "In den Warenkorb"', 'mp' ) ),
			'desc'    => __( 'MarketPress unterstützt zwei "Flows" für das Hinzufügen von Produkten zum Warenkorb. Nach dem Hinzufügen eines Produkts zu ihrem Warenkorb können zwei Dinge passieren:', 'mp' ),
			'options' => array(
				'addcart' => __( 'Auf der aktuellen Produktseite bleiben', 'mp' ),
				'buynow'  => __( 'Zur Warenkorbseite für sofortigen Checkout weiterleiten', 'mp' ),
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'show_quantity',
			'label'   => array( 'text' => __( 'Mengenfeld anzeigen?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
			'desc'    => __( 'Wenn aktiviert, können Benutzer auswählen, wie viele Produkte sie kaufen möchten, bevor sie sie in den Warenkorb legen. Wenn nicht aktiviert, kann die Menge später auf der Warenkorbseite geändert werden.', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'          => 'show_single_excerpt',
			'label'         => array( 'text' => __( 'Auszug anzeigen?', 'mp' ) ),
			'message'       => __( 'Ja', 'mp' ),
			'desc'          => __( 'Wenn aktiviert, wird der Beschreibungsauszug über dem "In den Warenkorb"-Button angezeigt.', 'mp' ),
			'default_value' => 1,
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'show_single_categories',
			'label'   => array( 'text' => __( 'Kategorienliste anzeigen?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
			'desc'    => __( 'Wenn aktiviert, wird die Kategorienliste auf der Produktseite angezeigt.', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'show_single_tags',
			'label'   => array( 'text' => __( 'Tags-Liste anzeigen?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
			'desc'    => __( 'Wenn aktiviert, wird die Tags-Liste auf der Produktseite angezeigt.', 'mp' ),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'    => 'show_img',
			'label'   => array( 'text' => __( 'Produktbild anzeigen?', 'mp' ) ),
			'message' => __( 'Ja', 'mp' ),
		) );
		$metabox->add_field( 'select', array(
			'name'        => 'product_img_size',
			'label'       => array( 'text' => __( 'Bildgröße', 'mp' ) ),
			'options'     => array(
				'thumbnail' => sprintf( __( 'Thumbnail - %s', 'mp' ), $this->get_image_size_label( 'thumbnail' ) ),
				'medium'    => sprintf( __( 'Mittel - %s', 'mp' ), $this->get_image_size_label( 'medium' ) ),
				'large'     => sprintf( __( 'Groß - %s', 'mp' ), $this->get_image_size_label( 'large' ) ),
				'custom'    => __( 'Benutzerdefiniert', 'mp' ),
			),
			'conditional' => array(
				'name'   => 'show_img',
				'value'  => '1',
				'action' => 'show',
			),
		) );
		$custom_size = $metabox->add_field( 'complex', array(
			'name'        => 'product_img_size_custom',
			'label'       => array( 'text' => __( 'Benutzerdefinierte Bildgröße', 'mp' ) ),
			'conditional' => array(
				'operator' => 'AND',
				'action'   => 'show',
				array(
					'name'  => 'show_img',
					'value' => '1',
				),
				array(
					'name'  => 'product_img_size',
					'value' => 'custom',
				)
			),
		) );

		if ( $custom_size instanceof PSOURCE_Field ) {
			$custom_size->add_field( 'text', array(
				'name'       => 'width',
				'label'      => array( 'text' => __( 'Breite', 'mp' ) ),
				'validation' => array(
					'required' => true,
					'digits'   => true,
					'min'      => 0,
				),
			) );
			$custom_size->add_field( 'text', array(
				'name'       => 'height',
				'label'      => array( 'text' => __( 'Höhe', 'mp' ) ),
				'validation' => array(
					'required' => true,
					'digits'   => true,
					'min'      => 0,
				),
			) );
		}

		$metabox->add_field( 'radio_group', array(
			'name'        => 'image_alignment_single',
			'label'       => array( 'text' => __( 'Bildausrichtung', 'mp' ) ),
			'options'     => array(
				//'alignnone'		 => __( 'None', 'mp' ),
				'alignleft'   => __( 'Links', 'mp' ),
				'aligncenter' => __( 'Zentriert', 'mp' ),
				'alignright'  => __( 'Rechts', 'mp' ),
			),
			'default_value' => 'alignleft',
			'conditional' => array(
				'name'   => 'show_img',
				'value'  => '1',
				'action' => 'show',
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'        => 'disable_large_image',
			'label'       => array( 'text' => __( 'Großes Bild deaktivieren?', 'mp' ) ),
			'message'     => __( 'Ja', 'mp' ),
			'conditional' => array(
				'name'   => 'show_img',
				'value'  => '1',
				'action' => 'show',
			),
		) );
		$metabox->add_field( 'checkbox', array(
			'name'        => 'show_lightbox',
			'label'       => array( 'text' => __( 'Eingebaute Lightbox für Bilder verwenden?', 'mp' ) ),
			'desc'        => __( 'Wenn Du Konflikte mit der Lightbox-Bibliothek Deines Themes oder eines anderen Plugins hast, solltest Du diese Option deaktivieren.', 'mp' ),
			'message'     => __( 'Ja', 'mp' ),
			'conditional' => array(
				'operator' => 'AND',
				'action'   => 'show',
				array(
					'name'  => 'show_img',
					'value' => '1',
				),
				array(
					'name'  => 'disable_large_image',
					'value' => '-1',
				),
			),
		) );
	}

	/**
	 * Init the general settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init_general_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-settings-presentation-general',
			'page_slugs'  => array( 'store-settings-presentation', 'store-settings_page_store-settings-presentation' ),
			'title'       => __( 'Allgemeine Design-Einstellungen', 'mp' ),
			'option_name' => 'mp_settings',
		) );

		$metabox->add_field( 'radio_group', array(
			'name'    => 'store_theme',
			'desc'    => sprintf( __( 'Diese Option ändert die eingebauten CSS-Stile für die Shop-Seiten. Für einen benutzerdefinierten CSS-Stil speichere Deine CSS-Datei mit der <strong>/* MarketPress Style: Dein CSS-Theme-Name hier */</strong> Kopfzeile im <strong>"%s"</strong> Ordner und sie wird in dieser Liste angezeigt, sodass Du sie auswählen kannst. Du solltest "Keine" auswählen, wenn Du keine benutzerdefinierten CSS-Stile verwenden möchtest oder wenn Du Standard-Theme-Vorlagen oder benutzerdefinierte Theme-Vorlagen und CSS verwenden, um Dein eigenes einzigartiges Shop-Design zu erstellen. Weitere Informationen zu benutzerdefinierten Theme-Vorlagen findest Du <a target="_blank" href="%s">hier &raquo;</a>.', 'mp' ), trailingslashit( WP_CONTENT_DIR ) . 'marketpress-styles/', mp_plugin_url( 'ui/themes/Theming_MarketPress.txt' ) ),
			'label'   => array( 'text' => __( 'Store Style', 'mp' ) ),
			'options' => mp_get_theme_list() + array(
				'default' => __( 'Standard - Verwende Standard-CSS-Stile', 'mp' ),
				'none' => __( 'Keine - Ohne spezielle CSS-Stile', 'mp' ),
				),
			'width'   => '50%',
		) );
		/*$metabox->add_field( 'checkbox', array(
			'name'		 => 'show_purchase_breadcrumbs',
			'label'		 => array( 'text' => __( 'Show Breadcrumbs?', 'mp' ) ),
			'message'	 => __( 'Yes', 'mp' ),
			'desc'		 => __( 'Shows previous, current and next steps when a customer is checking out -- shown below the title.', 'mp' ),
		) );*/
	}

}

MP_Store_Settings_Presentation::get_instance();
