<?php

$options = array();
$network_active = true;
$multisite = true;
$main_site = false;
$network_admin = false;
$can_manage = false;
$blog_id = 32;
$fail_add_option = false;
$fail_update_option = '';
define( 'MP_ROOT_BLOG', 77 );

class Test_WPDB {
	public $prefix = 'wp_32_';
	public $base_prefix = 'wp_';
	public $options = 'wp_32_options';
	public $posts = 'wp_32_posts';
	public $term_taxonomy = 'wp_32_term_taxonomy';
	public $last_error = '';
	public $fail = '';
	public $posts_found = false;
	public $terms_found = false;
	public $tables = array();
	public $queries = array();
	public $activate_during_update = false;

	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$replacement = is_int( $arg ) ? (string) $arg : "'" . addslashes( $arg ) . "'";
			$query = preg_replace_callback( '/%[sd]/', function () use ( $replacement ) { return $replacement; }, $query, 1 );
		}
		return $query;
	}
	public function get_results( $query ) {
		$this->last_error = '';
		if ( 'options' === $this->fail ) {
			$this->last_error = 'options error';
			return null;
		}
		$result = array();
		foreach ( $GLOBALS['options'] as $name => $value ) {
			if ( 0 === strpos( $name, 'mp_' ) || 0 === strpos( $name, 'widget_' ) || 'sidebars_widgets' === $name ) {
				$result[] = (object) array( 'option_name' => $name, 'option_value' => is_array( $value ) ? serialize( $value ) : $value );
			}
		}
		return $result;
	}
	public function get_var( $query ) {
		$this->queries[] = $query;
		$this->last_error = '';
		if ( $this->fail && false !== strpos( $query, $this->fail ) ) {
			$this->last_error = 'query error';
			return null;
		}
		if ( false !== strpos( $query, 'SHOW TABLES' ) ) {
			foreach ( $this->tables as $table => $has_rows ) {
				if ( false !== strpos( $query, addslashes( $this->esc_like( $table ) ) ) ) {
					return $table;
				}
			}
			return null;
		}
		if ( false !== strpos( $query, $this->posts ) ) {
			return $this->posts_found ? '1' : null;
		}
		if ( false !== strpos( $query, $this->term_taxonomy ) ) {
			return $this->terms_found ? '1' : null;
		}
		foreach ( $this->tables as $table => $has_rows ) {
			if ( false !== strpos( $query, 'FROM ' . $table . ' ' ) ) {
				return $has_rows ? '1' : null;
			}
		}
		throw new RuntimeException( 'Unexpected query: ' . $query );
	}
	public function query( $query ) {
		$this->last_error = '';
		if ( 'update_state' === $this->fail ) {
			$this->last_error = 'update error';
			return false;
		}
		if ( $this->activate_during_update ) {
			$GLOBALS['options']['mp_site_shop_activation_source'] = 'explicit';
		}
		if ( isset( $GLOBALS['options']['mp_site_shop_activation_source'] ) || isset( $GLOBALS['options']['mp_site_shop_needs_caps'] ) ) {
			return 0;
		}
		if ( '1' === (string) ( $GLOBALS['options']['mp_site_shop_active'] ?? '' ) ) {
			$GLOBALS['options']['mp_site_shop_active'] = '0';
			return 1;
		}
		return 0;
	}
}
$wpdb = new Test_WPDB();

function is_multisite() { return $GLOBALS['multisite']; }
function is_main_site( $site_id = null ) { return $GLOBALS['main_site']; }
function is_network_admin() { return $GLOBALS['network_admin']; }
function get_current_blog_id() { return $GLOBALS['blog_id']; }
function plugin_basename( $path ) { return basename( dirname( $path ) ) . '/' . basename( $path ); }
function get_site_option( $name, $default = false ) {
	return $GLOBALS['network_active'] ? array( 'marketpress/marketpress.php' => 1 ) : $default;
}
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value ) {
	if ( $GLOBALS['fail_update_option'] !== $name ) {
		$GLOBALS['options'][ $name ] = $value;
	}
}
function add_option( $name, $value ) {
	if ( $GLOBALS['fail_add_option'] || array_key_exists( $name, $GLOBALS['options'] ) ) {
		return false;
	}
	$GLOBALS['options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); }
function wp_cache_delete( $key, $group ) {}
function maybe_unserialize( $value ) {
	if ( is_string( $value ) && 0 === strpos( $value, 'a:' ) ) {
		return unserialize( $value );
	}
	return $value;
}
function apply_filters( $name, $value ) { return $value; }
function current_user_can( $cap ) { return $GLOBALS['can_manage']; }
function esc_html__( $message, $domain ) { return $message; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message ); }
function check_admin_referer( $action ) { throw new RuntimeException( 'Invalid nonce.' ); }

require dirname( __DIR__ ) . '/includes/common/site-activation.php';

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
	echo "PASS: $message\n";
}

