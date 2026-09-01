<?php

class MP_PS_Support_Integration {

	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {
		add_filter( 'mp_order/details', array( $this, 'append_support_actions' ), 120, 2 );
		add_filter( 'mp_order/history_actions', array( $this, 'append_support_actions' ), 120, 2 );
		add_action( 'admin_post_mp_create_order_support_ticket', array( $this, 'handle_create_ticket' ) );
		add_action( 'psource_support_front_ticket_context', array( $this, 'render_ticket_context' ) );
		add_filter( 'support_network_ticket_details_fields', array( $this, 'append_admin_ticket_context' ), 20, 2 );
		add_action( 'mp_network/suborder_created', array( $this, 'provision_shop_support' ), 10, 3 );
		add_action( 'add_meta_boxes', array( $this, 'add_product_support_metabox' ) );
		add_action( 'save_post_mp_product', array( $this, 'save_product_support' ) );
		add_filter( 'mp_product_list_meta', array( $this, 'append_product_support_links' ), 20, 2 );
		add_filter( 'mp_single_product_support_meta', array( $this, 'append_product_support_links' ), 20, 2 );
	}

	public function append_support_actions( $html, $order ) {
		if ( is_admin() || ! $order instanceof MP_Order || (int) $order->post_author !== (int) get_current_user_id() ) {
			return $html;
		}

		$shops = $this->get_order_shops( $order );
		$status = class_exists( 'MP_Withdrawal' ) ? MP_Withdrawal::get_instance()->get_customer_order_status( $order ) : array();
		if ( empty( $shops ) && empty( $status['label'] ) ) {
			return $html;
		}

		$order_key = (string) $order->get_id();
		$html .= '<aside class="mp-order-footer" aria-label="' . esc_attr__( 'Hilfe und Widerrufsstatus', 'mp' ) . '">';
		if ( $this->integration_is_enabled() && ! empty( $shops ) ) {
			$html .= '<div class="mp-order-support"><h4>' . esc_html__( 'Hilfe zur Bestellung', 'mp' ) . '</h4>';
			$html .= '<div class="mp-order-support-actions">';
			foreach ( $shops as $blog_id => $shop_name ) {
				$support_blog_id = $this->get_support_blog_id( $blog_id );
				if ( $this->is_enabled() && $this->can_create_ticket( $order, $support_blog_id ) ) {
					$label = count( $shops ) > 1 ? sprintf( __( 'Support von %s kontaktieren', 'mp' ), $shop_name ) : __( 'Support kontaktieren', 'mp' );
					$html .= '<details class="mp-order-support-form"><summary class="mp-order-footer-button">' . esc_html( $label ) . '</summary>';
					$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
					$html .= '<input type="hidden" name="action" value="mp_create_order_support_ticket">';
					$html .= '<input type="hidden" name="order_id" value="' . esc_attr( $order->ID ) . '">';
					$html .= '<input type="hidden" name="shop_blog_id" value="' . esc_attr( $blog_id ) . '">';
					$html .= wp_nonce_field( 'mp_order_support_' . $order->ID . '_' . $blog_id, '_mp_support_nonce', true, false );
					$html .= '<p><label>' . esc_html__( 'Betreff', 'mp' ) . '<br><input type="text" name="subject" required value="' . esc_attr( sprintf( __( 'Frage zur Bestellung %s', 'mp' ), $order_key ) ) . '"></label></p>';
					$html .= '<p><label>' . esc_html__( 'Nachricht', 'mp' ) . '<br><textarea name="message" rows="5" required></textarea></label></p>';
					$html .= '<p><button type="submit" class="mp-order-footer-button mp-order-footer-button-primary">' . esc_html__( 'Supportanfrage senden', 'mp' ) . '</button></p>';
					$html .= '</form></details>';
				}
			}
			if ( mp_get_network_setting( 'advanced->ps_support_customer_faq_button', 0 ) && function_exists( 'psource_support_get_faqs_page_url' ) ) {
				$faq_url = psource_support_get_faqs_page_url( $this->get_support_blog_id() );
				if ( $faq_url ) {
					$html .= '<a class="mp-order-footer-button mp-order-faq-link" href="' . esc_url( $faq_url ) . '">' . esc_html__( 'Zentrale FAQs', 'mp' ) . '</a>';
				}
			}
			$html .= '</div></div>';
		}
		if ( ! empty( $status['label'] ) ) {
			$state = sanitize_html_class( (string) mp_arr_get_value( 'state', $status, 'unavailable' ) );
			$html .= '<div class="mp-order-withdrawal-summary"><h4>' . esc_html__( 'Widerrufsstatus', 'mp' ) . '</h4>';
			$html .= '<span class="mp-order-withdrawal-state state-' . esc_attr( $state ) . '">' . esc_html( (string) $status['label'] ) . '</span>';
			if ( ! empty( $status['detail'] ) ) {
				$html .= '<span class="mp-order-withdrawal-detail">' . esc_html( (string) $status['detail'] ) . '</span>';
			}
			$html .= '</div>';
		}
		$html .= '</aside>';

		return $html;
	}

