<?php
/*
Plugin Name: MarketPress-Statistiken
Plugin URI: https://psource.eimen.net//marketpress
Description: Zeigt MarketPress-Statistiken mithilfe von Chart.js an.
Version: 1.1.0
Author: DerN3rd
*/

define( 'MP_ST_DB_VERSION', '1.1' );

/* ---------------------------------------------------------------
 * Installation / DB-Upgrade
 * ------------------------------------------------------------- */

register_activation_hook( __FILE__, 'mp_st_install' );
function mp_st_install() {
    mp_st_create_tables();
    update_option( 'mp_st_db_version', MP_ST_DB_VERSION );
}

function mp_st_create_tables() {
    global $wpdb;
    $table           = $wpdb->prefix . 'mp_download_events';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id            bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id      bigint(20) unsigned NOT NULL DEFAULT 0,
        product_id    bigint(20) unsigned NOT NULL DEFAULT 0,
        user_id       bigint(20) unsigned NOT NULL DEFAULT 0,
        order_total   decimal(10,2)       NOT NULL DEFAULT 0.00,
        is_free       tinyint(1)          NOT NULL DEFAULT 0,
        downloaded_at datetime            NOT NULL,
        blog_id       bigint(20) unsigned NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        KEY order_id      (order_id),
        KEY downloaded_at (downloaded_at),
        KEY is_free       (is_free)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

add_action( 'plugins_loaded', 'mp_st_maybe_upgrade_db' );
function mp_st_maybe_upgrade_db() {
    if ( get_option( 'mp_st_db_version' ) !== MP_ST_DB_VERSION ) {
        mp_st_create_tables();
        update_option( 'mp_st_db_version', MP_ST_DB_VERSION );
    }
}

/* Plugin-Deaktivierung */
register_deactivation_hook( __FILE__, 'mp_st_remove' );
function mp_st_remove() {
    // Tabelle bleibt erhalten, damit historische Daten nicht verloren gehen.
}

/* ---------------------------------------------------------------
 * Download-Tracking
 * ------------------------------------------------------------- */

add_action( 'mp_serve_download', 'mp_st_track_download', 10, 3 );
function mp_st_track_download( $url, $order, $download_count ) {
    global $wpdb;

    $order_total = (float) $order->get_meta( 'mp_order_total' );
    $product_id  = (int) get_queried_object_id();

    $wpdb->insert(
        $wpdb->prefix . 'mp_download_events',
        [
            'order_id'      => (int) $order->ID,
            'product_id'    => $product_id,
            'user_id'       => (int) get_current_user_id(),
            'order_total'   => $order_total,
            'is_free'       => ( $order_total <= 0.0 ) ? 1 : 0,
            'downloaded_at' => current_time( 'mysql' ),
            'blog_id'       => (int) get_current_blog_id(),
        ],
        [ '%d', '%d', '%d', '%f', '%d', '%s', '%d' ]
    );
}

/* ---------------------------------------------------------------
 * Admin-Menü
 * ------------------------------------------------------------- */

add_action( 'admin_menu', 'mp_st_admin_menu' );
function mp_st_admin_menu() {
    add_dashboard_page(
        __( 'Verkaufsstatistik', 'mp_st' ),
        __( 'Shopstatistik',    'mp_st' ),
        'manage_options',
        'mp_st',
        'mp_st_page',
    );
}

/* Skripte und Styles laden */
add_action( 'admin_enqueue_scripts', 'mp_st_enqueue_scripts' );
function mp_st_enqueue_scripts( $hook ) {
    if ( $hook !== 'dashboard_page_mp_st' ) {
        return;
    }

    wp_enqueue_script( 'chart-js',    'https://cdn.jsdelivr.net/npm/chart.js', [], null, true );
    wp_enqueue_script( 'mp-stats-js', plugins_url( 'mp-stats.js', __FILE__ ), [ 'jquery', 'chart-js' ], null, true );

    wp_localize_script( 'mp-stats-js', 'mpStatsAjax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'mp_stats_nonce' ),
    ] );
}