expect( ! mp_site_shop_is_active() && '0' === $options['mp_site_shop_active'], 'Clearly unused existing subsite becomes inactive' );
$options['mp_site_shop_active'] = '0';
expect( ! mp_site_shop_is_active(), 'New network subsite remains inactive' );
$main_site = true;
expect( mp_site_shop_is_active(), 'Main site remains active' );
$main_site = false;
$multisite = false;
expect( mp_site_shop_is_active(), 'Single-site installation remains active' );
$multisite = true;
$network_active = false;
expect( mp_site_shop_is_active(), 'Individually activated plugin is not disabled by a network state' );
$network_active = true;
$network_admin = true;
$options = array();
expect( mp_site_shop_is_active() && empty( $options ), 'Network administration does not migrate the current site' );
$network_admin = false;

$options = array(
	'mp_settings' => array(
		'shipping' => array( 'fedex' => array( 'build' => 2 ), 'pickup' => array( 'build' => 2 ) ),
		'addons' => array( 'MP_Prosites_Addon' ),
		'pages' => array( 'none' => 4, 'cart' => '0' ),
	),
	'mp_attributes_db_version' => '1',
	'mp_previous_version' => '1.1.9',
	'mp_version' => '1.1.9',
	'mp_subsite_need_redirect' => '1',
	'widget_mp_cart_widget' => array( '_multiwidget' => 1 ),
);
$wpdb->tables = array( 'wp_32_mp_product_attributes' => false, 'wp_32_mp_product_attributes_terms' => false );
expect( ! mp_site_shop_is_active(), 'Automatic module settings and empty tables do not count as shop setup' );
$GLOBALS['mp'] = (object) array( 'default_settings' => array( 'base_country' => 'AT', 'shipping' => array( 'method' => 'flat-rate' ) ) );
mp_prepare_site_shop_settings();
expect( 'AT' === $options['mp_settings']['base_country'] && 'flat-rate' === $options['mp_settings']['shipping']['method'] && 2 === $options['mp_settings']['shipping']['fedex']['build'], 'Setup initializes defaults without losing automatic settings' );

