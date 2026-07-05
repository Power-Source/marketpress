<?php

class MP_Store_Settings_Admin {

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
			self::$_instance = new MP_Store_Settings_Admin();
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
    mp_include_dir( mp_plugin_dir( 'includes/admin/store-settings/' ) );

    // Add menu items
    add_action( 'admin_menu', array( &$this, 'add_menu_items' ) );
    // Print scripts for setting the active admin menu item when on the product tag page
    add_action( 'admin_footer', array( &$this, 'print_product_tag_scripts' ) );
    // Print scripts for setting the active admin menu item when on the product category page
    add_action( 'admin_footer', array( &$this, 'print_product_category_scripts' ) );
    // Move product categories and tags to MarketPress Einstellungen menu
    add_action( 'parent_file', array( &$this, 'set_menu_item_parent' ) );

    if ( mp_get_get_value( 'action' ) == 'mp_add_product_attribute' || mp_get_get_value( 'action' ) == 'mp_edit_product_attribute' ) {
        MP_Product_Attributes_Admin::add_product_attribute_metaboxes();
        add_action( 'psource_metabox/before_save_fields/mp-store-settings-product-attributes-add', array( 'MP_Product_Attributes_Admin', 'save_product_attribute' ) );

        // Dynamisch die aktuelle Screen-ID holen und Action registrieren
        add_action( 'current_screen', function() {
            $screen = get_current_screen();
            if ( $screen && strpos( $screen->id, 'productattributes' ) !== false ) {
                add_action( $screen->id, array( &$this, 'display_settings_form' ) );
            }
        });

        if ( mp_get_get_value( 'action' ) == 'mp_edit_product_attribute' ) {
            add_filter( 'psource_field/before_get_value', array( 'MP_Product_Attributes_Admin', 'get_product_attribute_value' ), 10, 4 );
        }
    } else {
    // Dynamisch für alle relevanten Screens die Action registrieren
    add_action( 'current_screen', function() {
        $screen = get_current_screen();
		$current_page = (string) mp_get_get_value( 'page', '' );
        // NICHT für Addon-Detailseite!
        if (
            $screen
            && strpos( $screen->id, 'store-settings' ) !== false
			&& 'store-settings-shop-profile' !== $current_page
            && (
                $screen->id !== 'store-settings_page_store-settings-addons'
                || ( $screen->id === 'store-settings_page_store-settings-addons' && empty($_GET['addon']) )
            )
        ) {
            add_action( $screen->id, array( &$this, 'display_settings_form' ) );
        }
        if ( $screen && strpos( $screen->id, 'productattributes' ) !== false ) {
            add_action( $screen->id, array( 'MP_Product_Attributes_Admin', 'display_product_attributes' ) );
        }
    });
}

