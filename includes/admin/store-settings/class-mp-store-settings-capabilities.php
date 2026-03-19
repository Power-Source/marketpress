<?php

class MP_Store_Settings_Capabilities {
	/**
	 * Refers to a single instance of the class
	 *
	 * @since 1.0
	 * @access private
	 * @var object
	 */
	private static $_instance = null;

	/**
	 * Role cache for capabilities screen.
	 *
	 * @since 1.0.4
	 * @access private
	 * @var array
	 */
	private $_roles = array();
	
	/**
	 * Gets the single instance of the class
	 *
	 * @since 1.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {
		if ( is_null(self::$_instance) ) {
			self::$_instance = new MP_Store_Settings_Capabilities();
		}
		return self::$_instance;
	}
	
	/**
	 * Render custom capabilities UI (without PSOURCE metaboxes).
	 *
	 * @since 1.0.4
	 * @access public
	 */
	public function render_custom_settings() {
		if ( mp_get_get_value( 'page' ) !== 'store-settings-capabilities' ) {
			return;
		}

		$roles = $this->get_roles();
		if ( empty( $roles ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Keine bearbeitbaren Rollen gefunden.', 'mp' ) . '</p></div>';
			return;
		}

		$cap_groups = $this->get_grouped_capability_options();
		$first_role = key( $roles );
		$saved_caps = mp_get_setting( 'caps', array() );
		?>
		<div class="mp-capabilities-toolbar notice notice-info inline">
			<p>
				<label for="mp-capabilities-role-selector"><strong><?php _e( 'Rolle auswaehlen', 'mp' ); ?>:</strong></label>
				<select id="mp-capabilities-role-selector">
					<?php foreach ( $roles as $role_name => $role ) : ?>
						<option value="<?php echo esc_attr( $role_name ); ?>"><?php echo esc_html( $role['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button button-primary" id="mp-capabilities-save-role" style="margin-left:8px;">
					<?php _e( 'Aktuelle Rolle speichern', 'mp' ); ?>
				</button>
				<span class="description" style="margin-left:8px;"><?php _e( 'Es wird immer nur eine Rolle gleichzeitig angezeigt.', 'mp' ); ?></span>
			</p>
			<div id="mp-capabilities-feedback" class="notice inline" style="display:none;"></div>
			<div id="mp-capabilities-summary" class="mp-capabilities-summary" aria-live="polite"></div>
		</div>

		<div class="mp-capabilities-panels">
			<?php foreach ( $roles as $role_name => $role ) :
				$role_obj = get_role( $role_name );
				$style    = ( $role_name === $first_role ) ? '' : 'display:none;';
				$role_caps = mp_arr_get_value( $role_name, $saved_caps, array() );
			?>
				<div id="mp-settings-capabilities-<?php echo esc_attr( $role_name ); ?>" class="postbox mp-capabilities-role-box" data-role="<?php echo esc_attr( $role_name ); ?>" style="<?php echo esc_attr( $style ); ?>">
					<h3 class="hndle"><span><?php echo esc_html( sprintf( __( 'Berechtigungen fuer Rolle: %s', 'mp' ), $role['name'] ) ); ?></span></h3>
					<div class="inside">
						<p class="description"><?php _e( 'Waehle hier, was diese Rolle im Shop machen darf.', 'mp' ); ?></p>
						<div class="mp-capabilities-sections">
							<?php foreach ( $cap_groups as $group_key => $caps ) : ?>
								<?php if ( empty( $caps ) ) { continue; } ?>
								<section class="mp-capabilities-section">
									<h4><?php echo esc_html( $this->get_capability_group_title( $group_key ) ); ?></h4>
									<div class="mp-capabilities-checkbox-grid">
										<?php foreach ( $caps as $cap_key => $cap_label ) :
											$is_checked = (int) mp_arr_get_value( $cap_key, $role_caps, ( $role_obj && $role_obj->has_cap( $cap_key ) ) ? 1 : 0 );
											$checked = $is_checked ? 'checked="checked"' : '';
										?>
											<label class="mp-capability-item" for="mp-cap-<?php echo esc_attr( $role_name . '-' . $cap_key ); ?>">
												<input type="checkbox"
													id="mp-cap-<?php echo esc_attr( $role_name . '-' . $cap_key ); ?>"
													name="caps[<?php echo esc_attr( $role_name ); ?>][<?php echo esc_attr( $cap_key ); ?>]"
													value="1"
													<?php echo $checked; ?> />
												<span><?php echo esc_html( $cap_label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</section>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Get editable roles except administrator.
	 *
	 * @since 1.0.4
	 * @access private
	 * @return array
	 */
	private function get_roles() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . '/wp-admin/includes/user.php';
		}

		if ( ! empty( $this->_roles ) ) {
			return $this->_roles;
		}

		$roles = get_editable_roles();

		if ( isset( $roles['administrator'] ) ) {
			unset( $roles['administrator'] );
		}

		$this->_roles = $roles;

		return $this->_roles;
	}

	/**
	 * Returns capability options in slug => label format.
	 *
	 * @since 1.0.4
	 * @access private
	 * @return array
	 */
	private function get_capability_options() {
		$options = array();
		$caps    = mp_get_store_caps();

		foreach ( array_keys( $caps ) as $cap ) {
			$options[ $cap ] = $this->get_capability_label( $cap );
		}

		return $options;
	}

	/**
	 * Returns grouped capabilities for structured rendering.
	 *
	 * @since 1.0.4
	 * @access private
	 * @return array
	 */
	private function get_grouped_capability_options() {
		$options = $this->get_capability_options();
		$group_map = array(
			'store' => array(
				'manage_store_settings',
			),
			'catalog' => array(
				'manage_product_categories',
				'manage_product_tags',
				'edit_product',
				'read_product',
				'delete_product',
				'edit_products',
				'edit_others_products',
				'publish_products',
				'read_private_products',
				'edit_private_products',
				'delete_private_products',
				'edit_published_products',
				'delete_published_products',
				'delete_products',
			),
			'orders' => array(
				'edit_order',
				'read_order',
				'delete_order',
				'edit_orders',
				'edit_others_orders',
				'publish_orders',
				'read_private_orders',
				'edit_private_orders',
				'delete_private_orders',
				'edit_published_orders',
				'delete_published_orders',
				'delete_orders',
			),
			'store_orders' => array(
				'edit_store_orders',
				'read_store_orders',
				'delete_store_orders',
				'edit_others_store_orders',
				'publish_store_orders',
				'read_private_store_orders',
				'edit_private_store_orders',
				'delete_private_store_orders',
				'edit_published_store_orders',
				'delete_published_store_orders',
				'delete_others_store_orders',
			),
			'restricted' => array(
				'do_not_allow',
			),
		);

		$grouped = array();
		$used = array();

		foreach ( $group_map as $group_key => $cap_keys ) {
			foreach ( $cap_keys as $cap_key ) {
				if ( isset( $options[ $cap_key ] ) ) {
					$grouped[ $group_key ][ $cap_key ] = $options[ $cap_key ];
					$used[ $cap_key ] = true;
				}
			}
		}

		foreach ( $options as $cap_key => $label ) {
			if ( ! isset( $used[ $cap_key ] ) ) {
				$grouped['misc'][ $cap_key ] = $label;
			}
		}

		return $grouped;
	}

	/**
	 * Human-readable title for a capability group.
	 *
	 * @since 1.0.4
	 * @access private
	 * @param string $group_key Group key.
	 * @return string
	 */
	private function get_capability_group_title( $group_key ) {
		$titles = array(
			'store'        => __( 'Shop', 'mp' ),
			'catalog'      => __( 'Produkte und Katalog', 'mp' ),
			'orders'       => __( 'Bestellungen', 'mp' ),
			'store_orders' => __( 'Shop-Bestellungen', 'mp' ),
			'restricted'   => __( 'Sonderfaelle', 'mp' ),
			'misc'         => __( 'Weitere Rechte', 'mp' ),
		);

		return isset( $titles[ $group_key ] ) ? $titles[ $group_key ] : __( 'Weitere Rechte', 'mp' );
	}

	/**
	 * Returns a readable, translatable label for a capability slug.
	 *
	 * @since 1.0.4
	 * @access private
	 * @param string $cap Capability slug.
	 * @return string
	 */
	private function get_capability_label( $cap ) {
		$labels = array(
			'manage_store_settings'      => __( 'Shop-Einstellungen verwalten', 'mp' ),
			'manage_product_categories'  => __( 'Produktkategorien verwalten', 'mp' ),
			'manage_product_tags'        => __( 'Produktschlagworte verwalten', 'mp' ),
			'edit_product'               => __( 'Produkt bearbeiten', 'mp' ),
			'read_product'               => __( 'Produkt ansehen', 'mp' ),
			'delete_product'             => __( 'Produkt loeschen', 'mp' ),
			'edit_products'              => __( 'Produkte bearbeiten', 'mp' ),
			'edit_others_products'       => __( 'Produkte anderer bearbeiten', 'mp' ),
			'publish_products'           => __( 'Produkte veroeffentlichen', 'mp' ),
			'read_private_products'      => __( 'Private Produkte ansehen', 'mp' ),
			'edit_private_products'      => __( 'Private Produkte bearbeiten', 'mp' ),
			'delete_private_products'    => __( 'Private Produkte loeschen', 'mp' ),
			'edit_published_products'    => __( 'Veroeffentlichte Produkte bearbeiten', 'mp' ),
			'delete_published_products'  => __( 'Veroeffentlichte Produkte loeschen', 'mp' ),
			'delete_products'            => __( 'Produkte loeschen', 'mp' ),
			'edit_order'                 => __( 'Bestellung bearbeiten', 'mp' ),
			'read_order'                 => __( 'Bestellung ansehen', 'mp' ),
			'delete_order'               => __( 'Bestellung loeschen', 'mp' ),
			'edit_orders'                => __( 'Bestellungen bearbeiten', 'mp' ),
			'edit_others_orders'         => __( 'Bestellungen anderer bearbeiten', 'mp' ),
			'publish_orders'             => __( 'Bestellungen veroeffentlichen', 'mp' ),
			'read_private_orders'        => __( 'Private Bestellungen ansehen', 'mp' ),
			'edit_private_orders'        => __( 'Private Bestellungen bearbeiten', 'mp' ),
			'delete_private_orders'      => __( 'Private Bestellungen loeschen', 'mp' ),
			'edit_published_orders'      => __( 'Veroeffentlichte Bestellungen bearbeiten', 'mp' ),
			'delete_published_orders'    => __( 'Veroeffentlichte Bestellungen loeschen', 'mp' ),
			'delete_orders'              => __( 'Bestellungen loeschen', 'mp' ),
			'edit_store_orders'          => __( 'Shop-Bestellungen bearbeiten', 'mp' ),
			'read_store_orders'          => __( 'Shop-Bestellungen ansehen', 'mp' ),
			'delete_store_orders'        => __( 'Shop-Bestellungen loeschen', 'mp' ),
			'edit_others_store_orders'   => __( 'Shop-Bestellungen anderer bearbeiten', 'mp' ),
			'publish_store_orders'       => __( 'Shop-Bestellungen veroeffentlichen', 'mp' ),
			'read_private_store_orders'  => __( 'Private Shop-Bestellungen ansehen', 'mp' ),
			'edit_private_store_orders'  => __( 'Private Shop-Bestellungen bearbeiten', 'mp' ),
			'delete_private_store_orders'=> __( 'Private Shop-Bestellungen loeschen', 'mp' ),
			'edit_published_store_orders'=> __( 'Veroeffentlichte Shop-Bestellungen bearbeiten', 'mp' ),
			'delete_published_store_orders' => __( 'Veroeffentlichte Shop-Bestellungen loeschen', 'mp' ),
			'delete_others_store_orders' => __( 'Shop-Bestellungen anderer loeschen', 'mp' ),
			'do_not_allow'               => __( 'Kein Zugriff', 'mp' ),
		);

		if ( isset( $labels[ $cap ] ) ) {
			return $labels[ $cap ];
		}

		return ucwords( str_replace( '_', ' ', $cap ) );
	}



	/**
	 * Enqueue modern UI assets for capabilities settings page.
	 *
	 * @since 1.0.4
	 * @access public
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( mp_get_get_value( 'page' ) !== 'store-settings-capabilities' ) {
			return;
		}

		wp_enqueue_style(
			'mp-capabilities-settings',
			mp_plugin_url( 'includes/admin/ui/css/store-settings-capabilities.css' ),
			array(),
			MP_VERSION
		);

		wp_enqueue_script(
			'mp-capabilities-settings',
			mp_plugin_url( 'includes/admin/ui/js/store-settings-capabilities.js' ),
			array( 'jquery' ),
			MP_VERSION,
			true
		);

		wp_localize_script( 'mp-capabilities-settings', 'mpCapabilitiesI18n', array(
			'summaryTitle' => __( 'Schnelluebersicht', 'mp' ),
			'activeCount'  => __( 'Aktiv: %1$d von %2$d Berechtigungen', 'mp' ),
			'noneActive'   => __( 'Aktuell ist keine Berechtigung aktiviert.', 'mp' ),
			'nonce'        => wp_create_nonce( 'mp_save_role_caps' ),
			'saving'       => __( 'Speichere...', 'mp' ),
			'saveButton'   => __( 'Aktuelle Rolle speichern', 'mp' ),
			'saveSuccess'  => __( 'Berechtigungen wurden gespeichert.', 'mp' ),
			'saveError'    => __( 'Speichern fehlgeschlagen. Bitte versuch es erneut.', 'mp' ),
		) );
	}

	/**
	 * AJAX: Save capabilities for one role.
	 *
	 * @since 1.0.4
	 * @access public
	 */
	public function ajax_save_role_caps() {
		check_ajax_referer( 'mp_save_role_caps', 'nonce' );

		$required_cap = apply_filters( 'mp_store_settings_cap', 'manage_store_settings' );
		if ( ! current_user_can( $required_cap ) ) {
			wp_send_json_error( array( 'message' => __( 'Unzureichende Berechtigungen.', 'mp' ) ), 403 );
		}

		$role_name = sanitize_key( mp_get_post_value( 'role' ) );
		$caps      = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : array();
		$roles     = $this->get_roles();

		if ( empty( $role_name ) || ! isset( $roles[ $role_name ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unbekannte Rolle.', 'mp' ) ), 400 );
		}

		$role_obj = get_role( $role_name );
		if ( ! $role_obj ) {
			wp_send_json_error( array( 'message' => __( 'Rolle konnte nicht geladen werden.', 'mp' ) ), 400 );
		}

		$all_caps = array_keys( mp_get_store_caps() );
		$settings = get_option( 'mp_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( ! isset( $settings['caps'] ) || ! is_array( $settings['caps'] ) ) {
			$settings['caps'] = array();
		}

		$settings['caps'][ $role_name ] = array();

		foreach ( $all_caps as $cap ) {
			$is_enabled = isset( $caps[ $cap ] ) && (string) $caps[ $cap ] === '1';

			$settings['caps'][ $role_name ][ $cap ] = $is_enabled ? 1 : 0;

			if ( $is_enabled ) {
				$role_obj->add_cap( $cap );
			} else {
				$role_obj->remove_cap( $cap );
			}
		}

		update_option( 'mp_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Berechtigungen wurden gespeichert.', 'mp' ) ) );
	}
	

	
	/**
	 * Constructor function
	 *
	 * @since 1.0
	 * @access private
	 */
	private function __construct() {
		add_action( 'mp_render_settings/store-settings_page_store-settings-capabilities', array( &$this, 'render_custom_settings' ) );
		add_action( 'mp_render_settings/store-settings-capabilities', array( &$this, 'render_custom_settings' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_mp_save_role_caps', array( &$this, 'ajax_save_role_caps' ) );
	}
}

MP_Store_Settings_Capabilities::get_instance();