$active_settings = array(
	array( 'base_country' => 'DE' ),
	array( 'pages' => array( 'store' => 123 ) ),
	array( 'pages' => array( 'unknown' => '0' ) ),
	array( 'shipping' => array( 'fedex' => array( 'build' => 2, 'services' => array( 'test' ) ) ) ),
	array( 'addons' => array( 'unknown' ) ),
	'unknown',
);
foreach ( $active_settings as $settings ) {
	$options = array( 'mp_settings' => $settings );
	expect( mp_site_shop_is_active() && '1' === $options['mp_site_shop_active'], 'Configured or unknown settings remain active' );
}
foreach ( array(
	array( 'mp_needs_quick_setup' => 'skip' ),
	array( 'mp_needs_quick_setup' => '0' ),
	array( 'mp_needs_pages' => '0' ),
	array( 'mp_coupons' => array( 'used' ) ),
	array( 'mp_unknown_data' => '' ),
	array( 'widget_mp_cart_widget' => array( 2 => array( 'title' => 'Cart' ), '_multiwidget' => 1 ) ),
	array( 'sidebars_widgets' => array( 'sidebar-1' => array( 'mp_cart_widget-2' ) ) ),
	array( 'widget_text' => array( 2 => array( 'text' => '[mp_cart]' ) ) ),
) as $fixture ) {
	$options = $fixture;
	expect( mp_site_shop_is_active(), 'Setup or other existing shop data keeps the shop active' );
}
$options = array();
$wpdb->posts_found = true;
expect( mp_site_shop_is_active(), 'Existing product, order, coupon, shortcode or block keeps the shop active' );
$wpdb->posts_found = false;
$options = array();
$wpdb->terms_found = true;
expect( mp_site_shop_is_active(), 'Existing product taxonomy keeps the shop active' );
$wpdb->terms_found = false;
foreach ( array( 'wp_32_mp_product_attributes', 'wp_32_mp_product_attributes_terms', 'wp_32_mp_download_events', 'wp_mp_products', 'wp_mp_term_relationships', 'wp_mp_settlement_ledger' ) as $table ) {
	$options = array();
	$wpdb->tables = array( $table => true );
	expect( mp_site_shop_is_active(), 'Existing shop table data keeps the shop active: ' . $table );
}
$wpdb->tables = array();
foreach ( array( 'wp_mp_products' => 'blog_id', 'wp_mp_term_relationships' => 'blog_id', 'wp_mp_settlement_ledger' => 'shop_blog_id' ) as $table => $column ) {
	$options = array();
	$wpdb->queries = array();
	$wpdb->tables = array( $table => false );
	expect( ! mp_site_shop_is_active(), 'Other sites network data does not activate an unused shop: ' . $table );
	expect( in_array( "SELECT 1 FROM {$table} WHERE {$column} = 32 LIMIT 1", $wpdb->queries, true ), 'Network table is scoped to the current subsite: ' . $table );
}
$wpdb->tables = array();
foreach ( array( 'options', 'wp_32_posts', 'SHOW TABLES' ) as $failure ) {
	$options = array();
	$wpdb->fail = $failure;
	expect( mp_site_shop_is_active() && ! isset( $options['mp_site_shop_active'] ), 'Database errors keep the shop active without persisting a decision' );
}
$wpdb->fail = '';
$wpdb->tables = array( 'wp_32_mp_product_attributes' => false );
$wpdb->fail = 'FROM wp_32_mp_product_attributes ';
$options = array();
expect( mp_site_shop_is_active() && ! isset( $options['mp_site_shop_active'] ), 'Table data query errors keep the shop active without persisting a decision' );
$wpdb->fail = '';
$wpdb->tables = array();
$fail_add_option = true;
$options = array();
expect( mp_site_shop_is_active(), 'A failure to save the decision keeps the shop active' );
$fail_add_option = false;
$options = array( 'mp_site_shop_active' => '1', 'mp_settings' => array( 'pages' => array( 'none' => 4 ) ) );
expect( ! mp_site_shop_is_active() && '0' === $options['mp_site_shop_active'], 'Previously misclassified unused shop is rechecked and deactivated' );
$options = array( 'mp_site_shop_active' => '1', 'mp_needs_quick_setup' => '0' );
expect( mp_site_shop_is_active() && '2' === $options['mp_site_shop_migration_version'], 'Completed legacy setup remains active and is checked only once' );
$options = array( 'mp_site_shop_active' => '1', 'mp_needs_quick_setup' => 'skip' );
expect( mp_site_shop_is_active(), 'Legacy manual setup remains active' );
$options = array( 'mp_site_shop_active' => '1', 'mp_site_shop_activation_source' => 'explicit' );
expect( mp_site_shop_is_active() && '1' === $options['mp_site_shop_active'], 'Explicit active state is preserved even without other data' );
$options = array( 'mp_site_shop_active' => '1', 'mp_site_shop_needs_caps' => '1' );
expect( mp_site_shop_is_active(), 'In-progress activation remains active' );
$options = array( 'mp_site_shop_active' => '1', 'mp_site_shop_migration_version' => '2' );
expect( mp_site_shop_is_active(), 'A completed active migration is not repeated' );
$options = array( 'mp_site_shop_active' => '1' );
$wpdb->activate_during_update = true;
expect( mp_site_shop_is_active() && '1' === $options['mp_site_shop_active'], 'Concurrent explicit activation cannot be overwritten by migration' );
$wpdb->activate_during_update = false;
$options = array( 'mp_site_shop_active' => '1' );
$wpdb->fail = 'update_state';
expect( mp_site_shop_is_active() && ! isset( $options['mp_site_shop_migration_version'] ), 'A failed state update leaves the shop active and eligible for retry' );
$wpdb->fail = '';
$options = array( 'mp_site_shop_active' => '0', 'mp_settings' => array( 'base_country' => 'DE' ) );
expect( ! mp_site_shop_is_active(), 'Explicit inactive state is preserved' );
$options = array();
$blog_id = 77;
expect( mp_site_shop_is_active() && empty( $options ), 'Configured network shop root remains active' );
$blog_id = 32;

$options['mp_subsite_need_redirect'] = 1;
$fail_update_option = 'mp_site_shop_activation_source';
expect( ! mp_activate_site_shop(), 'Explicit activation fails safely if its source cannot be saved' );
$fail_update_option = '';
expect( mp_activate_site_shop(), 'Explicit activation succeeds' );
expect( mp_site_shop_is_active() && '1' === $options['mp_site_shop_needs_caps'] && 'explicit' === $options['mp_site_shop_activation_source'], 'Activation records its source and schedules administrator capabilities' );
expect( ! isset( $options['mp_subsite_need_redirect'] ), 'Manual activation does not force the wizard' );

try {
	mp_activate_site_shop_action();
	expect( false, 'Unauthorized activation is rejected' );
} catch ( RuntimeException $error ) {
	expect( false !== strpos( $error->getMessage(), 'nicht aktivieren' ), 'Unauthorized activation is rejected' );
}

$options['mp_site_shop_active'] = '0';
$can_manage = true;
try {
	mp_activate_site_shop_action();
	expect( false, 'Invalid nonce is rejected' );
} catch ( RuntimeException $error ) {
	expect( 'Invalid nonce.' === $error->getMessage() && ! mp_site_shop_is_active(), 'Invalid nonce cannot activate the shop' );
}
