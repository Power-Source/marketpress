<?php

class MP_Support_Addon {
	/**
	 * Singleton instance.
	 *
	 * @var MP_Support_Addon|null
	 */
	private static $_instance = null;

	/**
	 * Get singleton.
	 *
	 * @return MP_Support_Addon
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new MP_Support_Addon();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_init_settings_metaboxes' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_admin_metaboxes' ) );
		add_action( 'save_post_mp_support_ticket', array( $this, 'save_admin_ticket_meta' ) );
		add_filter( 'manage_edit-mp_support_ticket_columns', array( $this, 'filter_ticket_columns' ) );
		add_action( 'manage_mp_support_ticket_posts_custom_column', array( $this, 'render_ticket_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_ticket_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_ticket_admin_filters' ) );
		add_filter( 'post_row_actions', array( $this, 'add_ticket_row_actions' ), 10, 2 );
		add_action( 'admin_post_mp_support_quick_status', array( $this, 'handle_quick_status_action' ) );
		add_filter( 'bulk_actions-edit-mp_support_ticket', array( $this, 'register_ticket_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-mp_support_ticket', array( $this, 'handle_ticket_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'maybe_render_ticket_bulk_notice' ) );
		add_action( 'wp_ajax_mp_support_create_ticket', array( $this, 'ajax_create_ticket' ) );
		add_action( 'wp_ajax_mp_support_reply_ticket', array( $this, 'ajax_reply_ticket' ) );
		add_shortcode( 'mp_support_center', array( $this, 'render_support_center_shortcode' ) );
	}

	/**
	 * Register ticket data model.
	 */
	public function register_post_type() {
		$show_ui = true;
		if ( $this->use_mainshop_sync_mode() && is_multisite() && function_exists( 'mp_is_main_site' ) && ! mp_is_main_site() ) {
			$show_ui = false;
		}

		register_post_type( 'mp_support_ticket', array(
			'labels' => array(
				'name'          => __( 'Support Tickets', 'mp' ),
				'singular_name' => __( 'Support Ticket', 'mp' ),
				'add_new_item'  => __( 'Support Ticket erstellen', 'mp' ),
				'edit_item'     => __( 'Support Ticket bearbeiten', 'mp' ),
			),
			'public'              => false,
			'show_ui'             => $show_ui,
			'show_in_menu'        => 'edit.php?post_type=product',
			'menu_position'       => 58,
			'menu_icon'           => 'dashicons-sos',
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor', 'author' ),
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
		) );
	}

