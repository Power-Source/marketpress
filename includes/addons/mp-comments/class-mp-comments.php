<?php
/**
 * Widget: Top bewertete Produkte
 */
class MP_Top_Rated_Products_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'mp_top_rated_products',
            __('PS MarketPress – Top bewertete Produkte', 'mp'),
            array('description' => __('Zeigt die am besten bewerteten Produkte des Shops.', 'mp'))
        );
    }

    public function widget($args, $instance) {
        $title  = apply_filters('widget_title', isset($instance['title']) ? $instance['title'] : '');
        $number = (int) (isset($instance['number']) ? $instance['number'] : 5);

        echo wp_kses_post($args['before_widget']);
        if ($title) {
            echo wp_kses_post($args['before_title'] . $title . $args['after_title']);
        }

        // Alle Bewertungskommentare laden und Durchschnitt pro Produkt berechnen
        $comments = get_comments(array(
            'meta_key' => 'rating',
            'status'   => 'approve',
            'number'   => 500,
        ));

        $product_ratings = array();
        foreach ($comments as $comment) {
            $pid = (int) $comment->comment_post_ID;
            if (get_post_type($pid) !== MP_Product::get_post_type()) continue;
            $r = (int) get_comment_meta($comment->comment_ID, 'rating', true);
            if ($r < 1) continue;
            if (!isset($product_ratings[$pid])) {
                $product_ratings[$pid] = array('sum' => 0, 'count' => 0);
            }
            $product_ratings[$pid]['sum']   += $r;
            $product_ratings[$pid]['count'] += 1;
        }

        uasort($product_ratings, function($a, $b) {
            $avg_a = $a['sum'] / $a['count'];
            $avg_b = $b['sum'] / $b['count'];
            return ($avg_b > $avg_a) ? 1 : (($avg_b < $avg_a) ? -1 : 0);
        });

        $top_ids = array_slice(array_keys($product_ratings), 0, $number, true);

        if (!empty($top_ids)) {
            echo '<ul class="mp-top-rated-widget">';
            foreach ($top_ids as $pid) {
                $post = get_post($pid);
                if (!$post || $post->post_status !== 'publish') continue;
                $avg   = $product_ratings[$pid]['sum'] / $product_ratings[$pid]['count'];
                $stars = MP_MARKETPRESS_COMMENTS_Addon::render_stars_html($avg);
                $product = new MP_Product($pid);
                echo '<li class="mp-top-rated-item">';
                echo '<a href="' . esc_url($product->url(false)) . '">' . esc_html($post->post_title) . '</a>';
                echo ' <span class="mp-widget-stars">' . esc_html($stars) . '</span>';
                echo ' <span class="mp-widget-avg">' . esc_html(number_format($avg, 1)) . '/5</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('Noch keine Bewertungen vorhanden.', 'mp') . '</p>';
        }

        echo wp_kses_post($args['after_widget']);
    }

    public function form($instance) {
        $title  = isset($instance['title'])  ? $instance['title']  : __('Top bewertete Produkte', 'mp');
        $number = isset($instance['number']) ? (int) $instance['number'] : 5;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Titel:', 'mp'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('number')); ?>"><?php esc_html_e('Anzahl Produkte:', 'mp'); ?></label>
            <input id="<?php echo esc_attr($this->get_field_id('number')); ?>" name="<?php echo esc_attr($this->get_field_name('number')); ?>" type="number" value="<?php echo esc_attr($number); ?>" min="1" max="20">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        return array(
            'title'  => sanitize_text_field($new_instance['title']),
            'number' => max(1, (int) $new_instance['number']),
        );
    }
}

/**
 * MarketPress Erlaube Bewertungen Addon
 */
class MP_MARKETPRESS_COMMENTS_Addon {
    /**
     * Pfad zum Addon-Verzeichnis
     *
     * @var string
     */
    public $plugin_dir;

    /**
     * URL zum Addon-Verzeichnis
     *
     * @var string
     */
    public $plugin_url;
    
    /**
     * Refers to a single instance of the class
     *
     * @since 1.0
     * @access private
     * @var object
     */
    private static $_instance = null;

    /**
     * Produkt-ID des zuletzt verarbeiteten Tabs (für render_reviews_tab)
     *
     * @var int
     */
    private $current_product_id = 0;

