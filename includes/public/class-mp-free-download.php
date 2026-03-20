<?php

/**
 * Free Download Handler
 * Direkter Download für kostenlose digitale Produkte ohne Bestellung
 */
class MP_Free_Download {
	
	/**
	 * Singleton instance
	 */
	private static $_instance = null;
	
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	
	private function __construct() {
		// Hook wird über class-mp-public.php registriert
	}
	
	/**
	 * Handle free download requests
	 * 
	 * @action wp
	 */
	public function handle_free_download() {
		$action = mp_get_get_value( 'mp_action' );
		
		if ( $action !== 'free_download' ) {
			return false;
		}

		$return_url = $this->get_return_url();
		
		$product_id = absint( mp_get_get_value( 'product_id' ) );
		if ( empty( $product_id ) ) {
			$this->redirect_with_status( $return_url, 0, 'error', __( 'Fehler: Ungültige Produkt-ID.', 'mp' ) );
		}
		
		$product = new MP_Product( $product_id );
		if ( empty( $return_url ) ) {
			$return_url = $product->url( false );
		}
		
		// Prüfe ob Produkt existiert und digital ist
		if ( ! $product->exists() || ! $product->is_download() ) {
			$this->redirect_with_status( $return_url, $product_id, 'error', __( 'Fehler: Das Produkt existiert nicht oder ist kein Download.', 'mp' ) );
		}
		
		// Prüfe ob kostenlos
		$price = (float) $product->get_price( 'lowest' );
		if ( $price > 0 ) {
			$this->redirect_with_status( $return_url, $product_id, 'error', __( 'Fehler: Dieses Produkt ist nicht kostenlos.', 'mp' ) );
		}
		
		// Hole Download-URL (kann durch Multi-File Addon auch ein Array sein)
		$file_url = $this->resolve_download_url( $product->get_meta( 'file_url' ) );
		if ( empty( $file_url ) ) {
			$this->redirect_with_status( $return_url, $product_id, 'error', __( 'Fehler: Datei nicht gefunden.', 'mp' ) );
		}
		
		// Externe URL oder Alternative Download-Methode: Einfach weiterleiten
		if ( wp_http_validate_url( $file_url ) || mp_get_setting( 'use_alt_download_method' ) || ( defined( 'MP_LARGE_DOWNLOADS' ) && MP_LARGE_DOWNLOADS === true ) ) {
			wp_redirect( esc_url_raw( $file_url ) );
			exit;
		}
		
		// Lokale Datei: Direkt serven mit Download-Headers
		$this->serve_file( $file_url, basename( $file_url ) );
		exit;
	}

	/**
	 * Resolve the effective download URL from product meta.
	 * Supports single-file string values and multi-file arrays.
	 *
	 * @param mixed $file_meta Product file meta value.
	 * @return string|false
	 */
	private function resolve_download_url( $file_meta ) {
		if ( is_string( $file_meta ) ) {
			$file_meta = trim( $file_meta );

			return '' === $file_meta ? false : $file_meta;
		}

		if ( is_array( $file_meta ) ) {
			$current_file = (int) mp_get_get_value( 'numb' );

			if ( $current_file > 0 && isset( $file_meta[ $current_file - 1 ] ) && is_string( $file_meta[ $current_file - 1 ] ) ) {
				$selected = trim( $file_meta[ $current_file - 1 ] );
				if ( '' !== $selected ) {
					return $selected;
				}
			}

			foreach ( $file_meta as $candidate ) {
				if ( is_string( $candidate ) ) {
					$candidate = trim( $candidate );
					if ( '' !== $candidate ) {
						return $candidate;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get and validate optional return URL.
	 *
	 * @return string
	 */
	private function get_return_url() {
		$return_url = mp_get_get_value( 'mp_return' );
		if ( ! is_string( $return_url ) || '' === $return_url ) {
			return '';
		}

		$return_url = rawurldecode( $return_url );
		$validated  = wp_validate_redirect( $return_url, '' );

		return is_string( $validated ) ? $validated : '';
	}

	/**
	 * Redirect user back with inline status query args.
	 *
	 * @param string $return_url Product return URL.
	 * @param int    $product_id Current product id.
	 * @param string $status     success|error
	 * @param string $message    Human-readable status message.
	 */
	private function redirect_with_status( $return_url, $product_id, $status, $message ) {
		if ( ! is_string( $return_url ) || '' === $return_url ) {
			wp_die( esc_html( $message ) );
		}

		$redirect_url = add_query_arg(
			array(
				'mp_free_download_product' => absint( $product_id ),
				'mp_free_download_status'  => sanitize_key( $status ),
				'mp_free_download_msg'     => rawurlencode( $message ),
			),
			$return_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
	
	/**
	 * Serve a file for download
	 */
	private function serve_file( $file_path, $filename ) {
		if ( wp_http_validate_url( $file_path ) ) {
			$dirs     = wp_upload_dir();
			$location = str_replace( $dirs['baseurl'], $dirs['basedir'], $file_path );

			if ( $location !== $file_path ) {
				$file_path = $location;
			}
		}

		if ( ! file_exists( $file_path ) ) {
			$this->redirect_with_status( $this->get_return_url(), absint( mp_get_get_value( 'product_id' ) ), 'error', __( 'Fehler: Datei nicht gefunden.', 'mp' ) );
		}
		
		set_time_limit( 0 );
		
		// Set download headers
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( str_replace( '"', '', $filename ) ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		// Serve the file
		readfile( $file_path );
	}
}