	public function get_support_url( $shop_blog_id = 0 ) {
		if ( ! $this->integration_is_enabled() || ! function_exists( 'psource_support_get_support_page_url' ) ) {
			return false;
		}

		return psource_support_get_support_page_url( $this->get_support_blog_id( $shop_blog_id ) );
	}

	public function handle_create_ticket() {
		if ( ! $this->is_enabled() || ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Support ist derzeit nicht verfügbar.', 'mp' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$blog_id = isset( $_POST['shop_blog_id'] ) ? absint( $_POST['shop_blog_id'] ) : 0;
		$order = new MP_Order( $order_id );
		if ( ! $order->exists() || ! $this->can_create_ticket( $order, $this->get_support_blog_id( $blog_id ) ) ) {
			wp_die( esc_html__( 'Diese Bestellung ist nicht verfügbar.', 'mp' ), 403 );
		}

		check_admin_referer( 'mp_order_support_' . $order_id . '_' . $blog_id, '_mp_support_nonce' );
		$shops = $this->get_order_shops( $order );
		if ( ! isset( $shops[ $blog_id ] ) ) {
			wp_die( esc_html__( 'Der gewählte Shop gehört nicht zu dieser Bestellung.', 'mp' ), 403 );
		}

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === $subject || '' === trim( wp_strip_all_tags( $message ) ) ) {
			wp_die( esc_html__( 'Bitte gib einen Betreff und eine Nachricht ein.', 'mp' ), 400 );
		}

		$priority = absint( mp_get_network_setting( 'advanced->ps_support_priority', 0 ) );
		if ( false === psource_support_get_valid_ticket_priority( $priority ) ) {
			$priority = 0;
		}

		$support_blog_id = $this->get_support_blog_id( $blog_id );
		$args = array(
			'blog_id'        => $support_blog_id,
			'user_id'        => get_current_user_id(),
			'ticket_priority' => $priority,
			'title'          => $subject,
			'message'        => $message,
		);
		$switched = is_multisite() && get_current_blog_id() !== $support_blog_id;
		if ( $switched ) {
			switch_to_blog( $support_blog_id );
		}
		$ticket_id = psource_support_insert_ticket( $args );
		if ( $switched ) {
			restore_current_blog();
		}
		if ( is_wp_error( $ticket_id ) ) {
			wp_die( esc_html( $ticket_id->get_error_message() ), 500 );
		}

		psource_support_update_ticket_meta( $ticket_id, 'marketpress_order_id', $order_id );
		psource_support_update_ticket_meta( $ticket_id, 'marketpress_order_key', (string) $order->get_id() );
		psource_support_update_ticket_meta( $ticket_id, 'marketpress_shop_blog_id', $blog_id );
		psource_support_update_ticket_meta( $ticket_id, 'marketpress_shop_order_id', $this->get_shop_order_id( $order, $blog_id ) );

		$redirect_url = function_exists( 'psource_support_get_support_page_url' ) ? psource_support_get_support_page_url( $support_blog_id ) : false;
		if ( ! $redirect_url ) {
			$redirect_url = wp_get_referer() ?: home_url( '/' );
		}
		wp_redirect( add_query_arg( 'tid', $ticket_id, $redirect_url ) );
		exit;
	}

