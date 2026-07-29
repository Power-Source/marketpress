<?php

class MP_Network_Settlement {

	/**
	 * @var MP_Network_Settlement|null
	 */
	private static $_instance = null;

	/**
	 * @return MP_Network_Settlement
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new MP_Network_Settlement();
		}

		return self::$_instance;
	}

	/**
	 * @var string
	 */
	private $table_name = '';

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->base_prefix . 'mp_settlement_ledger';

		$this->maybe_create_table();

		add_action( 'mp_order/new_order', array( $this, 'sync_order_ledger' ), 30 );
		add_action( 'transition_post_status', array( $this, 'maybe_sync_on_status_change' ), 20, 3 );
		add_action( 'save_post_mp_order', array( $this, 'maybe_sync_on_order_save' ), 20, 2 );

		add_shortcode( 'mp_network_settlement_dashboard', array( $this, 'render_frontend_dashboard_shortcode' ) );

		if ( is_admin() && ! is_network_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_main_menu' ) );
		}

		add_action( 'init', array( $this, 'redirect_direct_settlement_paths' ), 1 );

		add_action( 'admin_post_mp_settlement_decision', array( $this, 'handle_admin_decision' ) );
	}

	/**
	 * Create settlement ledger table.
	 */
	public function maybe_create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_post_id bigint(20) unsigned NOT NULL,
			order_key varchar(64) NOT NULL,
			shop_blog_id bigint(20) unsigned NOT NULL,
			customer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_email varchar(190) NOT NULL DEFAULT '',
			currency varchar(12) NOT NULL DEFAULT '',
			gross_amount decimal(18,6) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'expected_credit',
			gate_reason varchar(100) NOT NULL DEFAULT '',
			rule_snapshot longtext NULL,
			manual_decision varchar(20) NOT NULL DEFAULT '',
			manual_note text NULL,
			released_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_blog (order_post_id,shop_blog_id),
			KEY status (status),
			KEY shop_blog_id (shop_blog_id),
			KEY order_key (order_key)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Main site admin menu.
	 */
	public function add_main_menu() {
		add_submenu_page(
			null,
			__( 'Auszahlungsfreigabe', 'mp' ),
			__( 'Auszahlungsfreigabe', 'mp' ),
			'read',
			'store-settings-settlement',
			array( $this, 'render_main_admin_page' )
		);
	}

	/**
	 * Resolve the capability required for accessing settlement admin UI.
	 * Falls back for multisite/main-site contexts where custom caps might be missing.
	 *
	 * @return string
	 */
	private function get_required_capability() {
		return (string) apply_filters( 'mp_settlement_required_cap', 'manage_settlement_approvals' );
	}

	/**
	 * Central access check for settlement moderation.
	 * Super admins are always allowed; site-level roles can be granted via dedicated capability.
	 *
	 * @return bool
	 */
	private function current_user_can_access_settlement() {
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		if ( current_user_can( 'manage_network_options' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		$required_cap = $this->get_required_capability();
		if ( '' !== $required_cap && current_user_can( $required_cap ) ) {
			return true;
		}

		if ( current_user_can( apply_filters( 'mp_store_settings_cap', 'manage_store_settings' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Redirect legacy/direct settlement paths to the canonical admin URL.
	 */
	public function redirect_direct_settlement_paths() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $request_uri ) {
			return;
		}

		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( '' === $path ) {
			return;
		}

		$normalized_path = rtrim( $path, '/' );
		$bad_paths = array(
			'/store-settings-settlement',
			'/wp-admin/store-settings-settlement',
			'/wp-admin/network/network-settlement-moderation',
		);

		if ( ! in_array( $normalized_path, $bad_paths, true ) ) {
			return;
		}

		$target = admin_url( 'admin.php?page=store-settings-settlement' );
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $target, 302 );
			exit;
		}

		wp_safe_redirect( wp_login_url( $target ), 302 );
		exit;
	}

	/**
	 * Render main site moderation queue.
	 */
	public function render_main_admin_page() {
		if ( ! $this->current_user_can_access_settlement() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mp' ) );
		}

		if ( ! mp_get_network_setting( 'advanced->settlement_enabled', 0 ) ) {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Auszahlungsfreigabe', 'mp' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Die Auszahlungsfreigabe ist aktuell deaktiviert. Aktiviere sie in den Netzwerk-Einstellungen unter MarketPress.', 'mp' ) . '</p></div>';
			echo '</div>';
			return;
		}

		$status = sanitize_key( (string) mp_get_get_value( 'status', 'open' ) );
		$rows   = $this->get_queue_rows( $status );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Auszahlungsfreigabe', 'mp' ) . '</h1>';
		echo '<p>' . esc_html__( 'Liste offener Auszahlungen, die zurueckgehalten oder freigegeben werden koennen.', 'mp' ) . '</p>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=store-settings-settlement&status=open' ) ) . '">' . esc_html__( 'Nur offene', 'mp' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=store-settings-settlement&status=all' ) ) . '">' . esc_html__( 'Alle', 'mp' ) . '</a>';
		echo '</p>';

		echo $this->render_rows_table( $rows, true );
		echo '</div>';
	}

	/**
	 * Frontend moderation/dashboard shortcode.
	 *
	 * @return string
	 */
	public function render_frontend_dashboard_shortcode() {
		return '';
	}

	/**
	 * Sync ledger after order creation.
	 *
	 * @param MP_Order $order
	 */
	public function sync_order_ledger( $order ) {
		if ( ! mp_get_network_setting( 'advanced->settlement_enabled', 0 ) ) {
			return;
		}

		if ( ! is_object( $order ) || ! method_exists( $order, 'exists' ) || ! $order->exists() ) {
			return;
		}

		$this->sync_order_ledger_by_id( (int) $order->ID );
	}

	/**
	 * Sync when order status changes.
	 */
	public function maybe_sync_on_status_change( $new_status, $old_status, $post ) {
		if ( 'mp_order' !== $post->post_type ) {
			return;
		}

		$this->sync_order_ledger_by_id( (int) $post->ID );
	}

	/**
	 * Sync when order is updated in admin.
	 */
	public function maybe_sync_on_order_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'mp_order' !== $post->post_type ) {
			return;
		}

		$this->sync_order_ledger_by_id( (int) $post_id );
	}

	/**
	 * Build/update settlement lines for a specific order.
	 */
	public function sync_order_ledger_by_id( $order_post_id ) {
		$order = new MP_Order( $order_post_id );
		if ( ! $order->exists() ) {
			return;
		}

		$splits = $this->calculate_shop_splits( $order );
		if ( empty( $splits ) ) {
			return;
		}

		$order_key     = (string) $order->get_id();
		$customer_id   = (int) $order->post_author;
		$customer_mail = (string) $order->get_meta( 'mp_billing_info->email', '' );
		$currency      = (string) $order->get_meta( 'mp_payment_info->currency', mp_get_setting( 'currency', 'EUR' ) );

		foreach ( $splits as $blog_id => $split ) {
			$current = $this->get_row_by_order_and_blog( $order_post_id, $blog_id );
			$rule    = $this->evaluate_release_gate( $order, $split, $current );

			$this->upsert_row( array(
				'order_post_id'     => $order_post_id,
				'order_key'         => $order_key,
				'shop_blog_id'      => $blog_id,
				'customer_user_id'  => $customer_id,
				'customer_email'    => $customer_mail,
				'currency'          => $currency,
				'gross_amount'      => $split['gross_amount'],
				'status'            => $rule['status'],
				'gate_reason'       => $rule['gate_reason'],
				'rule_snapshot'     => wp_json_encode( $rule ),
				'manual_decision'   => isset( $rule['manual_decision'] ) ? $rule['manual_decision'] : '',
				'manual_note'       => isset( $rule['manual_note'] ) ? $rule['manual_note'] : '',
				'released_at'       => isset( $rule['released_at'] ) ? $rule['released_at'] : null,
			) );
		}
	}

	/**
	 * Calculate per-shop gross credit split.
	 *
	 * @param MP_Order $order
	 *
	 * @return array
	 */
	private function calculate_shop_splits( MP_Order $order ) {
		$cart = $order->get_cart();
		if ( ! $cart instanceof MP_Cart ) {
			return array();
		}

		$all_items = (array) $cart->get_all_items();
		$all_items = array_filter( $all_items );
		if ( empty( $all_items ) ) {
			$all_items = array( get_current_blog_id() => (array) $cart->get_items() );
		}

		$order_total = (float) $order->get_meta( 'mp_order_total', 0 );
		$shop_data   = array();
		$total_base  = 0.0;

		foreach ( $all_items as $blog_id => $items ) {
			$blog_id = (int) $blog_id;
			if ( empty( $items ) ) {
				continue;
			}

			$current_blog_id = get_current_blog_id();
			if ( $blog_id !== $current_blog_id ) {
				switch_to_blog( $blog_id );
			}

			$subtotal               = 0.0;
			$has_physical           = false;
			$all_withdrawal_excl    = true;
			$all_warranty_excl      = true;

			foreach ( $items as $product_id => $qty ) {
				$product = new MP_Product( (int) $product_id, $blog_id );
				if ( ! method_exists( $product, 'exists' ) || ! $product->exists() ) {
					continue;
				}

				$price = (float) $product->get_price( 'lowest' );
				$qty   = max( 1, (int) $qty );
				$subtotal += ( $price * $qty );

				if ( ! $product->is_download() ) {
					$has_physical = true;
				}

				if ( ! (bool) get_post_meta( (int) $product_id, 'mp_withdrawal_excluded', true ) ) {
					$all_withdrawal_excl = false;
				}

				if ( ! (bool) get_post_meta( (int) $product_id, 'mp_warranty_excluded', true ) ) {
					$all_warranty_excl = false;
				}
			}

			if ( $blog_id !== $current_blog_id ) {
				restore_current_blog();
			}

			if ( $subtotal <= 0 ) {
				continue;
			}

			$shop_data[ $blog_id ] = array(
				'base_subtotal'       => $subtotal,
				'has_physical'        => $has_physical,
				'no_withdrawal_rule'  => $all_withdrawal_excl,
				'no_warranty_rule'    => $all_warranty_excl,
			);
			$total_base += $subtotal;
		}

		if ( empty( $shop_data ) ) {
			return array();
		}

		$allocated = array();
		$running   = 0.0;
		$idx       = 0;
		$count     = count( $shop_data );

		foreach ( $shop_data as $blog_id => $data ) {
			$idx ++;
			if ( $idx === $count ) {
				$gross = round( max( 0, $order_total - $running ), 2 );
			} else {
				$gross = $total_base > 0 ? round( $order_total * ( $data['base_subtotal'] / $total_base ), 2 ) : round( $order_total / $count, 2 );
				$running += $gross;
			}

			$allocated[ $blog_id ] = array_merge( $data, array( 'gross_amount' => $gross ) );
		}

		return $allocated;
	}

	/**
	 * Evaluate release gates.
	 */
	private function evaluate_release_gate( MP_Order $order, $split, $current_row = null ) {
		$rule = array(
			'status'          => 'expected_credit',
			'gate_reason'     => 'payment_pending',
			'manual_decision' => '',
			'manual_note'     => '',
			'released_at'     => null,
			'context'         => array(),
		);

		if ( is_array( $current_row ) && ! empty( $current_row['manual_decision'] ) ) {
			$rule['manual_decision'] = $current_row['manual_decision'];
			$rule['manual_note']     = $current_row['manual_note'];
			if ( 'hold' === $current_row['manual_decision'] ) {
				$rule['status']      = 'on_hold';
				$rule['gate_reason'] = 'manual_hold';
				return $rule;
			}
			if ( 'release' === $current_row['manual_decision'] ) {
				$rule['status']      = 'released';
				$rule['gate_reason'] = 'manual_release';
				$rule['released_at'] = gmdate( 'Y-m-d H:i:s' );
				return $rule;
			}
		}

		$order_status = (string) get_post_status( $order->ID );
		if ( ! in_array( $order_status, array( 'order_paid', 'order_shipped' ), true ) ) {
			$rule['status']      = 'expected_credit';
			$rule['gate_reason'] = 'payment_pending';
			return $rule;
		}

		if ( $this->has_open_withdrawal_or_objection( $order ) ) {
			$rule['status']      = 'on_hold';
			$rule['gate_reason'] = 'withdrawal_or_objection_open';
			return $rule;
		}

		if ( ! empty( $split['has_physical'] ) && 'order_shipped' !== $order_status ) {
			$rule['status']      = 'on_hold';
			$rule['gate_reason'] = 'awaiting_shipping';
			return $rule;
		}

		$hold_days = (int) mp_get_network_setting( 'advanced->settlement_hold_days', 14 );
		if ( ! empty( $split['no_withdrawal_rule'] ) && ! empty( $split['no_warranty_rule'] ) ) {
			$hold_days = 0;
		}

		$received_time = (int) $order->get_meta( 'mp_received_time', 0 );
		if ( ! $received_time ) {
			$received_time = strtotime( (string) get_post_field( 'post_date_gmt', $order->ID ) . ' UTC' );
		}

		$hold_until = $received_time + ( $hold_days * DAY_IN_SECONDS );
		if ( time() < $hold_until ) {
			$rule['status']      = 'on_hold';
			$rule['gate_reason'] = 'hold_period';
			$rule['context']     = array( 'hold_until' => gmdate( 'Y-m-d H:i:s', $hold_until ) );
			return $rule;
		}

		if ( mp_get_network_setting( 'advanced->settlement_auto_release', 0 ) ) {
			$rule['status']      = 'released';
			$rule['gate_reason'] = 'auto_release';
			$rule['released_at'] = gmdate( 'Y-m-d H:i:s' );
			return $rule;
		}

		$rule['status']      = 'releasable';
		$rule['gate_reason'] = 'ready_for_release';

		return $rule;
	}

	/**
	 * @return bool
	 */
	private function has_open_withdrawal_or_objection( MP_Order $order ) {
		if ( (bool) $order->get_meta( 'mp_settlement_objection_open', false ) ) {
			return true;
		}

		$requests = $order->get_meta( 'mp_withdrawal_requests', array() );
		if ( ! is_array( $requests ) ) {
			return false;
		}

		$done = array( 'resolved', 'completed', 'rejected', 'denied', 'cancelled', 'approved', 'refunded', 'closed' );
		foreach ( $requests as $request ) {
			$status = strtolower( (string) mp_arr_get_value( 'status', (array) $request, 'requested' ) );
			if ( ! in_array( $status, $done, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Upsert ledger row.
	 */
	private function upsert_row( $data ) {
		global $wpdb;

		$now      = gmdate( 'Y-m-d H:i:s' );
		$existing = $this->get_row_by_order_and_blog( (int) $data['order_post_id'], (int) $data['shop_blog_id'] );

		if ( $existing ) {
			$wpdb->update(
				$this->table_name,
				array(
					'currency'        => $data['currency'],
					'gross_amount'    => $data['gross_amount'],
					'status'          => $data['status'],
					'gate_reason'     => $data['gate_reason'],
					'rule_snapshot'   => $data['rule_snapshot'],
					'manual_decision' => $data['manual_decision'],
					'manual_note'     => $data['manual_note'],
					'released_at'     => $data['released_at'],
					'updated_at'      => $now,
				),
				array( 'id' => (int) $existing['id'] ),
				array( '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return;
		}

		$wpdb->insert(
			$this->table_name,
			array(
				'order_post_id'    => (int) $data['order_post_id'],
				'order_key'        => $data['order_key'],
				'shop_blog_id'     => (int) $data['shop_blog_id'],
				'customer_user_id' => (int) $data['customer_user_id'],
				'customer_email'   => $data['customer_email'],
				'currency'         => $data['currency'],
				'gross_amount'     => $data['gross_amount'],
				'status'           => $data['status'],
				'gate_reason'      => $data['gate_reason'],
				'rule_snapshot'    => $data['rule_snapshot'],
				'manual_decision'  => $data['manual_decision'],
				'manual_note'      => $data['manual_note'],
				'released_at'      => $data['released_at'],
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Fetch one row by order/blog pair.
	 */
	private function get_row_by_order_and_blog( $order_post_id, $blog_id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE order_post_id = %d AND shop_blog_id = %d LIMIT 1",
			(int) $order_post_id,
			(int) $blog_id
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get queue rows.
	 */
	private function get_queue_rows( $status = 'open' ) {
		global $wpdb;

		$where = '';
		if ( 'open' === $status ) {
			$where = "WHERE status IN ('expected_credit','on_hold','releasable')";
		} elseif ( 'all' !== $status ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		$sql = "SELECT * FROM {$this->table_name} {$where} ORDER BY updated_at DESC LIMIT 300";
		return (array) $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Render rows table for admin/frontend.
	 */
	private function render_rows_table( $rows, $allow_actions ) {
		if ( empty( $rows ) ) {
			return '<p>' . esc_html__( 'Keine offenen Auszahlungen zur Freigabe.', 'mp' ) . '</p>';
		}

		$html  = '<table class="widefat striped">';
		$html .= '<thead><tr>';
		$html .= '<th>ID</th><th>' . esc_html__( 'Bestellung', 'mp' ) . '</th><th>' . esc_html__( 'Shop', 'mp' ) . '</th><th>' . esc_html__( 'Status', 'mp' ) . '</th><th>' . esc_html__( 'Grund', 'mp' ) . '</th><th>' . esc_html__( 'Betrag', 'mp' ) . '</th><th>' . esc_html__( 'Aktion', 'mp' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$shop_name = '';
			$details   = get_blog_details( (int) $row['shop_blog_id'] );
			if ( $details && isset( $details->blogname ) ) {
				$shop_name = $details->blogname;
			}

			$status_label = $this->get_settlement_status_label( isset( $row['status'] ) ? (string) $row['status'] : '' );
			$reason_label = $this->get_settlement_reason_label( isset( $row['gate_reason'] ) ? (string) $row['gate_reason'] : '' );

			$html .= '<tr>';
			$html .= '<td>' . (int) $row['id'] . '</td>';
			$html .= '<td>#' . esc_html( $row['order_key'] ) . '</td>';
			$html .= '<td>' . esc_html( $shop_name ) . ' (' . (int) $row['shop_blog_id'] . ')</td>';
			$html .= '<td>' . esc_html( $status_label ) . '</td>';
			$html .= '<td>' . esc_html( $reason_label ) . '</td>';
			$html .= '<td>' . esc_html( mp_format_currency( $row['currency'], (float) $row['gross_amount'] ) ) . '</td>';
			$html .= '<td>';

			if ( $allow_actions ) {
				$hold_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=mp_settlement_decision&row=' . (int) $row['id'] . '&decision=hold' ),
					'mp_settlement_decision_' . (int) $row['id']
				);
				$release_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=mp_settlement_decision&row=' . (int) $row['id'] . '&decision=release' ),
					'mp_settlement_decision_' . (int) $row['id']
				);
				$html .= '<a class="button" href="' . esc_url( $hold_url ) . '">' . esc_html__( 'Zurueckhalten', 'mp' ) . '</a> ';
				$html .= '<a class="button button-primary" href="' . esc_url( $release_url ) . '">' . esc_html__( 'Freigeben', 'mp' ) . '</a>';
			} else {
				$html .= '&mdash;';
			}

			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * Get localized label for a settlement status key.
	 *
	 * @param string $status
	 * @return string
	 */
	private function get_settlement_status_label( $status ) {
		$labels = array(
			'expected_credit' => __( 'Noch offen', 'mp' ),
			'on_hold'         => __( 'Zurueckgehalten', 'mp' ),
			'releasable'      => __( 'Freigabefaehig', 'mp' ),
			'released'        => __( 'Freigegeben', 'mp' ),
		);

		$status = sanitize_key( (string) $status );

		if ( isset( $labels[ $status ] ) ) {
			return $labels[ $status ];
		}

		if ( '' === $status ) {
			return __( 'Unbekannt', 'mp' );
		}

		return ucwords( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Get localized label for a settlement reason key.
	 *
	 * @param string $reason
	 * @return string
	 */
	private function get_settlement_reason_label( $reason ) {
		$labels = array(
			'payment_pending'        => __( 'Zahlung noch offen', 'mp' ),
			'awaiting_shipping'      => __( 'Wartet auf Versandabschluss', 'mp' ),
			'awaiting_withdrawal'    => __( 'Wartet auf Widerrufsfrist', 'mp' ),
			'awaiting_warranty'      => __( 'Wartet auf Gewaehrleistungsfrist', 'mp' ),
			'ready_for_release'      => __( 'Bereit zur Freigabe', 'mp' ),
			'manual_hold'            => __( 'Manuell zurueckgehalten', 'mp' ),
			'manual_release'         => __( 'Manuell freigegeben', 'mp' ),
			'invalid_order'          => __( 'Ungueltige Bestellung', 'mp' ),
			'no_shop_split'          => __( 'Keine Shop-Aufteilung gefunden', 'mp' ),
			'objection_open'         => __( 'Offener Einwand', 'mp' ),
			'chargeback_open'        => __( 'Chargeback offen', 'mp' ),
			'refund_open'            => __( 'Rueckerstattung offen', 'mp' ),
		);

		$reason = sanitize_key( (string) $reason );

		if ( isset( $labels[ $reason ] ) ) {
			return $labels[ $reason ];
		}

		if ( '' === $reason ) {
			return __( 'Unbekannt', 'mp' );
		}

		return ucwords( str_replace( '_', ' ', $reason ) );
	}

	/**
	 * Manual hold/release action.
	 */
	public function handle_admin_decision() {
		if ( ! $this->current_user_can_access_settlement() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mp' ) );
		}

		$row_id   = (int) mp_get_get_value( 'row', 0 );
		$decision = sanitize_key( (string) mp_get_get_value( 'decision', '' ) );

		if ( ! in_array( $decision, array( 'hold', 'release' ), true ) || ! $row_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=store-settings-settlement' ) );
			exit;
		}

		check_admin_referer( 'mp_settlement_decision_' . $row_id );

		global $wpdb;
		$payload = array(
			'manual_decision' => $decision,
			'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( 'hold' === $decision ) {
			$payload['status']      = 'on_hold';
			$payload['gate_reason'] = 'manual_hold';
		} else {
			$payload['status']      = 'released';
			$payload['gate_reason'] = 'manual_release';
			$payload['released_at'] = gmdate( 'Y-m-d H:i:s' );
		}

		$wpdb->update( $this->table_name, $payload, array( 'id' => $row_id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=store-settings-settlement' ) );
		exit;
	}
}

MP_Network_Settlement::get_instance();
