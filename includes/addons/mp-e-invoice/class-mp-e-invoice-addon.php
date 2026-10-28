<?php

require_once dirname( __DIR__, 2 ) . '/admin/class-mp-orders-admin.php';

class MP_E_Invoice_Addon {

	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		if ( 'MP_E_Invoice_Addon' === mp_get_get_value( 'addon', null ) ) {
			$this->view_settings();
		}

		add_action( 'wp_ajax_mp_e_invoice_download', array( $this, 'download_invoice' ) );
		add_filter( 'mp_order/details', array( $this, 'add_customer_download_button' ), 99, 2 );
		add_filter( 'mp_order/sendmail_attachments', array( $this, 'add_email_attachment' ), 20, 3 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes_mp_order', array( $this, 'add_order_meta_box' ) );
		}
	}

	public function add_order_meta_box() {
		if ( $this->enabled() ) {
			add_meta_box( 'mp-e-invoice', __( 'B2B-Rechnungs-XML', 'mp' ), array( $this, 'render_order_meta_box' ), 'mp_order', 'side', 'high' );
		}
	}

	public function render_order_meta_box( $post ) {
		$order = new MP_Order( $post->ID );
		if ( ! $order->exists() || ! $this->is_b2b_order( $order ) ) {
			echo esc_html__( 'Nur für B2B-Bestellungen verfügbar.', 'mp' );
			return;
		}

		echo '<p>' . esc_html__( 'Erzeugt eine einfache strukturierte B2B-Rechnung zusätzlich zur PDF-Rechnung. Dieses XML ist kein Behördenformat.', 'mp' ) . '</p>';
		echo '<a class="button" href="' . esc_url( $this->download_url( $order ) ) . '">' . esc_html__( 'B2B-Rechnungs-XML herunterladen', 'mp' ) . '</a>';
	}

	public function add_customer_download_button( $html, $order ) {
		if ( ! $this->enabled() || ! $this->customer_download_enabled() || ! $this->is_b2b_order( $order ) || ! $this->current_user_can_download( $order ) ) {
			return $html;
		}

		return $html . '<section class="mp_orders_invoice"><a class="mp_button" href="' . esc_url( $this->download_url( $order ) ) . '">' . esc_html__( 'B2B-Rechnungs-XML herunterladen', 'mp' ) . '</a></section>';
	}

	public function download_invoice() {
		$order = new MP_Order( absint( mp_get_get_value( 'order_id', 0 ) ) );
		if ( ! $this->enabled() || ! $order->exists() || ! $this->is_b2b_order( $order ) || ! wp_verify_nonce( mp_get_get_value( 'wpnonce' ), 'mp_e_invoice_download_' . $order->ID ) || ! $this->current_user_can_download( $order ) ) {
			wp_die( esc_html__( 'Ungültige Anfrage.', 'mp' ), '', array( 'response' => 403 ) );
		}

		$xml = $this->build_invoice_xml( $order );
		if ( is_wp_error( $xml ) ) {
			wp_die( esc_html( $xml->get_error_message() ), esc_html__( 'B2B-Rechnungs-XML konnte nicht erstellt werden.', 'mp' ), array( 'response' => 422 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="b2b-rechnung_' . rawurlencode( $this->invoice_number( $order ) ) . '.xml"' );
		header( 'Content-Length: ' . strlen( $xml ) );
		echo $xml;
		exit;
	}

	public function add_email_attachment( $attachments, $order, $context ) {
		if ( ! $this->enabled() || ! $this->is_b2b_order( $order ) || ! $this->attachment_enabled( $context ) ) {
			return $attachments;
		}

		$xml = $this->build_invoice_xml( $order );
		if ( is_wp_error( $xml ) ) {
			error_log( 'MarketPress B2B-Rechnungs-XML: ' . $xml->get_error_message() );
			return $attachments;
		}

		$file = wp_tempnam( 'b2b-rechnung-' . $this->invoice_number( $order ) . '.xml' );
		if ( ! $file || false === file_put_contents( $file, $xml ) ) {
			error_log( 'MarketPress B2B-Rechnungs-XML: Die XML-Datei für den E-Mail-Anhang konnte nicht erstellt werden.' );
			return $attachments;
		}

		register_shutdown_function( 'unlink', $file );
		$attachments[] = $file;
		return $attachments;
	}

	private function build_invoice_xml( $order ) {
		$billing = (array) $order->get_meta( 'mp_billing_info', array() );
		$seller  = $this->seller_details();
		$missing_seller = array();
		foreach ( array( 'company_name', 'street', 'postal_code', 'city', 'country' ) as $key ) {
			if ( empty( $seller[ $key ] ) ) {
				$missing_seller[] = $key;
			}
		}
		if ( empty( $seller['vat_id'] ) && empty( $seller['tax_number'] ) ) {
			$missing_seller[] = 'tax_id';
		}
		if ( $missing_seller ) {
			return new WP_Error( 'mp_e_invoice_seller_data', __( 'Für die B2B-Rechnungs-XML fehlen vollständige Verkäuferdaten oder eine Steuerkennung in den Add-on-Einstellungen.', 'mp' ) );
		}
		foreach ( array( 'company_name', 'address1', 'zip', 'city', 'country' ) as $key ) {
			if ( empty( $billing[ $key ] ) ) {
				return new WP_Error( 'mp_e_invoice_buyer_data', __( 'Die B2B-Rechnungs-XML benötigt vollständige Käuferdaten aus der Bestellung.', 'mp' ) );
			}
		}

		$amounts = $this->amounts_from_snapshot( $order );
		if ( is_wp_error( $amounts ) ) {
			return $amounts;
		}

		$e        = array( $this, 'xml' );
		$currency = (string) $order->get_meta( 'mp_payment_info->currency', 'EUR' );
		$currency = $currency ? $currency : 'EUR';
		$date     = get_post_field( 'post_date', $order->ID );
		$date     = $date ? substr( $date, 0, 10 ) : gmdate( 'Y-m-d' );
		$lines    = '';
		foreach ( $amounts['lines'] as $line ) {
			$lines .= '<Line type="' . $e( $line['type'] ) . '"><Name>' . $e( $line['name'] ) . '</Name><Quantity>' . $e( $line['quantity'] ) . '</Quantity><UnitNetAmount>' . $e( $line['unit_net'] ) . '</UnitNetAmount><NetAmount>' . $e( $line['net'] ) . '</NetAmount></Line>';
		}

		$buyer = array(
			'company_name' => $billing['company_name'],
			'street'       => $billing['address1'],
			'postal_code'  => $billing['zip'],
			'city'         => $billing['city'],
			'country'      => $billing['country'],
			'vat_id'       => isset( $billing['vat_id'] ) ? $billing['vat_id'] : '',
			'tax_number'   => isset( $billing['tax_number'] ) ? $billing['tax_number'] : '',
			'email'        => isset( $billing['email'] ) ? $billing['email'] : '',
		);

		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<B2BInvoice version="1.0" currency="' . $e( $currency ) . '">'
			. '<InvoiceNumber>' . $e( $this->invoice_number( $order ) ) . '</InvoiceNumber><IssueDate>' . $e( $date ) . '</IssueDate>'
			. $this->party_xml( 'Seller', $seller ) . $this->party_xml( 'Buyer', $buyer )
			. '<Lines>' . $lines . '</Lines><Totals><NetAmount>' . $e( $amounts['net'] ) . '</NetAmount><TaxAmount>' . $e( $amounts['tax'] ) . '</TaxAmount><GrossAmount>' . $e( $amounts['gross'] ) . '</GrossAmount></Totals>'
			. ( 'reverse_charge' === $order->get_meta( '_mp_b2b_tax_treatment', '' ) ? '<TaxNote>' . $e( __( 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).', 'mp' ) ) . '</TaxNote>' : '' )
			. '</B2BInvoice>';
	}

	private function party_xml( $role, $party ) {
		$e = array( $this, 'xml' );
		$tax_id = ! empty( $party['vat_id'] ) ? $party['vat_id'] : ( ! empty( $party['tax_number'] ) ? $party['tax_number'] : '' );
		return '<' . $role . '><CompanyName>' . $e( $party['company_name'] ) . '</CompanyName><Address><Street>' . $e( $party['street'] ) . '</Street><PostalCode>' . $e( $party['postal_code'] ) . '</PostalCode><City>' . $e( $party['city'] ) . '</City><CountryCode>' . $e( strtoupper( $party['country'] ) ) . '</CountryCode></Address>'
			. ( $tax_id ? '<TaxIdentifier>' . $e( $tax_id ) . '</TaxIdentifier>' : '' )
			. ( ! empty( $party['email'] ) ? '<Email>' . $e( $party['email'] ) . '</Email>' : '' )
			. '</' . $role . '>';
	}

	private function amounts_from_snapshot( $order ) {
		$gross = round( (float) $order->get_meta( 'mp_order_total', 0 ), 2 );
		$tax   = round( (float) $order->get_meta( 'mp_tax_total', 0 ), 2 );
		$net   = round( $gross - $tax, 2 );
		if ( $gross < 0 || $tax < 0 || $net < 0 ) {
			return new WP_Error( 'mp_e_invoice_snapshot', __( 'Die gespeicherten Bestellsummen können nicht als B2B-Rechnungs-XML abgebildet werden.', 'mp' ) );
		}

		$source_lines = array();
		foreach ( (array) $order->get_meta( 'mp_cart_items', array() ) as $group ) {
			foreach ( (array) $group as $item ) {
				if ( empty( $item['name'] ) || ! isset( $item['price'], $item['quantity'] ) || (float) $item['quantity'] <= 0 ) {
					return new WP_Error( 'mp_e_invoice_snapshot', __( 'Die gespeicherten Bestellpositionen sind für die B2B-Rechnungs-XML unvollständig.', 'mp' ) );
				}
				$source_lines[] = array( 'type' => 'product', 'name' => $item['name'], 'quantity' => (float) $item['quantity'], 'amount' => (float) $item['price'] * (float) $item['quantity'] );
			}
		}
		$shipping = (float) $order->get_meta( 'mp_shipping_total', 0 );
		if ( 0.0 !== $shipping ) {
			$source_lines[] = array( 'type' => 'shipping', 'name' => __( 'Versandkosten', 'mp' ), 'quantity' => 1, 'amount' => $shipping );
		}
		foreach ( (array) $order->get_meta( 'mp_discount_info', array() ) as $code => $discount ) {
			if ( 0.0 !== (float) $discount ) {
				$source_lines[] = array( 'type' => 'discount', 'name' => sprintf( __( 'Rabatt: %s', 'mp' ), $code ), 'quantity' => 1, 'amount' => (float) $discount );
			}
		}
		if ( empty( $source_lines ) ) {
			return new WP_Error( 'mp_e_invoice_snapshot', __( 'Die Bestellung enthält keine exportierbaren Positionen.', 'mp' ) );
		}

		$source_total = array_sum( wp_list_pluck( $source_lines, 'amount' ) );
		if ( abs( $source_total ) < 0.00001 ) {
			return new WP_Error( 'mp_e_invoice_snapshot', __( 'Die gespeicherten Bestellpositionen ergeben keinen exportierbaren Rechnungsbetrag.', 'mp' ) );
		}

		$factor = $net / $source_total;
		$lines = array();
		$remaining_cents = (int) round( $net * 100 );
		foreach ( $source_lines as $index => $line ) {
			$cents = count( $source_lines ) - 1 === $index ? $remaining_cents : (int) round( $line['amount'] * $factor * 100 );
			$remaining_cents -= $cents;
			$line_net = $cents / 100;
			$lines[] = array(
				'type'     => $line['type'],
				'name'     => $line['name'],
				'quantity' => $this->quantity( $line['quantity'] ),
				'unit_net' => $this->decimal( $line_net / $line['quantity'], 4 ),
				'net'      => $this->amount( $line_net ),
			);
		}

		return array( 'lines' => $lines, 'net' => $this->amount( $net ), 'tax' => $this->amount( $tax ), 'gross' => $this->amount( $gross ) );
	}

	private function seller_details() {
		$legal = (array) mp_get_setting( 'legal', array() );
		return array(
			'company_name' => mp_get_setting( 'e_invoice->seller_company_name', isset( $legal['company_name'] ) ? $legal['company_name'] : '' ),
			'street'       => mp_get_setting( 'e_invoice->seller_street', '' ),
			'postal_code'  => mp_get_setting( 'e_invoice->seller_postal_code', mp_get_setting( 'base_zip', '' ) ),
			'city'         => mp_get_setting( 'e_invoice->seller_city', '' ),
			'country'      => mp_get_setting( 'e_invoice->seller_country', mp_get_setting( 'base_country', '' ) ),
			'vat_id'       => mp_get_setting( 'e_invoice->seller_vat_id', isset( $legal['vat_id'] ) ? $legal['vat_id'] : '' ),
			'tax_number'   => mp_get_setting( 'e_invoice->seller_tax_number', isset( $legal['tax_number'] ) ? $legal['tax_number'] : '' ),
		);
	}

	private function download_url( $order ) {
		return add_query_arg( array( 'action' => 'mp_e_invoice_download', 'order_id' => $order->ID, 'wpnonce' => wp_create_nonce( 'mp_e_invoice_download_' . $order->ID ) ), admin_url( 'admin-ajax.php' ) );
	}

	private function current_user_can_download( $order ) {
		return current_user_can( 'manage_options' ) || current_user_can( apply_filters( 'mp_store_settings_cap', 'read_store_order' ) ) || ( get_current_user_id() && (int) $order->post_author === get_current_user_id() );
	}

	private function attachment_enabled( $context ) {
		$key = array( 'new_order_client' => 'customer_new_order', 'new_order_admin' => 'admin_new_order', 'order_shipped_client' => 'customer_shipped_order' );
		return isset( $key[ $context ] ) && (bool) mp_get_setting( 'e_invoice->attach_to->' . $key[ $context ], false );
	}

	private function customer_download_enabled() {
		return (bool) mp_get_setting( 'e_invoice->customer_download', false );
	}

	private function invoice_number( $order ) {
		return MP_Orders_Admin::get_instance()->get_or_create_invoice_number( $order->ID );
	}

	private function is_b2b_order( $order ) {
		return (bool) $order->get_meta( 'mp_billing_info->b2b_customer', false );
	}

	private function enabled() {
		return (bool) mp_get_setting( 'e_invoice->enabled', false );
	}

	private function amount( $amount ) {
		return $this->decimal( $amount, 2 );
	}

	private function decimal( $amount, $precision ) {
		return number_format( (float) $amount, $precision, '.', '' );
	}

	private function quantity( $quantity ) {
		return rtrim( rtrim( number_format( (float) $quantity, 4, '.', '' ), '0' ), '.' );
	}

	private function xml( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}

	public function view_settings() {
		$metabox = new PSOURCE_Metabox( array( 'id' => 'mp-e-invoice-settings-metabox', 'title' => __( 'B2B-Rechnungs-XML', 'mp' ), 'page_slugs' => array( 'store-settings-addons' ), 'option_name' => 'mp_settings' ) );
		$metabox->add_field( 'checkbox', array( 'name' => 'e_invoice[enabled]', 'label' => array( 'text' => __( 'B2B-Rechnungs-XML aktivieren', 'mp' ) ), 'message' => __( 'Erzeugt zusätzlich zur PDF-Rechnung eine strukturierte XML-Datei für Geschäftskunden. Es ist kein Behördenformat.', 'mp' ) ) );
		$metabox->add_field( 'checkbox', array( 'name' => 'e_invoice[customer_download]', 'label' => array( 'text' => __( 'Kunden-Download im Bestellbereich anbieten', 'mp' ) ) ) );
		$metabox->add_field( 'checkbox_group', array( 'name' => 'e_invoice[attach_to]', 'label' => array( 'text' => __( 'XML an E-Mails anhängen', 'mp' ) ), 'options' => array( 'customer_new_order' => __( 'Neue Bestellung an Kunden', 'mp' ), 'admin_new_order' => __( 'Neue Bestellung an Shop-Admin', 'mp' ), 'customer_shipped_order' => __( 'Versandbestätigung an Kunden', 'mp' ) ) ) );
		foreach ( array(
			'seller_company_name' => __( 'Verkäufer: Firmenname', 'mp' ),
			'seller_street'       => __( 'Verkäufer: Straße und Hausnummer', 'mp' ),
			'seller_postal_code'  => __( 'Verkäufer: Postleitzahl', 'mp' ),
			'seller_city'         => __( 'Verkäufer: Ort', 'mp' ),
			'seller_country'      => __( 'Verkäufer: Ländercode (z. B. DE)', 'mp' ),
			'seller_vat_id'       => __( 'Verkäufer: USt-IdNr.', 'mp' ),
			'seller_tax_number'   => __( 'Verkäufer: Steuernummer', 'mp' ),
		) as $key => $label ) {
			$metabox->add_field( 'text', array( 'name' => 'e_invoice[' . $key . ']', 'label' => array( 'text' => $label ) ) );
		}
	}
}

MP_E_Invoice_Addon::get_instance();