	/**
	 * Register addon settings metabox.
	 */
	public function maybe_init_settings_metaboxes() {
		if ( ! is_admin() ) {
			return;
		}

		if ( 'store-settings-addons' !== mp_get_get_value( 'page', '' ) ) {
			return;
		}

		if ( 'MP_Support_Addon' !== mp_get_get_value( 'addon', '' ) ) {
			return;
		}

		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-support-settings-metabox',
			'title'       => __( 'Kundensupport Einstellungen', 'mp' ),
			'page_slugs'  => array( 'store-settings-addons' ),
			'option_name' => 'mp_settings',
		) );

		$metabox->add_field( 'checkbox', array(
			'name'          => 'support[enabled]',
			'label'         => array( 'text' => __( 'Support-Center aktivieren?', 'mp' ) ),
			'message'       => __( 'Ja', 'mp' ),
			'default_value' => 1,
		) );

		$metabox->add_field( 'textarea', array(
			'name'          => 'support[intro_text]',
			'label'         => array( 'text' => __( 'Einleitungstext im Support-Center', 'mp' ) ),
			'custom'        => array( 'rows' => 4 ),
			'default_value' => __( 'Erstelle ein Ticket und verfolge den Bearbeitungsstand direkt in Deinem Kundenbereich.', 'mp' ),
		) );

		$metabox->add_field( 'checkbox', array(
			'name'          => 'support[allow_customer_replies]',
			'label'         => array( 'text' => __( 'Antworten durch Kunden erlauben?', 'mp' ) ),
			'message'       => __( 'Ja, Kunden duerfen auf offene Tickets antworten', 'mp' ),
			'default_value' => 1,
		) );

		$metabox->add_field( 'text', array(
			'name'          => 'support[max_message_length]',
			'label'         => array( 'text' => __( 'Maximale Nachrichtenlaenge', 'mp' ) ),
			'default_value' => 800,
		) );

		$metabox->add_field( 'text', array(
			'name'          => 'support[sla_hours]',
			'label'         => array( 'text' => __( 'SLA in Stunden', 'mp' ) ),
			'desc'          => __( 'Definiert, wann offene Tickets als ueberfaellig markiert werden.', 'mp' ),
			'default_value' => 24,
		) );

		$metabox->add_field( 'text', array(
			'name'          => 'support[email_staff_reply_subject]',
			'label'         => array( 'text' => __( 'E-Mail-Betreff bei Staff-Antwort', 'mp' ) ),
			'desc'          => __( 'Platzhalter: TICKET_ID, TICKET_SUBJECT, STORE_NAME', 'mp' ),
			'default_value' => __( 'Antwort auf Dein Support-Ticket #TICKET_ID', 'mp' ),
		) );

		$metabox->add_field( 'textarea', array(
			'name'          => 'support[email_staff_reply_text]',
			'label'         => array( 'text' => __( 'E-Mail-Text bei Staff-Antwort', 'mp' ) ),
			'custom'        => array( 'rows' => 7 ),
			'desc'          => __( 'Platzhalter: CUSTOMER_NAME, TICKET_ID, TICKET_SUBJECT, STAFF_MESSAGE, STORE_NAME', 'mp' ),
			'default_value' => __( "Hallo CUSTOMER_NAME,\n\nzu Deinem Support-Ticket #TICKET_ID gibt es eine neue Antwort:\n\nSTAFF_MESSAGE\n\nDu kannst direkt in Deinem Kundenbereich antworten.", 'mp' ),
		) );
	}

	/**
	 * Register admin metaboxes for ticket management.
	 */
	public function register_admin_metaboxes() {
		add_meta_box(
			'mp-support-ticket-meta',
			__( 'Ticket-Status', 'mp' ),
			array( $this, 'render_ticket_status_metabox' ),
			'mp_support_ticket',
			'side',
			'default'
		);

		add_meta_box(
			'mp-support-ticket-thread',
			__( 'Ticket-Verlauf', 'mp' ),
			array( $this, 'render_ticket_thread_metabox' ),
			'mp_support_ticket',
			'normal',
			'default'
		);
	}

	/**
	 * Render status metabox.
	 *
	 * @param WP_Post $post Ticket post.
	 */
	public function render_ticket_status_metabox( $post ) {
		$status   = (string) get_post_meta( $post->ID, '_mp_support_status', true );
		$priority = (string) get_post_meta( $post->ID, '_mp_support_priority', true );
		$order_id = (int) get_post_meta( $post->ID, '_mp_support_order_id', true );
		$assignee = (int) get_post_meta( $post->ID, '_mp_support_assignee', true );

		if ( '' === $status ) {
			$status = 'open';
		}
		if ( '' === $priority ) {
			$priority = 'normal';
		}

		wp_nonce_field( 'mp_support_ticket_admin_' . $post->ID, 'mp_support_ticket_admin_nonce' );

		echo '<p><label for="mp_support_status"><strong>' . esc_html__( 'Status', 'mp' ) . '</strong></label></p>';
		echo '<select id="mp_support_status" name="mp_support_status" style="width:100%;">';
		echo '<option value="open" ' . selected( $status, 'open', false ) . '>' . esc_html__( 'Offen', 'mp' ) . '</option>';
		echo '<option value="in_progress" ' . selected( $status, 'in_progress', false ) . '>' . esc_html__( 'In Bearbeitung', 'mp' ) . '</option>';
		echo '<option value="resolved" ' . selected( $status, 'resolved', false ) . '>' . esc_html__( 'Geloest', 'mp' ) . '</option>';
		echo '<option value="closed" ' . selected( $status, 'closed', false ) . '>' . esc_html__( 'Geschlossen', 'mp' ) . '</option>';
		echo '</select>';

		echo '<p style="margin-top:10px;"><label for="mp_support_priority"><strong>' . esc_html__( 'Prioritaet', 'mp' ) . '</strong></label></p>';
		echo '<select id="mp_support_priority" name="mp_support_priority" style="width:100%;">';
		echo '<option value="normal" ' . selected( $priority, 'normal', false ) . '>' . esc_html__( 'Normal', 'mp' ) . '</option>';
		echo '<option value="high" ' . selected( $priority, 'high', false ) . '>' . esc_html__( 'Hoch', 'mp' ) . '</option>';
		echo '</select>';

		echo '<p style="margin-top:10px;"><label for="mp_support_order_id"><strong>' . esc_html__( 'Bestell-ID (optional)', 'mp' ) . '</strong></label></p>';
		echo '<input type="number" min="0" id="mp_support_order_id" name="mp_support_order_id" value="' . esc_attr( (string) $order_id ) . '" style="width:100%;">';

		$users = get_users( array(
			'fields'  => array( 'ID', 'display_name' ),
			'orderby' => 'display_name',
			'order'   => 'ASC',
		) );

		echo '<p style="margin-top:10px;"><label for="mp_support_assignee"><strong>' . esc_html__( 'Zuweisung', 'mp' ) . '</strong></label></p>';
		echo '<select id="mp_support_assignee" name="mp_support_assignee" style="width:100%;">';
		echo '<option value="0">' . esc_html__( 'Nicht zugewiesen', 'mp' ) . '</option>';
		foreach ( (array) $users as $user ) {
			echo '<option value="' . esc_attr( (string) $user->ID ) . '" ' . selected( $assignee, (int) $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Render ticket thread metabox.
	 *
	 * @param WP_Post $post Ticket post.
	 */
	public function render_ticket_thread_metabox( $post ) {
		$messages = get_post_meta( $post->ID, '_mp_support_messages', true );
		$messages = is_array( $messages ) ? $messages : array();

		if ( empty( $messages ) ) {
			echo '<p>' . esc_html__( 'Noch keine Nachrichten vorhanden.', 'mp' ) . '</p>';
			return;
		}

		$internal_notes = get_post_meta( $post->ID, '_mp_support_internal_notes', true );
		$internal_notes = is_array( $internal_notes ) ? $internal_notes : array();

		// Merge public messages and internal notes into one sorted timeline.
		$timeline = array();
		foreach ( $messages as $entry ) {
			$timeline[] = array_merge( $entry, array( 'is_internal' => false ) );
		}
		foreach ( $internal_notes as $entry ) {
			$timeline[] = array_merge( $entry, array( 'is_internal' => true ) );
		}
		usort( $timeline, function ( $a, $b ) {
			return (int) mp_arr_get_value( 'timestamp', $a, 0 ) - (int) mp_arr_get_value( 'timestamp', $b, 0 );
		} );

		echo '<div style="display:grid;gap:10px;">';
		foreach ( $timeline as $entry ) {
			$author      = (string) mp_arr_get_value( 'author', $entry, __( 'Unbekannt', 'mp' ) );
			$role        = (string) mp_arr_get_value( 'role', $entry, 'customer' );
			$text        = (string) mp_arr_get_value( 'message', $entry, '' );
			$time        = (int) mp_arr_get_value( 'timestamp', $entry, 0 );
			$is_internal = (bool) mp_arr_get_value( 'is_internal', $entry, false );

			if ( $is_internal ) {
				$bg     = '#fffde7';
				$border = '#f5c518';
				$label  = __( 'Interne Notiz', 'mp' );
			} elseif ( 'staff' === $role ) {
				$bg     = '#e9f4ff';
				$border = '#6baed6';
				$label  = __( 'Team', 'mp' );
			} else {
				$bg     = '#fff';
				$border = '#dbe2ea';
				$label  = __( 'Kunde', 'mp' );
			}

			echo '<div style="border:1px solid ' . esc_attr( $border ) . ';border-radius:8px;padding:10px;background:' . esc_attr( $bg ) . ';">';
			echo '<p style="margin:0 0 6px;">';
			if ( $is_internal ) {
				echo '<span style="background:#f5c518;color:#333;border-radius:4px;padding:1px 6px;font-size:11px;margin-right:6px;">' . esc_html__( 'Intern', 'mp' ) . '</span>';
			}
			echo '<strong>' . esc_html( $author ) . '</strong> <em>(' . esc_html( $label ) . ')</em> · ' . esc_html( $time ? date_i18n( 'd.m.Y H:i', $time ) : '-' );
			echo '</p>';
			echo '<div>' . nl2br( esc_html( $text ) ) . '</div>';
			echo '</div>';
		}
		echo '</div>';

		echo '<hr style="margin:14px 0;">';

		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';

		echo '<div>';
		echo '<p><strong>' . esc_html__( 'Staff-Antwort (öffentlich)', 'mp' ) . '</strong></p>';
		echo '<p style="margin:0 0 4px;font-size:12px;color:#4f6a82;">' . esc_html__( 'Sichtbar für den Kunden', 'mp' ) . '</p>';
		echo '<textarea name="mp_support_staff_reply" rows="5" style="width:100%;" placeholder="' . esc_attr__( 'Antwort an den Kunden eingeben...', 'mp' ) . '"></textarea>';
		echo '</div>';

		echo '<div>';
		echo '<p><strong>' . esc_html__( 'Interne Notiz', 'mp' ) . '</strong></p>';
		echo '<p style="margin:0 0 4px;font-size:12px;color:#7a6200;">' . esc_html__( 'Nur für das Team sichtbar', 'mp' ) . '</p>';
		echo '<textarea name="mp_support_internal_note" rows="5" style="width:100%;background:#fffde7;border-color:#f5c518;" placeholder="' . esc_attr__( 'Interne Notiz für das Team eingeben...', 'mp' ) . '"></textarea>';
		echo '</div>';

		echo '</div>';

		$current_status = (string) get_post_meta( $post->ID, '_mp_support_status', true );
		if ( '' === $current_status ) {
			$current_status = 'open';
		}

		echo '<p style="margin-top:12px;"><label for="mp_support_status_after_reply"><strong>' . esc_html__( 'Status nach Antwort', 'mp' ) . '</strong></label></p>';
		echo '<select id="mp_support_status_after_reply" name="mp_support_status_after_reply" style="width:100%;">';
		echo '<option value="in_progress" ' . selected( $current_status, 'in_progress', false ) . '>' . esc_html__( 'In Bearbeitung', 'mp' ) . '</option>';
		echo '<option value="open" ' . selected( $current_status, 'open', false ) . '>' . esc_html__( 'Offen', 'mp' ) . '</option>';
		echo '<option value="resolved" ' . selected( $current_status, 'resolved', false ) . '>' . esc_html__( 'Geloest', 'mp' ) . '</option>';
		echo '<option value="closed" ' . selected( $current_status, 'closed', false ) . '>' . esc_html__( 'Geschlossen', 'mp' ) . '</option>';
		echo '</select>';

		echo '<p style="margin-top:10px;"><label><input type="checkbox" name="mp_support_notify_customer" value="1" checked> ' . esc_html__( 'Kunden per E-Mail benachrichtigen', 'mp' ) . '</label></p>';
	}

	/**
	 * Save ticket metadata from admin metabox.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_admin_ticket_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = (string) mp_get_post_value( 'mp_support_ticket_admin_nonce', '' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'mp_support_ticket_admin_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$status   = sanitize_key( (string) mp_get_post_value( 'mp_support_status', 'open' ) );
		$priority = sanitize_key( (string) mp_get_post_value( 'mp_support_priority', 'normal' ) );
		$order_id = (int) mp_get_post_value( 'mp_support_order_id', 0 );
		$assignee = (int) mp_get_post_value( 'mp_support_assignee', 0 );
		$reply_text         = sanitize_textarea_field( (string) mp_get_post_value( 'mp_support_staff_reply', '' ) );
		$internal_note_text = sanitize_textarea_field( (string) mp_get_post_value( 'mp_support_internal_note', '' ) );
		$status_after_reply = sanitize_key( (string) mp_get_post_value( 'mp_support_status_after_reply', 'in_progress' ) );
		$notify_customer    = (bool) mp_get_post_value( 'mp_support_notify_customer', 0 );

		if ( ! in_array( $status, array( 'open', 'in_progress', 'resolved', 'closed' ), true ) ) {
			$status = 'open';
		}
		if ( ! in_array( $priority, array( 'normal', 'high' ), true ) ) {
			$priority = 'normal';
		}
		if ( ! in_array( $status_after_reply, array( 'open', 'in_progress', 'resolved', 'closed' ), true ) ) {
			$status_after_reply = 'in_progress';
		}

		update_post_meta( $post_id, '_mp_support_status', $status );
		update_post_meta( $post_id, '_mp_support_priority', $priority );
		update_post_meta( $post_id, '_mp_support_order_id', $order_id );
		update_post_meta( $post_id, '_mp_support_assignee', $assignee );

		if ( '' !== trim( $reply_text ) ) {
			$staff_user = wp_get_current_user();
			$messages   = get_post_meta( $post_id, '_mp_support_messages', true );
			$messages   = is_array( $messages ) ? $messages : array();
			$messages[] = array(
				'timestamp' => time(),
				'author'    => (string) $staff_user->display_name,
				'role'      => 'staff',
				'user_id'   => (int) $staff_user->ID,
				'message'   => $reply_text,
			);

			update_post_meta( $post_id, '_mp_support_messages', $messages );
			update_post_meta( $post_id, '_mp_support_status', $status_after_reply );
			$status = $status_after_reply;

			if ( $notify_customer ) {
				$this->send_staff_reply_email( $post_id, $reply_text );
			}
		}

		if ( '' !== trim( $internal_note_text ) ) {
			$staff_user     = wp_get_current_user();
			$internal_notes = get_post_meta( $post_id, '_mp_support_internal_notes', true );
			$internal_notes = is_array( $internal_notes ) ? $internal_notes : array();
			$internal_notes[] = array(
				'timestamp' => time(),
				'author'    => (string) $staff_user->display_name,
				'role'      => 'staff',
				'user_id'   => (int) $staff_user->ID,
				'message'   => $internal_note_text,
			);
			update_post_meta( $post_id, '_mp_support_internal_notes', $internal_notes );
		}

		do_action( 'mp_support_ticket_updated', (int) $post_id, $status );
	}

	/**
	 * Send optional customer notification email for staff replies.
	 *
	 * @param int    $ticket_id  Ticket ID.
	 * @param string $reply_text Staff reply.
	 */
	private function send_staff_reply_email( $ticket_id, $reply_text ) {
		$ticket = get_post( (int) $ticket_id );
		if ( ! $ticket ) {
			return;
		}

		$customer_id = (int) get_post_meta( (int) $ticket_id, '_mp_support_customer_id', true );
		if ( $customer_id <= 0 ) {
			$customer_id = (int) $ticket->post_author;
		}

		$user = $customer_id > 0 ? get_userdata( $customer_id ) : false;
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$store_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject_tpl = (string) mp_get_setting( 'support->email_staff_reply_subject', __( 'Antwort auf Dein Support-Ticket #TICKET_ID', 'mp' ) );
		$text_tpl    = (string) mp_get_setting( 'support->email_staff_reply_text', __( "Hallo CUSTOMER_NAME,\n\nzu Deinem Support-Ticket #TICKET_ID gibt es eine neue Antwort:\n\nSTAFF_MESSAGE\n\nDu kannst direkt in Deinem Kundenbereich antworten.", 'mp' ) );

		$replacements = array(
			'TICKET_ID'      => (string) $ticket_id,
			'TICKET_SUBJECT' => (string) $ticket->post_title,
			'STAFF_MESSAGE'  => (string) $reply_text,
			'CUSTOMER_NAME'  => (string) $user->display_name,
			'STORE_NAME'     => (string) $store_name,
		);

		$subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject_tpl );
		$body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $text_tpl );

		wp_mail( $user->user_email, $subject, $body );
	}

	/**
	 * Register custom columns for ticket list.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function filter_ticket_columns( $columns ) {
		return array(
			'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'title'      => __( 'Ticket', 'mp' ),
			'ticket_meta' => __( 'Details', 'mp' ),
			'ticket_sla' => __( 'SLA', 'mp' ),
			'author'     => __( 'Kunde', 'mp' ),
			'date'       => __( 'Datum', 'mp' ),
		);
	}

	/**
	 * Render custom ticket columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_ticket_column( $column, $post_id ) {
		$status   = (string) get_post_meta( $post_id, '_mp_support_status', true );
		$priority = (string) get_post_meta( $post_id, '_mp_support_priority', true );
		$order_id = (int) get_post_meta( $post_id, '_mp_support_order_id', true );
		$assignee = (int) get_post_meta( $post_id, '_mp_support_assignee', true );

		if ( 'ticket_meta' === $column ) {
			echo '<strong>' . esc_html__( 'Status:', 'mp' ) . '</strong> ' . esc_html( $this->get_status_label( $status ? $status : 'open' ) ) . '<br>';
			echo '<strong>' . esc_html__( 'Prioritaet:', 'mp' ) . '</strong> ' . esc_html( $this->get_priority_label( $priority ? $priority : 'normal' ) ) . '<br>';
			echo '<strong>' . esc_html__( 'Bestellung:', 'mp' ) . '</strong> ' . esc_html( $order_id ? '#' . $order_id : '-' ) . '<br>';
			if ( $assignee > 0 ) {
				$user = get_userdata( $assignee );
				echo '<strong>' . esc_html__( 'Zugewiesen:', 'mp' ) . '</strong> ' . esc_html( $user ? $user->display_name : '-' );
			} else {
				echo '<strong>' . esc_html__( 'Zugewiesen:', 'mp' ) . '</strong> ' . esc_html__( 'Niemand', 'mp' );
			}
		}

		if ( 'ticket_sla' === $column ) {
			$due_ts = $this->get_ticket_due_timestamp( $post_id );
			if ( ! $due_ts ) {
				echo '-';
				return;
			}

			$is_open = in_array( $status, array( 'open', 'in_progress', '' ), true );
			$is_overdue = $is_open && ( $due_ts < time() );
			echo esc_html( date_i18n( 'd.m.Y H:i', $due_ts ) );
			if ( $is_overdue ) {
				echo '<br><span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Ueberfaellig', 'mp' ) . '</span>';
			}
		}
	}

	/**
	 * Add list filters for ticket admin table.
	 */
	public function render_ticket_filters() {
		global $typenow;

		if ( 'mp_support_ticket' !== $typenow ) {
			return;
		}

		$status = sanitize_key( (string) mp_get_get_value( 'mp_support_status', '' ) );
		$priority = sanitize_key( (string) mp_get_get_value( 'mp_support_priority', '' ) );
		$sla = sanitize_key( (string) mp_get_get_value( 'mp_support_sla', '' ) );

		echo '<select name="mp_support_status">';
		echo '<option value="">' . esc_html__( 'Alle Status', 'mp' ) . '</option>';
		foreach ( array( 'open', 'in_progress', 'resolved', 'closed' ) as $item ) {
			echo '<option value="' . esc_attr( $item ) . '" ' . selected( $status, $item, false ) . '>' . esc_html( $this->get_status_label( $item ) ) . '</option>';
		}
		echo '</select>';

		echo '<select name="mp_support_priority">';
		echo '<option value="">' . esc_html__( 'Alle Prioritaeten', 'mp' ) . '</option>';
		foreach ( array( 'normal', 'high' ) as $item ) {
			echo '<option value="' . esc_attr( $item ) . '" ' . selected( $priority, $item, false ) . '>' . esc_html( $this->get_priority_label( $item ) ) . '</option>';
		}
		echo '</select>';

		echo '<select name="mp_support_sla">';
		echo '<option value="">' . esc_html__( 'SLA: Alle', 'mp' ) . '</option>';
		echo '<option value="overdue" ' . selected( $sla, 'overdue', false ) . '>' . esc_html__( 'Ueberfaellig', 'mp' ) . '</option>';
		echo '<option value="in_time" ' . selected( $sla, 'in_time', false ) . '>' . esc_html__( 'In SLA', 'mp' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Apply admin filters to ticket query.
	 *
	 * @param WP_Query $query Query object.
	 */
	public function apply_ticket_admin_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'mp_support_ticket' !== $query->get( 'post_type' ) ) {
			return;
		}

		$status = sanitize_key( (string) mp_get_get_value( 'mp_support_status', '' ) );
		$priority = sanitize_key( (string) mp_get_get_value( 'mp_support_priority', '' ) );
		$sla = sanitize_key( (string) mp_get_get_value( 'mp_support_sla', '' ) );

		$meta_query = array();

		if ( $status ) {
			$meta_query[] = array(
				'key'     => '_mp_support_status',
				'value'   => $status,
				'compare' => '=',
			);
		}

		if ( $priority ) {
			$meta_query[] = array(
				'key'     => '_mp_support_priority',
				'value'   => $priority,
				'compare' => '=',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}

		if ( 'overdue' === $sla || 'in_time' === $sla ) {
			$all_ids = get_posts( array(
				'post_type'      => 'mp_support_ticket',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			) );

			$match_ids = array();
			foreach ( (array) $all_ids as $ticket_id ) {
				$current_status = (string) get_post_meta( (int) $ticket_id, '_mp_support_status', true );
				if ( ! in_array( $current_status, array( 'open', 'in_progress', '' ), true ) ) {
					continue;
				}

				$due_ts = $this->get_ticket_due_timestamp( (int) $ticket_id );
				if ( ! $due_ts ) {
					continue;
				}

				$is_overdue = $due_ts < time();
				if ( ( 'overdue' === $sla && $is_overdue ) || ( 'in_time' === $sla && ! $is_overdue ) ) {
					$match_ids[] = (int) $ticket_id;
				}
			}

			$query->set( 'post__in', ! empty( $match_ids ) ? $match_ids : array( 0 ) );
		}
	}

	/**
	 * Add quick status actions to ticket rows.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Current post.
	 *
	 * @return array
	 */
	public function add_ticket_row_actions( $actions, $post ) {
		if ( ! is_object( $post ) || 'mp_support_ticket' !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['mp_support_open'] = '<a href="' . esc_url( $this->get_quick_status_url( $post->ID, 'open' ) ) . '">' . esc_html__( 'Als offen markieren', 'mp' ) . '</a>';
		$actions['mp_support_in_progress'] = '<a href="' . esc_url( $this->get_quick_status_url( $post->ID, 'in_progress' ) ) . '">' . esc_html__( 'In Bearbeitung', 'mp' ) . '</a>';
		$actions['mp_support_resolved'] = '<a href="' . esc_url( $this->get_quick_status_url( $post->ID, 'resolved' ) ) . '">' . esc_html__( 'Als geloest markieren', 'mp' ) . '</a>';

		return $actions;
	}

	/**
	 * Handle quick status action.
	 */
	public function handle_quick_status_action() {
		$ticket_id = (int) mp_get_get_value( 'ticket_id', 0 );
		$status = sanitize_key( (string) mp_get_get_value( 'status', '' ) );
		$nonce = (string) mp_get_get_value( '_wpnonce', '' );

		if ( ! $ticket_id || ! in_array( $status, array( 'open', 'in_progress', 'resolved', 'closed' ), true ) ) {
			wp_die( esc_html__( 'Ungueltige Ticket-Aktion.', 'mp' ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'mp_support_quick_status_' . $ticket_id . '_' . $status ) ) {
			wp_die( esc_html__( 'Sicherheitspruefung fehlgeschlagen.', 'mp' ) );
		}

		if ( ! current_user_can( 'edit_post', $ticket_id ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mp' ) );
		}

		update_post_meta( $ticket_id, '_mp_support_status', $status );
		do_action( 'mp_support_ticket_updated', (int) $ticket_id, $status );

		$redirect = remove_query_arg( array( 'action', 'ticket_id', 'status', '_wpnonce' ), wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=mp_support_ticket' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Register bulk actions for ticket list.
	 *
	 * @param array $actions Actions.
	 *
	 * @return array
	 */
	public function register_ticket_bulk_actions( $actions ) {
		$actions['mp_support_bulk_open'] = __( 'Status: Offen', 'mp' );
		$actions['mp_support_bulk_in_progress'] = __( 'Status: In Bearbeitung', 'mp' );
		$actions['mp_support_bulk_resolved'] = __( 'Status: Geloest', 'mp' );
		$actions['mp_support_bulk_closed'] = __( 'Status: Geschlossen', 'mp' );

		return $actions;
	}

	/**
	 * Handle ticket bulk actions.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Bulk action.
	 * @param array  $post_ids     IDs.
	 *
	 * @return string
	 */
	public function handle_ticket_bulk_actions( $redirect_url, $action, $post_ids ) {
		$map = array(
			'mp_support_bulk_open'        => 'open',
			'mp_support_bulk_in_progress' => 'in_progress',
			'mp_support_bulk_resolved'    => 'resolved',
			'mp_support_bulk_closed'      => 'closed',
		);

		if ( ! isset( $map[ $action ] ) ) {
			return $redirect_url;
		}

		$status = $map[ $action ];
		$updated = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_mp_support_status', $status );
			do_action( 'mp_support_ticket_updated', (int) $post_id, $status );
			$updated++;
		}

		return add_query_arg( array( 'mp_support_bulk_updated' => $updated ), $redirect_url );
	}

	/**
	 * Show bulk update admin notice.
	 */
	public function maybe_render_ticket_bulk_notice() {
		if ( 'mp_support_ticket' !== mp_get_get_value( 'post_type', '' ) ) {
			return;
		}

		$updated = (int) mp_get_get_value( 'mp_support_bulk_updated', 0 );
		if ( $updated <= 0 ) {
			return;
		}

		echo '<div class="updated notice"><p>' . esc_html( sprintf( _n( '%d Ticket aktualisiert.', '%d Tickets aktualisiert.', $updated, 'mp' ), $updated ) ) . '</p></div>';
	}

	/**
	 * Frontend shortcode.
	 *
	 * @return string
	 */
	public function render_support_center_shortcode() {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Bitte melde Dich an, um den Supportbereich zu nutzen.', 'mp' ) . '</p>';
		}

		if ( $this->use_mainshop_sync_mode() && function_exists( 'mp_is_main_site' ) && ! mp_is_main_site() ) {
			return '<p>' . esc_html__( 'Support laeuft im Mainshop-Sync. Nutze bitte das zentrale Kundenportal im Mainshop.', 'mp' ) . '</p>';
		}

		$user_id       = get_current_user_id();
		$intro_text    = (string) mp_get_setting( 'support->intro_text', '' );
		$max_len       = (int) mp_get_setting( 'support->max_message_length', 800 );
		$allow_replies = (bool) mp_get_setting( 'support->allow_customer_replies', 1 );
		if ( $max_len < 120 ) {
			$max_len = 120;
		}

		$tickets = $this->get_customer_tickets( $user_id );

		ob_start();
		?>
		<section id="mp-support-center" class="mp-support-center" style="border:1px solid #d8e5ef;border-radius:12px;padding:14px;background:#f9fcff;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Kundensupport', 'mp' ); ?></h3>
			<p><?php echo esc_html( $intro_text ? $intro_text : __( 'Erstelle ein Ticket und verfolge den Bearbeitungsstand direkt hier.', 'mp' ) ); ?></p>

			<form class="mp-support-create" method="post" action="<?php echo esc_url( mp_get_ajax_url() ); ?>" style="display:grid;gap:8px;margin-bottom:16px;">
				<input type="hidden" name="action" value="mp_support_create_ticket">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mp_support_create' ) ); ?>">
				<label><strong><?php esc_html_e( 'Betreff', 'mp' ); ?></strong></label>
				<input type="text" name="subject" required maxlength="180">
				<label><strong><?php esc_html_e( 'Bestell-ID (optional)', 'mp' ); ?></strong></label>
				<input type="number" min="0" name="order_id">
				<label><strong><?php esc_html_e( 'Nachricht', 'mp' ); ?></strong></label>
				<textarea name="message" rows="4" maxlength="<?php echo esc_attr( (string) $max_len ); ?>" required></textarea>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Ticket erstellen', 'mp' ); ?></button>
			</form>

			<?php if ( empty( $tickets ) ) : ?>
				<p><?php esc_html_e( 'Noch keine Support-Tickets vorhanden.', 'mp' ); ?></p>
			<?php else : ?>
				<div style="display:grid;gap:10px;">
					<?php foreach ( $tickets as $ticket ) : ?>
						<?php
						$ticket_id = (int) $ticket['id'];
						$status    = (string) $ticket['status'];
						?>
						<article style="background:#fff;border:1px solid #d9e3ed;border-radius:10px;padding:10px;">
							<h4 style="margin:0 0 6px;"><?php echo esc_html( $ticket['title'] ); ?></h4>
							<p style="margin:0 0 8px;color:#4f6a82;">
								<?php echo esc_html( sprintf( __( 'Status: %1$s · Prioritaet: %2$s · Erstellt: %3$s', 'mp' ), $this->get_status_label( $status ), $this->get_priority_label( $ticket['priority'] ), date_i18n( 'd.m.Y H:i', $ticket['created_at'] ) ) ); ?>
							</p>
							<div style="display:grid;gap:8px;">
								<?php foreach ( $ticket['messages'] as $entry ) : ?>
									<?php if ( 'internal' === (string) mp_arr_get_value( 'type', $entry, '' ) ) : ?><?php continue; ?><?php endif; ?>
									<div style="border-left:3px solid <?php echo esc_attr( 'staff' === $entry['role'] ? '#2f6ca3' : '#8aa1b8' ); ?>;padding-left:8px;">
										<p style="margin:0 0 4px;"><strong><?php echo esc_html( $entry['author'] ); ?></strong> · <?php echo esc_html( date_i18n( 'd.m.Y H:i', (int) $entry['timestamp'] ) ); ?></p>
										<div><?php echo nl2br( esc_html( $entry['message'] ) ); ?></div>
									</div>
								<?php endforeach; ?>
							</div>

							<?php if ( $allow_replies && in_array( $status, array( 'open', 'in_progress' ), true ) ) : ?>
								<form method="post" action="<?php echo esc_url( mp_get_ajax_url() ); ?>" style="margin-top:8px;display:grid;gap:8px;">
									<input type="hidden" name="action" value="mp_support_reply_ticket">
									<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mp_support_reply_' . $ticket_id ) ); ?>">
									<input type="hidden" name="ticket_id" value="<?php echo esc_attr( (string) $ticket_id ); ?>">
									<textarea name="message" rows="3" maxlength="<?php echo esc_attr( (string) $max_len ); ?>" required></textarea>
									<button type="submit" class="button"><?php esc_html_e( 'Antwort senden', 'mp' ); ?></button>
								</form>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Create ticket from frontend.
	 */
	public function ajax_create_ticket() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Bitte melde Dich zuerst an.', 'mp' ) );
		}

		if ( ! $this->is_enabled() ) {
			wp_die( esc_html__( 'Support ist aktuell deaktiviert.', 'mp' ) );
		}

		$nonce = (string) mp_get_post_value( 'nonce', '' );
		if ( ! wp_verify_nonce( $nonce, 'mp_support_create' ) ) {
			wp_die( esc_html__( 'Sicherheitspruefung fehlgeschlagen.', 'mp' ) );
		}

		$subject = sanitize_text_field( (string) mp_get_post_value( 'subject', '' ) );
		$message = sanitize_textarea_field( (string) mp_get_post_value( 'message', '' ) );
		$order_id = (int) mp_get_post_value( 'order_id', 0 );
		$max_len  = (int) mp_get_setting( 'support->max_message_length', 800 );
		if ( $max_len < 120 ) {
			$max_len = 120;
		}

		if ( '' === $subject || '' === $message ) {
			wp_die( esc_html__( 'Betreff und Nachricht sind erforderlich.', 'mp' ) );
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $message, 'UTF-8' ) > $max_len : strlen( $message ) > $max_len ) {
			wp_die( esc_html__( 'Nachricht ist zu lang.', 'mp' ) );
		}

		$user      = wp_get_current_user();
		$insert_cb = function () use ( $user, $subject, $message, $order_id ) {
			$post_id = wp_insert_post( array(
				'post_type'    => 'mp_support_ticket',
				'post_status'  => 'publish',
				'post_title'   => $subject,
				'post_content' => $message,
				'post_author'  => (int) $user->ID,
			) );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return 0;
			}

			update_post_meta( $post_id, '_mp_support_status', 'open' );
			update_post_meta( $post_id, '_mp_support_priority', 'normal' );
			update_post_meta( $post_id, '_mp_support_customer_id', (int) $user->ID );
			update_post_meta( $post_id, '_mp_support_order_id', (int) $order_id );
			update_post_meta( $post_id, '_mp_support_source_blog_id', (int) get_current_blog_id() );
			update_post_meta( $post_id, '_mp_support_messages', array(
				array(
					'timestamp' => time(),
					'author'    => (string) $user->display_name,
					'role'      => 'customer',
					'user_id'   => (int) $user->ID,
					'message'   => $message,
				),
			) );

			do_action( 'mp_support_ticket_updated', (int) $post_id, 'open' );
			return (int) $post_id;
		};

		$post_id = $this->execute_in_data_scope( $insert_cb );
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Ticket konnte nicht erstellt werden.', 'mp' ) );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		exit;
	}

	/**
	 * Add customer reply to ticket.
	 */
	public function ajax_reply_ticket() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Bitte melde Dich zuerst an.', 'mp' ) );
		}

		if ( ! $this->is_enabled() ) {
			wp_die( esc_html__( 'Support ist aktuell deaktiviert.', 'mp' ) );
		}

		if ( ! mp_get_setting( 'support->allow_customer_replies', 1 ) ) {
			wp_die( esc_html__( 'Antworten sind aktuell deaktiviert.', 'mp' ) );
		}

		$ticket_id = (int) mp_get_post_value( 'ticket_id', 0 );
		$nonce     = (string) mp_get_post_value( 'nonce', '' );
		$message   = sanitize_textarea_field( (string) mp_get_post_value( 'message', '' ) );
		$max_len   = (int) mp_get_setting( 'support->max_message_length', 800 );
		if ( $max_len < 120 ) {
			$max_len = 120;
		}

		if ( ! $ticket_id || ! wp_verify_nonce( $nonce, 'mp_support_reply_' . $ticket_id ) ) {
			wp_die( esc_html__( 'Ungueltige Anfrage.', 'mp' ) );
		}

		if ( '' === $message ) {
			wp_die( esc_html__( 'Bitte gib eine Nachricht ein.', 'mp' ) );
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $message, 'UTF-8' ) > $max_len : strlen( $message ) > $max_len ) {
			wp_die( esc_html__( 'Nachricht ist zu lang.', 'mp' ) );
		}

		$user = wp_get_current_user();
		$ok   = $this->execute_in_data_scope( function () use ( $ticket_id, $user, $message ) {
			$post = get_post( $ticket_id );
			if ( ! $post || 'mp_support_ticket' !== $post->post_type ) {
				return false;
			}

			if ( (int) $post->post_author !== (int) $user->ID && ! current_user_can( 'edit_post', $ticket_id ) ) {
				return false;
			}

			$status = (string) get_post_meta( $ticket_id, '_mp_support_status', true );
			if ( in_array( $status, array( 'resolved', 'closed' ), true ) ) {
				return false;
			}

			$messages = get_post_meta( $ticket_id, '_mp_support_messages', true );
			$messages = is_array( $messages ) ? $messages : array();
			$messages[] = array(
				'timestamp' => time(),
				'author'    => (string) $user->display_name,
				'role'      => 'customer',
				'user_id'   => (int) $user->ID,
				'message'   => $message,
			);

			update_post_meta( $ticket_id, '_mp_support_messages', $messages );
			if ( 'open' !== $status && 'in_progress' !== $status ) {
				update_post_meta( $ticket_id, '_mp_support_status', 'open' );
			}

			do_action( 'mp_support_ticket_updated', (int) $ticket_id, 'open' );
			return true;
		} );

		if ( ! $ok ) {
			wp_die( esc_html__( 'Antwort konnte nicht gespeichert werden.', 'mp' ) );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		exit;
	}

	/**
	 * Get customer tickets in current support scope.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_customer_tickets( $user_id ) {
		$items = $this->execute_in_data_scope( function () use ( $user_id ) {
			$query = new WP_Query( array(
				'post_type'      => 'mp_support_ticket',
				'post_status'    => 'publish',
				'author'         => (int) $user_id,
				'posts_per_page' => 30,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			) );

			$result = array();
			foreach ( (array) $query->posts as $post ) {
				$status   = (string) get_post_meta( $post->ID, '_mp_support_status', true );
				$priority = (string) get_post_meta( $post->ID, '_mp_support_priority', true );
				$messages = get_post_meta( $post->ID, '_mp_support_messages', true );
				$messages = is_array( $messages ) ? $messages : array();

				$result[] = array(
					'id'         => (int) $post->ID,
					'title'      => (string) $post->post_title,
					'status'     => $status ? $status : 'open',
					'priority'   => $priority ? $priority : 'normal',
					'created_at' => strtotime( (string) $post->post_date_gmt ) ? strtotime( (string) $post->post_date_gmt ) : strtotime( (string) $post->post_date ),
					'messages'   => $messages,
				);
			}

			return $result;
		} );

		return is_array( $items ) ? $items : array();
	}

	/**
	 * Return localized status label.
	 *
	 * @param string $status Status key.
	 *
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'open'        => __( 'Offen', 'mp' ),
			'in_progress' => __( 'In Bearbeitung', 'mp' ),
			'resolved'    => __( 'Geloest', 'mp' ),
			'closed'      => __( 'Geschlossen', 'mp' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Offen', 'mp' );
	}

	/**
	 * Return localized priority label.
	 *
	 * @param string $priority Priority key.
	 *
	 * @return string
	 */
	private function get_priority_label( $priority ) {
		$labels = array(
			'normal' => __( 'Normal', 'mp' ),
			'high'   => __( 'Hoch', 'mp' ),
		);

		return isset( $labels[ $priority ] ) ? $labels[ $priority ] : __( 'Normal', 'mp' );
	}

	/**
	 * Build admin URL for quick status links.
	 *
	 * @param int    $ticket_id Ticket ID.
	 * @param string $status    Target status.
	 *
	 * @return string
	 */
	private function get_quick_status_url( $ticket_id, $status ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'mp_support_quick_status',
					'ticket_id' => (int) $ticket_id,
					'status'    => $status,
				),
				admin_url( 'admin-post.php' )
			),
			'mp_support_quick_status_' . (int) $ticket_id . '_' . $status
		);
	}

	/**
	 * Resolve SLA due timestamp for ticket.
	 *
	 * @param int $ticket_id Ticket ID.
	 *
	 * @return int
	 */
	private function get_ticket_due_timestamp( $ticket_id ) {
		$post = get_post( (int) $ticket_id );
		if ( ! $post ) {
			return 0;
		}

		$sla_hours = (int) mp_get_setting( 'support->sla_hours', 24 );
		if ( $sla_hours < 1 ) {
			$sla_hours = 24;
		}

		$base = strtotime( (string) $post->post_date_gmt );
		if ( ! $base ) {
			$base = strtotime( (string) $post->post_date );
		}
		if ( ! $base ) {
			return 0;
		}

		return $base + ( $sla_hours * HOUR_IN_SECONDS );
	}

	/**
	 * Check if support center is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		if ( ! mp_get_setting( 'support->enabled', 1 ) ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return true;
		}

		if ( ! mp_get_network_setting( 'advanced->network_support_enabled', 0 ) ) {
			return true;
		}

		return true;
	}

	/**
	 * Determine if current network mode uses mainshop sync.
	 *
	 * @return bool
	 */
	private function use_mainshop_sync_mode() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! mp_get_network_setting( 'advanced->network_support_enabled', 0 ) ) {
			return false;
		}

		return 'mainshop_sync' === (string) mp_get_network_setting( 'advanced->network_support_mode', 'autonomous' );
	}

	/**
	 * Execute callback in the correct data scope.
	 *
	 * @param callable $callback Callback.
	 *
	 * @return mixed
	 */
	private function execute_in_data_scope( $callback ) {
		if ( ! is_callable( $callback ) ) {
			return null;
		}

		if ( ! $this->use_mainshop_sync_mode() ) {
			return call_user_func( $callback );
		}

		$root_blog_id = function_exists( 'mp_root_blog_id' ) ? (int) mp_root_blog_id() : 1;
		$current_blog = (int) get_current_blog_id();
		if ( $current_blog === $root_blog_id ) {
			return call_user_func( $callback );
		}

		switch_to_blog( $root_blog_id );
		$result = call_user_func( $callback );
		restore_current_blog();

		return $result;
	}
}

MP_Support_Addon::get_instance();

if ( ! function_exists( 'mp_support_addon' ) ) :
	/**
	 * Get support addon instance.
	 *
	 * @return MP_Support_Addon
	 */
	function mp_support_addon() {
		return MP_Support_Addon::get_instance();
	}
endif;