	public function render_ticket_context( $ticket ) {
		$order_key = $ticket ? (string) psource_support_get_ticket_meta( $ticket->ticket_id, 'marketpress_order_key', true ) : '';
		if ( '' === $order_key ) {
			return;
		}

		echo '<div class="support-system-ticket-order-context"><strong>' . esc_html__( 'MarketPress-Bestellung:', 'mp' ) . '</strong> ' . esc_html( $order_key ) . '</div>';
	}

	public function append_admin_ticket_context( $fields, $ticket ) {
		$order_key = (string) psource_support_get_ticket_meta( $ticket->ticket_id, 'marketpress_order_key', true );
		$blog_id = absint( psource_support_get_ticket_meta( $ticket->ticket_id, 'marketpress_shop_blog_id', true ) );
		$shop_order_id = absint( psource_support_get_ticket_meta( $ticket->ticket_id, 'marketpress_shop_order_id', true ) );
		if ( '' === $order_key ) {
			return $fields;
		}

		$content = esc_html( $order_key );
		if ( $blog_id && $shop_order_id && get_blog_details( $blog_id ) ) {
			switch_to_blog( $blog_id );
			$url = admin_url( 'post.php?post=' . $shop_order_id . '&action=edit' );
			restore_current_blog();
			$content = '<a href="' . esc_url( $url ) . '">' . esc_html( $order_key ) . '</a>';
		}

		$fields['marketpress-order'] = array(
			'label' => __( 'MarketPress-Bestellung', 'mp' ),
			'content' => $content,
		);

		return $fields;
	}

	public function provision_shop_support( $suborder, $master_order, $shop ) {
		if ( ! $this->integration_is_enabled() || ! function_exists( 'psource_support_provision_subsite' ) ) {
			return;
		}
		psource_support_provision_subsite( get_current_blog_id(), 'marketpress', array(
			'tickets' => true,
			'faqs' => (bool) mp_get_network_setting( 'advanced->ps_support_customer_faq_button', 0 ) || (bool) mp_get_network_setting( 'advanced->ps_support_product_links', 0 ),
			'create_pages' => true,
		) );
	}

	public function add_product_support_metabox() {
		if ( ! $this->integration_is_enabled() || ! function_exists( 'psource_support_get_faqs' ) ) {
			return;
		}
		add_meta_box( 'mp-ps-support', __( 'Support und FAQ', 'mp' ), array( $this, 'render_product_support_metabox' ), MP_Product::get_post_type(), 'side' );
	}

	public function render_product_support_metabox( $post ) {
		wp_nonce_field( 'mp_product_support_' . $post->ID, '_mp_product_support_nonce' );
		$faq_id = absint( get_post_meta( $post->ID, '_mp_ps_support_faq_id', true ) );
		$faqs = psource_support_get_faqs( array( 'per_page' => -1 ) );
		echo '<p><label><input type="checkbox" name="mp_ps_support_link" value="1" ' . checked( (bool) get_post_meta( $post->ID, '_mp_ps_support_link', true ), true, false ) . '> ' . esc_html__( 'Supportlink anzeigen', 'mp' ) . '</label></p>';
		echo '<p><label for="mp-ps-support-faq">' . esc_html__( 'Gekoppelte FAQ', 'mp' ) . '</label><br><select id="mp-ps-support-faq" name="mp_ps_support_faq_id"><option value="0">' . esc_html__( 'Keine FAQ', 'mp' ) . '</option>';
		foreach ( $faqs as $faq ) {
			echo '<option value="' . esc_attr( $faq->faq_id ) . '" ' . selected( $faq_id, $faq->faq_id, false ) . '>' . esc_html( $faq->question ) . '</option>';
		}
		echo '</select></p>';
	}