    /**
     * Gets the single instance of the class
     *
     * @since 1.0
     * @access public
     * @return object
     */
    public static function get_instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new MP_MARKETPRESS_COMMENTS_Addon();
        }
        return self::$_instance;
    }

    /**
     * Konstruktor
     * @access private
     */
    private function __construct() {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Konstanten definieren für die Abwärtskompatibilität
        if (!defined('MP_COMMENTS_PLUGIN_DIR')) {
            define('MP_COMMENTS_PLUGIN_DIR', $this->plugin_dir);
        }
        if (!defined('MP_COMMENTS_PLUGIN_URL')) {
            define('MP_COMMENTS_PLUGIN_URL', $this->plugin_url);
        }

        // Erforderliche Dateien einbinden
        require_once $this->plugin_dir . 'templates/comment-template.php';
        require_once $this->plugin_dir . 'includes/functions.php';
        
        // Hooks für Admin und Einstellungen
        if (is_admin()) {
            // Initialisiere die Settings-Metaboxen nur wenn wir auf der Einstellungsseite dieses Addons sind
            if (isset($_GET['page']) && $_GET['page'] == 'store-settings-addons' && 
                isset($_GET['addon']) && $_GET['addon'] == 'MP_MARKETPRESS_COMMENTS_Addon') {
                add_action('init', array($this, 'init_settings_metaboxes'));
            }
        }
        
        // Hooks initialisieren
        add_action('init', array($this, 'init'), 20);
    }
    
    /**
     * Initialisiere die Addon-Funktionalität
     */
    public function init() {
        // Hooks für die Integration der Produktbewertungen initialisieren
        $this->init_hooks();
    }
    
    /**
     * Initialisiere Settings Metaboxes
     * 
     * @since 1.0
     * @access public
     * @action init
     */
    public function init_settings_metaboxes() {
        $metabox = new PSOURCE_Metabox(array(
            'id'          => 'mp-comments-settings-metabox',
            'title'       => __('Bewertungseinstellungen', 'mp'),
            'page_slugs'  => array('store-settings-addons'),
            'option_name' => 'mp_settings',
        ));
        
        // Wer darf Bewertungen abgeben?
        $metabox->add_field('checkbox_group', array(
            'name'          => 'comments[allowed_users]',
            'label'         => array('text' => __('Wer darf Bewertungen abgeben?', 'mp')),
            'options'       => array(
                'registered' => __('Registrierte Benutzer', 'mp'),
                'guests'     => __('Gäste', 'mp'),
            ),
            'default_value' => array('registered', 'guests'),
        ));
        
        // Müssen Käufer das Produkt gekauft haben?
        $metabox->add_field('radio_group', array(
            'name'          => 'comments[require_purchase]',
            'label'         => array('text' => __('Nur Käufer können bewerten', 'mp')),
            'desc'          => __('Wenn aktiviert, können nur Benutzer, die das Produkt gekauft haben, eine Bewertung abgeben.', 'mp'),
            'default_value' => 'no',
            'options'       => array(
                'no'  => __('Nein', 'mp'),
                'yes' => __('Ja', 'mp'),
            ),
        ));

        // Hilfreich-Funktion aktivieren?
        $metabox->add_field('checkbox', array(
            'name'          => 'comments[enable_helpful]',
            'label'         => array('text' => __('"Hilfreich"-Funktion aktivieren', 'mp')),
            'desc'          => __('Zeigt unter jeder Rezension einen "Hilfreich"-Button, mit dem Besucher nützliche Bewertungen markieren können.', 'mp'),
            'default_value' => 1,
        ));
    }


    
    /**
     * Deaktiviere Addon
     */
    public function deactivate() {
        // Bereinigungsaktionen bei der Deaktivierung ausführen
        flush_rewrite_rules();
    }
    
    /**
     * Initialisiere Hooks
     */
    private function init_hooks() {

        // Kommentarunterstützung für Produkte aktivieren
        add_action('init', array($this, 'enable_product_comments'));
        
        // Prüfen, ob eine Bewertung abgegeben wurde
        add_filter('preprocess_comment', array($this, 'verify_comment_rating'));
        
        // Bewertung speichern
        add_action('comment_post', array($this, 'save_comment_rating'), 10, 3);
        
        // Assets laden
        add_action('wp_enqueue_scripts', array($this, 'load_rating_assets'));
        
        // Tab-Integration: Bewertungs-Tab zur Produktseite hinzufügen
        add_filter('mp_product/content_tabs_array', array($this, 'add_reviews_tab'), 10, 2);
        add_filter('mp_content_tab_html',           array($this, 'render_reviews_tab'), 10, 2);
        
        // Mini-Sterne in Produktlisten
        add_filter('mp_product_price_html', array($this, 'add_mini_rating_to_price'), 10, 2);
        
        // Shortcode [mp_product_rating]
        add_shortcode('mp_product_rating', array($this, 'shortcode_product_rating'));
        
        // Widget registrieren
        add_action('widgets_init', array($this, 'register_reviews_widget'));
        
        // Top-bewertet-Sortierung für [mp_list_products order_by="rating"]
        add_filter('shortcode_atts_mp_list_products', array($this, 'intercept_rating_orderby'), 10, 3);
        add_filter('mp_list_products_query_args',     array($this, 'apply_rating_orderby'), 10, 2);
        
        // Admin-Kommentarspalte für Bewertungen
        add_filter('manage_edit-comments_columns', array($this, 'add_comment_rating_column'));
        add_action('manage_comments_custom_column', array($this, 'comment_rating_column_content'), 10, 2);
        
        // Sicherstellen, dass Kommentare für Produkte aktiviert sind
        add_filter('comments_open', array($this, 'enable_comments_for_products'), 20, 2);
        
        // Entferne die standard ClassicPress Comments-Metabox für Produkte und füge eine Bewertungs-Metabox hinzu
        add_action('add_meta_boxes', array($this, 'replace_comments_metabox'), 10);
        
        // Entferne die Diskussions-Metabox für Produkte (nicht benötigt)
        add_action('admin_menu', array($this, 'remove_discussion_metabox'));
        
        // Für die Korrektur des 404-Fehlers der Schriftarten
        add_action('admin_enqueue_scripts', array($this, 'load_admin_fonts'));
    }
    
    // -------------------------------------------------------------------------
    // Tab-Integration
    // -------------------------------------------------------------------------
    
    /**
     * Tab-Array erweitern: Bewertungs-Tab hinzufügen
     *
     * @param array      $tabs
     * @param MP_Product $product
     * @return array
     */
    public function add_reviews_tab($tabs, $product) {
        $this->current_product_id = $product->ID;
        
        $avg   = self::get_average_rating($product->ID);
        $count = self::get_rating_count($product->ID);
        
        if ($avg > 0) {
            $stars = self::render_stars_html($avg);
            $label = sprintf(
                __('Bewertungen <span class="mp-tab-stars">%s</span> (%d)', 'mp'),
                esc_html($stars),
                $count
            );
        } else {
            $label = __('Bewertungen', 'mp');
        }
        
        $tabs['mp-reviews'] = $label;
        return $tabs;
    }
    
    /**
     * Tab-HTML rendern
     *
     * @param string $html
     * @param string $slug
     * @return string
     */
    public function render_reviews_tab($html, $slug) {
        if ($slug !== 'mp-reviews') {
            return $html;
        }
        
        global $post;
        $product_post = $post;
        
        // Fallback: Produkt-ID aus dem letzten add_reviews_tab()-Aufruf
        if (!$product_post || get_post_type($product_post->ID) !== MP_Product::get_post_type()) {
            if ($this->current_product_id > 0) {
                $product_post = get_post($this->current_product_id);
            }
        }
        
        if (!$product_post || empty($product_post->ID)) {
            return '<p class="mp-no-reviews">' . esc_html__('Bewertungen konnten nicht geladen werden.', 'mp') . '</p>';
        }
        
        $saved_post = $post;
        $post       = $product_post;
        setup_postdata($post);

        // Explizit mitgeben, damit das Template auch außerhalb des Standard-Loops die richtige Produkt-ID hat.
        $GLOBALS['mp_comments_product_id'] = (int) $product_post->ID;
        
        ob_start();
        include $this->plugin_dir . 'templates/comments.php';
        $html = ob_get_clean();
        
        unset($GLOBALS['mp_comments_product_id']);
        $post = $saved_post;
        wp_reset_postdata();
        
        return $html;
    }
    
    // -------------------------------------------------------------------------
    // Hilfsfunktionen (statisch, damit Widget + Shortcode sie nutzen können)
    // -------------------------------------------------------------------------
    
    /**
     * Durchschnittliche Bewertung eines Produkts berechnen
     *
     * @param int $product_id
     * @return float  0 wenn keine Bewertungen
     */
    public static function get_average_rating($product_id) {
        $comments = get_comments(array(
            'post_id'  => $product_id,
            'meta_key' => 'rating',
            'status'   => 'approve',
        ));
        if (empty($comments)) {
            return 0.0;
        }
        $total = array_sum(array_map(function($c) {
            return (int) get_comment_meta($c->comment_ID, 'rating', true);
        }, $comments));
        return $total / count($comments);
    }
    
    /**
     * Anzahl der Bewertungen eines Produkts
     *
     * @param int $product_id
     * @return int
     */
    public static function get_rating_count($product_id) {
        return (int) get_comments(array(
            'post_id'  => $product_id,
            'meta_key' => 'rating',
            'status'   => 'approve',
            'count'    => true,
        ));
    }
    
    /**
     * Sterne-HTML generieren
     *
     * @param float $rating  z.B. 4.3
     * @param int   $max     Standard 5
     * @return string  Unicode-Sterne, z.B. "★★★★☆"
     */
    public static function render_stars_html($rating, $max = 5) {
        $full  = (int) floor($rating);
        $half  = (($rating - $full) >= 0.5) ? 1 : 0;
        $empty = $max - $full - $half;
        return str_repeat('★', $full) . ($half ? '½' : '') . str_repeat('☆', $empty);
    }
    
    // -------------------------------------------------------------------------
    // Mini-Sterne in Produktlisten
    // -------------------------------------------------------------------------
    
    /**
     * Mini-Sterne vor dem Preis in Produktübersichten einblenden
     *
     * @param string $price_html
     * @param int    $post_id
     * @return string
     */
    public function add_mini_rating_to_price($price_html, $post_id) {
        if (get_post_type($post_id) !== MP_Product::get_post_type()) {
            return $price_html;
        }
        // Auf der Einzelproduktseite zeigen wir die Sterne im Tab-Label – hier überspringen
        if (is_singular(MP_Product::get_post_type())) {
            return $price_html;
        }
        $avg = self::get_average_rating($post_id);
        if ($avg <= 0.0) {
            return $price_html;
        }
        $count = self::get_rating_count($post_id);
        $stars = self::render_stars_html($avg);
        $mini  = '<div class="mp-mini-rating" aria-label="' . esc_attr(sprintf(__('%.1f von 5 Sternen', 'mp'), $avg)) . '">';
        $mini .= '<span class="mp-mini-stars">' . esc_html($stars) . '</span>';
        $mini .= ' <span class="mp-mini-count">(' . $count . ')</span>';
        $mini .= '</div>';
        return $mini . $price_html;
    }
    
    // -------------------------------------------------------------------------
    // Shortcode [mp_product_rating id="123" format="full" link="yes"]
    // -------------------------------------------------------------------------
    
    /**
     * Shortcode-Callback
     *
     * @param array $atts
     * @return string
     */
    public function shortcode_product_rating($atts) {
        $atts = shortcode_atts(array(
            'id'     => get_the_ID(),
            'format' => 'full',   // 'full' | 'stars' | 'number'
            'link'   => 'yes',
        ), $atts, 'mp_product_rating');
        
        $product_id = (int) $atts['id'];
        if (!$product_id || get_post_type($product_id) !== MP_Product::get_post_type()) {
            return '';
        }
        
        $avg   = self::get_average_rating($product_id);
        $count = self::get_rating_count($product_id);
        
        if ($avg <= 0) {
            return '<span class="mp-shortcode-rating mp-no-rating">' . esc_html__('Noch keine Bewertungen', 'mp') . '</span>';
        }
        
        $stars = self::render_stars_html($avg);
        
        $inner  = '<span class="mp-shortcode-rating" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
        $inner .= '<meta itemprop="ratingValue" content="' . esc_attr(number_format($avg, 1)) . '">';
        $inner .= '<meta itemprop="reviewCount" content="' . esc_attr($count) . '">';
        
        if (in_array($atts['format'], array('full', 'stars'), true)) {
            $inner .= '<span class="mp-sc-stars">' . esc_html($stars) . '</span>';
        }
        if (in_array($atts['format'], array('full', 'number'), true)) {
            $inner .= ' <span class="mp-sc-avg">' . esc_html(number_format($avg, 1)) . '/5</span>';
            $inner .= ' <span class="mp-sc-count">(' . esc_html(sprintf(_n('%d Bewertung', '%d Bewertungen', $count, 'mp'), $count)) . ')</span>';
        }
        $inner .= '</span>';
        
        if ($atts['link'] === 'yes') {
            $product = new MP_Product($product_id);
            $url     = esc_url($product->url(false) . '#mp-reviews-' . $product_id);
            return '<a href="' . $url . '" class="mp-rating-link">' . $inner . '</a>';
        }
        return $inner;
    }
    
    // -------------------------------------------------------------------------
    // Widget registrieren
    // -------------------------------------------------------------------------
    
    /**
     * Widget-Klasse registrieren
     */
    public function register_reviews_widget() {
        register_widget('MP_Top_Rated_Products_Widget');
    }

    // -------------------------------------------------------------------------
    // Top-bewertet-Sortierung für [mp_list_products order_by="rating"]
    // -------------------------------------------------------------------------

    /**
     * Shortcode-Atts abfangen: order_by="rating" merken und neutralisieren
     *
     * @param array $out   Zusammengeführte Attribute nach shortcode_atts()
     * @param array $pairs Standardwerte
     * @param array $atts  Vom User angegebene Attribute
     * @return array
     */
    public function intercept_rating_orderby($out, $pairs, $atts) {
        if (isset($atts['order_by']) && $atts['order_by'] === 'rating') {
            // Flag setzen, damit apply_rating_orderby() die Query modifiziert
            $this->_sort_by_rating = true;
            // order_by auf null zurücksetzen, damit mp_list_products() kein falsches orderby setzt
            $out['order_by'] = null;
        } else {
            $this->_sort_by_rating = false;
        }
        return $out;
    }

    /**
     * WP_Query-Args für Rating-Sortierung modifizieren
     *
     * Wird via mp_list_products_query_args-Filter aufgerufen.
     * Berechnet für alle Produkte den Durchschnitt und sortiert per post__in.
     *
     * @param array $query WP_Query-Args
     * @param array $args  mp_list_products-Args
     * @return array
     */
    public function apply_rating_orderby($query, $args) {
        if (empty($this->_sort_by_rating)) {
            return $query;
        }
        $this->_sort_by_rating = false;

        // Alle genehmigten Bewertungskommentare laden
        $comments = get_comments(array(
            'meta_key' => 'rating',
            'status'   => 'approve',
            'number'   => 2000,
        ));

        $product_ratings = array();
        foreach ($comments as $c) {
            $pid = (int) $c->comment_post_ID;
            if (get_post_type($pid) !== MP_Product::get_post_type()) {
                continue;
            }
            $r = (int) get_comment_meta($c->comment_ID, 'rating', true);
            if ($r < 1) {
                continue;
            }
            if (!isset($product_ratings[$pid])) {
                $product_ratings[$pid] = array('sum' => 0, 'count' => 0);
            }
            $product_ratings[$pid]['sum']   += $r;
            $product_ratings[$pid]['count'] += 1;
        }

        if (empty($product_ratings)) {
            return $query;
        }

        uasort($product_ratings, function ($a, $b) {
            $avg_a = $a['sum'] / $a['count'];
            $avg_b = $b['sum'] / $b['count'];
            if ($avg_b === $avg_a) {
                return $b['count'] - $a['count']; // Bei Gleichstand: mehr Bewertungen zuerst
            }
            return ($avg_b > $avg_a) ? 1 : -1;
        });

        $sorted_ids = array_keys($product_ratings);

        // Falls die Query bereits nach bestimmten IDs filtert, Schnittmenge bilden
        if (!empty($query['post__in'])) {
            $sorted_ids = array_values(array_intersect($sorted_ids, $query['post__in']));
        }

        if (empty($sorted_ids)) {
            // Keine bewerteten Produkte → leeres Ergebnis erzwingen
            $query['post__in'] = array(0);
        } else {
            $query['post__in'] = $sorted_ids;
            $query['orderby']  = 'post__in';
        }

        // Ggf. bereits gesetztes meta_key/orderby (price/sales) entfernen
        unset($query['meta_key'], $query['order']);

        return $query;
    }

    /**
     * Aktiviere Kommentarunterstützung für Produkte
     */
    public function enable_product_comments() {
        add_post_type_support('product', 'comments');
    }
    
    /**
     * Prüfe, ob eine Bewertung abgegeben wurde und ob bereits eine Bewertung existiert
     */
    public function verify_comment_rating($commentdata) {
        // Nur für Produkte prüfen und nur wenn ein Rating-Feld im Formular vorhanden war
        if (get_post_type($commentdata['comment_post_ID']) === MP_Product::get_post_type() && isset($_POST['rating'])) {
            // Prüfe auf doppelte Bewertungen (nur wenn eine Bewertung abgegeben wurde)
            $args = array(
                'post_id' => $commentdata['comment_post_ID'],
                'meta_key' => 'rating',
                'count' => true
            );
            
            // Wenn der Benutzer angemeldet ist, nach Benutzer-ID filtern
            if (is_user_logged_in()) {
                $args['user_id'] = get_current_user_id();
            } else {
                // Für Gäste nach E-Mail filtern
                $args['author_email'] = $commentdata['comment_author_email'];
            }
            
            // Zähle die vorhandenen Bewertungen des Benutzers für dieses Produkt
            $existing_ratings = get_comments($args);
            
            if ($existing_ratings > 0) {
                wp_die(
                    __('Du hast dieses Produkt bereits bewertet. Du kannst deine bestehende Bewertung bearbeiten, aber keine neue hinzufügen.', 'mp'),
                    __('Doppelte Bewertung', 'mp'),
                    array('back_link' => true)
                );
            }
            
            // Stelle sicher, dass eine Bewertung abgegeben wurde, wenn das Feld vorhanden ist
            if (empty($_POST['rating'])) {
                wp_die(__('Fehler: Bitte wähle eine Bewertung aus.', 'mp'), __('Bewertung fehlt', 'mp'), array('back_link' => true));
            }
            
            // Wenn kein Kommentartext eingegeben wurde, erstellen wir einen Standardtext basierend auf der Bewertung
            if (empty($commentdata['comment_content'])) {
                $rating = intval($_POST['rating']);
                $rating_text = '';
                switch ($rating) {
                    case 1: $rating_text = __('Schlecht (1 Stern)', 'mp'); break;
                    case 2: $rating_text = __('Ausreichend (2 Sterne)', 'mp'); break;
                    case 3: $rating_text = __('Gut (3 Sterne)', 'mp'); break;
                    case 4: $rating_text = __('Sehr gut (4 Sterne)', 'mp'); break;
                    case 5: $rating_text = __('Ausgezeichnet (5 Sterne)', 'mp'); break;
                }
                $commentdata['comment_content'] = sprintf(__('Bewertung: %s', 'mp'), $rating_text);
            }
        }
        
        return $commentdata;
    }
    
    /**
     * Bewertung speichern
     */
    public function save_comment_rating($comment_id, $comment_approved, $commentdata) {
        if (isset($_POST['rating']) && get_post_type($commentdata['comment_post_ID']) === MP_Product::get_post_type()) {
            $rating = intval($_POST['rating']);
            if ($rating >= 1 && $rating <= 5) {
                add_comment_meta($comment_id, 'rating', $rating, true);
            }
        }
    }
    
    /**
     * Kommentarformular in die Produktseite einfügen
     */
    public function display_comments_template() {
        global $post;
        if (get_post_type() === MP_Product::get_post_type()) {
            // Unser eigenes Bewertungstemplate verwenden
            include_once $this->plugin_dir . 'templates/comments.php';
        }
    }
    
    /**
     * Sicherstellen, dass Kommentare für Produkte aktiviert sind
     * Dies ist ein wichtiger Hook, der sicherstellt, dass Kommentare für Produkte immer aktiviert sind,
     * unabhängig von den ClassicPress-Einstellungen
     */
    public function enable_comments_for_products($open, $post_id) {
        $post_type = get_post_type($post_id);
        if ($post_type === 'product') {
            // Aktiviere Kommentare für alle Produkte, unabhängig von den ClassicPress-Einstellungen
            return true;
        }
        return $open;
    }

    /**
     * Füge eine Bewertungsspalte zur Kommentar-Admin-Ansicht hinzu
     */
    public function add_comment_rating_column($columns) {
        $columns['rating'] = __('Bewertung', 'mp');
        return $columns;
    }

    /**
     * Fülle die Bewertungsspalte mit Daten
     */
    public function comment_rating_column_content($column, $comment_ID) {
        if ($column !== 'rating') return;
        
        $comment = get_comment($comment_ID);
        if (get_post_type($comment->comment_post_ID) !== MP_Product::get_post_type()) return;
        
        $rating = get_comment_meta($comment_ID, 'rating', true);
        if ($rating) {
            echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . ' (' . $rating . '/5)';
        } else {
            echo '–';
        }
    }

    /**
     * Enqueue Frontend-Skripte für bessere Darstellung
     */
    public function enqueue_rating_scripts() {
        if (is_singular(MP_Product::get_post_type())) {
            // Inline-CSS für bessere Sternbewertung im Header
            wp_add_inline_style('mp-style', '
                .comment-rating {
                    display: flex;
                    align-items: center;
                    margin-bottom: 15px;
                    background: #f9f9f9;
                    padding: 10px;
                    border-radius: 5px;
                    font-weight: bold;
                }
                .rating-stars {
                    color: #FFD700;
                    font-size: 1.3em;
                    margin-right: 10px;
                }
                .rating-score {
                    margin-right: 5px;
                    font-weight: bold;
                }
                .rating-label {
                    color: #666;
                }
                .average-rating {
                    display: flex;
                    align-items: center;
                    margin: 20px 0;
                    padding: 15px;
                    background: #f5f5f5;
                    border-radius: 8px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                }
                .rating-count {
                    margin-left: 10px;
                    color: #666;
                }
                
                /* Schnellbewertungs-Button Styling */
                .mp-quick-rating-button {
                    display: inline-block;
                    margin-top: 15px;
                    padding: 8px 15px;
                    background: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-weight: 600;
                    transition: background-color 0.2s ease;
                }
                .mp-quick-rating-button:hover {
                    background: #3e8e41;
                }
                .mp-quick-rating-button:disabled {
                    background: #cccccc;
                    cursor: not-allowed;
                }
                .comment-form-comment .optional {
                    color: #666;
                    font-size: 0.9em;
                    font-style: italic;
                    font-weight: normal;
                }
                .mp-rating-success {
                    padding: 15px;
                    background-color: #dff0d8;
                    border: 1px solid #d6e9c6;
                    color: #3c763d;
                    border-radius: 4px;
                    text-align: center;
                    font-weight: bold;
                }
                
                /* Live-Vorschau Styling */
                .preview-stars, .preview-rating {
                    animation: rating-preview-pulse 1s infinite alternate;
                    font-weight: bold;
                }
                
                @keyframes rating-preview-pulse {
                    from { opacity: 0.8; }
                    to { opacity: 1; }
                }
                
                .mp-edit-success {
                    padding: 10px;
                    background-color: #dff0d8;
                    border: 1px solid #d6e9c6;
                    color: #3c763d;
                    border-radius: 4px;
                    margin: 10px 0;
                    text-align: center;
                }
            ');
            
            // Lade das Script für Schnellbewertungen
            wp_enqueue_script('mp-quick-ratings', MP_COMMENTS_PLUGIN_URL . 'assets/js/quick-ratings.js', array('jquery'), '1.0', true);
            
            // Nonce für AJAX-Sicherheit
            $post_id = get_the_ID();
            wp_localize_script('mp-quick-ratings', 'mp_ratings_i18n', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'post_id' => $post_id,
                'nonce' => wp_create_nonce('mp_quick_rating_nonce'),
                'rating_1' => __('Schlecht (1 Stern)', 'mp'),
                'rating_2' => __('Ausreichend (2 Sterne)', 'mp'),
                'rating_3' => __('Gut (3 Sterne)', 'mp'),
                'rating_4' => __('Sehr gut (4 Sterne)', 'mp'),
                'rating_5' => __('Ausgezeichnet (5 Sterne)', 'mp'),
                'select_rating' => __('Bitte wähle eine Bewertung aus.', 'mp'),
                'quick_rating_button' => __('Nur Sterne bewerten', 'mp'),
                'processing' => __('Wird gespeichert...', 'mp'),
                'error' => __('Ein Fehler ist aufgetreten.', 'mp'),
                'required_name' => __('Bitte gib deinen Namen ein.', 'mp'),
                'required_email' => __('Bitte gib deine E-Mail-Adresse ein.', 'mp'),
                'optional' => __('optional', 'mp'),
                'your_rating' => __('Deine Bewertung', 'mp'),
            ));
        }
    }

    /**
     * Überschreibe das Standard-Kommentar-Template für Produktseiten
     */
    public function override_comments_template($template) {
        if (get_post_type() === MP_Product::get_post_type()) {
            return $this->plugin_dir . 'templates/comments.php';
        }
        return $template;
    }

    /**
     * Lade Bewertungssystem-Assets und UI-Fixes
     */
    public function load_rating_assets() {
        $product_post_type = MP_Product::get_post_type();
        $post              = get_post();
        $post_content      = ($post && isset($post->post_content)) ? $post->post_content : '';
        $has_product_shortcode = !empty($post_content) && (
            has_shortcode($post_content, 'mp_product') ||
            has_shortcode($post_content, 'mp_list_products') ||
            has_shortcode($post_content, 'mp_featured_products')
        );
        $is_mp_context = is_singular($product_post_type)
            || is_post_type_archive($product_post_type)
            || is_tax('product_category')
            || is_tax('product_tag')
            || (is_singular() && $has_product_shortcode);

        // Bewertungsbearbeitung-Skript laden
        if ($is_mp_context) {
            $edit_rating_ver = file_exists(MP_COMMENTS_PLUGIN_DIR . 'assets/js/edit-rating.js') ? (string) filemtime(MP_COMMENTS_PLUGIN_DIR . 'assets/js/edit-rating.js') : '1.0.0';
            wp_enqueue_script('mp-edit-rating', MP_COMMENTS_PLUGIN_URL . 'assets/js/edit-rating.js', array('jquery'), $edit_rating_ver, true);
        }
        
        // UI-Fixes für MarketPress-Produkte
        if ($is_mp_context) {
            // Inline CSS für globale MarketPress UI-Fixes
            wp_add_inline_style('mp-style', '
                /* Verhindere unerwünschte Cursor-Positionierung und Textauswahl */
                .mp_product_content, 
                .mp_product_price,
                .mp_product_meta,
                .mp_product_details,
                .mp_product_categories,
                .mp_product_tags,
                .mp-product {
                    user-select: none;
                }
                
                /* Erlaube Textauswahl für wichtige Inhalte */
                .mp_product_content .entry-content,
                .mp_product_content .entry-summary,
                .mp_product_description {
                    user-select: text;
                }
                
                /* Verbessere Input-Elemente und Links */
                .mp_product input, 
                .mp_product textarea,
                .mp_product select,
                .mp_product button,
                .mp_product a {
                    user-select: text;
                    outline: 2px solid transparent;
                }
            ');
            
            // Inline JavaScript für globale MarketPress UI-Fixes
            wp_add_inline_script('mp-global', '
                jQuery(document).ready(function($) {
                    // Verhindere Cursor-Probleme in MarketPress-Elementen
                    $("body").on("mousedown", ".mp_product_content, .mp_product_price, .mp_product_meta, .mp_product_details, .mp-product", function(e) {
                        if (!$(e.target).is("input, textarea, select, button, a") && 
                            !$(e.target).closest(".mp_product_description").length) {
                            e.preventDefault();
                        }
                    });
                });
            ', 'after');
        }
        
        // Bewertungssystem-Assets auf allen MarketPress-Produktkontexten laden
        if ($is_mp_context) {
            $ratings_css_ver = file_exists(MP_COMMENTS_PLUGIN_DIR . 'assets/css/ratings.css') ? (string) filemtime(MP_COMMENTS_PLUGIN_DIR . 'assets/css/ratings.css') : '1.0.0';
            $ratings_js_ver  = file_exists(MP_COMMENTS_PLUGIN_DIR . 'assets/js/ratings.js') ? (string) filemtime(MP_COMMENTS_PLUGIN_DIR . 'assets/js/ratings.js') : '1.0.0';
            wp_enqueue_style('mp-ratings-style', MP_COMMENTS_PLUGIN_URL . 'assets/css/ratings.css', array(), $ratings_css_ver);
            wp_enqueue_script('mp-ratings-script', MP_COMMENTS_PLUGIN_URL . 'assets/js/ratings.js', array('jquery'), $ratings_js_ver, true);
            
            // Lokalisierung für JavaScript
            wp_localize_script('mp-ratings-script', 'mp_ratings_i18n', array(
                'ajaxurl'       => admin_url('admin-ajax.php'),
                'rating_1'      => __('Schlecht (1 Stern)', 'mp'),
                'rating_2'      => __('Ausreichend (2 Sterne)', 'mp'),
                'rating_3'      => __('Gut (3 Sterne)', 'mp'),
                'rating_4'      => __('Sehr gut (4 Sterne)', 'mp'),
                'rating_5'      => __('Ausgezeichnet (5 Sterne)', 'mp'),
                'select_rating' => __('Bitte wähle eine Bewertung aus.', 'mp'),
                'helpful_voted' => __('Als hilfreich markiert', 'mp'),
            ));
            
            // Lokalisierung für das Bearbeitungsformular
            wp_localize_script('mp-edit-rating', 'mp_edit_rating', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'rating_label' => __('Deine Sternebewertung', 'mp'),
                'rating_text' => array(
                    1 => __('Schlecht (1 Stern)', 'mp'),
                    2 => __('Ausreichend (2 Sterne)', 'mp'),
                    3 => __('Gut (3 Sterne)', 'mp'),
                    4 => __('Sehr gut (4 Sterne)', 'mp'),
                    5 => __('Ausgezeichnet (5 Sterne)', 'mp')
                ),
                'rating_label_1' => __('Schlecht', 'mp'),
                'rating_label_2' => __('Ausreichend', 'mp'),
                'rating_label_3' => __('Gut', 'mp'),
                'rating_label_4' => __('Sehr gut', 'mp'),
                'rating_label_5' => __('Ausgezeichnet', 'mp'),
                'save_button' => __('Bewertung speichern', 'mp'),
                'cancel_button' => __('Abbrechen', 'mp'),
                'saving' => __('Wird gespeichert...', 'mp'),
                'error_message' => __('Ein Fehler ist aufgetreten. Bitte versuche es erneut.', 'mp'),
                'comment_label' => __('Dein Kommentar', 'mp'),
                'optional_text' => __('optional', 'mp'),
                'select_rating' => __('Bitte wähle eine Bewertung aus.', 'mp'),
                'success_message' => __('Deine Bewertung wurde aktualisiert.', 'mp'),
                'find_your_review' => __('Zu deiner Bewertung', 'mp'),
                'already_rated' => __('Du hast dieses Produkt bereits bewertet.', 'mp')
            ));
        }
    }
    
    /**
     * Ersetze die standard ClassicPress Comments-Metabox für Produkte
     */
    public function replace_comments_metabox() {
        // Entferne die Standard-Metabox für Kommentare bei Produkten
        remove_meta_box('commentsdiv', 'product', 'normal');
        
        // Füge unsere eigene Metabox für Produktbewertungen hinzu
        add_meta_box(
            'mp_product_ratings',
            __('Produktbewertungen', 'mp'),
            array($this, 'display_product_ratings_metabox'),
            'product',
            'normal',
            'default'
        );
    }
    
    /**
     * Anzeige der eigenen Bewertungs-Metabox für Produkte im Admin-Bereich
     */
    public function display_product_ratings_metabox($post) {
        // Lade die Bewertungen
        $args = array(
            'post_id' => $post->ID,
            'status' => 'approve',
            'meta_key' => 'rating',
        );
        $reviews = get_comments($args);
        $reviews_count = count($reviews);
        
        echo '<div class="mp-product-ratings-admin">';
        
        if ($reviews_count > 0) {
            // Berechne Durchschnittsbewertung
            $total_rating = 0;
            foreach ($reviews as $review) {
                $rating = get_comment_meta($review->comment_ID, 'rating', true);
                if ($rating) {
                    $total_rating += $rating;
                }
            }
            $average_rating = $total_rating / $reviews_count;
            $stars = str_repeat('★', round($average_rating)) . str_repeat('☆', 5 - round($average_rating));
            
            // Zeige Zusammenfassung
            echo '<div class="mp-ratings-summary">';
            echo '<p class="mp-ratings-average">';
            echo sprintf(
                __('Durchschnittliche Bewertung: <span style="color: #FFD700; font-size: 1.2em;">%s</span> (%s/5) aus %s Bewertungen', 'mp'),
                $stars,
                number_format($average_rating, 1),
                $reviews_count
            );
            echo '</p>';
            echo '</div>';
            
            // Zeige Liste der Bewertungen
            echo '<div class="mp-ratings-list">';
            echo '<h4>' . __('Alle Bewertungen', 'mp') . '</h4>';
            
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . __('Benutzer', 'mp') . '</th>';
            echo '<th>' . __('Bewertung', 'mp') . '</th>';
            echo '<th>' . __('Datum', 'mp') . '</th>';
            echo '<th>' . __('Kommentar', 'mp') . '</th>';
            echo '<th>' . __('Aktionen', 'mp') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($reviews as $review) {
                $rating = get_comment_meta($review->comment_ID, 'rating', true);
                $rating_stars = $rating ? str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) : '–';
                
                echo '<tr>';
                echo '<td>' . esc_html($review->comment_author) . '</td>';
                echo '<td><span style="color: #FFD700;">' . $rating_stars . '</span> (' . $rating . '/5)</td>';
                echo '<td>' . get_comment_date('d.m.Y H:i', $review->comment_ID) . '</td>';
                echo '<td>' . wp_trim_words(strip_tags($review->comment_content), 15) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('comment.php?action=editcomment&c=' . $review->comment_ID)) . '" class="button button-small">' . __('Bearbeiten', 'mp') . '</a> ';
                echo '<a href="' . esc_url(admin_url('comment.php?action=cdc&c=' . $review->comment_ID)) . '" class="button button-small">' . __('Löschen', 'mp') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            
            // Link zu allen Kommentaren
            echo '<p class="mp-view-all-ratings">';
            echo '<a href="' . admin_url('edit-comments.php?p=' . $post->ID) . '" class="button">' . __('Alle Bewertungen verwalten', 'mp') . '</a>';
            echo '</p>';
            
            echo '</div>';
        } else {
            echo '<p>' . __('Dieses Produkt hat noch keine Bewertungen.', 'mp') . '</p>';
        }
        
        echo '</div>';
        
        // Füge etwas CSS für die Darstellung hinzu
        echo '<style>
            .mp-product-ratings-admin {
                padding: 10px 0;
            }
            .mp-ratings-summary {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #4CAF50;
            }
            .mp-ratings-average {
                font-size: 16px;
                margin: 0;
            }
            .mp-ratings-list h4 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .mp-view-all-ratings {
                margin-top: 15px;
                text-align: right;
            }
        </style>';
    }
    
    /**
     * Lade notwendige Schriftarten im Admin-Bereich
     */
    public function load_admin_fonts() {
        global $post;
        
        // Nur auf der Produkt-Bearbeitungsseite laden
        if (is_admin() && isset($post) && $post && get_post_type($post->ID) === MP_Product::get_post_type()) {
            // Füge einen CSS-Fix für die fehlenden Schriftarten hinzu
            wp_add_inline_style('wp-admin', '
                /* Fallback für fehlende Source Sans Pro Schriftart */
                @font-face {
                    font-family: "Source Sans Pro";
                    src: local("Segoe UI"), local("Helvetica Neue"), local("Roboto"), local("Arial"), sans-serif;
                    font-weight: normal;
                    font-style: normal;
                }
                
                @font-face {
                    font-family: "Source Sans Pro";
                    src: local("Segoe UI Bold"), local("Helvetica Neue Bold"), local("Roboto Bold"), local("Arial Bold"), sans-serif;
                    font-weight: bold;
                    font-style: normal;
                }
            ');
        }
    }
    
    /**
     * Entferne die Diskussions-Metabox für Produkte
     * Diese Box ist für unser Bewertungssystem nicht notwendig
     */
    public function remove_discussion_metabox() {
        // Entferne die Diskussions-Metabox für Produkte
        remove_meta_box('commentstatusdiv', 'product', 'normal');
        remove_meta_box('commentstatusdiv', 'product', 'side');
    }
}

/**
 * Hilfsfunktion für den Zugriff auf die Addon-Instanz
 *
 * @since 1.0
 * @access public
 * @return MP_MARKETPRESS_COMMENTS_Addon
 */
function mp_comments() {
    return MP_MARKETPRESS_COMMENTS_Addon::get_instance();
}

// Initialisieren des Addons
mp_comments();


