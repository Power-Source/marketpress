<?php
/**
 * MarketPress Gateway: PayPal Commerce Platform (Marketplace)
 *
 * Modernes Gateway für Split Payments & Seller-Onboarding
 *
 * @author AI
 * @since 2025
 */

class MP_Gateway_PayPal_Marketplace extends MP_Gateway_API {

    public $admin_name = 'PayPal Marketplace (Commerce Platform)';
    public $public_name = 'PayPal';
    public $method_img_url = '';
    public $method_button_img_url = '';
    public $force_ssl = true;
    public $skip_form = false;
    public $build = 1;
    public $plugin_name = 'paypal_marketplace';

    public function on_creation() {
        //set names hier, damit sie immer korrekt sind
        if ( function_exists('is_super_admin') && is_super_admin() ) {
            $this->admin_name = __( 'PayPal Marketplace (Commerce Platform)', 'mp' );
        } else {
            $this->admin_name = __( 'PayPal', 'mp' );
        }
        $this->public_name = __( 'PayPal', 'mp' );
        $this->method_img_url        = mp_plugin_url('includes/common/payment-gateways/paypal-marketplace/paypal-marketplace.png');
        $this->method_button_img_url = $this->method_img_url;
        $this->force_ssl = true;
        $this->skip_form = false;
    }

    public function __construct() {
        parent::__construct();
        $this->on_creation();
    }

    public function admin_settings( $settings ) {
        $settings[ 'client_id' ] = array(
            'label'       => __( 'PayPal Client-ID', 'mp' ),
            'type'        => 'text',
            'description' => __( 'Deine PayPal REST-API Client-ID (Live oder Sandbox)', 'mp' ),
            'value'       => $this->get_setting( 'client_id', '' ),
        );
        $settings[ 'secret' ] = array(
            'label'       => __( 'PayPal Secret', 'mp' ),
            'type'        => 'password',
            'description' => __( 'Dein PayPal REST-API Secret (Live oder Sandbox)', 'mp' ),
            'value'       => $this->get_setting( 'secret', '' ),
        );
        $settings[ 'webhook_url' ] = array(
            'label'       => __( 'Webhook-URL', 'mp' ),
            'type'        => 'text',
            'custom_html' => '<code>' . esc_html( $this->get_webhook_url() ) . '</code>',
            'description' => __( 'Diese URL in deinem PayPal Developer Dashboard als Webhook eintragen.', 'mp' ),
            'value'       => $this->get_webhook_url(),
            'readonly'    => true,
        );
        // Onboarding-Status und Button direkt in den Gateway-Einstellungen anzeigen
        $merchant_id = get_option( 'mp_paypal_marketplace_merchant_id_' . get_current_blog_id() );
        if ( $merchant_id ) {
            $settings['onboarding_status'] = array(
                'label' => __( 'PayPal-Onboarding', 'mp' ),
                'type'  => 'custom',
                'custom_html' => '<p style="color:green;"><strong>' . __( 'PayPal-Konto verbunden!', 'mp' ) . '</strong></p>'
            );
        } else {
            $onboard_url = esc_url( add_query_arg( array( 'mp_paypal_onboard' => 1 ), admin_url() ) );
            $settings['onboarding_status'] = array(
                'label' => __( 'PayPal-Onboarding', 'mp' ),
                'type'  => 'custom',
                'custom_html' => '<a href="' . $onboard_url . '" class="button button-primary">' . __( 'PayPal-Konto verbinden', 'mp' ) . '</a>'
            );
        }
        return $settings;
    }

    /**
     * Liefert die Webhook-URL für PayPal
     */
    public function get_webhook_url() {
        return home_url( '/?mp_paypal_marketplace_webhook=1' );
    }

