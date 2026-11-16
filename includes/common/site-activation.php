<?php

function mp_site_shop_is_active() {
	if ( ! is_multisite() || is_network_admin() || is_main_site() ||
		( defined( 'MP_ROOT_BLOG' ) && MP_ROOT_BLOG && (int) MP_ROOT_BLOG === (int) get_current_blog_id() ) ) {
		return true;
	}

	$network_plugins = get_site_option( 'active_sitewide_plugins', array() );
	if ( ! isset( $network_plugins[ plugin_basename( dirname( __DIR__, 2 ) . '/marketpress.php' ) ] ) ) {
		return true;
	}

	$state = get_option( 'mp_site_shop_active', false );
	if ( false !== $state ) {
		if ( '0' === (string) $state ) {
			return false;
		}
		if ( '1' !== (string) $state ||
			false !== get_option( 'mp_site_shop_activation_source', false ) ||
			false !== get_option( 'mp_site_shop_needs_caps', false ) ||
			'2' === (string) get_option( 'mp_site_shop_migration_version', '' ) ) {
			return true;
		}
	}

	$unused = mp_site_shop_is_clearly_unused();
	if ( null === $unused ) {
		return true;
	}

	if ( false === $state ) {
		add_option( 'mp_site_shop_active', $unused ? '0' : '1' );
	} elseif ( $unused ) {
		global $wpdb;

		// Do not overwrite an activation that started while the old state was being checked.
		$updated = $wpdb->query(
			"UPDATE {$wpdb->options} AS state
			LEFT JOIN {$wpdb->options} AS activation ON activation.option_name = 'mp_site_shop_activation_source'
			LEFT JOIN {$wpdb->options} AS caps ON caps.option_name = 'mp_site_shop_needs_caps'
			SET state.option_value = '0'
			WHERE state.option_name = 'mp_site_shop_active' AND state.option_value = '1'
				AND activation.option_id IS NULL AND caps.option_id IS NULL"
		);
		if ( false === $updated ) {
			error_log( 'MarketPress: Could not update the existing subsite shop state: ' . $wpdb->last_error );
			return true;
		}
		if ( $updated ) {
			wp_cache_delete( 'mp_site_shop_active', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
	$state = get_option( 'mp_site_shop_active', false );
	if ( false === $state ) {
		error_log( 'MarketPress: Could not save the existing subsite shop state for site ' . (int) get_current_blog_id() );
		return true;
	}
	if ( '1' === (string) $state &&
		! add_option( 'mp_site_shop_migration_version', '2' ) &&
		'2' !== (string) get_option( 'mp_site_shop_migration_version', '' ) ) {
		error_log( 'MarketPress: Could not save the subsite shop migration version for site ' . (int) get_current_blog_id() );
	}
	return '0' !== (string) $state;
}

function mp_site_settings_are_automatic( $settings ) {
	if ( ! is_array( $settings ) ) {
		return false;
	}
	foreach ( $settings as $key => $value ) {
		if ( 'addons' === $key && array( 'MP_Prosites_Addon' ) === $value ) {
			continue;
		}
		if ( 'pages' === $key && is_array( $value ) ) {
			foreach ( $value as $page => $page_id ) {
				if ( 'none' === $page && ( is_int( $page_id ) || ( is_string( $page_id ) && ctype_digit( $page_id ) ) ) && (int) $page_id >= 0 ) {
					continue;
				}
				if ( ! in_array( $page, array( 'store', 'products', 'cart', 'checkout', 'order_status' ), true ) ||
					! in_array( $page_id, array( 0, '0', '', null ), true ) ) {
					return false;
				}
			}
			continue;
		}
		if ( 'shipping' !== $key || ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $module => $module_settings ) {
			if ( ! in_array( $module, array( 'fedex', 'pickup', 'table_rate', 'ups', 'usps', 'weight_rate' ), true ) ||
				! is_array( $module_settings ) || array( 'build' ) !== array_keys( $module_settings ) ||
				! in_array( $module_settings['build'], array( 2, '2' ), true ) ) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Return null on a database error so the shop remains active and can be rechecked.
 */
function mp_site_shop_is_clearly_unused() {
	global $wpdb;

	$options = $wpdb->get_results( $wpdb->prepare(
		"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name = 'sidebars_widgets'",
		$wpdb->esc_like( 'mp_' ) . '%',
		$wpdb->esc_like( 'widget_' ) . '%'
	) );
	if ( $wpdb->last_error || ! is_array( $options ) ) {
		error_log( 'MarketPress: Could not inspect subsite shop options: ' . $wpdb->last_error );
		return null;
	}
	foreach ( $options as $option ) {
		$value = maybe_unserialize( $option->option_value );
		if ( 0 === strpos( $option->option_name, 'widget_' ) ) {
			if ( 0 === strpos( $option->option_name, 'widget_mp_' ) &&
				array() !== $value && array( '_multiwidget' => 1 ) !== $value &&
				array( '_multiwidget' => '1' ) !== $value ) {
				return false;
			}
			if ( false !== strpos( $option->option_value, '[mp_' ) ||
				false !== strpos( $option->option_value, '<!-- wp:marketpress/' ) ) {
				return false;
			}
			continue;
		}
		switch ( $option->option_name ) {
			case 'sidebars_widgets':
				if ( ! is_array( $value ) ) {
					return false;
				}
				foreach ( $value as $widgets ) {
					if ( ! is_array( $widgets ) ) {
						continue;
					}
					foreach ( $widgets as $widget ) {
						if ( ! is_string( $widget ) || 0 === strpos( $widget, 'mp_' ) ) {
							return false;
						}
					}
				}
				break;
			case 'mp_settings':
				if ( ! mp_site_settings_are_automatic( $value ) ) {
					return false;
				}
				break;
			case 'mp_needs_pages':
			case 'mp_needs_quick_setup':
				if ( '1' !== (string) $value ) {
					return false;
				}
				break;
			case 'mp_coupons':
				if ( array() !== $value ) {
					return false;
				}
				break;
			case 'mp_site_shop_active':
				if ( '1' !== (string) $value ) {
					return false;
				}
				break;
			case 'mp_attributes_db_version':
			case 'mp_deprecated_gateway_notice_showed':
			case 'mp_flush_rewrites':
			case 'mp_flush_rewrites_30':
			case 'mp_plugin_do_activation_redirect':
			case 'mp_previous_version':
			case 'mp_st_db_version':
			case 'mp_subsite_need_redirect':
			case 'mp_version':
				break;
			default:
				return false;
		}
	}

	$checks = array(
		$wpdb->prepare(
			"SELECT 1 FROM {$wpdb->posts} WHERE post_type IN ('product', 'mp_product', 'mp_product_variation', 'mp_order', 'mp_coupon') OR post_content LIKE %s OR post_content LIKE %s LIMIT 1",
			'%' . $wpdb->esc_like( '[mp_' ) . '%',
			'%' . $wpdb->esc_like( '<!-- wp:marketpress/' ) . '%'
		),
		$wpdb->prepare(
			"SELECT 1 FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('product_category', 'product_tag') OR taxonomy LIKE %s LIMIT 1",
			$wpdb->esc_like( 'product_attr_' ) . '%'
		),
	);
	foreach ( $checks as $query ) {
		$found = $wpdb->get_var( $query );
		if ( $wpdb->last_error ) {
			error_log( 'MarketPress: Could not inspect subsite shop data: ' . $wpdb->last_error );
			return null;
		}
		if ( null !== $found ) {
			return false;
		}
	}

	$tables = array(
		$wpdb->prefix . 'mp_product_attributes'       => '',
		$wpdb->prefix . 'mp_product_attributes_terms' => '',
		$wpdb->prefix . 'mp_download_events'          => '',
		$wpdb->base_prefix . 'mp_products'            => 'blog_id',
		$wpdb->base_prefix . 'mp_term_relationships'  => 'blog_id',
		$wpdb->base_prefix . 'mp_settlement_ledger'   => 'shop_blog_id',
	);
	foreach ( $tables as $table => $site_column ) {
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $wpdb->last_error ) {
			error_log( 'MarketPress: Could not inspect subsite shop tables: ' . $wpdb->last_error );
			return null;
		}
		if ( $table !== $exists ) {
			continue;
		}
		$query = $site_column
			? $wpdb->prepare( "SELECT 1 FROM {$table} WHERE {$site_column} = %d LIMIT 1", get_current_blog_id() )
			: "SELECT 1 FROM {$table} LIMIT 1";
		$found = $wpdb->get_var( $query );
		if ( $wpdb->last_error ) {
			error_log( 'MarketPress: Could not inspect subsite shop table data: ' . $wpdb->last_error );
			return null;
		}
		if ( null !== $found ) {
			return false;
		}
	}
	return true;
}

function mp_mark_new_site_inactive( $site ) {
	if ( ! wp_is_site_initialized( $site ) || is_main_site( $site->id ) ) {
		return;
	}

	update_blog_option( $site->id, 'mp_site_shop_active', '0' );
	if ( '0' !== (string) get_blog_option( $site->id, 'mp_site_shop_active', '' ) ) {
		error_log( 'MarketPress: Could not initialize the shop state for site ' . (int) $site->id );
	}
}

function mp_activate_site_shop() {
	update_option( 'mp_site_shop_activation_source', 'explicit' );
	if ( 'explicit' !== get_option( 'mp_site_shop_activation_source', false ) ) {
		error_log( 'MarketPress: Could not save the explicit shop activation for site ' . (int) get_current_blog_id() );
		return false;
	}
	update_option( 'mp_site_shop_needs_caps', '1' );
	update_option( 'mp_flush_rewrites', 1 );
	update_option( 'mp_flush_rewrites_30', 1 );
	update_option( 'mp_site_shop_active', '1' );

	if ( '1' !== (string) get_option( 'mp_site_shop_active', '0' ) ) {
		return false;
	}
	delete_option( 'mp_subsite_need_redirect' );
	return true;
}

function mp_activate_site_shop_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Du darfst diesen Shop nicht aktivieren.', 'mp' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mp_activate_site_shop' );

	if ( ! mp_activate_site_shop() ) {
		wp_die( esc_html__( 'Der Shop konnte nicht aktiviert werden.', 'mp' ) );
	}

	update_option( 'mp_needs_quick_setup', 'skip' );
	wp_safe_redirect( admin_url( 'admin.php?page=store-settings' ) );
	exit;
}

function mp_inactive_shop_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'MarketPress – Allgemein', 'mp' ); ?></h1>
		<p><?php esc_html_e( 'Der Shop ist auf dieser Website noch nicht aktiviert.', 'mp' ); ?></p>
		<p><?php esc_html_e( 'Aktiviere den Shop zur manuellen Einrichtung oder verwende die Schnelleinrichtung.', 'mp' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mp_activate_site_shop">
			<?php wp_nonce_field( 'mp_activate_site_shop' ); ?>
			<?php submit_button( __( 'Shop jetzt aktivieren', 'mp' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

function mp_inactive_shop_menu() {
	$cap = 'manage_options';
	add_menu_page( __( 'MarketPress', 'mp' ), __( 'MarketPress', 'mp' ), $cap, 'store-settings', 'mp_inactive_shop_page', 'dashicons-store', 99.33 );
	add_submenu_page( 'store-settings', __( 'Allgemein', 'mp' ), __( 'Allgemein', 'mp' ), $cap, 'store-settings', 'mp_inactive_shop_page' );
	add_submenu_page( 'store-settings', __( 'Schnelle Einrichtung', 'mp' ), __( 'Schnelle Einrichtung', 'mp' ), $cap, 'store-setup-wizard', 'mp_inactive_shop_page' );
}

function mp_setup_shop_capabilities() {
	if ( '1' !== (string) get_option( 'mp_site_shop_needs_caps', '0' ) ) {
		return;
	}

	$role = get_role( 'administrator' );
	if ( ! $role ) {
		error_log( 'MarketPress: Administrator role not found while initializing shop capabilities.' );
		return;
	}
	foreach ( mp_get_store_caps() as $cap ) {
		if ( 'do_not_allow' !== $cap && ! $role->has_cap( $cap ) ) {
			$role->add_cap( $cap );
		}
	}
	delete_option( 'mp_site_shop_needs_caps' );
}

function mp_prepare_site_shop_settings() {
	$settings = get_option( 'mp_settings', false );
	if ( false !== $settings && ! mp_site_settings_are_automatic( $settings ) ) {
		return;
	}
	$defaults = apply_filters( 'mp_default_settings', $GLOBALS['mp']->default_settings );
	if ( false !== $settings ) {
		$defaults = array_replace_recursive( $defaults, $settings );
		update_option( 'mp_settings', $defaults );
	} else {
		add_option( 'mp_settings', $defaults );
	}
	if ( $defaults !== get_option( 'mp_settings', false ) ) {
		error_log( 'MarketPress: Could not initialize shop settings.' );
	}
}

function mp_finish_site_shop_setup() {
	if ( mp_site_shop_is_active() ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || '2' !== (string) mp_get_get_value( 'quick_setup_step' ) ) {
		wp_die( esc_html__( 'Die Schnelleinrichtung konnte nicht abgeschlossen werden.', 'mp' ), '', array( 'response' => 403 ) );
	}
	$country = mp_get_setting( 'base_country' );
	if ( ! is_string( $country ) || ! array_key_exists( $country, mp_countries() ) ) {
		wp_die( esc_html__( 'Bitte wähle vor dem Abschluss ein gültiges Basisland.', 'mp' ), '', array( 'back_link' => true ) );
	}
	if ( ! mp_activate_site_shop() ) {
		wp_die( esc_html__( 'Der Shop konnte nicht aktiviert werden.', 'mp' ) );
	}
}
