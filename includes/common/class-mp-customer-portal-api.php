<?php

class MP_Customer_Portal_API {

	/**
	 * Singleton instance.
	 *
	 * @var MP_Customer_Portal_API|null
	 */
	private static $_instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MP_Customer_Portal_API
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_mp_customer_portal_snapshot', array( $this, 'ajax_get_snapshot' ) );
		add_action( 'wp_ajax_mp_customer_portal_sync', array( $this, 'ajax_sync_snapshot' ) );
		add_action( 'wp_ajax_nopriv_mp_customer_portal_snapshot', array( $this, 'ajax_guest_forbidden' ) );
		add_action( 'wp_ajax_nopriv_mp_customer_portal_sync', array( $this, 'ajax_guest_forbidden' ) );
		add_action( 'admin_post_mp_retry_order_payment', array( $this, 'handle_retry_payment' ) );
		add_filter( 'mp_order/header', array( $this, 'append_payment_retry' ), 20, 2 );

		add_action( 'transition_post_status', array( $this, 'invalidate_after_order_status_change' ), 10, 3 );
		add_action( 'mp_order/new_order', array( $this, 'invalidate_after_new_order' ), 10, 1 );
		add_action( 'comment_post', array( $this, 'invalidate_after_comment_change' ), 10, 2 );
		add_action( 'edit_comment', array( $this, 'invalidate_after_comment_edit' ), 10, 1 );
		add_action( 'wp_set_comment_status', array( $this, 'invalidate_after_comment_edit' ), 10, 1 );
		add_action( 'deleted_comment', array( $this, 'invalidate_after_comment_edit' ), 10, 1 );
		add_action( 'mp_withdrawal_updated', array( $this, 'invalidate_after_withdrawal_update' ), 10, 3 );
	}

	/**
	 * AJAX response for guests.
	 */
	public function ajax_guest_forbidden() {
		wp_send_json_error( array( 'message' => __( 'Bitte melde Dich an.', 'mp' ) ), 401 );
	}

	/**
	 * Append a payment retry form to an unpaid customer order.
	 *
	 * @param string   $html
	 * @param MP_Order $order
	 * @return string
	 */
	public function append_payment_retry( $html, $order ) {
		if ( is_admin() || ! is_user_logged_in() || ! $order instanceof MP_Order || 'order_received' !== $order->post_status ) {
			return $html;
		}

		if ( (int) $order->post_author !== (int) get_current_user_id() ) {
			return $html;
		}

		$gateways = $this->get_retry_gateways();
		if ( empty( $gateways ) ) {
			return $html;
		}

		$form  = '<div class="mp_order_payment_retry">';
		$form .= '<h5>' . esc_html__( 'Zahlung ausstehend', 'mp' ) . '</h5>';
		$form .= '<p>' . esc_html__( 'Die Bestellung ist gespeichert. Du kannst die Zahlung jetzt erneut starten.', 'mp' ) . '</p>';
		if ( 'failed' === sanitize_key( (string) mp_get_get_value( 'mp_payment_retry', '' ) ) ) {
			$form .= '<p class="mp_order_payment_retry_error">' . esc_html__( 'Die Zahlung konnte nicht gestartet werden. Bitte versuche es erneut oder wähle eine andere Zahlungsart.', 'mp' ) . '</p>';
		}
		$form .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$form .= '<input type="hidden" name="action" value="mp_retry_order_payment">';
		$form .= '<input type="hidden" name="order_id" value="' . (int) $order->ID . '">';
		$form .= wp_nonce_field( 'mp_retry_order_payment_' . (int) $order->ID, 'mp_retry_order_payment_nonce', true, false );
		$form .= '<label><span>' . esc_html__( 'Zahlungsart', 'mp' ) . '</span><select name="payment_method">';
		foreach ( $gateways as $code => $label ) {
			$form .= '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
		}
		$form .= '</select></label>';
		$form .= '<button class="mp_button" type="submit">' . esc_html__( 'Zahlung erneut versuchen', 'mp' ) . '</button>';
		$form .= '</form></div>';

		return $html . $form;
	}

	/**
	 * Start a new gateway attempt for an existing unpaid order.
	 */
	public function handle_retry_payment() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$order_id = absint( mp_get_post_value( 'order_id', 0 ) );
		$order    = new MP_Order( $order_id );
		if ( ! $order->exists() || (int) $order->post_author !== (int) get_current_user_id() || 'order_received' !== $order->post_status ) {
			wp_die( esc_html__( 'Diese Bestellung kann nicht erneut bezahlt werden.', 'mp' ), 403 );
		}

		check_admin_referer( 'mp_retry_order_payment_' . $order_id, 'mp_retry_order_payment_nonce' );

		$gateway_code = sanitize_key( (string) mp_get_post_value( 'payment_method', '' ) );
		$gateways     = $this->get_retry_gateways();
		$registered   = $this->get_registered_gateways();
		if ( ! isset( $gateways[ $gateway_code ], $registered[ $gateway_code ][0] ) ) {
			$this->redirect_retry_error( $order );
		}

		$class   = $registered[ $gateway_code ][0];
		$gateway = new $class();
		$result  = $gateway->retry_payment( $order );
		if ( is_wp_error( $result ) || ! is_string( $result ) || '' === $result ) {
			$this->redirect_retry_error( $order );
		}

		wp_redirect( esc_url_raw( $result ) );
		exit;
	}

	/**
	 * Get enabled gateways that support retrying an existing order.
	 *
	 * @return array
	 */
	private function get_retry_gateways() {
		$options = array();
		foreach ( $this->get_registered_gateways() as $code => $gateway ) {
			$class = isset( $gateway[0] ) ? $gateway[0] : '';
			if ( ! $class || ! method_exists( $class, 'retry_payment' ) ) {
				continue;
			}

			$network_enabled = (bool) mp_get_network_setting( 'gateways->allowed->' . $code, 0 );
			$local_enabled   = (bool) mp_get_setting( 'gateways->allowed->' . $code, 0 );
			if ( ! $network_enabled && ! $local_enabled ) {
				continue;
			}

			$options[ $code ] = isset( $gateway[1] ) ? (string) $gateway[1] : $code;
		}

		return (array) apply_filters( 'mp_customer_portal_retry_gateways', $options );
	}

	/**
	 * Read the gateway registry before checkout routing restrictions are applied.
	 *
	 * @return array
	 */
	private function get_registered_gateways() {
		$multisite = null;
		if ( is_multisite() && class_exists( 'MP_Multisite' ) ) {
			$multisite = MP_Multisite::get_instance();
			remove_filter( 'mp_gateway_api/get_gateways', array( $multisite, 'get_gateways' ) );
		}

		$gateways = MP_Gateway_API::get_gateways();
		if ( $multisite ) {
			add_filter( 'mp_gateway_api/get_gateways', array( $multisite, 'get_gateways' ) );
		}

		return $gateways;
	}

	/**
	 * Redirect back to the order after a failed retry attempt.
	 *
	 * @param MP_Order $order
	 */
	private function redirect_retry_error( $order ) {
		wp_safe_redirect( add_query_arg( 'mp_payment_retry', 'failed', $order->tracking_url( false ) ) );
		exit;
	}

	/**
	 * AJAX: read snapshot.
	 */
	public function ajax_get_snapshot() {
		if ( ! is_user_logged_in() ) {
			$this->ajax_guest_forbidden();
		}

		$nonce = mp_get_request_value( 'ajax_nonce', '' );
		if ( ! wp_verify_nonce( $nonce, 'mp-ajax-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungueltiger Sicherheits-Token.', 'mp' ) ), 403 );
		}

		$scope    = sanitize_key( (string) mp_get_request_value( 'scope', 'single' ) );
		$blog_id  = (int) mp_get_request_value( 'blog_id', get_current_blog_id() );
		$snapshot = $this->get_snapshot( $scope, array(
			'user_id'    => get_current_user_id(),
			'blog_id'    => $blog_id,
			'force_sync' => false,
		) );

		wp_send_json_success( $snapshot );
	}

	/**
	 * AJAX: force sync snapshot.
	 */
	public function ajax_sync_snapshot() {
		if ( ! is_user_logged_in() ) {
			$this->ajax_guest_forbidden();
		}

		$nonce = mp_get_request_value( 'ajax_nonce', '' );
		if ( ! wp_verify_nonce( $nonce, 'mp-ajax-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungueltiger Sicherheits-Token.', 'mp' ) ), 403 );
		}

		$scope    = sanitize_key( (string) mp_get_request_value( 'scope', 'single' ) );
		$blog_id  = (int) mp_get_request_value( 'blog_id', get_current_blog_id() );
		$snapshot = $this->get_snapshot( $scope, array(
			'user_id'    => get_current_user_id(),
			'blog_id'    => $blog_id,
			'force_sync' => true,
		) );

		wp_send_json_success( $snapshot );
	}

	/**
	 * Get synchronized customer portal snapshot.
	 *
	 * @param string $scope
	 * @param array  $args
	 *
	 * @return array
	 */
	public function get_snapshot( $scope = 'single', $args = array() ) {
		$scope   = ( 'network' === $scope && is_multisite() ) ? 'network' : 'single';
		$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : get_current_user_id();
		$blog_id = isset( $args['blog_id'] ) ? (int) $args['blog_id'] : (int) get_current_blog_id();
		$force   = ! empty( $args['force_sync'] );

		if ( $user_id <= 0 ) {
			return $this->get_empty_snapshot( $scope, $blog_id );
		}

		$cache_key = $this->get_cache_key( $scope, $user_id, $blog_id );
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached['scope'] ) ) {
				return $cached;
			}
		}

		$stored = $this->read_synced_snapshot( $scope, $user_id, $blog_id );
		if ( ! $force && ! empty( $stored['snapshot'] ) && ! $this->is_snapshot_stale( $stored['synced_at'], $scope ) ) {
			set_transient( $cache_key, $stored['snapshot'], $this->get_cache_ttl( $scope ) );
			return $stored['snapshot'];
		}

		if ( 'network' === $scope ) {
			$snapshot = $this->build_network_snapshot( $user_id );
		} else {
			$snapshot = $this->build_single_snapshot( $user_id, $blog_id );
		}

		$this->write_synced_snapshot( $scope, $user_id, $blog_id, $snapshot );
		set_transient( $cache_key, $snapshot, $this->get_cache_ttl( $scope ) );

		return $snapshot;
	}

	/**
	 * Invalidate snapshots when order status changes.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function invalidate_after_order_status_change( $new_status, $old_status, $post ) {
		if ( ! $post || 'mp_order' !== $post->post_type ) {
			return;
		}

		$user_id = isset( $post->post_author ) ? (int) $post->post_author : 0;
		if ( $user_id > 0 ) {
			$this->invalidate_user_cache( $user_id, (int) get_current_blog_id() );
		}
	}

	/**
	 * Invalidate snapshots after an order has been added to customer history.
	 *
	 * @param MP_Order $order
	 */
	public function invalidate_after_new_order( $order ) {
		if ( ! is_object( $order ) || ! isset( $order->ID ) ) {
			return;
		}

		$post = get_post( (int) $order->ID );
		if ( $post && (int) $post->post_author > 0 ) {
			$this->invalidate_user_cache( (int) $post->post_author, (int) get_current_blog_id() );
		}
	}

	/**
	 * Invalidate snapshots after comment creation.
	 *
	 * @param int $comment_id
	 */
	public function invalidate_after_comment_change( $comment_id ) {
		$this->invalidate_after_comment_edit( $comment_id );
	}

	/**
	 * Invalidate snapshots after comment updates.
	 *
	 * @param int $comment_id
	 */
	public function invalidate_after_comment_edit( $comment_id ) {
		$comment = get_comment( (int) $comment_id );
		if ( ! $comment ) {
			return;
		}

		$user_id = (int) $comment->user_id;
		if ( $user_id > 0 ) {
			$this->invalidate_user_cache( $user_id, (int) get_current_blog_id() );
		}
	}

	/**
	 * Invalidate snapshots after withdrawal updates.
	 *
	 * @param int $order_id
	 * @param int $user_id
	 * @param int $blog_id
	 */
	public function invalidate_after_withdrawal_update( $order_id, $user_id, $blog_id ) {
		$user_id = (int) $user_id;
		$blog_id = (int) $blog_id;

		if ( $user_id <= 0 ) {
			return;
		}

		if ( $blog_id <= 0 ) {
			$blog_id = (int) get_current_blog_id();
		}

		$this->invalidate_user_cache( $user_id, $blog_id );
	}

	/**
	 * Remove transient cache for one user.
	 *
	 * @param int $user_id
	 * @param int $blog_id
	 */
	private function invalidate_user_cache( $user_id, $blog_id ) {
		delete_transient( $this->get_cache_key( 'single', $user_id, $blog_id ) );
		delete_transient( $this->get_cache_key( 'network', $user_id, $blog_id ) );
		delete_user_meta( $user_id, '_mp_customer_portal_single_snapshot_' . $blog_id );
		delete_user_meta( $user_id, '_mp_customer_portal_single_synced_at_' . $blog_id );
		delete_user_meta( $user_id, '_mp_customer_portal_network_snapshot' );
		delete_user_meta( $user_id, '_mp_customer_portal_network_synced_at' );
	}

	/**
	 * Build single-site customer snapshot.
	 *
	 * @param int $user_id
	 * @param int $blog_id
	 *
	 * @return array
	 */
	private function build_single_snapshot( $user_id, $blog_id ) {
		$current_blog_id = (int) get_current_blog_id();
		$switched        = false;

		if ( is_multisite() && $blog_id > 0 && $blog_id !== $current_blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		$currency        = mp_get_setting( 'currency' );
		$history         = (array) array_filter( mp_get_order_history( $user_id ) );
		$status_labels   = $this->get_status_labels();
		$closed_statuses = $this->get_closed_statuses();
		$reviews_active  = class_exists( 'MP_MARKETPRESS_COMMENTS_Addon' );

		$totals = array(
			'orders'        => 0,
			'value'         => 0.0,
			'open_shipping' => 0,
			'to_review'     => 0,
			'withdrawal_open' => 0,
		);

		$withdrawal_data = array(
			'counts' => array(
				'none'      => 0,
				'requested' => 0,
				'in_review' => 0,
				'approved'  => 0,
				'rejected'  => 0,
				'refunded'  => 0,
				'closed'    => 0,
			),
			'recent' => array(),
		);

		$orders           = array();
		$pending_reviews  = array();
		$pending_index    = array();
		$candidate_review = array();

		foreach ( $history as $timestamp => $entry ) {
			if ( empty( $entry['id'] ) ) {
				continue;
			}

			$order_id = (int) $entry['id'];
			$order    = new MP_Order( $order_id );
			if ( ! $order->exists() ) {
				continue;
			}

			$status = get_post_status( $order_id );
			if ( ! $status || in_array( $status, array( 'trash', 'auto-draft' ), true ) ) {
				continue;
			}

			$total = (float) get_post_meta( $order_id, 'mp_order_total', true );
			$time  = (int) $timestamp;

			$totals['orders']++;
			$totals['value'] += $total;
			if ( in_array( $status, array( 'order_received', 'order_paid' ), true ) ) {
				$totals['open_shipping']++;
			}

			$orders[] = array(
				'order_id'   => $order->get_id(),
				'post_id'    => $order_id,
				'status'     => $status,
				'status_text'=> isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( str_replace( 'order_', '', $status ) ),
				'total'      => $total,
				'timestamp'  => $time,
				'checkout_mode' => sanitize_key( (string) get_post_meta( $order_id, '_mp_network_multishop_checkout_mode', true ) ),
				'payout_status' => sanitize_key( (string) get_post_meta( $order_id, '_mp_network_payout_status', true ) ),
				'tracking_url' => $order->tracking_url( false ),
			);

			$withdrawal_entry = $this->get_order_withdrawal_entry( $order_id, $order->get_id(), '', $order->tracking_url( false ) );
			$withdrawal_status = isset( $withdrawal_entry['status'] ) ? $withdrawal_entry['status'] : 'none';
			if ( isset( $withdrawal_data['counts'][ $withdrawal_status ] ) ) {
				$withdrawal_data['counts'][ $withdrawal_status ]++;
			}

			if ( in_array( $withdrawal_status, array( 'requested', 'in_review' ), true ) ) {
				$totals['withdrawal_open']++;
			}

			if ( 'none' !== $withdrawal_status ) {
				$withdrawal_data['recent'][] = $withdrawal_entry;
			}

			if ( ! $reviews_active || ! in_array( $status, $closed_statuses, true ) ) {
				continue;
			}

			$product_ids = $this->extract_order_product_ids( $order );
			foreach ( $product_ids as $product_id ) {
				if ( isset( $candidate_review[ $product_id ] ) ) {
					if ( $time > $candidate_review[ $product_id ]['timestamp'] ) {
						$candidate_review[ $product_id ] = array(
							'product_id' => $product_id,
							'product_name' => get_the_title( $product_id ),
							'product_url' => get_permalink( $product_id ),
							'order_id' => $order->get_id(),
							'status' => $status,
							'timestamp' => $time,
						);
					}
					continue;
				}

				$candidate_review[ $product_id ] = array(
					'product_id' => $product_id,
					'product_name' => get_the_title( $product_id ),
					'product_url' => get_permalink( $product_id ),
					'order_id' => $order->get_id(),
					'status' => $status,
					'timestamp' => $time,
				);
			}
		}

		usort( $orders, array( $this, 'sort_by_timestamp_desc' ) );

		if ( $reviews_active && ! empty( $candidate_review ) ) {
			$reviewed_ids = $this->get_reviewed_product_ids( $user_id, array_keys( $candidate_review ) );
			foreach ( $candidate_review as $product_id => $item ) {
				if ( isset( $reviewed_ids[ $product_id ] ) ) {
					continue;
				}

				if ( isset( $pending_index[ $product_id ] ) ) {
					continue;
				}

				$pending_reviews[] = $item;
				$pending_index[ $product_id ] = true;
			}
		}

		usort( $pending_reviews, array( $this, 'sort_by_timestamp_desc' ) );
		usort( $withdrawal_data['recent'], array( $this, 'sort_by_timestamp_desc' ) );
		$totals['to_review'] = count( $pending_reviews );

		$recent_reviews = $reviews_active ? $this->get_recent_reviews( $user_id, 0, 5 ) : array();

		$snapshot = array(
			'scope'        => 'single',
			'blog_id'      => (int) get_current_blog_id(),
			'user_id'      => $user_id,
			'currency'     => $currency,
			'status_labels'=> $status_labels,
			'totals'       => $totals,
			'orders'       => $orders,
			'pending_reviews' => $pending_reviews,
			'recent_reviews'  => $recent_reviews,
			'withdrawals'     => array(
				'counts' => $withdrawal_data['counts'],
				'recent' => array_slice( $withdrawal_data['recent'], 0, 8 ),
			),
			'synced_at'    => time(),
		);

		if ( $switched ) {
			restore_current_blog();
		}

		return $snapshot;
	}

	/**
	 * Build network customer snapshot.
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
	private function build_network_snapshot( $user_id ) {
		$currency       = mp_get_setting( 'currency' );
		$status_labels  = $this->get_status_labels();
		$closed_statuses = $this->get_closed_statuses();
		$reviews_active = class_exists( 'MP_MARKETPRESS_COMMENTS_Addon' );
		$sites          = get_sites( array( 'fields' => 'ids' ) );

		$totals = array(
			'orders'        => 0,
			'value'         => 0.0,
			'shops'         => 0,
			'open_shipping' => 0,
			'to_review'     => 0,
			'withdrawal_open' => 0,
		);

		$withdrawal_data = array(
			'counts' => array(
				'none'      => 0,
				'requested' => 0,
				'in_review' => 0,
				'approved'  => 0,
				'rejected'  => 0,
				'refunded'  => 0,
				'closed'    => 0,
			),
			'recent' => array(),
		);

		$rows             = array();
		$shop_seen        = array();
		$pending_reviews  = array();
		$pending_index    = array();
		$recent_reviews   = array();

		foreach ( (array) $sites as $blog_id ) {
			$blog_id = (int) $blog_id;
			if ( $blog_id <= 0 ) {
				continue;
			}

			switch_to_blog( $blog_id );
			$shop_name = (string) get_option( 'blogname' );
			$history_key = 'mp_order_history_' . $blog_id;
			$history = (array) get_user_meta( $user_id, $history_key, true );
			$history = array_filter( $history );
			$site_review_candidates = array();

			foreach ( $history as $entry ) {
				if ( empty( $entry['id'] ) ) {
					continue;
				}

				$post_id = (int) $entry['id'];
				$post    = get_post( $post_id );
				if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					continue;
				}

				$order = new MP_Order( $post_id );
				if ( ! $order->exists() ) {
					continue;
				}

				$total = (float) get_post_meta( $post_id, 'mp_order_total', true );
				$time  = (int) get_post_time( 'U', true, $post_id );
				$totals['orders']++;
				$totals['value'] += $total;
				$shop_seen[ $blog_id ] = true;

				if ( in_array( $post->post_status, array( 'order_received', 'order_paid' ), true ) ) {
					$totals['open_shipping']++;
				}

				$rows[] = array(
					'shop'       => $shop_name,
					'shop_id'    => $blog_id,
					'post_id'    => $post_id,
					'order'      => $order->get_id(),
					'total'      => $total,
					'status'     => $post->post_status,
					'status_text'=> isset( $status_labels[ $post->post_status ] ) ? $status_labels[ $post->post_status ] : ucfirst( str_replace( 'order_', '', $post->post_status ) ),
					'checkout_mode' => sanitize_key( (string) get_post_meta( $post_id, '_mp_network_multishop_checkout_mode', true ) ),
					'payout_status' => sanitize_key( (string) get_post_meta( $post_id, '_mp_network_payout_status', true ) ),
					'url'        => $order->tracking_url( false, $blog_id ),
					'timestamp'  => $time,
				);

				$withdrawal_entry = $this->get_order_withdrawal_entry( $post_id, $order->get_id(), $shop_name, $order->tracking_url( false, $blog_id ) );
				$withdrawal_status = isset( $withdrawal_entry['status'] ) ? $withdrawal_entry['status'] : 'none';
				if ( isset( $withdrawal_data['counts'][ $withdrawal_status ] ) ) {
					$withdrawal_data['counts'][ $withdrawal_status ]++;
				}
				if ( in_array( $withdrawal_status, array( 'requested', 'in_review' ), true ) ) {
					$totals['withdrawal_open']++;
				}
				if ( 'none' !== $withdrawal_status ) {
					$withdrawal_data['recent'][] = $withdrawal_entry;
				}

				if ( ! $reviews_active || ! in_array( $post->post_status, $closed_statuses, true ) ) {
					continue;
				}

				$product_ids = $this->extract_order_product_ids( $order );
				foreach ( $product_ids as $product_id ) {
					$review_key = $blog_id . ':' . $product_id;
					if ( isset( $site_review_candidates[ $review_key ] ) ) {
						continue;
					}

					$site_review_candidates[ $review_key ] = array(
						'shop'         => $shop_name,
						'shop_id'      => $blog_id,
						'product_id'   => $product_id,
						'product_name' => get_the_title( $product_id ),
						'product_url'  => get_permalink( $product_id ),
						'order_id'     => $order->get_id(),
						'status'       => $post->post_status,
						'timestamp'    => $time,
					);
				}
			}

			if ( $reviews_active && ! empty( $site_review_candidates ) ) {
				$site_product_ids = array();
				foreach ( $site_review_candidates as $candidate ) {
					$site_product_ids[] = (int) $candidate['product_id'];
				}

				$reviewed_map = $this->get_reviewed_product_ids( $user_id, $site_product_ids );
				foreach ( $site_review_candidates as $review_key => $candidate ) {
					$product_id = (int) $candidate['product_id'];
					if ( isset( $reviewed_map[ $product_id ] ) || isset( $pending_index[ $review_key ] ) ) {
						continue;
					}

					unset( $candidate['product_id'] );
					$pending_reviews[] = $candidate;
					$pending_index[ $review_key ] = true;
				}
			}

			if ( $reviews_active ) {
				$site_recent_reviews = $this->get_recent_reviews( $user_id, $blog_id, 10 );
				foreach ( $site_recent_reviews as $review ) {
					$review['shop'] = $shop_name;
					$recent_reviews[] = $review;
				}
			}

			restore_current_blog();
		}

		$totals['shops'] = count( $shop_seen );
		usort( $rows, array( $this, 'sort_by_timestamp_desc' ) );
		usort( $pending_reviews, array( $this, 'sort_by_timestamp_desc' ) );
		usort( $recent_reviews, array( $this, 'sort_by_timestamp_desc' ) );
		usort( $withdrawal_data['recent'], array( $this, 'sort_by_timestamp_desc' ) );
		$totals['to_review'] = count( $pending_reviews );

		return array(
			'scope'         => 'network',
			'blog_id'       => (int) get_current_blog_id(),
			'user_id'       => $user_id,
			'currency'      => $currency,
			'status_labels' => $status_labels,
			'totals'        => $totals,
			'rows'          => $rows,
			'pending_reviews' => $pending_reviews,
			'recent_reviews'  => $recent_reviews,
			'withdrawals'     => array(
				'counts' => $withdrawal_data['counts'],
				'recent' => array_slice( $withdrawal_data['recent'], 0, 10 ),
			),
			'synced_at'     => time(),
		);
	}

	/**
	 * Build one normalized withdrawal entry for an order.
	 *
	 * @param int    $order_post_id
	 * @param int    $order_id
	 * @param string $shop_name
	 * @param string $tracking_url
	 *
	 * @return array
	 */
	private function get_order_withdrawal_entry( $order_post_id, $order_id, $shop_name, $tracking_url ) {
		$requests = get_post_meta( (int) $order_post_id, 'mp_withdrawal_requests', true );
		$requests = is_array( $requests ) ? $requests : array();

		if ( empty( $requests ) ) {
			return array(
				'order_post_id' => (int) $order_post_id,
				'order_id'      => (int) $order_id,
				'shop'          => (string) $shop_name,
				'tracking_url'  => (string) $tracking_url,
				'status'        => 'none',
				'status_label'  => $this->get_withdrawal_status_label( 'none' ),
				'reason_label'  => '',
				'timestamp'     => 0,
				'date_text'     => '',
			);
		}

		$latest = end( $requests );
		$status = sanitize_key( (string) mp_arr_get_value( 'status', $latest, 'requested' ) );
		$time   = (int) mp_arr_get_value( 'timestamp', $latest, 0 );

		return array(
			'order_post_id' => (int) $order_post_id,
			'order_id'      => (int) $order_id,
			'shop'          => (string) $shop_name,
			'tracking_url'  => (string) $tracking_url,
			'status'        => $status,
			'status_label'  => $this->get_withdrawal_status_label( $status ),
			'reason_label'  => (string) mp_arr_get_value( 'reason_label', $latest, '' ),
			'timestamp'     => $time,
			'date_text'     => $time ? date_i18n( get_option( 'date_format' ), $time ) : '',
		);
	}

	/**
	 * Read synced snapshot from user meta.
	 *
	 * @param string $scope
	 * @param int    $user_id
	 * @param int    $blog_id
	 *
	 * @return array
	 */
	private function read_synced_snapshot( $scope, $user_id, $blog_id ) {
		if ( 'network' === $scope ) {
			$snapshot = get_user_meta( $user_id, '_mp_customer_portal_network_snapshot', true );
			$synced   = (int) get_user_meta( $user_id, '_mp_customer_portal_network_synced_at', true );
		} else {
			$snapshot = get_user_meta( $user_id, '_mp_customer_portal_single_snapshot_' . $blog_id, true );
			$synced   = (int) get_user_meta( $user_id, '_mp_customer_portal_single_synced_at_' . $blog_id, true );
		}

		if ( ! is_array( $snapshot ) ) {
			$snapshot = array();
		}

		return array(
			'snapshot'  => $snapshot,
			'synced_at' => $synced,
		);
	}

	/**
	 * Write synced snapshot to user meta.
	 *
	 * @param string $scope
	 * @param int    $user_id
	 * @param int    $blog_id
	 * @param array  $snapshot
	 */
	private function write_synced_snapshot( $scope, $user_id, $blog_id, $snapshot ) {
		if ( 'network' === $scope ) {
			update_user_meta( $user_id, '_mp_customer_portal_network_snapshot', $snapshot );
			update_user_meta( $user_id, '_mp_customer_portal_network_synced_at', time() );
			return;
		}

		update_user_meta( $user_id, '_mp_customer_portal_single_snapshot_' . $blog_id, $snapshot );
		update_user_meta( $user_id, '_mp_customer_portal_single_synced_at_' . $blog_id, time() );
	}

	/**
	 * Returns whether snapshot is stale.
	 *
	 * @param int    $synced_at
	 * @param string $scope
	 *
	 * @return bool
	 */
	private function is_snapshot_stale( $synced_at, $scope ) {
		$synced_at = (int) $synced_at;
		if ( $synced_at <= 0 ) {
			return true;
		}

		return ( time() - $synced_at ) > $this->get_sync_ttl( $scope );
	}

	/**
	 * Extract unique product ids from an order.
	 *
	 * @param MP_Order $order
	 *
	 * @return array
	 */
	private function extract_order_product_ids( $order ) {
		$product_ids = array();
		$cart_items  = $order->get_meta( 'mp_cart_items' );

		if ( is_array( $cart_items ) ) {
			foreach ( $cart_items as $product_id => $items ) {
				$product_id = (int) $product_id;
				if ( $product_id > 0 ) {
					$product_ids[] = $product_id;
				}
			}
		} else {
			$cart = $order->get_cart();
			if ( is_object( $cart ) && method_exists( $cart, 'get_items' ) ) {
				$items = (array) $cart->get_items();
				foreach ( $items as $item ) {
					$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
					if ( $product_id > 0 ) {
						$product_ids[] = $product_id;
					}
				}
			}
		}

		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );

		if ( ! empty( $product_ids ) ) {
			$product_post_type = MP_Product::get_post_type();
			$product_ids = array_values( array_filter( $product_ids, function( $product_id ) use ( $product_post_type ) {
				return get_post_type( (int) $product_id ) === $product_post_type;
			} ) );
		}

		return $product_ids;
	}

	/**
	 * Get map of reviewed product ids for user.
	 *
	 * @param int   $user_id
	 * @param array $product_ids
	 *
	 * @return array
	 */
	private function get_reviewed_product_ids( $user_id, $product_ids ) {
		$map = array();
		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $product_ids ) ) ) );
		if ( empty( $product_ids ) ) {
			return $map;
		}

		$comments = get_comments( array(
			'user_id'  => $user_id,
			'post__in' => $product_ids,
			'status'   => 'approve',
			'number'   => 0,
			'meta_key' => 'rating',
		) );

		foreach ( (array) $comments as $comment ) {
			$map[ (int) $comment->comment_post_ID ] = true;
		}

		return $map;
	}

	/**
	 * Get recent reviews for current blog context.
	 *
	 * @param int $user_id
	 * @param int $blog_id
	 * @param int $limit
	 *
	 * @return array
	 */
	private function get_recent_reviews( $user_id, $blog_id, $limit ) {
		if ( $blog_id > 0 && is_multisite() && $blog_id !== (int) get_current_blog_id() ) {
			switch_to_blog( $blog_id );
			$switched = true;
		} else {
			$switched = false;
		}

		$product_post_type = MP_Product::get_post_type();
		$comments = get_comments( array(
			'user_id' => $user_id,
			'status'  => 'approve',
			'number'  => (int) $limit,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		) );

		$items = array();
		foreach ( (array) $comments as $comment ) {
			$post_id = (int) $comment->comment_post_ID;
			$rating  = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			if ( $rating < 1 || get_post_type( $post_id ) !== $product_post_type ) {
				continue;
			}

			$items[] = array(
				'product_id'   => $post_id,
				'product_name' => get_the_title( $post_id ),
				'product_url'  => get_permalink( $post_id ),
				'rating'       => $rating,
				'timestamp'    => strtotime( $comment->comment_date_gmt . ' GMT' ),
				'date_text'    => date_i18n( get_option( 'date_format' ), strtotime( $comment->comment_date ) ),
			);
		}

		if ( $switched ) {
			restore_current_blog();
		}

		usort( $items, array( $this, 'sort_by_timestamp_desc' ) );
		return array_slice( $items, 0, (int) $limit );
	}

	/**
	 * Shared timestamp sorting.
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return int
	 */
	private function sort_by_timestamp_desc( $a, $b ) {
		$at = isset( $a['timestamp'] ) ? (int) $a['timestamp'] : 0;
		$bt = isset( $b['timestamp'] ) ? (int) $b['timestamp'] : 0;
		return $bt <=> $at;
	}

	/**
	 * Get status labels.
	 *
	 * @return array
	 */
	private function get_status_labels() {
		return array(
			'order_received' => __( 'Ausstehend', 'mp' ),
			'order_paid'     => __( 'Bezahlt', 'mp' ),
			'order_shipped'  => __( 'Versandt', 'mp' ),
			'order_closed'   => __( 'Abgeschlossen', 'mp' ),
		);
	}

	/**
	 * Get closed statuses.
	 *
	 * @return array
	 */
	private function get_closed_statuses() {
		return array( 'order_closed', 'order_shipped' );
	}

	/**
	 * Get labels for withdrawal statuses.
	 *
	 * @return array
	 */
	private function get_withdrawal_status_labels() {
		return array(
			'none'      => __( 'Kein Widerruf', 'mp' ),
			'requested' => __( 'Eingegangen', 'mp' ),
			'in_review' => __( 'In Pruefung', 'mp' ),
			'approved'  => __( 'Genehmigt', 'mp' ),
			'rejected'  => __( 'Abgelehnt', 'mp' ),
			'refunded'  => __( 'Erstattet', 'mp' ),
			'closed'    => __( 'Abgeschlossen', 'mp' ),
		);
	}

	/**
	 * Resolve one withdrawal status label.
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	private function get_withdrawal_status_label( $status ) {
		$status = sanitize_key( (string) $status );
		$labels = $this->get_withdrawal_status_labels();

		return isset( $labels[ $status ] ) ? (string) $labels[ $status ] : (string) $labels['none'];
	}

	/**
	 * Get sync ttl.
	 *
	 * @param string $scope
	 *
	 * @return int
	 */
	private function get_sync_ttl( $scope ) {
		$default_ttl = ( 'network' === $scope ) ? 420 : 300;
		return (int) apply_filters( 'mp_customer_portal_sync_ttl/' . $scope, $default_ttl );
	}

	/**
	 * Get cache ttl.
	 *
	 * @param string $scope
	 *
	 * @return int
	 */
	private function get_cache_ttl( $scope ) {
		$default_ttl = ( 'network' === $scope ) ? 90 : 60;
		return (int) apply_filters( 'mp_customer_portal_cache_ttl/' . $scope, $default_ttl );
	}

	/**
	 * Build transient cache key.
	 *
	 * @param string $scope
	 * @param int    $user_id
	 * @param int    $blog_id
	 *
	 * @return string
	 */
	private function get_cache_key( $scope, $user_id, $blog_id ) {
		if ( 'network' === $scope ) {
			return 'mp_portal_snapshot_network_' . $user_id;
		}

		return 'mp_portal_snapshot_single_' . $user_id . '_' . $blog_id;
	}

	/**
	 * Empty snapshot fallback.
	 *
	 * @param string $scope
	 * @param int    $blog_id
	 *
	 * @return array
	 */
	private function get_empty_snapshot( $scope, $blog_id ) {
		$empty = array(
			'orders'        => 0,
			'value'         => 0.0,
			'open_shipping' => 0,
			'to_review'     => 0,
			'shops'         => 0,
		);

		return array(
			'scope'          => $scope,
			'blog_id'        => (int) $blog_id,
			'user_id'        => 0,
			'currency'       => mp_get_setting( 'currency' ),
			'status_labels'  => $this->get_status_labels(),
			'totals'         => $empty,
			'orders'         => array(),
			'rows'           => array(),
			'pending_reviews'=> array(),
			'recent_reviews' => array(),
			'withdrawals'    => array(
				'counts' => array(
					'none'      => 0,
					'requested' => 0,
					'in_review' => 0,
					'approved'  => 0,
					'rejected'  => 0,
					'refunded'  => 0,
					'closed'    => 0,
				),
				'recent' => array(),
			),
			'synced_at'      => time(),
		);
	}
}

MP_Customer_Portal_API::get_instance();