    add_action( 'psource_metabox/after_settings_metabox_saved/mp-settings-presentation-pages-slugs', array( $this, 'reset_store_pages_cache' ) );
}

	/**
	 * Set menu item parent file
	 *
	 * @since 1.0
	 * @access public
	 * @action parent_file
	 */
	public function set_menu_item_parent( $parent_file ) {
		switch ( get_current_screen()->taxonomy ) {
			case 'product_category' :
			case 'product_tag' :
				$parent_file = 'store-settings';
				break;
		}

		return $parent_file;
	}

	/**
	 * Add items to the admin menu
	 *
	 * @since 1.0
	 * @access public
	 * @uses $wp_version
	 */
	public function add_menu_items() {
		global $wp_version;
	
		$cap = apply_filters( 'mp_store_settings_cap', 'manage_store_settings' );
	
		add_menu_page( __( 'MarketPress', 'mp' ), __( 'MarketPress', 'mp' ), $cap, 'store-settings', null, 'dashicons-store', 99.33 );
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Allgemein', 'mp' ), __( 'Allgemein', 'mp' ), $cap, 'store-settings', array( &$this, 'display_settings_form' ) );
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Präsentation', 'mp' ), __( 'Präsentation', 'mp' ), $cap, 'store-settings-presentation', array( &$this, 'display_settings_form' ) );
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Benachrichtigungen', 'mp' ), __( 'Benachrichtigungen', 'mp' ), $cap, 'store-settings-notifications', array( &$this, 'display_settings_form' ) );
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Versand', 'mp' ), __( 'Versand', 'mp' ), $cap, 'store-settings-shipping', array( &$this, 'display_settings_form' ) );
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Zahlungen', 'mp' ), __( 'Zahlungen', 'mp' ), $cap, 'store-settings-payments', array( &$this, 'display_settings_form' ) );
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Produkteigenschaften', 'mp' ), __( 'Produkteigenschaften', 'mp' ), $cap, 'store-settings-productattributes', array( 'MP_Product_Attributes_Admin', 'display_product_attributes' ) );
	
		add_submenu_page(
			'store-settings',
			__( 'MarketPress Einstellungen: Produktkategorien', 'mp' ),
			__( 'Produktkategorien', 'mp' ),
			apply_filters( 'mp_manage_product_categories_cap', 'manage_product_categories' ),
			'edit-tags.php?taxonomy=product_category&post_type=' . MP_Product::get_post_type()
		);

		add_submenu_page(
			'store-settings',
			__( 'MarketPress Einstellungen: Produkttags', 'mp' ),
			__( 'Produkttags', 'mp' ),
			apply_filters( 'mp_manage_product_tags_cap', 'manage_product_tags' ),
			'edit-tags.php?taxonomy=product_tag&post_type=' . MP_Product::get_post_type()
		);
	
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Berechtigungen', 'mp' ), __( 'Berechtigungen', 'mp' ), $cap, 'store-settings-capabilities', array( &$this, 'display_settings_form' ) );
	
		// Importers (Redirect)
		//add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Importierer', 'mp' ), __( 'Importierer', 'mp' ), $cap, 'store-settings-importers', array( $this, 'redirect_to_importers' ) );
	
		// Exporters (Redirect)
		//add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Exportierer', 'mp' ), __( 'Exportierer', 'mp' ), $cap, 'store-settings-exporters', array( $this, 'redirect_to_exporters' ) );
	
		add_submenu_page( 'store-settings', __( 'MarketPress Einstellungen: Erweiterungen', 'mp' ), __( 'Erweiterungen', 'mp' ), $cap, 'store-settings-addons', array( MP_Store_Settings_Addons::get_instance(), 'display_settings' ) );
	
		$mp_needs_quick_setup = get_option( 'mp_needs_quick_setup', 1 );
	
		if ( $mp_needs_quick_setup == 'skip' || $mp_needs_quick_setup == 1 && current_user_can( 'manage_options' ) || ( isset( $_GET['quick_setup_step'] ) ) ) {
			add_submenu_page( 'store-settings', __( 'Schnelle Einrichtung', 'mp' ), __( 'Schnelle Einrichtung', 'mp' ), $cap, 'store-setup-wizard', array( &$this, 'display_settings_form' ) );
		}
	
		if ( !PSOURCE_REMOVE_BRANDING ) {
			add_action( 'load-toplevel_page_store-settings', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-presentation', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-notifications', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-shipping', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-payments', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-product-attributes', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-capabilities', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-importers', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-exporters', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-setup-wizard', array( &$this, 'add_help_tab' ) );
			add_action( 'store-settings_page_store-settings-addons', array( &$this, 'add_help_tab' ) );
		}
	}
	
	public function redirect_to_product_categories() {
		wp_redirect( admin_url( 'edit-tags.php?taxonomy=product_category&post_type=' . MP_Product::get_post_type() ) );
		exit;
	}
	
	public function redirect_to_product_tags() {
		wp_redirect( admin_url( 'edit-tags.php?taxonomy=product_tag&post_type=' . MP_Product::get_post_type() ) );
		exit;
	}
	
	public function redirect_to_importers() {
		wp_redirect( admin_url( 'tools.php?page=store-settings-import' ) );
		exit;
	}
	
	public function redirect_to_exporters() {
		wp_redirect( admin_url( 'tools.php?page=store-settings-export' ) );
		exit;
	}

	/**
	 * Add help tab to current screen
	 *
	 * @since 1.0
	 * @access public
	 */
	public function add_help_tab() {
		MP_Admin::get_instance()->add_help_tab();
	}

	/**
	 * Print scripts for setting the active admin menu item when on the product tag page
	 *
	 * @since 1.0
	 * @access public
	 */
	public function print_product_tag_scripts() {
		if ( mp_get_current_screen()->id != 'edit-product_tag' ) {
			return false;
		}
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( 'a[href="edit-tags.php?taxonomy=product_tag&post_type=<?php echo MP_Product::get_post_type(); ?>"]' ).addClass( 'current' ).parent().addClass( 'current' );
			} );
		</script>
		<?php
	}

	/**
	 * Print scripts for setting the active admin menu item when on the product category page
	 *
	 * @since 1.0
	 * @access public
	 */
	public function print_product_category_scripts() {
		if ( mp_get_current_screen()->id != 'edit-product_category' ) {
			return false;
		}
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( 'a[href="edit-tags.php?taxonomy=product_category&post_type=<?php echo MP_Product::get_post_type(); ?>"]' ).addClass( 'current' ).parent().addClass( 'current' );
			} );
		</script>
		<?php
	}

	/**
	 * Gets an appropriate message by it's key
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_message_by_key( $key ) {
		$messages = array(
			'mp_product_attribute_added'	 => __( 'Produktattribut erfolgreich hinzugefügt.', 'mp' ),
			'mp_product_attribute_updated'	 => __( 'Produktattribut erfolgreich aktualisiert.', 'mp' ),
		);

		return ( isset( $messages[ $key ] ) ) ? $messages[ $key ] : sprintf( __( 'Eine passende Nachricht für den Schlüssel "%s" konnte nicht gefunden werden.', 'mp' ), $key );
	}

	/**
	 * Displays the settings form/metaboxes
	 *
	 * @since 1.0
	 * @access public
	 */
	public function display_settings_form() {
		// NICHTS rendern, wenn wir auf einer Addon-Detailseite sind!
		if (
			isset($_GET['page']) && $_GET['page'] === 'store-settings-addons'
			&& !empty($_GET['addon'])
		) {
			return;
		}
		$updated = false;
		$page = isset($_GET['page']) ? $_GET['page'] : '';
		switch ($page) {
			case 'store-settings':
				$title = __( 'MarketPress Einstellungen: Allgemein', 'mp' );
				break;
			case 'store-settings-presentation':
				$title = __( 'MarketPress Einstellungen: Darstellung', 'mp' );
				break;
			case 'store-settings-notifications':
				$title = __( 'MarketPress Einstellungen: Benachrichtigungen', 'mp' );
				break;
			case 'store-settings-shipping':
				$title = __( 'MarketPress Einstellungen: Versand', 'mp' );
				break;
			case 'store-settings-payments':
				$title = __( 'MarketPress Einstellungen: Zahlungen', 'mp' );
				break;
			case 'store-settings-shortcodes':
				$title = __( 'MarketPress Einstellungen: Shortcodes', 'mp' );
				break;
			case 'store-settings-importers':
				$title = __( 'MarketPress Einstellungen: Importe', 'mp' );
				break;
			case 'store-settings-exporters':
				$title = __( 'MarketPress Einstellungen: Exporte', 'mp' );
				break;
			case 'store-settings-productattributes':
				$title = __( 'Produktattribute', 'mp' );
				break;
			case 'store-settings-capabilities':
				$title = __( 'MarketPress Einstellungen: Berechtigungen', 'mp' );
				break;
			case 'store-settings-import':
				$title = __( 'MarketPress Einstellungen: Import/Export', 'mp' );
				break;
			case 'store-setup-wizard':
				$title = __( 'Schnelle Einrichtung', 'mp' );
				break;
			default:
				$title = __( 'MarketPress Einstellungen', 'mp' );
				break;
		}
		?>
		<div class="wrap mp-wrap">
			<div class="icon32"><img src="<?php echo mp_plugin_url( 'ui/images/settings.png' ); ?>" /></div>
			<h2 class="mp-settings-title"><?php echo $title; ?></h2>
			<div class="clear"></div>
			<?php if ( $message_key = mp_get_get_value( 'mp_message' ) ) : ?>
				<div class="updated"><p><?php echo $this->get_message_by_key( $message_key ); ?></p></div>
			<?php endif;
			?> 	
			<div class="mp-settings">
				<form id="mp-main-form" method="post" action="<?php echo add_query_arg( array() ); ?>">
					<?php
					if ( $page === 'store-settings-capabilities' ) {
						// Custom renderer for capabilities page (without PSOURCE metabox UI).
						if ( class_exists( 'MP_Store_Settings_Capabilities' ) ) {
							MP_Store_Settings_Capabilities::get_instance()->render_custom_settings();
						}
					} else {
						/**
						 * Render PSOURCE Metabox settings
						 *
						 * @since 1.0
						 */
						do_action( 'psource_metabox/render_settings_metaboxes' );

						/**
						 * Render settings
						 *
						 * @since 1.0
						 */
						do_action( 'mp_render_settings/' . mp_get_current_screen()->id );
					}
					?>
				</form>
			</div>
		</div>
		<?php
	}
    
    /**
	 * Resets the store pages ids in wp cache
	 *
	 * @since 3.2.5
	 * @access public
	 */
    public function reset_store_pages_cache( $metabox ){

		if( ! $metabox instanceof PSOURCE_Metabox ){
			return;
		}

		$store_pages_ids = array();

		foreach ( $metabox->fields as $field ) {
			$field_key 			= $field->get_post_key( $field->args['name'] );
			$value    			= $field->get_post_value( $field_key );
			$store_pages_ids[] 	= $value;
		}

		wp_cache_set( 'store_pages_ids', $store_pages_ids, 'marketpress' );

	}

}

MP_Store_Settings_Admin::get_instance();