/* AJAX-Endpunkt für Verkaufsdaten */
add_action( 'wp_ajax_mp_get_sales_data', 'mp_st_get_sales_data' );
function mp_st_get_sales_data() {
    check_ajax_referer( 'mp_stats_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Unzureichende Berechtigungen.', 'mp_st' ) ], 403 );
    }

    global $wpdb;

    $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '3_months';
    $month1 = isset( $_POST['month1'] ) ? sanitize_text_field( $_POST['month1'] ) : null;
    $month2 = isset( $_POST['month2'] ) ? sanitize_text_field( $_POST['month2'] ) : null;

    $cache_key = 'mp_stats_' . md5( serialize( [ $period, $month1, $month2 ] ) );
    $cache     = get_transient( $cache_key );
    if ( $cache !== false ) {
        wp_send_json( $cache );
    }

    /* ---- Umsatz-Query (bestehend) ---- */
    $rev_query  = "SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS month, SUM(meta_value) AS total
                   FROM {$wpdb->posts} p
                   JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                   WHERE post_type = %s AND meta_key = %s";
    $rev_params = [ 'mp_order', 'mp_order_total' ];

    /* ---- Gratis-Downloads-Query ---- */
    $dl_table       = $wpdb->prefix . 'mp_download_events';
    $dl_query       = "SELECT DATE_FORMAT(downloaded_at, '%%Y-%%m') AS month, COUNT(*) AS downloads
                       FROM {$dl_table}
                       WHERE is_free = 1";
    $dl_params      = [];
    $dl_where_extra = '';

    /* ---- Periodenfilter ---- */
    if ( $period === 'this_month' ) {
        $rev_query      .= ' AND MONTH(post_date) = MONTH(NOW()) AND YEAR(post_date) = YEAR(NOW())';
        $dl_where_extra  = ' AND MONTH(downloaded_at) = MONTH(NOW()) AND YEAR(downloaded_at) = YEAR(NOW())';
    } elseif ( $period === '3_months' ) {
        $rev_query      .= ' AND post_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)';
        $dl_where_extra  = ' AND downloaded_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)';
    } elseif ( $period === 'year' ) {
        $rev_query      .= ' AND YEAR(post_date) = YEAR(NOW())';
        $dl_where_extra  = ' AND YEAR(downloaded_at) = YEAR(NOW())';
    } elseif ( $period === 'custom' && $month1 && $month2 ) {
        $rev_query      .= " AND DATE_FORMAT(post_date, '%%Y-%%m') BETWEEN %s AND %s";
        $rev_params[]    = $month1;
        $rev_params[]    = $month2;
        $dl_where_extra  = " AND DATE_FORMAT(downloaded_at, '%%Y-%%m') BETWEEN %s AND %s";
        $dl_params[]     = $month1;
        $dl_params[]     = $month2;
    }
    // 'total' → kein zusätzlicher Filter; alle Datensätze

    $rev_query .= ' GROUP BY month ORDER BY month ASC';
    $dl_query  .= $dl_where_extra . ' GROUP BY month ORDER BY month ASC';

    $revenue_rows = $wpdb->get_results( $wpdb->prepare( $rev_query, $rev_params ) );
    $dl_rows      = empty( $dl_params )
        ? $wpdb->get_results( $dl_query )
        : $wpdb->get_results( $wpdb->prepare( $dl_query, $dl_params ) );

    /* ---- Allzeit-Gesamtwerte ---- */
    $total_revenue = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(meta_value) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE post_type = %s AND meta_key = %s",
            'mp_order', 'mp_order_total'
        )
    );

    $total_free_dl = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$dl_table} WHERE is_free = 1"
    );

    $response = [
        'data'           => $revenue_rows,
        'total'          => $total_revenue,
        'free_downloads' => $dl_rows,
        'free_dl_total'  => $total_free_dl,
    ];
    set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
    wp_send_json( $response );
}

/* Admin-Seite für Statistiken */
function mp_st_page() {
    ?>
    <div class="wrap">
        <h1><?php _e( 'Shopstatistik', 'mp_st' ); ?></h1>

        <!-- Filteroptionen -->
        <div id="mp-stats-filters" style="margin-bottom:16px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <label for="mp-stats-period"><?php _e( 'Zeitraum:', 'mp_st' ); ?></label>
            <select id="mp-stats-period">
                <option value="this_month"><?php _e( 'Dieser Monat',      'mp_st' ); ?></option>
                <option value="3_months" selected><?php _e( 'Letzte 3 Monate', 'mp_st' ); ?></option>
                <option value="year"><?php _e( 'Dieses Jahr',       'mp_st' ); ?></option>
                <option value="custom"><?php _e( 'Benutzerdefiniert', 'mp_st' ); ?></option>
                <option value="total"><?php _e( 'Gesamt',           'mp_st' ); ?></option>
            </select>

            <div id="mp-stats-custom-filters" style="display:none; align-items:center; gap:8px;">
                <label for="mp-stats-month1"><?php _e( 'Von:', 'mp_st' ); ?></label>
                <input type="month" id="mp-stats-month1">
                <label for="mp-stats-month2"><?php _e( 'Bis:', 'mp_st' ); ?></label>
                <input type="month" id="mp-stats-month2">
            </div>

            <button id="mp-stats-apply-filters" class="button button-primary">
                <?php _e( 'Filter anwenden', 'mp_st' ); ?>
            </button>
        </div>

        <!-- Diagramm -->
        <canvas id="salesChart" width="400" height="150"></canvas>

        <!-- KPI-Boxen -->
        <div id="mp-stats-kpis" style="display:flex; gap:20px; margin-top:20px; flex-wrap:wrap;">
            <div style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:16px 24px; min-width:180px;">
                <div style="font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.06em;">
                    <?php _e( 'Gesamtumsatz', 'mp_st' ); ?>
                </div>
                <div style="font-size:28px; font-weight:700; margin-top:4px; color:#1d2327;">
                    <span id="mp-stats-total-value">0</span>&nbsp;€
                </div>
            </div>
            <div style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:16px 24px; min-width:180px;">
                <div style="font-size:11px; color:#646970; text-transform:uppercase; letter-spacing:.06em;">
                    <?php _e( 'Gratis-Downloads (gesamt)', 'mp_st' ); ?>
                </div>
                <div style="font-size:28px; font-weight:700; margin-top:4px; color:#1d2327;">
                    <span id="mp-stats-freedl-value">0</span>
                </div>
            </div>
        </div>
    </div>
    <?php
}