    /**
     * Logging-Helfer für Gateway-Fehler und wichtige Events
     */
    protected function log($msg) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[MP PayPal Marketplace] ' . $msg);
        }
    }

    /**
     * Get the PayPal API base URL.
     *
     * @return string
     */
    protected function get_api_base_url() {
        $sandbox = (bool) mp_get_network_setting(
            'advanced->paypal_marketplace_sandbox',
            true
        );

        return $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Get the PayPal Partner Client ID.
     *
     * @return string
     */
    protected function get_partner_client_id() {
        return (string) mp_get_network_setting(
            'paypal_marketplace_client_id',
            ''
        );
    }

    /**
     * Get the PayPal Partner Secret.
     *
     * @return string
     */
    protected function get_partner_secret() {
        return (string) mp_get_network_setting(
            'paypal_marketplace_secret',
            ''
        );
    }

        /**
         * Get a PayPal access token.
         *
         * The token is cached until shortly before its expiry.
         *
         * @return string|WP_Error
         */
        protected function get_paypal_access_token() {

            $client_id = $this->get_partner_client_id();
            $secret    = $this->get_partner_secret();

            if ( '' === $client_id || '' === $secret ) {
                return new WP_Error(
                    'paypal_credentials_missing',
                    __( 'PayPal Partner-Zugangsdaten fehlen.', 'mp' )
                );
            }

            $cache_key = 'paypal_marketplace_access_token_' . md5(
                $client_id . '|' . $this->get_api_base_url()
            );

            $cached = wp_cache_get( $cache_key, 'marketpress' );

            if (
                is_array( $cached ) &&
                ! empty( $cached['token'] ) &&
                ! empty( $cached['expires'] ) &&
                time() < (int) $cached['expires']
            ) {
                return $cached['token'];
            }

            $response = wp_remote_post(
                $this->get_api_base_url() . '/v1/oauth2/token',
                array(
                    'headers' => array(
                        'Accept'          => 'application/json',
                        'Accept-Language' => 'en_US',
                        'Authorization'   => 'Basic ' . base64_encode(
                            $client_id . ':' . $secret
                        ),
                        'Content-Type'    => 'application/x-www-form-urlencoded',
                    ),
                    'body' => array(
                        'grant_type' => 'client_credentials',
                    ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                $this->log(
                    'PayPal OAuth HTTP-Fehler: ' .
                    $response->get_error_message()
                );

                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if (
                200 !== $code ||
                empty( $data['access_token'] )
            ) {
                $message = ! empty( $data['error_description'] )
                    ? sanitize_text_field( $data['error_description'] )
                    : __(
                        'PayPal Authentifizierung fehlgeschlagen.',
                        'mp'
                    );

                $this->log(
                    'Access Token Fehler (' .
                    $code .
                    '): ' .
                    $body
                );

                return new WP_Error(
                    'paypal_auth',
                    $message
                );
            }

            $expires_in = ! empty( $data['expires_in'] )
                ? absint( $data['expires_in'] )
                : HOUR_IN_SECONDS;

            /*
            * Token 60 Sekunden vor Ablauf aus dem Cache entfernen.
            */
            $cache_expires = max(
                60,
                $expires_in - 60
            );

            wp_cache_set(
                $cache_key,
                array(
                    'token'   => $data['access_token'],
                    'expires' => time() + $cache_expires,
                ),
                'marketpress',
                $cache_expires
            );

            return $data['access_token'];
        }

    /**
     * Create a PayPal Partner Referral for a MarketPress shop.
     *
     * @param int $blog_id Blog ID.
     * @return string|WP_Error
     */
    protected function create_partner_referral( $blog_id ) {

        $access_token = $this->get_paypal_access_token();

        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $tracking_id = 'marketpress-blog-' . absint( $blog_id );

        $return_url = add_query_arg(
            array(
                'mp_paypal_onboard' => 1,
            ),
            network_admin_url()
        );

        $payload = array(
            'tracking_id' => $tracking_id,

            'partner_config_override' => array(
                'return_url'             => $return_url,
                'return_url_description' => __( 'MarketPress PayPal-Onboarding', 'mp' ),
            ),

            'operations' => array(
                array(
                    'operation' => 'API_INTEGRATION',
                    'api_integration_preference' => array(
                        'rest_api_integration' => array(
                            'integration_method' => 'PAYPAL',
                            'integration_type'   => 'THIRD_PARTY',
                            'third_party_details' => array(
                                'features' => array(
                                    'PAYMENT',
                                    'REFUND',
                                ),
                            ),
                        ),
                    ),
                ),
            ),

            'products' => array(
                'EXPRESS_CHECKOUT',
            ),

            'legal_consents' => array(
                array(
                    'type'    => 'SHARE_DATA_CONSENT',
                    'granted' => true,
                ),
            ),
        );

        $response = wp_remote_post(
            $this->get_api_base_url() . '/v2/customer/partner-referrals',
            array(
                'headers' => array(
                    'Content-Type'                  => 'application/json',
                    'Authorization'                 => 'Bearer ' . $access_token,
                    'PayPal-Partner-Attribution-Id' => mp_get_network_setting(
                        'paypal_marketplace_bn_code',
                        ''
                    ),
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 201 !== $code ) {
            $this->log(
                'Partner Referral Fehler (' . $code . '): ' . $body
            );

            return new WP_Error(
                'paypal_partner_referral',
                __( 'PayPal Partner-Onboarding konnte nicht gestartet werden.', 'mp' )
            );
        }

        foreach ( (array) mp_arr_get_value( 'links', $data ) as $link ) {
            if (
                isset( $link['rel'], $link['href'] )
                && 'action_url' === $link['rel']
            ) {
                return esc_url_raw( $link['href'] );
            }
        }

        return new WP_Error(
            'paypal_partner_referral_url',
            __( 'PayPal hat keine Onboarding-URL zurückgegeben.', 'mp' )
        );
    }

    /**
     * Startet bzw. verarbeitet das PayPal Partner-Onboarding.
     *
     * @return void
     */
    public static function maybe_handle_onboarding() {

        if ( ! isset( $_GET['mp_paypal_onboard'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'Keine Berechtigung.', 'mp' )
            );
        }

        $gateway = new self();

        /*
        * Schritt 1:
        * PayPal-Rückgabe fehlt → Partner Referral erzeugen.
        */
        if ( ! isset( $_GET['merchantIdInPayPal'] ) ) {

            $blog_id = get_current_blog_id();

            $onboard_url = $gateway->create_partner_referral( $blog_id );

            if ( is_wp_error( $onboard_url ) ) {

                $gateway->log(
                    'Onboarding-Fehler: ' .
                    $onboard_url->get_error_message()
                );

                wp_die(
                    esc_html( $onboard_url->get_error_message() )
                );
            }

            wp_safe_redirect( $onboard_url );
            exit;
        }

        /*
        * Schritt 2:
        * PayPal gibt Merchant-ID und Tracking-ID zurück.
        */
        $merchant_id = sanitize_text_field(
            wp_unslash( $_GET['merchantIdInPayPal'] )
        );

        $tracking_id = isset( $_GET['tracking_id'] )
            ? sanitize_text_field( wp_unslash( $_GET['tracking_id'] ) )
            : '';

        if ( '' === $merchant_id ) {

            wp_die(
                esc_html__(
                    'PayPal hat keine Merchant-ID zurückgegeben.',
                    'mp'
                )
            );
        }

        /*
        * Die Tracking-ID ist unsere Zuordnung zum Shop.
        *
        * Erwartetes Format:
        * marketpress-blog-{BLOG_ID}
        */
        if (
            ! preg_match(
                '/^marketpress-blog-(\d+)$/',
                $tracking_id,
                $matches
            )
        ) {

            $gateway->log(
                'Ungültige PayPal-Tracking-ID: ' .
                $tracking_id
            );

            wp_die(
                esc_html__(
                    'Die PayPal-Onboarding-Antwort konnte keinem Shop zugeordnet werden.',
                    'mp'
                )
            );
        }

        $blog_id = absint( $matches[1] );

        if ( $blog_id < 1 ) {

            wp_die(
                esc_html__(
                    'Ungültige Shop-ID beim PayPal-Onboarding.',
                    'mp'
                )
            );
        }

        /*
        * Merchant-ID dauerhaft für den Shop speichern.
        */
        switch_to_blog( $blog_id );
        update_option(
            'mp_paypal_marketplace_merchant_id_' . $blog_id,
            $merchant_id
        );
        restore_current_blog();

        $gateway->log(
            'PayPal-Onboarding erfolgreich. Blog ' .
            $blog_id .
            ', Merchant-ID ' .
            $merchant_id
        );

        /*
        * Zur PayPal-Auszahlungsseite des Shops zurück.
        */
        switch_to_blog( $blog_id );

        $redirect_url = admin_url(
            'options-general.php?page=paypal-marketplace-payout&onboard=1'
        );

        restore_current_blog();

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Liest die Marketplace-Provision aus den Network Settings.
     *
     * @return float Provision als Dezimalwert, z. B. 0.02 für 2 %.
     */
    protected function get_network_provision() {
        $provision = mp_get_network_setting(
            'provision',
            0
        );

        $provision = (float) str_replace( ',', '.', $provision );

        return max( 0, min( 1, $provision / 100 ) );
    }

    /**
     * Ermittelt Seller und deren Bruttobeträge für Split Payments.
     *
     * Die Blog-ID wird direkt aus dem globalen MarketPress-Cart
     * übernommen. Dadurch funktioniert die Zuordnung auch bei
     * mehreren Shops im globalen Warenkorb.
     *
     * @param MP_Cart|array $cart Warenkorb.
     *
     * @return array Merchant-ID => Betrag.
     */
    public function get_split_payments( $cart ) {

        $seller_totals = array();

        if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_all_items' ) ) {
            return $seller_totals;
        }

        $current_blog_id = get_current_blog_id();

        foreach ( $cart->get_all_items() as $blog_id => $items ) {

            if ( empty( $items ) || ! is_array( $items ) ) {
                continue;
            }

            switch_to_blog( absint( $blog_id ) );

            $merchant_id = get_option(
                'mp_paypal_marketplace_merchant_id_' . absint( $blog_id )
            );

            if ( ! $merchant_id ) {

                $this->log(
                    'Fehlende Merchant-ID für Blog ' . absint( $blog_id )
                );

                restore_current_blog();
                continue;
            }

            foreach ( $items as $product_id => $qty ) {

                $qty = max( 1, absint( $qty ) );

                if ( $qty <= 0 ) {
                    continue;
                }

                $product = new MP_Product( absint( $product_id ) );

                if ( ! $product->ID ) {
                    continue;
                }

                /*
                * Den im Cart verwendeten Verkaufspreis verwenden.
                * Bei Varianten wird MP_Product selbst über die Produkt-ID
                * aufgelöst.
                */
                $price = max( 0, (float) $product->get_price( 'lowest' ) );

                $amount = round( $price * $qty, 2 );

                if ( $amount <= 0 ) {
                    continue;
                }

                if ( ! isset( $seller_totals[ $merchant_id ] ) ) {
                    $seller_totals[ $merchant_id ] = 0.00;
                }

                $seller_totals[ $merchant_id ] += $amount;
            }

            restore_current_blog();
        }

        return $seller_totals;
    }

    /**
     * Erstellt die PayPal-Order-Daten für einen globalen Warenkorb.
     *
     * Jeder Seller erhält eine eigene Purchase Unit.
     * Die Marketplace-Provision wird bereits in get_split_payments()
     * berücksichtigt.
     *
     * @param MP_Cart|array $cart  Warenkorb.
     * @param MP_Order      $order MarketPress-Bestellung.
     *
     * @return array
     */
    public function create_paypal_order_data( $cart, $order ) {

        $cart_items = array();

        if ( is_object( $cart ) && method_exists( $cart, 'get_all_items' ) ) {
            $cart_items = $cart->get_all_items();
        } elseif ( is_array( $cart ) ) {
            $cart_items = $cart;
        }

        if ( empty( $cart_items ) ) {
            return array();
        }

        $currency = mp_get_setting( 'currency', 'EUR' );

        if (
            $order &&
            is_object( $order ) &&
            isset( $order->currency ) &&
            $order->currency
        ) {
            $currency = $order->currency;
        }

        $currency = strtoupper( sanitize_text_field( $currency ) );

        /*
        * Seller-Beträge zunächst ohne Provision ermitteln.
        */
        $seller_totals = array();

        foreach ( $cart_items as $blog_id => $items ) {

            if ( ! is_array( $items ) || empty( $items ) ) {
                continue;
            }

            $blog_id = absint( $blog_id );
            switch_to_blog( $blog_id );

            $merchant_id = get_option(
                'mp_paypal_marketplace_merchant_id_' . $blog_id
            );

            if ( ! $merchant_id ) {
                $this->log(
                    'Fehlende Merchant-ID für Blog ' . $blog_id
                );
                restore_current_blog();
                continue;
            }

            foreach ( $items as $product_id => $qty ) {

                $qty = max( 1, absint( $qty ) );

                $product = new MP_Product( absint( $product_id ) );

                if ( ! $product->ID ) {
                    continue;
                }

                $price = max( 0, (float) $product->get_price( 'lowest' ) );
                $amount = round( $price * $qty, 2 );

                if ( $amount <= 0 ) {
                    continue;
                }

                if ( ! isset( $seller_totals[ $merchant_id ] ) ) {
                    $seller_totals[ $merchant_id ] = 0.00;
                }

                $seller_totals[ $merchant_id ] += $amount;
            }

            restore_current_blog();
        }

        /*
        * Marketplace-Provision.
        */
        $provision = $this->get_network_provision();

        if ( $provision < 0 ) {
            $provision = 0;
        }

        if ( $provision > 1 ) {
            $provision = 1;
        }

        /*
        * Netzwerk-Merchant für die Platform Fee.
        */
        $network_merchant = get_option(
            'mp_paypal_marketplace_merchant_id_network'
        );

        $purchase_units = array();

        foreach ( $seller_totals as $merchant_id => $seller_total ) {

            $seller_total = round( $seller_total, 2 );

            if ( $seller_total <= 0 ) {
                continue;
            }

            /*
            * Provision dieses Sellers.
            */
            $platform_fee = round(
                $seller_total * $provision,
                2
            );

            /*
            * Seller erhält den ursprünglichen Betrag.
            *
            * Die Platform Fee wird von PayPal separat
            * an den Plattformbetreiber abgeführt.
            */
            $purchase_unit = array(
                'reference_id' => 'seller-' . sanitize_key( $merchant_id ),

                'amount' => array(
                    'currency_code' => $currency,
                    'value'         => number_format(
                        $seller_total,
                        2,
                        '.',
                        ''
                    ),
                ),

                'payee' => array(
                    'merchant_id' => sanitize_text_field(
                        $merchant_id
                    ),
                ),
            );

            /*
            * Platform Fee nur hinzufügen, wenn:
            * - eine Provision konfiguriert ist
            * - ein Netzwerk-Merchant vorhanden ist
            */
            if (
                $platform_fee > 0 &&
                $network_merchant
            ) {
                $purchase_unit['payment_instruction'] = array(
                    'platform_fees' => array(
                        array(
                            'amount' => array(
                                'currency_code' => $currency,
                                'value' => number_format(
                                    $platform_fee,
                                    2,
                                    '.',
                                    ''
                                ),
                            ),
                        ),
                    ),
                    'disbursement_mode' => 'INSTANT',
                );
            }

            $purchase_units[] = $purchase_unit;
        }

        if ( empty( $purchase_units ) ) {
            return array();
        }

        return array(
            'intent' => 'CAPTURE',
            'purchase_units' => $purchase_units,
        );
    }

    /**
     * Erstellt eine PayPal-Order über die REST API.
     *
     * @param array $order_data PayPal Order-Daten.
     *
     * @return array|WP_Error
     */
    public function paypal_api_create_order( $order_data ) {

        if ( empty( $order_data ) || empty( $order_data['purchase_units'] ) ) {
            return new WP_Error(
                'paypal_invalid_order',
                __( 'Die PayPal-Bestellung enthält keine gültigen Zahlungsdaten.', 'mp' )
            );
        }

        $access_token = $this->get_paypal_access_token();

        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        /*
        * Eindeutige Request-ID verhindert bei Wiederholungen
        * eine versehentliche doppelte PayPal-Order.
        */
        $request_id = wp_generate_uuid4();

        $headers = array(
            'Content-Type'                  => 'application/json',
            'Accept'                        => 'application/json',
            'Authorization'                 => 'Bearer ' . $access_token,
            'PayPal-Request-Id'             => $request_id,
            'PayPal-Partner-Attribution-Id' => mp_get_network_setting(
                'paypal_marketplace_bn_code',
                ''
            ),
        );

        $response = wp_remote_post(
            $this->get_api_base_url() . '/v2/checkout/orders',
            array(
                'headers' => $headers,
                'body'    => wp_json_encode( $order_data ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log(
                'PayPal Order HTTP-Fehler: ' .
                $response->get_error_message()
            );

            return new WP_Error(
                'paypal_order_request',
                __( 'PayPal konnte nicht erreicht werden.', 'mp' )
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 201 !== $code || empty( $data['id'] ) ) {

            $this->log(
                'PayPal Order Fehler (' . $code . '): ' . $body
            );

            $message = __( 'PayPal konnte die Bestellung nicht erstellen.', 'mp' );

            if ( ! empty( $data['details'][0]['description'] ) ) {
                $message = sanitize_text_field(
                    $data['details'][0]['description']
                );
            } elseif ( ! empty( $data['message'] ) ) {
                $message = sanitize_text_field(
                    $data['message']
                );
            }

            return new WP_Error(
                'paypal_order',
                $message
            );
        }

        /*
        * Approval-Link suchen.
        */
        $approval_url = '';

        if ( ! empty( $data['links'] ) && is_array( $data['links'] ) ) {

            foreach ( $data['links'] as $link ) {

                if (
                    isset( $link['rel'], $link['href'] ) &&
                    'approve' === $link['rel']
                ) {
                    $approval_url = esc_url_raw( $link['href'] );
                    break;
                }
            }
        }

        if ( '' === $approval_url ) {

            $this->log(
                'PayPal Order ' . $data['id'] .
                ' wurde erstellt, aber keine Approval-URL gefunden.'
            );

            return new WP_Error(
                'paypal_no_approval',
                __( 'PayPal hat keine Weiterleitungs-URL zurückgegeben.', 'mp' )
            );
        }

        $this->log(
            'PayPal Order erfolgreich erstellt: ' . $data['id']
        );

        return array(
            'id'           => sanitize_text_field( $data['id'] ),
            'approval_url' => $approval_url,
            'raw'          => $data,
        );
    }

    /**
     * Verarbeitet die PayPal-Zahlung.
     *
     * Erstellt zunächst die MarketPress-Bestellung, danach die
     * PayPal-Order und speichert die Zuordnung zwischen beiden.
     *
     * @param MP_Cart $cart
     * @param array   $billing_info
     * @param array   $shipping_info
     *
     * @return void
     */
    public function process_payment( $cart, $billing_info, $shipping_info ) {

        /*
        * MarketPress-Bestellung erzeugen.
        */
        $order = new MP_Order();

        $order_id = $order->save(
            array(
                'cart' => $cart,

                'payment_info' => array(
                    'gateway_public_name'  => $this->public_name,
                    'gateway_private_name' => $this->admin_name,
                    'gateway_plugin_name'  => $this->plugin_name,
                    'method'               => __( 'PayPal', 'mp' ),
                    'transaction_id'       => '',
                    'currency'             => mp_get_setting( 'currency', 'EUR' ),
                ),

                'paid' => false,
            )
        );

        if ( ! $order_id ) {

            $this->log(
                'MarketPress-Bestellung konnte nicht erstellt werden.'
            );

            mp_checkout()->add_error(
                __( 'Die Bestellung konnte nicht erstellt werden.', 'mp' ),
                'general'
            );

            return;
        }

        /*
        * PayPal Order-Daten erzeugen.
        */
        $order_data = $this->create_paypal_order_data(
            $cart,
            $order
        );

        if ( empty( $order_data ) ) {

            $this->log(
                'Keine gültigen PayPal-Order-Daten für MarketPress-Order ' .
                $order_id
            );

            mp_checkout()->add_error(
                __(
                    'Für diese Bestellung konnten keine gültigen PayPal-Zahlungsdaten erstellt werden.',
                    'mp'
                ),
                'general'
            );

            return;
        }

        /*
        * PayPal Order erstellen.
        */
        $api_result = $this->paypal_api_create_order(
            $order_data
        );

        if ( is_wp_error( $api_result ) ) {

            $this->log(
                'PayPal Order Fehler für MarketPress-Order ' .
                $order_id . ': ' .
                $api_result->get_error_message()
            );

            mp_checkout()->add_error(
                $api_result->get_error_message(),
                'general'
            );

            return;
        }

        /*
        * PayPal Order-ID mit der MarketPress-Bestellung verknüpfen.
        */
        update_post_meta(
            $order_id,
            '_paypal_marketplace_order_id',
            $api_result['id']
        );

        /*
        * Zusätzlich den aktuellen Zahlungsstatus festhalten.
        */
        update_post_meta(
            $order_id,
            '_paypal_marketplace_status',
            'CREATED'
        );

        /*
        * Bestellung und Redirect-URL für den Checkout bereitstellen.
        *
        * class-mp-checkout.php erwartet "order_redirect_url".
        */
        wp_cache_set(
            'order_object',
            $order,
            'mp',
            HOUR_IN_SECONDS
        );

        wp_cache_set(
            'order_redirect_url',
            $api_result['approval_url'],
            'mp',
            HOUR_IN_SECONDS
        );

        /*
        * Rückwärtskompatibilität für eventuell vorhandene
        * Integrationen beibehalten.
        */
        wp_cache_set(
            'order_paypal_redirect_url',
            $api_result['approval_url'],
            'mp',
            HOUR_IN_SECONDS
        );

        $this->log(
            'Checkout vorbereitet. MP-Order ' .
            $order_id .
            ' → PayPal-Order ' .
            $api_result['id']
        );
    }

    /**
     * Verify a PayPal webhook signature through the PayPal API.
     *
     * @param array $event Decoded webhook event.
     * @return bool
     */
    protected function verify_webhook_signature( $event ) {
        $webhook_id = (string) mp_get_network_setting( 'paypal_marketplace_webhook_id', '' );
        if ( '' === $webhook_id ) {
            return false;
        }

        $headers = array(
            'transmission_id'   => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ) ) : '',
            'transmission_time' => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ) ) : '',
            'transmission_sig'  => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ) ) : '',
            'cert_url'          => isset( $_SERVER['HTTP_PAYPAL_CERT_URL'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_PAYPAL_CERT_URL'] ) ) : '',
            'auth_algo'         => isset( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ) ) : '',
        );
        if ( in_array( '', $headers, true ) ) {
            return false;
        }

        $access_token = $this->get_paypal_access_token();
        if ( is_wp_error( $access_token ) ) {
            return false;
        }

        $response = wp_remote_post(
            $this->get_api_base_url() . '/v1/notifications/verify-webhook-signature',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array_merge( $headers, array(
                    'webhook_id'    => $webhook_id,
                    'webhook_event' => $event,
                ) ) ),
                'timeout' => 30,
            )
        );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $result ) && 'SUCCESS' === mp_arr_get_value( 'verification_status', $result, '' );
    }

    /**
     * Handle verified PayPal payment webhooks.
     */
    public static function maybe_handle_webhook() {
        if ( ! isset( $_GET['mp_paypal_marketplace_webhook'] ) ) {
            return;
        }

        $body = file_get_contents( 'php://input' );
        $event = json_decode( $body, true );
        if ( ! is_array( $event ) ) {
            status_header( 400 );
            exit;
        }

        $gateway = new self();
        if ( ! $gateway->verify_webhook_signature( $event ) ) {
            status_header( 401 );
            exit;
        }

        $event_type = sanitize_text_field( (string) mp_arr_get_value( 'event_type', $event, '' ) );
        if ( ! in_array( $event_type, array( 'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.REFUNDED' ), true ) ) {
            status_header( 200 );
            exit;
        }

        $order_id = sanitize_text_field( (string) mp_arr_get_value(
            'resource->supplementary_data->related_ids->order_id',
            $event,
            mp_arr_get_value( 'resource->id', $event, '' )
        ) );
        $orders = get_posts( array(
            'post_type'      => 'mp_order',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_paypal_marketplace_order_id',
                    'value' => $order_id,
                ),
            ),
        ) );
        if ( ! empty( $orders ) ) {
            $order = new MP_Order( (int) reset( $orders ) );
            if ( 'PAYMENT.CAPTURE.REFUNDED' === $event_type ) {
                $amount = (float) mp_arr_get_value( 'resource->amount->value', $event, 0 );
                $reference = (string) mp_arr_get_value( 'resource->id', $event, '' );
                do_action( 'mp_order/refunded', $order, $amount, $reference );
                update_post_meta( $order->ID, '_paypal_marketplace_status', 'REFUNDED' );
            } else {
                $old_status = (string) get_post_status( $order->ID );
                if ( $order->exists() && ! in_array( $old_status, array( 'order_paid', 'order_shipped', 'order_closed' ), true ) ) {
                    $order->change_status( 'order_paid', true, $old_status );
                }
                update_post_meta( $order->ID, '_paypal_marketplace_status', 'COMPLETED' );
            }
        }

        status_header( 200 );
        exit;
    }
}

// Hook für das Onboarding im Admin-Bereich
add_action( 'admin_init', array( MP_Gateway_PayPal_Marketplace::class, 'maybe_handle_onboarding' ) );
// Webhook-Handler aktivieren
add_action( 'init', array( MP_Gateway_PayPal_Marketplace::class, 'maybe_handle_webhook' ) );