	public function save_product_support( $post_id ) {
		if ( ! isset( $_POST['_mp_product_support_nonce'] ) || ! wp_verify_nonce( $_POST['_mp_product_support_nonce'], 'mp_product_support_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_mp_ps_support_link', ! empty( $_POST['mp_ps_support_link'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_mp_ps_support_faq_id', isset( $_POST['mp_ps_support_faq_id'] ) ? absint( $_POST['mp_ps_support_faq_id'] ) : 0 );
	}

	public function append_product_support_links( $html, $product_id ) {
		if ( ! $this->integration_is_enabled() || ! mp_get_network_setting( 'advanced->ps_support_product_links', 0 ) ) {
			return $html;
		}
		$links = array();
		$support_blog_id = $this->get_support_blog_id( get_current_blog_id() );
		if ( get_post_meta( $product_id, '_mp_ps_support_link', true ) && function_exists( 'psource_support_get_support_page_url' ) ) {
			$url = psource_support_get_support_page_url( $support_blog_id );
			if ( $url ) {
				$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Support', 'mp' ) . '</a>';
			}
		}
		$faq_id = absint( get_post_meta( $product_id, '_mp_ps_support_faq_id', true ) );
		if ( $faq_id && function_exists( 'psource_support_get_faqs_page_url' ) ) {
			$url = psource_support_get_faqs_page_url( $support_blog_id, $faq_id );
			if ( $url ) {
				$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Häufige Frage zu diesem Produkt', 'mp' ) . '</a>';
			}
		}

		return $links ? $html . '<div class="mp-product-support-links">' . implode( ' | ', $links ) . '</div>' : $html;
	}

	private function integration_is_enabled() {
		return function_exists( 'psource_support_insert_ticket' )
			&& (bool) mp_get_network_setting( 'advanced->ps_support_integration', 0 );
	}

	private function is_enabled() {
		return $this->integration_is_enabled()
			&& function_exists( 'psource_support_current_user_can' )
			&& function_exists( 'psource_support_update_ticket_meta' )
			&& (bool) mp_get_network_setting( 'advanced->ps_support_customer_button', 0 );
	}

	private function can_create_ticket( MP_Order $order, $support_blog_id = 0 ) {
		if ( (int) $order->post_author !== (int) get_current_user_id() ) {
			return false;
		}

		$switched = is_multisite() && $support_blog_id && get_current_blog_id() !== (int) $support_blog_id;
		if ( $switched ) {
			switch_to_blog( $support_blog_id );
		}
		$allowed = psource_support_current_user_can( 'insert_ticket' );
		if ( $switched ) {
			restore_current_blog();
		}

		return (bool) $allowed;
	}

	private function get_support_blog_id( $shop_blog_id = 0 ) {
		if ( ! is_multisite() ) {
			return get_current_blog_id();
		}

		$support_blog_id = function_exists( 'psource_support_get_setting' )
			? absint( psource_support_get_setting( 'psource_support_blog_id' ) )
			: 0;
		if ( ! $support_blog_id && function_exists( 'get_main_site_id' ) ) {
			$support_blog_id = absint( get_main_site_id( get_current_network_id() ) );
		}
		if ( ! $support_blog_id ) {
			$support_blog_id = absint( $shop_blog_id );
		}

		return $support_blog_id && get_blog_details( $support_blog_id ) ? $support_blog_id : get_current_blog_id();
	}

	private function get_order_shops( MP_Order $order ) {
		$blog_ids = array();
		foreach ( (array) $order->get_meta( '_mp_network_suborders', array() ) as $suborder ) {
			$blog_ids[] = absint( mp_arr_get_value( 'blog_id', $suborder, 0 ) );
		}

		if ( empty( array_filter( $blog_ids ) ) ) {
			$snapshot = $order->get_meta( 'mp_settlement_snapshot', array() );
			if ( is_array( $snapshot ) && ! empty( $snapshot['shops'] ) ) {
				$blog_ids = array_map( 'absint', array_keys( $snapshot['shops'] ) );
			}
		}

		if ( empty( array_filter( $blog_ids ) ) ) {
			$blog_ids[] = get_current_blog_id();
		}

		$shops = array();
		foreach ( array_unique( array_filter( $blog_ids ) ) as $blog_id ) {
			$details = get_blog_details( $blog_id );
			if ( $details ) {
				$shops[ $blog_id ] = $details->blogname;
			}
		}

		return $shops;
	}

	private function get_shop_order_id( MP_Order $order, $blog_id ) {
		foreach ( (array) $order->get_meta( '_mp_network_suborders', array() ) as $suborder ) {
			if ( absint( mp_arr_get_value( 'blog_id', $suborder, 0 ) ) === absint( $blog_id ) ) {
				return absint( mp_arr_get_value( 'post_id', $suborder, 0 ) );
			}
		}

		return 0;
	}
}

MP_PS_Support_Integration::get_instance();