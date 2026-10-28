<?php

require_once dirname( __DIR__, 2 ) . '/admin/class-mp-orders-admin.php';
class MP_B2B_Addon {

	private static $_instance = null;

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new MP_B2B_Addon();
		}

		return self::$_instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		if ( 'MP_B2B_Addon' === mp_get_get_value( 'addon', null ) ) {
			$this->view_settings();
		}

		add_filter( 'mp_checkout/address_fields_array', array( $this, 'add_checkout_fields' ), 10, 2 );
		add_filter( 'mp_checkout/address_fields', array( $this, 'add_checkout_field_toggle' ), 10, 3 );
		add_filter( 'mp_can_checkout', array( $this, 'validate_checkout_data' ), 10, 5 );
		add_action( 'wp_ajax_mp_update_checkout_data', array( $this, 'normalize_checkout_request' ), 1 );
		add_action( 'wp_ajax_nopriv_mp_update_checkout_data', array( $this, 'normalize_checkout_request' ), 1 );
		add_action( 'wp_ajax_mp_process_checkout', array( $this, 'sync_b2b_checkout_data' ), 1 );
		add_action( 'wp_ajax_nopriv_mp_process_checkout', array( $this, 'sync_b2b_checkout_data' ), 1 );

		if ( $this->profile_enabled() ) {
			add_action( 'show_user_profile', array( $this, 'render_user_profile' ) );
			add_action( 'edit_user_profile', array( $this, 'render_user_profile' ) );
			add_action( 'personal_options_update', array( $this, 'save_user_profile' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_profile' ) );
		}

		add_filter( 'mp_order/get_address', array( $this, 'add_b2b_address_details' ), 10, 3 );
		add_filter( 'mp_cart/tax_total', array( $this, 'apply_reverse_charge_tax' ), 20, 5 );
		add_action( 'mp_order/new_order', array( $this, 'save_order_tax_treatment' ) );
		add_filter( 'mp_pdf_invoice/order_details/after_tax_total', array( $this, 'add_reverse_charge_pdf_note' ), 10, 4 );

		if ( is_admin() ) {
			add_filter( 'manage_edit-mp_order_columns', array( $this, 'add_order_column' ) );
			add_action( 'manage_mp_order_posts_custom_column', array( $this, 'render_order_column' ), 10, 2 );
		}
	}

	public function add_checkout_fields( $fields, $type ) {
		if ( 'billing' !== $type || ! $this->checkout_enabled() ) {
			return $fields;
		}

		$billing = mp_get_user_address( 'billing' );
		$is_b2b  = ! empty( $billing['b2b_customer'] );
		$fields[] = array(
			'type'  => 'hidden',
			'name'  => 'billing[b2b_customer]',
			'value' => '0',
		);
		$checkbox_atts = array(
			'id' => 'mp-b2b-customer',
		);
		if ( $is_b2b ) {
			$checkbox_atts['checked'] = 'checked';
		}
		$fields[] = array(
			'type'  => 'checkbox',
			'label' => __( 'Als Geschäftskunde bestellen', 'mp' ),
			'name'  => 'billing[b2b_customer]',
			'value' => '1',
			'atts'  => $checkbox_atts,
		);

		foreach ( $this->selected_fields() as $key => $label ) {
			$fields[] = array(
				'type'  => 'text',
				'label' => $label,
				'name'  => 'billing[' . $key . ']',
				'value' => isset( $billing[ $key ] ) ? $billing[ $key ] : '',
				'atts'  => array(
					'class' => 'mp_form_input mp-b2b-field-input',
				),
			);
		}

		return $fields;
	}

	public function add_checkout_field_toggle( $html, $type, $value_only ) {
		if ( 'billing' !== $type || $value_only || ! $this->checkout_enabled() ) {
			return $html;
		}

		return $html . '<script>
document.addEventListener("DOMContentLoaded", function () {
	var checkbox = document.getElementById("mp-b2b-customer");
	if (!checkbox) { return; }
	var fields = document.querySelectorAll(".mp-b2b-field-input");
	var toggle = function () {
		fields.forEach(function (field) {
			var wrapper = field.closest(".mp_checkout_field");
			if (wrapper) { wrapper.style.display = checkbox.checked ? "" : "none"; }
		});
	};
	checkbox.addEventListener("change", toggle);
	toggle();
});
</script>';
	}

	public function normalize_checkout_request() {
		if ( empty( $_POST['billing'] ) || ! is_array( $_POST['billing'] ) ) {
			return;
		}

		$_POST['billing']['b2b_customer'] = ! empty( $_POST['billing']['b2b_customer'] ) ? '1' : '0';
		if ( isset( $_POST['billing']['vat_id'] ) ) {
			$_POST['billing']['vat_id'] = strtoupper( preg_replace( '/\s+/', '', (string) $_POST['billing']['vat_id'] ) );
		}
	}

	public function sync_b2b_checkout_data() {
		if ( empty( $_POST['billing'] ) || ! is_array( $_POST['billing'] ) || empty( $_POST['billing']['b2b_customer'] ) ) {
			return;
		}

		foreach ( $_POST['billing'] as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $value ) );
			if ( 'vat_id' === $key ) {
				$value = strtoupper( preg_replace( '/\s+/', '', $value ) );
			}
			mp_update_session_value( 'mp_billing_info->' . $key, $value );
		}
	}

	public function validate_checkout_data( $can_checkout, $checkout, $cart, $billing_info, $shipping_info ) {
		if ( empty( $billing_info['b2b_customer'] ) ) {
			return $can_checkout;
		}

		if ( empty( $billing_info['company_name'] ) ) {
			$checkout->add_error( __( 'Bitte gib für die B2B-Bestellung einen Firmennamen an.', 'mp' ) );
			$can_checkout = false;
		}

		foreach ( $this->required_fields() as $key => $label ) {
			if ( empty( $billing_info[ $key ] ) ) {
				$checkout->add_error( sprintf( __( 'Bitte gib %s für die B2B-Bestellung an.', 'mp' ), $label ) );
				$can_checkout = false;
			}
		}

		if ( ! empty( $billing_info['vat_id'] ) && ! $this->is_valid_vat_id( $billing_info['vat_id'] ) ) {
			$checkout->add_error( __( 'Bitte gib eine gültig formatierte USt-IdNr. an.', 'mp' ) );
			$can_checkout = false;
		}

		if ( 'reverse_charge_eu' === $this->tax_treatment() && 'inclusive' === mp_get_setting( 'tax->set_price_with_tax' ) ) {
			$checkout->add_error( __( 'Reverse Charge kann nur mit Netto-Preisen aktiviert werden. Bitte wende Dich an den Shopbetreiber.', 'mp' ) );
			$can_checkout = false;
		}

		return $can_checkout;
	}

	public function apply_reverse_charge_tax( $tax_total, $total, $cart, $country, $state ) {
		if ( $this->qualifies_for_reverse_charge( mp_get_user_address( 'billing' ) ) ) {
			return 0;
		}

		return $tax_total;
	}

	public function save_order_tax_treatment( $order ) {
		$billing = (array) $order->get_meta( 'mp_billing_info', array() );
		$treatment = $this->qualifies_for_reverse_charge( $billing ) ? 'reverse_charge' : 'standard';
		update_post_meta( $order->ID, '_mp_b2b_tax_treatment', $treatment );
	}

	public function add_reverse_charge_pdf_note( $details, $order, $cart, $type ) {
		if ( 'reverse_charge' !== $order->get_meta( '_mp_b2b_tax_treatment', '' ) ) {
			return $details;
		}

		$details[] = '<tr><td colspan="4" class="no-border">' . esc_html__( 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).', 'mp' ) . '</td></tr>';
		return $details;
	}

	public function render_user_profile( $user ) {
		$billing = get_user_meta( $user->ID, 'mp_billing_info', true );
		$billing = is_array( $billing ) ? $billing : array();
		?>
		<h2><?php esc_html_e( 'B2B-Rechnungsdaten', 'mp' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="mp-b2b-customer"><?php esc_html_e( 'Geschäftskunde', 'mp' ); ?></label></th>
				<td><label><input id="mp-b2b-customer" name="mp_b2b_customer" type="checkbox" value="1" <?php checked( ! empty( $billing['b2b_customer'] ) ); ?> /> <?php esc_html_e( 'Diesen Kunden als Geschäftskunden speichern', 'mp' ); ?></label></td>
			</tr>
			<tr>
				<th><label for="mp-b2b-company-name"><?php esc_html_e( 'Firma', 'mp' ); ?></label></th>
				<td><input id="mp-b2b-company-name" class="regular-text" name="mp_b2b_company_name" type="text" value="<?php echo esc_attr( isset( $billing['company_name'] ) ? $billing['company_name'] : '' ); ?>" /></td>
			</tr>
			<?php foreach ( $this->selected_fields() as $key => $label ) : ?>
				<tr>
					<th><label for="mp-b2b-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input id="mp-b2b-<?php echo esc_attr( $key ); ?>" class="regular-text" name="mp_b2b_<?php echo esc_attr( $key ); ?>" type="text" value="<?php echo esc_attr( isset( $billing[ $key ] ) ? $billing[ $key ] : '' ); ?>" /></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public function save_user_profile( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$billing = get_user_meta( $user_id, 'mp_billing_info', true );
		$billing = is_array( $billing ) ? $billing : array();
		$billing['b2b_customer'] = empty( $_POST['mp_b2b_customer'] ) ? '0' : '1';
		$billing['company_name'] = isset( $_POST['mp_b2b_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mp_b2b_company_name'] ) ) : '';

		foreach ( $this->selected_fields() as $key => $label ) {
			$value = isset( $_POST[ 'mp_b2b_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'mp_b2b_' . $key ] ) ) : '';
			$billing[ $key ] = 'vat_id' === $key ? strtoupper( preg_replace( '/\s+/', '', $value ) ) : $value;
		}

		update_user_meta( $user_id, 'mp_billing_info', $billing );
	}

	public function add_b2b_address_details( $html, $type, $order ) {
		if ( 'billing' !== $type || ! $order->get_meta( 'mp_billing_info->b2b_customer', false ) ) {
			return $html;
		}

		$details = array();
		foreach ( $this->selected_fields() as $key => $label ) {
			$value = $order->get_meta( 'mp_billing_info->' . $key, '' );
			if ( '' !== $value && null !== $value ) {
				$details[] = esc_html( $label ) . ': ' . esc_html( $value );
			}
		}

		return empty( $details ) ? $html : $html . implode( '<br />', $details ) . '<br />';
	}

	public function add_order_column( $columns ) {
		$columns['mp_b2b_customer'] = __( 'B2B', 'mp' );
		return $columns;
	}

	public function render_order_column( $column, $post_id ) {
		if ( 'mp_b2b_customer' !== $column ) {
			return;
		}

		$is_b2b = get_post_meta( $post_id, 'mp_billing_info', true );
		if ( is_array( $is_b2b ) && ! empty( $is_b2b['b2b_customer'] ) ) {
			echo esc_html__( 'B2B', 'mp' );
		} else {
			echo '&mdash;';
		}
	}

	public function view_settings() {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-b2b-settings-metabox',
			'title'       => __( 'B2B-Einstellungen', 'mp' ),
			'page_slugs'  => array( 'store-settings-addons' ),
			'option_name' => 'mp_settings',
		) );
		$metabox->add_field( 'checkbox', array(
			'name'          => 'b2b[checkout]',
			'label'         => array( 'text' => __( 'B2B-Felder im Checkout anzeigen', 'mp' ) ),
			'default_value' => 1,
		) );
		$metabox->add_field( 'checkbox', array(
			'name'          => 'b2b[profile]',
			'label'         => array( 'text' => __( 'B2B-Felder im Benutzerprofil anzeigen', 'mp' ) ),
			'default_value' => 1,
		) );
		$metabox->add_field( 'checkbox_group', array(
			'name'          => 'b2b[fields]',
			'label'         => array( 'text' => __( 'Zusätzliche B2B-Felder', 'mp' ) ),
			'options'       => $this->available_fields(),
			'default_value' => 1,
		) );
		$metabox->add_field( 'checkbox_group', array(
			'name'    => 'b2b[required_fields]',
			'label'   => array( 'text' => __( 'Pflichtfelder für B2B-Bestellungen', 'mp' ) ),
			'message' => __( 'Der Firmenname ist für B2B-Bestellungen immer erforderlich.', 'mp' ),
			'options' => $this->available_fields(),
		) );
		$metabox->add_field( 'select', array(
			'name'    => 'b2b[tax_treatment]',
			'label'   => array( 'text' => __( 'Steuerbehandlung für B2B', 'mp' ) ),
			'options' => array(
				'standard'          => __( 'Bestehende Steuerberechnung unverändert verwenden', 'mp' ),
				'reverse_charge_eu' => __( 'Reverse Charge für qualifizierte EU-B2B-Bestellungen', 'mp' ),
			),
			'message' => __( 'Reverse Charge setzt Netto-Preise, eine gültige USt-IdNr. und eine Rechnungsadresse in einem anderen EU-Land voraus.', 'mp' ),
		) );

	}

	private function checkout_enabled() {
		return (bool) mp_get_setting( 'b2b->checkout', true );
	}

	private function profile_enabled() {
		return (bool) mp_get_setting( 'b2b->profile', true );
	}

	private function selected_fields() {
		$fields = mp_get_setting( 'b2b->fields', null );
		if ( null === $fields ) {
			$selected = $this->available_fields();
		} else {
			$selected = array_intersect_key( $this->available_fields(), array_flip( $this->setting_field_keys( $fields ) ) );
		}

		return $selected;
	}

	private function required_fields() {
		$fields = mp_get_setting( 'b2b->required_fields', array() );
		$required = array_intersect_key( $this->selected_fields(), array_flip( $this->setting_field_keys( $fields ) ) );
		return $required;
	}

	private function available_fields() {
		return apply_filters( 'mp_b2b/available_fields', array(
			'vat_id'          => __( 'USt-IdNr.', 'mp' ),
			'tax_number'      => __( 'Steuernummer', 'mp' ),
		) );
	}

	private function tax_treatment() {
		return mp_get_setting( 'b2b->tax_treatment', 'standard' );
	}

	private function qualifies_for_reverse_charge( $billing ) {
		if ( 'reverse_charge_eu' !== $this->tax_treatment() || 'inclusive' === mp_get_setting( 'tax->set_price_with_tax' ) ) {
			return false;
		}

		$country = isset( $billing['country'] ) ? strtoupper( $billing['country'] ) : '';
		$base_country = strtoupper( mp_get_setting( 'base_country', '' ) );
		$eu_countries = isset( mp()->eu_countries ) ? array_map( 'strtoupper', (array) mp()->eu_countries ) : array();

		return ! empty( $billing['b2b_customer'] )
			&& ! empty( $billing['vat_id'] )
			&& $this->is_valid_vat_id( $billing['vat_id'] )
			&& $country
			&& $country !== $base_country
			&& in_array( $country, $eu_countries, true )
			&& in_array( $base_country, $eu_countries, true );
	}

	private function is_valid_vat_id( $vat_id ) {
		return 1 === preg_match( '/^[A-Z]{2}[A-Z0-9]{2,14}$/', strtoupper( (string) $vat_id ) );
	}

	private function setting_field_keys( $fields ) {
		$keys = array();
		foreach ( (array) $fields as $key => $value ) {
			if ( is_int( $key ) ) {
				$keys[] = $value;
			} elseif ( ! empty( $value ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}
}

MP_B2B_Addon::get_instance();
