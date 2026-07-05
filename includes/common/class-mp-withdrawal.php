<?php

class MP_Withdrawal {
	/**
	 * @var MP_Withdrawal|null
	 */
	private static $_instance = null;

	/**
	 * @return MP_Withdrawal
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new MP_Withdrawal();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'mp_order/new_order', array( $this, 'save_order_withdrawal_snapshot' ), 20 );
		add_filter( 'mp_order/details', array( $this, 'append_customer_zone_to_order_details' ), 20, 2 );
		add_filter( 'mp_order/status_html', array( $this, 'append_customer_zone_to_status_page' ), 20, 3 );
		add_filter( 'mp_order/status_url', array( $this, 'add_withdrawal_token_for_guest_orders' ), 20, 2 );
		add_filter( 'mp_store_navigation', array( $this, 'append_navigation_link' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'register_order_metabox' ) );
		add_action( 'save_post_mp_order', array( $this, 'save_order_metabox' ), 10, 2 );

		add_action( 'wp_ajax_mp_submit_withdrawal', array( $this, 'ajax_submit_withdrawal' ) );
		add_action( 'wp_ajax_nopriv_mp_submit_withdrawal', array( $this, 'ajax_submit_withdrawal' ) );
		add_action( 'wp_ajax_mp_get_withdrawal_status', array( $this, 'ajax_get_withdrawal_status' ) );
		add_action( 'wp_ajax_nopriv_mp_get_withdrawal_status', array( $this, 'ajax_get_withdrawal_status' ) );
	}

	/**
	 * Create immutable withdrawal eligibility snapshot for each ordered item.
	 *
	 * @param MP_Order $order
	 */
	public function save_order_withdrawal_snapshot( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'exists' ) || ! $order->exists() ) {
			return;
		}

		$items = $order->get_meta( 'mp_cart_items', array() );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return;
		}

		$snapshot = array();

		foreach ( $items as $product_id => $variations ) {
			if ( ! is_array( $variations ) ) {
				continue;
			}

			foreach ( $variations as $variation_id => $item ) {
				$resolved_product_id = (int) ( (int) $variation_id > 0 ? $variation_id : $product_id );
				$product             = new MP_Product( $resolved_product_id );
				$product_name        = '';
				$product_type        = '';
				$quantity            = (int) mp_arr_get_value( 'quantity', $item, 1 );

				if ( method_exists( $product, 'exists' ) && $product->exists() ) {
					$product_name = $product->title( false );
					$product_type = $product->get_meta( 'product_type', 'physical' );
				}

				$excluded = (int) get_post_meta( $resolved_product_id, 'mp_withdrawal_excluded', true );
				if ( ! $excluded && (int) $variation_id > 0 ) {
					$excluded = (int) get_post_meta( (int) $product_id, 'mp_withdrawal_excluded', true );
				}

				$reason = (string) get_post_meta( $resolved_product_id, 'mp_withdrawal_exclusion_reason', true );
				if ( '' === $reason && (int) $variation_id > 0 ) {
					$reason = (string) get_post_meta( (int) $product_id, 'mp_withdrawal_exclusion_reason', true );
				}

				$key = (int) $product_id . ':' . (int) $variation_id;
				$snapshot[ $key ] = array(
					'product_id'                  => (int) $product_id,
					'variation_id'                => (int) $variation_id,
					'resolved_product_id'         => $resolved_product_id,
					'product_name'                => $product_name,
					'product_type'                => $product_type,
					'quantity'                    => max( 1, $quantity ),
					'withdrawal_excluded'         => (int) $excluded,
					'withdrawal_exclusion_reason' => sanitize_text_field( $reason ),
				);
			}
		}

		if ( ! empty( $snapshot ) ) {
			update_post_meta( $order->ID, 'mp_withdrawal_snapshot', $snapshot );
		}
	}

	/**
	 * Add dedicated token for guest withdrawal checks.
	 *
	 * @param string   $url
	 * @param MP_Order $order
	 *
	 * @return string
	 */
	public function add_withdrawal_token_for_guest_orders( $url, $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return $url;
		}

		if ( 'guest' !== $order->get_meta( 'mp_user_kind', '' ) ) {
			return $url;
		}

		$tokens = $this->get_valid_withdrawal_tokens( $order );
		$token  = reset( $tokens );
		if ( ! is_string( $token ) || '' === $token ) {
			return $url;
		}

		return add_query_arg( 'mp_withdrawal_token', $token, $url );
	}

	/**
	 * Append customer zone to order details view.
	 *
	 * @param string   $html
	 * @param MP_Order $order
	 *
	 * @return string
	 */
	public function append_customer_zone_to_order_details( $html, $order ) {
		if ( ! $this->is_enabled() ) {
			return $html;
		}

		if ( ! is_object( $order ) || ! method_exists( $order, 'exists' ) || ! $order->exists() ) {
			return $html;
		}

		if ( ! mp_is_shop_page( 'order_status' ) || ! get_query_var( 'mp_order_id' ) ) {
			return $html;
		}

		if ( ! $this->can_access_order( $order ) ) {
			return $html;
		}

		$zone_html = $this->render_customer_zone( $order );
		return $html . $zone_html;
	}

	/**
	 * Append customer zone intro panel to order status page output.
	 *
	 * @param string        $html
	 * @param MP_Order|null $order
	 * @param array         $args
	 *
	 * @return string
	 */
	public function append_customer_zone_to_status_page( $html, $order, $args ) {
		if ( ! mp_is_shop_page( 'order_status' ) || ! $this->is_enabled() ) {
			return $html;
		}

		$policy = (string) mp_get_setting( 'withdrawal->policy_text', '' );
		if ( '' === trim( $policy ) ) {
			$policy = __( 'Du kannst Deinen Widerruf hier digital erklären. Wähle Positionen, Grund und sende direkt ab. Danach siehst Du jederzeit den Status.', 'mp' );
		}

		$intro  = '<section class="mp_customer_zone_intro">';
		$intro .= '<h2>' . esc_html__( 'Kundenzone', 'mp' ) . '</h2>';
		$intro .= '<p>' . esc_html( $policy ) . '</p>';
		$intro .= '</section>';

		if ( is_object( $order ) && method_exists( $order, 'exists' ) && $order->exists() ) {
			return $intro . $html;
		}

		if ( is_user_logged_in() ) {
			$intro .= '<p class="mp_customer_zone_intro_hint">' . esc_html__( 'Wähle eine Bestellung aus Deiner Historie aus, um einen Widerruf zu starten.', 'mp' ) . '</p>';
		} else {
			$intro .= '<p class="mp_customer_zone_intro_hint">' . esc_html__( 'Nutze die Bestellsuche, öffne Deine Bestellung und widerrufe widerrufsfähige Positionen direkt online.', 'mp' ) . '</p>';
		}

		return $intro . $html;
	}

	/**
	 * Handle digital withdrawal submission.
	 */
	public function ajax_submit_withdrawal() {
		if ( ! $this->is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Der digitale Widerruf ist aktuell deaktiviert.', 'mp' ) ) );
		}

		$order_id = (int) mp_get_post_value( 'order_id', 0 );
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Anfrage. Bitte lade die Seite neu.', 'mp' ) ) );
		}

		$order = new MP_Order( $order_id );
		if ( ! $order->exists() ) {
			wp_send_json_error( array( 'message' => __( 'Bestellung nicht gefunden oder kein Zugriff.', 'mp' ) ) );
		}

		$nonce        = (string) mp_get_post_value( 'nonce', '' );
		$ajax_nonce   = (string) mp_get_post_value( 'ajax_nonce', '' );
		$access_token = sanitize_text_field( (string) mp_get_post_value( 'access_token', '' ) );
		$nonce_valid  = (bool) wp_verify_nonce( $nonce, 'mp_submit_withdrawal_' . $order_id );
		if ( ! $nonce_valid && '' !== $ajax_nonce ) {
			$nonce_valid = (bool) wp_verify_nonce( $ajax_nonce, 'mp-ajax-nonce' );
		}
		$token_valid  = $this->is_valid_withdrawal_access_token( $order, $access_token );

		if ( ! $nonce_valid && ! $token_valid ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Anfrage. Bitte lade die Seite neu.', 'mp' ) ) );
		}

		if ( ! $this->can_access_order( $order, $access_token ) ) {
			wp_send_json_error( array( 'message' => __( 'Bestellung nicht gefunden oder kein Zugriff.', 'mp' ) ) );
		}

		$items = mp_get_post_value( 'items', array() );
		if ( ! is_array( $items ) || empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'Bitte wähle mindestens eine Position aus.', 'mp' ) ) );
		}

		$snapshot = $this->get_snapshot( $order );
		if ( empty( $snapshot ) ) {
			wp_send_json_error( array( 'message' => __( 'Für diese Bestellung sind keine Widerrufspositionen verfügbar.', 'mp' ) ) );
		}

		$selected = array();
		$blocked  = array();

		foreach ( $items as $item_key ) {
			$key = sanitize_text_field( (string) $item_key );
			if ( ! isset( $snapshot[ $key ] ) ) {
				continue;
			}

			$row = $snapshot[ $key ];
			if ( ! empty( $row['withdrawal_excluded'] ) ) {
				$blocked[] = mp_arr_get_value( 'product_name', $row, __( 'Produkt', 'mp' ) );
				continue;
			}

			$selected[ $key ] = $row;
		}

		if ( empty( $selected ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine widerrufsfähigen Positionen ausgewählt.', 'mp' ) ) );
		}

		$reason_options = $this->get_reason_options();
		$reason_code    = sanitize_key( (string) mp_get_post_value( 'reason_code', '' ) );
		if ( '' === $reason_code || ! isset( $reason_options[ $reason_code ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Bitte wähle einen Widerrufsgrund aus.', 'mp' ) ) );
		}

		$max_reason_length = $this->get_reason_max_length();
		$reason_note       = sanitize_textarea_field( (string) mp_get_post_value( 'reason_note', '' ) );
		if ( ! $this->allow_custom_reason() ) {
			$reason_note = '';
		}

		if ( function_exists( 'mb_strlen' ) ) {
			$reason_len = mb_strlen( $reason_note, 'UTF-8' );
		} else {
			$reason_len = strlen( $reason_note );
		}

		if ( $reason_len > $max_reason_length ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Bitte kürze die Begründung auf maximal %d Zeichen.', 'mp' ), $max_reason_length ) ) );
		}

		$entry = array(
			'timestamp'      => time(),
			'order_id'       => $order->get_id(),
			'status'         => 'requested',
			'reason_code'    => $reason_code,
			'reason_label'   => (string) $reason_options[ $reason_code ],
			'reason_note'    => $reason_note,
			'items'          => $selected,
			'blocked_items'  => $blocked,
			'customer_email' => (string) $order->get_meta( 'mp_billing_info->email', '' ),
			'customer_name'  => trim( (string) $order->get_name( 'billing' ) ),
			'source'         => 'digital_button',
		);

		$requests = $order->get_meta( 'mp_withdrawal_requests', array() );
		if ( ! is_array( $requests ) ) {
			$requests = array();
		}

		$requests[] = $entry;
		update_post_meta( $order->ID, 'mp_withdrawal_requests', $requests );
		update_post_meta( $order->ID, '_mp_withdrawal_status', 'requested' );
		update_post_meta( $order->ID, '_mp_withdrawal_last_update', time() );
		do_action( 'mp_withdrawal_updated', (int) $order->ID, (int) $order->post_author, (int) get_current_blog_id() );

		$this->send_confirmation_email( $order, $entry );

		wp_send_json_success( array(
			'message' => __( 'Widerruf wurde übermittelt. Du erhältst sofort eine Eingangsbestätigung per E-Mail.', 'mp' ),
			'status'  => array(
				'key'   => 'requested',
				'label' => $this->get_status_label( 'requested' ),
			),
		) );
	}

	/**
	 * Return customer-visible status timeline for one order.
	 */
	public function ajax_get_withdrawal_status() {
		$order_id = (int) mp_get_request_value( 'order_id', 0 );
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Ungueltige Bestellung.', 'mp' ) ), 400 );
		}

		$order = new MP_Order( $order_id );
		if ( ! $order->exists() ) {
			wp_send_json_error( array( 'message' => __( 'Bestellung nicht gefunden.', 'mp' ) ), 404 );
		}

		$access_token = sanitize_text_field( (string) mp_get_request_value( 'access_token', '' ) );
		if ( ! $this->can_access_order( $order, $access_token ) ) {
			wp_send_json_error( array( 'message' => __( 'Kein Zugriff auf diese Bestellung.', 'mp' ) ), 403 );
		}

		wp_send_json_success( array(
			'entries' => $this->get_withdrawal_entries_for_customer( $order ),
		) );
	}

	/**
	 * Add customer zone link to nav.
	 *
	 * @param string $nav
	 *
	 * @return string
	 */
	public function append_navigation_link( $nav ) {
		if ( ! $this->is_enabled() ) {
			return $nav;
		}

		$link = '<li class="page_item"><a href="' . esc_url( mp_store_page_url( 'order_status', false ) ) . '#mp-customer-zone" title="' . esc_attr__( 'Kundenzone & Widerruf', 'mp' ) . '">' . esc_html__( 'Kundenzone', 'mp' ) . '</a></li>';

		if ( false !== strpos( $nav, '</ul>' ) ) {
			return str_replace( '</ul>', $link . '</ul>', $nav );
		}

		return $nav . '<ul class="mp_store_navigation">' . $link . '</ul>';
	}

	/**
	 * Enqueue UI assets.
	 */
	public function enqueue_assets() {
		if ( is_admin() || ! mp_is_shop_page( 'order_status' ) || ! $this->is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'mp-withdrawal-zone',
			mp_plugin_url( 'ui/css/mp-withdrawal-zone.css' ),
			array( 'mp-frontend' ),
			MP_VERSION
		);

		wp_enqueue_script(
			'mp-withdrawal-zone',
			mp_plugin_url( 'ui/js/mp-withdrawal-zone.js' ),
			array( 'jquery' ),
			MP_VERSION,
			true
		);

		wp_localize_script( 'mp-withdrawal-zone', 'mp_withdrawal_i18n', array(
			'ajaxurl' => mp_get_ajax_url(),
			'messages' => array(
				'selectItems' => __( 'Bitte wähle mindestens eine Position aus.', 'mp' ),
				'selectReason' => __( 'Bitte wähle einen Widerrufsgrund aus.', 'mp' ),
				'noteTooLong'  => __( 'Bitte kürze die Begründung auf die maximal erlaubte Länge.', 'mp' ),
				'submitError'  => __( 'Widerruf konnte nicht übermittelt werden.', 'mp' ),
			),
		) );
	}

	/**
	 * Check whether current visitor can access order.
	 *
	 * @param MP_Order $order
	 *
	 * @return bool
	 */
	private function can_access_order( $order, $access_token = '' ) {
		if ( is_user_logged_in() ) {
			if ( (int) $order->post_author === (int) get_current_user_id() || current_user_can( apply_filters( 'mp_store_settings_cap', 'read_store_order' ) ) ) {
				return true;
			}

			return false;
		}

		if ( 'guest' !== $order->get_meta( 'mp_user_kind', '' ) ) {
			return false;
		}

		$legacy_hash = trim( (string) $access_token );
		if ( '' === $legacy_hash ) {
			$legacy_hash = (string) get_query_var( 'mp_guest_email', '' );
		}
		if ( '' === $legacy_hash ) {
			$legacy_hash = (string) mp_get_get_value( 'mp_withdrawal_token', '' );
		}

		return $this->is_valid_withdrawal_access_token( $order, $legacy_hash );
	}

	/**
	 * Build all valid access tokens for guest withdrawal verification.
	 *
	 * @param MP_Order $order
	 *
	 * @return array
	 */
	private function get_valid_withdrawal_tokens( $order ) {
		$email = strtolower( trim( (string) $order->get_meta( 'mp_billing_info->email', '' ) ) );
		if ( '' === $email ) {
			return array();
		}

		$order_id = (int) $order->get_id();
		$tokens   = array(
			hash_hmac( 'sha256', $email . '|' . $order_id . '|withdrawal', wp_salt( 'nonce' ) ),
			md5( $order->get_meta( 'mp_billing_info->email', '' ) ),
			md5( $email ),
			md5( $email . '|' . $order_id . '|withdrawal' ),
		);

		return array_values( array_unique( array_filter( array_map( 'strval', $tokens ) ) ) );
	}

	/**
	 * Validate one provided guest access token for a withdrawal request.
	 *
	 * @param MP_Order $order
	 * @param string   $token
	 *
	 * @return bool
	 */
	private function is_valid_withdrawal_access_token( $order, $token ) {
		$token = trim( (string) $token );
		if ( '' === $token ) {
			return false;
		}

		foreach ( $this->get_valid_withdrawal_tokens( $order ) as $valid_token ) {
			if ( hash_equals( $valid_token, $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get snapshot from order (or generate one).
	 *
	 * @param MP_Order $order
	 *
	 * @return array
	 */
	private function get_snapshot( $order ) {
		$snapshot = $order->get_meta( 'mp_withdrawal_snapshot', array() );
		if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
			return $snapshot;
		}

		$this->save_order_withdrawal_snapshot( $order );
		$snapshot = $order->get_meta( 'mp_withdrawal_snapshot', array() );

		return is_array( $snapshot ) ? $snapshot : array();
	}

	/**
	 * Render customer zone section.
	 *
	 * @param MP_Order $order
	 *
	 * @return string
	 */
	private function render_customer_zone( $order ) {
		$snapshot = $this->get_snapshot( $order );
		if ( empty( $snapshot ) ) {
			return '';
		}

		$reason_options   = $this->get_reason_options();
		$allow_reason_note = $this->allow_custom_reason();
		$max_reason_len    = $this->get_reason_max_length();
		$status_entries    = $this->get_withdrawal_entries_for_customer( $order );

		$eligible_count = 0;
		$blocked_count  = 0;

		foreach ( $snapshot as $row ) {
			if ( ! empty( $row['withdrawal_excluded'] ) ) {
				$blocked_count ++;
			} else {
				$eligible_count ++;
			}
		}

		$nonce = wp_create_nonce( 'mp_submit_withdrawal_' . $order->get_id() );
		$guest_token = '';
		if ( 'guest' === $order->get_meta( 'mp_user_kind', '' ) ) {
			$guest_token = sanitize_text_field( (string) mp_get_get_value( 'mp_withdrawal_token', '' ) );
			if ( '' === $guest_token ) {
				$tokens = $this->get_valid_withdrawal_tokens( $order );
				$guest_token = (string) reset( $tokens );
			}
		}

		ob_start();
		?>
		<section id="mp-customer-zone" class="mp_customer_zone mp_customer_zone-modern">
			<header class="mp_customer_zone_head">
				<h2><?php esc_html_e( 'Kundenzone', 'mp' ); ?></h2>
				<p><?php esc_html_e( 'Verwalte Deine Bestellung und reiche bei Bedarf einen digitalen Widerruf ein.', 'mp' ); ?></p>
			</header>

			<div class="mp_customer_zone_stats">
				<div class="mp_stat_card">
					<span class="mp_stat_value"><?php echo esc_html( (string) $eligible_count ); ?></span>
					<span class="mp_stat_label"><?php esc_html_e( 'Widerrufsfähige Positionen', 'mp' ); ?></span>
				</div>
				<div class="mp_stat_card">
					<span class="mp_stat_value"><?php echo esc_html( (string) $blocked_count ); ?></span>
					<span class="mp_stat_label"><?php esc_html_e( 'Ausgeschlossene Positionen', 'mp' ); ?></span>
				</div>
			</div>

			<div class="mp_withdrawal_panel">
				<h3><?php esc_html_e( 'Digitaler Widerruf', 'mp' ); ?></h3>
				<p class="mp_withdrawal_hint"><?php esc_html_e( 'Wähle Positionen, nenne den Grund und sende den Widerruf direkt ab.', 'mp' ); ?></p>

				<?php if ( ! empty( $status_entries ) ) : ?>
					<div class="mp_withdrawal_status_block">
						<h4><?php esc_html_e( 'Dein Widerrufsstatus', 'mp' ); ?></h4>
						<ul class="mp_withdrawal_status_list">
							<?php foreach ( array_slice( $status_entries, 0, 5 ) as $entry ) : ?>
								<li>
									<span class="mp_withdrawal_status_badge state-<?php echo esc_attr( (string) $entry['status'] ); ?>"><?php echo esc_html( (string) $entry['status_label'] ); ?></span>
									<span class="mp_withdrawal_status_meta">
										<?php
										echo esc_html(
											sprintf(
												__( '%1$s · %2$s', 'mp' ),
												(string) $entry['reason_label'],
												(string) $entry['date_text']
											)
										);
										?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<form class="mp_withdrawal_form" data-order-id="<?php echo esc_attr( (string) $order->get_id() ); ?>" data-max-note="<?php echo esc_attr( (string) $max_reason_len ); ?>">
					<input type="hidden" name="action" value="mp_submit_withdrawal">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
					<input type="hidden" name="ajax_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mp-ajax-nonce' ) ); ?>">
					<input type="hidden" name="access_token" value="<?php echo esc_attr( $guest_token ); ?>">
					<div class="mp_withdrawal_items">
						<?php foreach ( $snapshot as $key => $row ) : ?>
							<?php
							$excluded = ! empty( $row['withdrawal_excluded'] );
							$name     = (string) mp_arr_get_value( 'product_name', $row, __( 'Produkt', 'mp' ) );
							$qty      = (int) mp_arr_get_value( 'quantity', $row, 1 );
							$reason   = (string) mp_arr_get_value( 'withdrawal_exclusion_reason', $row, '' );
							?>
							<div class="mp_withdrawal_item<?php echo $excluded ? ' is-disabled' : ''; ?>">
								<label>
									<input type="checkbox" name="items[]" value="<?php echo esc_attr( (string) $key ); ?>" <?php disabled( $excluded, true ); ?>>
									<span class="mp_item_title"><?php echo esc_html( $name ); ?></span>
									<span class="mp_item_qty"><?php echo esc_html( sprintf( __( 'Menge: %d', 'mp' ), $qty ) ); ?></span>
								</label>
								<?php if ( $excluded ) : ?>
									<p class="mp_item_blocked"><?php echo esc_html( sprintf( __( 'Vom Widerruf ausgeschlossen: %s', 'mp' ), $reason ? $reason : __( 'gesetzliche Ausnahme', 'mp' ) ) ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="mp_withdrawal_reason mp_withdrawal_reason_block" hidden>
						<label for="mp_withdrawal_reason_<?php echo esc_attr( (string) $order->get_id() ); ?>"><strong><?php esc_html_e( 'Widerrufsgrund', 'mp' ); ?></strong></label>
						<select id="mp_withdrawal_reason_<?php echo esc_attr( (string) $order->get_id() ); ?>" name="reason_code">
							<option value=""><?php esc_html_e( 'Bitte Grund wählen', 'mp' ); ?></option>
							<?php foreach ( $reason_options as $code => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $code ); ?>"><?php echo esc_html( (string) $label ); ?></option>
							<?php endforeach; ?>
						</select>

						<?php if ( $allow_reason_note ) : ?>
							<label for="mp_withdrawal_note_<?php echo esc_attr( (string) $order->get_id() ); ?>"><strong><?php esc_html_e( 'Optionale Begründung', 'mp' ); ?></strong></label>
							<textarea id="mp_withdrawal_note_<?php echo esc_attr( (string) $order->get_id() ); ?>" name="reason_note" maxlength="<?php echo esc_attr( (string) $max_reason_len ); ?>" rows="3"></textarea>
							<p class="mp_withdrawal_note_counter" data-max="<?php echo esc_attr( (string) $max_reason_len ); ?>">0/<?php echo esc_html( (string) $max_reason_len ); ?></p>
						<?php endif; ?>
					</div>

					<div class="mp_withdrawal_actions">
						<button type="submit" class="mp_button mp_button-primary mp_withdrawal_submit"><?php esc_html_e( 'Widerruf absenden', 'mp' ); ?></button>
					</div>

					<div class="mp_withdrawal_feedback" aria-live="polite"></div>
				</form>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Register admin metabox for withdrawal requests on orders.
	 */
	public function register_order_metabox() {
		if ( ! is_admin() ) {
			return;
		}

		add_meta_box(
			'mp-withdrawal-admin-metabox',
			__( 'Widerrufsanfragen', 'mp' ),
			array( $this, 'render_order_metabox' ),
			'mp_order',
			'normal',
			'default'
		);
	}

	/**
	 * Render admin metabox for withdrawal requests.
	 *
	 * @param WP_Post $post
	 */
	public function render_order_metabox( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			echo '<p>' . esc_html__( 'Keine Berechtigung.', 'mp' ) . '</p>';
			return;
		}

		$requests = get_post_meta( $post->ID, 'mp_withdrawal_requests', true );
		$requests = is_array( $requests ) ? $requests : array();

		wp_nonce_field( 'mp_save_withdrawal_admin_' . $post->ID, 'mp_withdrawal_admin_nonce' );

		if ( empty( $requests ) ) {
			echo '<p>' . esc_html__( 'Bisher sind keine Widerrufsanfragen für diese Bestellung eingegangen.', 'mp' ) . '</p>';
			return;
		}

		echo '<p>' . esc_html__( 'Hier kannst Du eingegangene Widerrufe prüfen und den internen Bearbeitungsstatus pflegen.', 'mp' ) . '</p>';

		foreach ( $requests as $index => $request ) {
			$timestamp  = (int) mp_arr_get_value( 'timestamp', $request, 0 );
			$status     = (string) mp_arr_get_value( 'status', $request, 'requested' );
			$admin_note = (string) mp_arr_get_value( 'admin_note', $request, '' );
			$reason     = (string) mp_arr_get_value( 'reason_label', $request, '' );
			$reason_note = (string) mp_arr_get_value( 'reason_note', $request, '' );
			$items      = mp_arr_get_value( 'items', $request, array() );
			$email      = (string) mp_arr_get_value( 'customer_email', $request, '' );

			echo '<div style="border:1px solid #dcdcde; border-radius:8px; padding:12px; margin:12px 0; background:#fff;">';
			echo '<input type="hidden" name="mp_withdrawal_entry_index[]" value="' . esc_attr( (string) $index ) . '">';
			echo '<p><strong>' . esc_html__( 'Anfrage', 'mp' ) . ' #' . esc_html( (string) ( $index + 1 ) ) . '</strong><br>';
			echo esc_html__( 'Eingang:', 'mp' ) . ' ' . esc_html( $timestamp ? date_i18n( 'd.m.Y H:i', $timestamp ) : '-' ) . '<br>';
			echo esc_html__( 'Kunden-E-Mail:', 'mp' ) . ' ' . esc_html( $email ? $email : '-' ) . '</p>';

			echo '<p><strong>' . esc_html__( 'Positionen', 'mp' ) . '</strong></p>';
			echo '<ul style="margin-top:0;">';
			if ( is_array( $items ) && ! empty( $items ) ) {
				foreach ( $items as $row ) {
					$name = (string) mp_arr_get_value( 'product_name', $row, __( 'Produkt', 'mp' ) );
					$qty  = (int) mp_arr_get_value( 'quantity', $row, 1 );
					echo '<li>' . esc_html( $name ) . ' x ' . esc_html( (string) $qty ) . '</li>';
				}
			} else {
				echo '<li>' . esc_html__( 'Keine Positionsdaten vorhanden.', 'mp' ) . '</li>';
			}
			echo '</ul>';

			if ( '' !== $reason ) {
				echo '<p><strong>' . esc_html__( 'Widerrufsgrund', 'mp' ) . ':</strong> ' . esc_html( $reason ) . '</p>';
			}
			if ( '' !== $reason_note ) {
				echo '<p><strong>' . esc_html__( 'Begründung', 'mp' ) . ':</strong><br>' . nl2br( esc_html( $reason_note ) ) . '</p>';
			}

			echo '<p><label for="mp_withdrawal_status_' . esc_attr( (string) $index ) . '"><strong>' . esc_html__( 'Status', 'mp' ) . '</strong></label><br>';
			echo '<select id="mp_withdrawal_status_' . esc_attr( (string) $index ) . '" name="mp_withdrawal_status[' . esc_attr( (string) $index ) . ']">';
			foreach ( $this->get_admin_status_options() as $status_key => $status_label ) {
				echo '<option value="' . esc_attr( $status_key ) . '"' . selected( $status, $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
			}
			echo '</select></p>';

			echo '<p><label for="mp_withdrawal_note_' . esc_attr( (string) $index ) . '"><strong>' . esc_html__( 'Interne Notiz', 'mp' ) . '</strong></label><br>';
			echo '<textarea id="mp_withdrawal_note_' . esc_attr( (string) $index ) . '" name="mp_withdrawal_note[' . esc_attr( (string) $index ) . ']" rows="3" style="width:100%;">' . esc_textarea( $admin_note ) . '</textarea></p>';
			echo '</div>';
		}

		echo '<p><em>' . esc_html__( 'Hinweis: Änderungen werden mit dem Speichern der Bestellung übernommen.', 'mp' ) . '</em></p>';
	}

	/**
	 * Save admin metabox data.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_order_metabox( $post_id, $post ) {
		if ( ! $post || 'mp_order' !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['mp_withdrawal_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp_withdrawal_admin_nonce'] ) ), 'mp_save_withdrawal_admin_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$requests = get_post_meta( $post_id, 'mp_withdrawal_requests', true );
		if ( ! is_array( $requests ) || empty( $requests ) ) {
			return;
		}

		$posted_status = isset( $_POST['mp_withdrawal_status'] ) && is_array( $_POST['mp_withdrawal_status'] ) ? wp_unslash( $_POST['mp_withdrawal_status'] ) : array();
		$posted_notes  = isset( $_POST['mp_withdrawal_note'] ) && is_array( $_POST['mp_withdrawal_note'] ) ? wp_unslash( $_POST['mp_withdrawal_note'] ) : array();

		$allowed = array_keys( $this->get_admin_status_options() );

		foreach ( $requests as $index => &$request ) {
			$new_status = isset( $posted_status[ $index ] ) ? sanitize_key( (string) $posted_status[ $index ] ) : (string) mp_arr_get_value( 'status', $request, 'requested' );
			if ( ! in_array( $new_status, $allowed, true ) ) {
				$new_status = 'requested';
			}

			$request['status'] = $new_status;
			$request['admin_note'] = isset( $posted_notes[ $index ] ) ? sanitize_textarea_field( (string) $posted_notes[ $index ] ) : '';
			$request['admin_updated_at'] = time();
			$request['admin_updated_by'] = get_current_user_id();
		}
		unset( $request );

		update_post_meta( $post_id, 'mp_withdrawal_requests', $requests );

		$latest = end( $requests );
		if ( is_array( $latest ) ) {
			$latest_status = sanitize_key( (string) mp_arr_get_value( 'status', $latest, 'requested' ) );
			update_post_meta( $post_id, '_mp_withdrawal_status', $latest_status );
			update_post_meta( $post_id, '_mp_withdrawal_last_update', time() );
		}

		do_action( 'mp_withdrawal_updated', (int) $post_id, (int) $post->post_author, (int) get_current_blog_id() );
	}

	/**
	 * @return array
	 */
	private function get_admin_status_options() {
		return array(
			'requested' => __( 'Eingegangen', 'mp' ),
			'in_review' => __( 'In Prüfung', 'mp' ),
			'approved'  => __( 'Genehmigt', 'mp' ),
			'rejected'  => __( 'Abgelehnt', 'mp' ),
			'refunded'  => __( 'Erstattet', 'mp' ),
			'closed'    => __( 'Abgeschlossen', 'mp' ),
		);
	}

	/**
	 * Human-readable status label.
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public function get_status_label( $status ) {
		$status = sanitize_key( (string) $status );
		$labels = $this->get_admin_status_options();

		return isset( $labels[ $status ] ) ? (string) $labels[ $status ] : (string) __( 'Unbekannt', 'mp' );
	}

	/**
	 * Return sorted customer-visible entries.
	 *
	 * @param MP_Order $order
	 *
	 * @return array
	 */
	public function get_withdrawal_entries_for_customer( $order ) {
		$requests = $order->get_meta( 'mp_withdrawal_requests', array() );
		$requests = is_array( $requests ) ? $requests : array();

		$entries = array();
		foreach ( $requests as $request ) {
			$status    = sanitize_key( (string) mp_arr_get_value( 'status', $request, 'requested' ) );
			$timestamp = (int) mp_arr_get_value( 'timestamp', $request, 0 );
			$entries[] = array(
				'status'      => $status,
				'status_label' => $this->get_status_label( $status ),
				'reason_code' => sanitize_key( (string) mp_arr_get_value( 'reason_code', $request, '' ) ),
				'reason_label' => (string) mp_arr_get_value( 'reason_label', $request, __( 'Nicht angegeben', 'mp' ) ),
				'reason_note' => (string) mp_arr_get_value( 'reason_note', $request, '' ),
				'timestamp'   => $timestamp,
				'date_text'   => $timestamp ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '',
			);
		}

		usort( $entries, function( $a, $b ) {
			return (int) $b['timestamp'] <=> (int) $a['timestamp'];
		} );

		return $entries;
	}

	/**
	 * Parse configured withdrawal reason options.
	 *
	 * @return array
	 */
	private function get_reason_options() {
		$raw = (string) mp_get_setting( 'withdrawal->reason_options', '' );
		if ( '' === trim( $raw ) ) {
			$raw = "defect|Artikel ist beschaedigt oder fehlerhaft\nnot_as_described|Artikel entspricht nicht der Beschreibung\nwrong_item|Falscher Artikel geliefert\ndelay|Lieferung kam zu spaet\nother|Anderer rechtlicher Widerrufsgrund";
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$options = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			$code  = '';
			$label = '';
			if ( false !== strpos( $line, '|' ) ) {
				$parts = explode( '|', $line, 2 );
				$code  = sanitize_key( trim( (string) $parts[0] ) );
				$label = sanitize_text_field( trim( (string) $parts[1] ) );
			} else {
				$label = sanitize_text_field( $line );
				$code  = sanitize_key( $label );
			}

			if ( '' === $code || '' === $label ) {
				continue;
			}

			$options[ $code ] = $label;
		}

		if ( empty( $options ) ) {
			$options = array(
				'other' => __( 'Anderer rechtlicher Widerrufsgrund', 'mp' ),
			);
		}

		return $options;
	}

	/**
	 * Whether free text reason note is allowed.
	 *
	 * @return bool
	 */
	private function allow_custom_reason() {
		return (bool) mp_get_setting( 'withdrawal->allow_custom_reason', 1 );
	}

	/**
	 * Max reason note length.
	 *
	 * @return int
	 */
	private function get_reason_max_length() {
		$max = (int) mp_get_setting( 'withdrawal->max_reason_length', 300 );
		if ( $max < 50 ) {
			$max = 300;
		}

		if ( $max > 1000 ) {
			$max = 1000;
		}

		return $max;
	}

	/**
	 * Send confirmation email immediately after request.
	 *
	 * @param MP_Order $order
	 * @param array    $entry
	 */
	private function send_confirmation_email( $order, $entry ) {
		$email = sanitize_email( (string) mp_arr_get_value( 'customer_email', $entry, '' ) );
		if ( '' === $email ) {
			return;
		}

		$selected_names = array();
		$items          = mp_arr_get_value( 'items', $entry, array() );
		if ( is_array( $items ) ) {
			foreach ( $items as $row ) {
				$selected_names[] = (string) mp_arr_get_value( 'product_name', $row, __( 'Produkt', 'mp' ) );
			}
		}

		$subject_template = (string) mp_get_setting( 'email->withdrawal_confirmation->subject', __( 'Eingangsbestätigung Widerruf (ORDERID)', 'mp' ) );
		$body_template    = (string) mp_get_setting( 'email->withdrawal_confirmation->text', __( "Hallo CUSTOMERNAME,\n\nwir bestätigen den Eingang Deines Widerrufs zur Bestellung ORDERID.\n\nBetroffene Positionen:\nWITHDRAWALITEMS\n\nWir bearbeiten Dein Anliegen so schnell wie möglich.", 'mp' ) );

		$subject = mp_filter_email( $order, stripslashes( $subject_template ) );
		$body    = mp_filter_email( $order, nl2br( stripslashes( $body_template ) ) );

		$body = str_replace(
			array( 'WITHDRAWALITEMS', 'WITHDRAWALDATE' ),
			array(
				esc_html( implode( ', ', $selected_names ) ),
				esc_html( date_i18n( 'd.m.Y H:i', (int) mp_arr_get_value( 'timestamp', $entry, time() ) ) ),
			),
			$body
		);

		MP_Mailer::get_instance()->send( $email, $subject, $body );
	}

	/**
	 * @return bool
	 */
	private function is_enabled() {
		return (bool) mp_get_setting( 'withdrawal->enabled', 1 );
	}
}

MP_Withdrawal::get_instance();
