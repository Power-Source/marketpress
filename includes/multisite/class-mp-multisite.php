<?php

class MP_Multisite {

	/**
	 * Refers to the current multisite build
	 *
	 * @since 1.0
	 * @access public
	 * @var int
	 */
	var $build = 3;

	/**
	 * Refers to a single instance of the class
	 *
	 * @since 1.0
	 * @access private
	 * @var object
	 */
	private static $_instance = null;

	/**
	 * Gets the single instance of the class
	 *
	 * @since 1.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new MP_Multisite();
		}

		return self::$_instance;
	}

	/**
	 * Constructor function
	 *
	 * @since 1.0
	 * @access private
	 */
	private function __construct() {
		if ( ! is_plugin_active_for_network( mp_get_plugin_slug() ) ) {
			return;
		}

		$this->maybe_install();
		//we will need to register a post type use for index
		if ( mp_get_network_setting( 'global_cart' ) ) {
			mp_cart()->is_global = true;

			add_filter( 'mp_product/url', array( &$this, 'product_url' ), 10, 2 );
			add_action( 'switch_blog', array( &$this, 'refresh_autoloaded_options' ) );
			add_action( 'mp/cart/before_calculate_shipping', array( &$this, 'load_shipping_plugins' ) );
			add_action( 'mp_order/get_cart', array( &$this, 'maybe_show_cart_global' ), 10, 2 );
		}
		add_filter( 'rewrite_rules_array', array( &$this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( &$this, 'add_query_vars' ) );

		add_filter( 'mp_gateway_api/get_gateways', array( &$this, 'get_gateways' ) );

		$settings = get_site_option( 'mp_network_settings', array() );
		if ( ( isset($settings['main_blog']) && mp_is_main_site() ) || isset($settings['main_blog']) && !$settings['main_blog'] ) {
			//shortcode
			add_shortcode( 'mp_list_global_products', array( &$this, 'mp_list_global_products_sc' ) );
			add_shortcode( 'mp_global_categories_list', array( &$this, 'mp_global_categories_list_sc' ) );
			add_shortcode( 'mp_global_tag_cloud', array( &$this, 'mp_global_tag_cloud_sc' ) );
		}

		add_shortcode( 'mp_network_customer_hub', array( &$this, 'mp_network_customer_hub_sc' ) );
		add_shortcode( 'mp_network_shop_performance', array( &$this, 'mp_network_shop_performance_sc' ) );

		//filter global product list
		add_action( 'wp_ajax_mp_global_update_product_list', array( &$this, 'filter_products' ) );
		add_action( 'wp_ajax_nopriv_mp_global_update_product_list', array( &$this, 'filter_products' ) );
		//for indexer
		//index products
		add_action( 'wp_insert_post', array( &$this, 'save_post' ), 10, 3 );
		add_action( 'untrashed_post', array( &$this, 'untrash_post' ) );
		add_action( 'trashed_post', array( &$this, 'delete_product' ) );
		add_action( 'after_delete_post', array( &$this, 'delete_product' ) );
		add_action( 'mp_checkout/product_sale', array( &$this, 'record_sale' ), 10, 2 );

		add_filter( 'the_content', array( &$this, 'taxonomy_output' ) );

		add_action( 'wp_enqueue_scripts', array( &$this, 'load_scripts' ), 11 );

		add_action( 'wpmu_new_blog', array( &$this, 'wpmu_new_blog' ) );
		add_action( 'admin_init', array( &$this, 'redirect_to_wizard_subsite' ) );
	}

	public function redirect_to_wizard_subsite() {
		if ( ! is_admin() ) {
			return;
		}

		if ( get_current_blog_id() == 1 ) {
			return;
		}

		if ( get_option( 'mp_subsite_need_redirect', 0 ) == 0 ) {
			return;
		}

		$screen = mp_get_current_screen();

		if ( $screen->id == 'store-settings_page_store-setup-wizard' ) {
			//user already inside this first time, return
			update_option( 'mp_subsite_need_redirect', 0 );
			return;
		}

		$ids  = array(
			'product',
			'edit-product',
			'edit-mp_order',
			'toplevel_page_store-settings'
		);
		$base = 'store-settings_page';
		if ( ( in_array( $screen->id, $ids ) || strpos( $screen->id, $base ) === 0 ) ) {
			update_option( 'mp_subsite_need_redirect', 0 );
			wp_redirect( admin_url( 'admin.php?page=store-setup-wizard' ) );
			exit;
		}
	}

	public function wpmu_new_blog( $blog_id ) {
		switch_to_blog( $blog_id );
		update_option( 'mp_subsite_need_redirect', 1 );
		restore_current_blog();
	}

	public function load_scripts() {
		$terms    = mp_global_get_terms( 'product_category' );
		$cat_urls = array();
		foreach ( $terms as $term ) {
			$cat_urls[ $term->term_id ] = mp_global_taxonomy_url( $term->slug, 'product_category' );
		}
		wp_localize_script( 'mp-frontend', 'mp_global', array(
			'cat_urls' => $cat_urls,
			'cat_url'  => get_permalink( mp_get_network_setting( 'pages->network_categories' ) )
		) );
	}

	public function taxonomy_output( $content ) {
		if ( ! in_the_loop() ) {
			return $content;
		}

		remove_filter( 'the_content', array( &$this, 'taxonomy_output' ) );

		$type     = '';
		$taxonomy = '';
		if ( get_the_ID() == mp_get_network_setting( 'pages->network_categories' ) ) {
			$type     = 'mp_global_category';
			$taxonomy = 'product_category';
		} elseif ( get_the_ID() == mp_get_network_setting( 'pages->network_tags' ) ) {
			$type     = 'mp_global_tag';
			$taxonomy = 'product_tag';
		}

		if ( ! empty( $type ) ) {
			$slug = get_query_var( $type );
			if ( $slug ) {
				$content = do_shortcode( '[mp_list_global_products]' );
			}
		}

		return $content;
	}

	public function add_rewrite_rules( $rewrite_rules ) {
		$new_rules = array();

		if ( $post_id = mp_get_network_setting( 'pages->network_categories' ) ) {
			$uri                                           = get_page_uri( $post_id );
			$new_rules[ $uri . '/([^/]+)/page/([^/]*)/?' ] = 'index.php?pagename=' . $uri . '&mp_global_category=$matches[1]&paged=$matches[2]';
			$new_rules[ $uri . '/([^/]+)/?' ]              = 'index.php?pagename=' . $uri . '&mp_global_category=$matches[1]';
		}

		if ( $post_id = mp_get_network_setting( 'pages->network_tags' ) ) {
			$uri                                           = get_page_uri( $post_id );
			$new_rules[ $uri . '/([^/]+)/page/([^/]*)/?' ] = 'index.php?pagename=' . $uri . '&mp_global_tag=$matches[1]&paged=$matches[2]';
			$new_rules[ $uri . '/([^/]+)/?' ]              = 'index.php?pagename=' . $uri . '&mp_global_tag=$matches[1]';
		}

		return $new_rules + $rewrite_rules;
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'mp_global_category';
		$vars[] = 'mp_global_tag';

		return $vars;
	}

	/**
	 *
	 */
	public function filter_products() {
		$page      = mp_get_post_value( 'page', 1 );
		$widget_id = mp_get_post_value( 'widget_id', - 1 );
		list( $order_by, $order ) = explode( '-', mp_get_post_value( 'order' ) );
		$category = mp_get_post_value( 'product_category', null ) > 0 ? mp_get_post_value( 'product_category' ) : null;
		echo mp_global_list_products( array(
			'page'      => $page,
			'order_by'  => trim( $order_by ),
			'order'     => trim( $order ),
			'widget_id' => $widget_id,
			'category'  => $category
		) );
		die;
	}

	/**
	 * @since 1.0
	 * @access public
	 */
	public function register_post_type() {
		register_post_type( 'mp_ms_indexer', array(
			'public'             => false,
			'show_ui'            => false,
			'publicly_queryable' => true,
			'hierarchical'       => true,
			'rewrite'            => false,
			'query_var'          => false,
			'supports'           => array(),
		) );
	}

	/**
	 * This function use for the hook mp_checkout/product_sale, we will need to update the sales count of index
	 *
	 * @param MP_Product $item
	 * @param $paid
	 *
	 * @since 1.0
	 * @access public
	 */
	public function record_sale( MP_Product $item, $paid ) {

	}

	/**
	 * @param $post_id
	 *
	 * @since 1.0
	 * @access public
	 */
	public function untrash_post( $post_id ) {
		$this->add_index( get_current_blog_id(), get_post( $post_id ) );
		$post = get_post( $post_id );
		$this->index_product_terms( get_current_blog_id(), $post );

		if ( function_exists( 'mp_invalidate_global_terms_cache' ) ) {
			mp_invalidate_global_terms_cache();
		}
	}

	/**
	 * @param $post_id
	 *
	 * @since 1.0
	 * @access public
	 */
	public function delete_product( $post_id ) {
		$this->delete_index( get_current_blog_id(), $post_id );

		if ( function_exists( 'mp_invalidate_global_terms_cache' ) ) {
			mp_invalidate_global_terms_cache();
		}
	}

	/**
	 * @param $post_id
	 * @param $post
	 * @param $update
	 *
	 * @since 1.0
	 */
	public function save_post( $post_id, $post, $update ) {
		if ( $post->post_type != MP_Product::get_post_type() ) {
			return;
		}

		if ( ! $update ) {
			//this is new product added, create new index
			$this->add_index( get_current_blog_id(), $post );
		} else {
			//find the indexer id
			$this->update_index( get_current_blog_id(), $post );
		}
		//update the terms
		$this->index_product_terms( get_current_blog_id(), $post );

		if ( function_exists( 'mp_invalidate_global_terms_cache' ) ) {
			mp_invalidate_global_terms_cache();
		}
	}

	/**
	 * This is use for find an index
	 *
	 * @param $blog_id
	 * @param $product_id
	 *
	 * @return mixed
	 * @since 1.0
	 */
	public function find_index( $blog_id, $product_id ) {
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}mp_products WHERE post_id=%d AND blog_id=%d", $product_id, $blog_id );

		return $wpdb->get_row( $sql );
	}

	/**
	 * @param $blog_id
	 * @param $post
	 *
	 * @return false|int
	 */
	public function add_index( $blog_id, $post ) {
		global $wpdb;
		$blog_public  = get_blog_status( $blog_id, 'public' );
		$product      = new MP_Product( $post->ID );
		$product_data = array(
			'site_id'           => $wpdb->siteid,
			'blog_id'           => $blog_id,
			'blog_public'       => $blog_public,
			'post_id'           => $post->ID,
			'post_author'       => $post->post_author,
			'post_title'        => $post->post_title,
			'post_content'      => strip_shortcodes( $post->post_content ),
			'post_permalink'    => $this->get_canonical_product_url( $post->ID, $blog_id ),
			'post_date'         => $post->post_date,
			'post_date_gmt'     => $post->post_date_gmt,
			'post_modified'     => $post->post_modified,
			'post_modified_gmt' => $post->post_modified_gmt,
			'post_status'       => $post->post_status,
			'price'             => $product->get_price( 'lowest' ),
			'sales_count'       => $product->get_meta( 'mp_sales_count' )
		);
		$wpdb->insert( $wpdb->base_prefix . 'mp_products', $product_data );
		$index_id = $wpdb->insert_id;
		return $index_id;
	}

	public function update_index( $blog_id, $post ) {
		global $wpdb;
		$blog_public = get_blog_status( $blog_id, 'public' );
		$product     = new MP_Product( $post->ID );
		$index       = $this->find_index( $blog_id, $post->ID );

		if ( ! $index ) {
			return false;
		}
		$product_data = array(
			'site_id'           => $wpdb->siteid,
			'blog_id'           => $blog_id,
			'blog_public'       => $blog_public,
			'post_id'           => $post->ID,
			'post_author'       => $post->post_author,
			'post_title'        => $post->post_title,
			'post_content'      => strip_shortcodes( $post->post_content ),
			'post_permalink'    => $this->get_canonical_product_url( $post->ID, $blog_id ),
			'post_date'         => $post->post_date,
			'post_date_gmt'     => $post->post_date_gmt,
			'post_modified'     => $post->post_modified,
			'post_modified_gmt' => $post->post_modified_gmt,
			'post_status'       => $post->post_status,
			'price'             => $product->get_price( 'lowest' ),
			'sales_count'       => $product->get_meta( 'mp_sales_count' )
		);
		unset( $product_data['site_id'] );
		unset( $product_data['blog_id'] );
		unset( $product_data['post_id'] );

		$wpdb->update( $wpdb->base_prefix . 'mp_products', $product_data, array(
			'post_id' => $post->ID,
			'blog_id' => $blog_id
		) );

		return $index->id;
	}

	public function index_product_terms( $blog_id, $post ) {
		global $wpdb;

		$indexer = $this->find_index( $blog_id, $post->ID );
		if ( ! $indexer ) {
			return;
		}

		$index_id = $indexer->id;

		$terms      = wp_get_object_terms( $post->ID, array( 'product_category', 'product_tag' ) );
		$while_list = array();
		foreach ( $terms as $term ) {
			//check if the term exist
			$exist = mp_global_term_exist( $term->slug, $term->taxonomy );
			if ( ! is_object( $exist ) ) {
				//term not exists, just create
				$wpdb->insert( $wpdb->base_prefix . 'mp_terms', array(
					'name' => $term->name,
					'slug' => $term->slug,
					'type' => $term->taxonomy
				) );
				$term_id = $wpdb->insert_id;
			} else {
				$term_id = $exist->term_id;
			}

			$sql    = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}mp_term_relationships WHERE post_id = %d AND blog_id = %d AND term_id=%d",
				$index_id, $blog_id, $term_id
			);
			$linked = $wpdb->get_var( $sql );
			if ( ! $linked ) {
				$wpdb->insert( $wpdb->base_prefix . 'mp_term_relationships', array(
					'post_id' => $index_id,
					'blog_id' => $blog_id,
					'term_id' => $term_id
				) );
			}

			$while_list[] = "'$term_id'";
		}

		if ( empty( $while_list ) ) {
			$while_list[] = - 1;
		}

		$while_list = implode( ',', $while_list );

		$sql = "DELETE FROM {$wpdb->base_prefix}mp_term_relationships WHERE post_id = $index_id AND term_id NOT IN ($while_list)";
		$wpdb->query( $sql );
	}

	public function delete_index( $blog_id, $product_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "DELETE p.*, r.* FROM {$wpdb->base_prefix}mp_products p LEFT JOIN {$wpdb->base_prefix}mp_term_relationships r ON p.id = r.post_id WHERE p.site_id = {$wpdb->siteid} AND p.blog_id = {$blog_id} AND p.post_id = %d", $product_id );
		$wpdb->query( $sql );

		$sql_r = $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}mp_term_relationships WHERE post_id = %d and blog_id = %d", $product_id, $blog_id );
		$wpdb->query( $sql_r );
	}

	public function count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM " . $wpdb->base_prefix . "mp_products";

		return $wpdb->get_var( $sql );
	}

	/**
	 * Loop through all the blogs, we will store all the products/categories/tags of the blog
	 * to global table.
	 * After store all to the table, started to create relations
	 */
	public function index_content() {
		$this->maybe_create_ms_tables();
		//Delete all records on mp_terms table to fix issue with deleted categories / tags still exist
		$this->truncate_index_table();
		$blogs = get_sites( array( 'fields' => 'ids' ) );
		$batch_size = apply_filters( 'mp_multisite_index_batch_size', 200 );
		$batch_size = max( 20, absint( $batch_size ) );
		$count = 0;
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			global $wpdb;

			$blog_archived = get_blog_status( $wpdb->blogid, 'archived' );
			$blog_mature   = get_blog_status( $wpdb->blogid, 'mature' );
			$blog_spam     = get_blog_status( $wpdb->blogid, 'spam' );
			$blog_deleted  = get_blog_status( $wpdb->blogid, 'deleted' );

			if ( $blog_archived || $blog_deleted || $blog_mature || $blog_spam ) {
				restore_current_blog();
				continue;
			}

			$paged = 1;
			do {
				$tmp = new WP_Query( array(
					'post_type'              => MP_Product::get_post_type(),
					'post_status'            => 'publish',
					'posts_per_page'         => $batch_size,
					'paged'                  => $paged,
					'fields'                 => 'ids',
					'no_found_rows'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				) );

				if ( $tmp->post_count > 0 ) {
					foreach ( $tmp->posts as $post_id ) {
						$post = get_post( $post_id );

						if ( ! $post || $post->post_status !== 'publish' ) {
							continue;
						}

						// ALTEN INDEX-EINTRAG LÖSCHEN!
						$this->delete_index( $blog_id, $post->ID );

						// Immer neu anlegen:
						$this->add_index( $blog_id, $post );

						//product indexed, now taxonomies & terms
						$this->index_product_terms( $blog_id, $post );
						$count ++;
					}
				}

				$paged ++;
			} while ( $paged <= $tmp->max_num_pages );

			wp_reset_postdata();
			restore_current_blog();
		}

		if ( function_exists( 'mp_invalidate_global_terms_cache' ) ) {
			mp_invalidate_global_terms_cache();
		}

		return array(
			'count' => $count
		);
	}

	/**
	 * This is use for index the products within network
	 *
	 * @return array
	 *
	 * @since 1.0
	 * @access public
	 * @deprecated
	 */
	public function _index_content() {
		_deprecated_function( 'deprecated from 3.0.0.3', '3.0.0.3' );
		//build an index with the whole site

		$data       = array();
		$categories = array();
		$tags       = array();
		$batch_size = apply_filters( 'mp_multisite_index_batch_size', 200 );
		$batch_size = max( 20, absint( $batch_size ) );
		$blogs      = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );

			$paged = 1;
			do {
				$tmp = new WP_Query( array(
					'post_type'              => MP_Product::get_post_type(),
					'post_status'            => 'publish',
					'posts_per_page'         => $batch_size,
					'paged'                  => $paged,
					'fields'                 => 'ids',
					'no_found_rows'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				) );

				if ( $tmp->post_count > 0 ) {
					foreach ( $tmp->posts as $post_id ) {
						$post = get_post( $post_id );
						if ( ! $post ) {
							continue;
						}

						$product = new MP_Product( $post->ID );
						$data[]  = array(
							'blog_id'        => $blog_id,
							'post'           => $post->to_array(),
							'regular_price'  => $product->get_price( 'lowest' ),
							'mp_sales_count' => $product->get_meta( 'mp_sales_count' ),
						);
					}
				}

				$paged ++;
			} while ( $paged <= $tmp->max_num_pages );

			wp_reset_postdata();
			//now we need to process the taxonomy
			$cats = get_terms( 'product_category', array(
				//only get parent
				'hierarchical' => false
			) );
			foreach ( $cats as $cat ) {
				$categories[] = array(
					'blog_id' => $blog_id,
					'term_id' => $cat->term_id,
					'name'    => $cat->name,
					'slug'    => $cat->slug,
					'count'   => $cat->count
				);
			}

			$ts = get_terms( 'product_tag', array(
				//only get parent
				'hierarchical' => false
			) );
			foreach ( $ts as $tag ) {
				$tags[] = array(
					'blog_id' => $blog_id,
					'term_id' => $tag->term_id,
					'name'    => $tag->name,
					'slug'    => $tag->slug,
					'count'   => $tag->count
				);
			}

			restore_current_blog();
		}

		switch_to_blog( 1 );
		//got the index, we will need to drop the old index for new
		$paged = 1;
		do {
			$indexer = new WP_Query( array(
				'post_type'              => 'mp_ms_indexer',
				'posts_per_page'         => $batch_size,
				'paged'                  => $paged,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( $indexer->post_count > 0 ) {
				foreach ( $indexer->posts as $p_id ) {
					//after the wp_delete_post, it auto switch back to original blog, so we need to switch to 1
					switch_to_blog( 1 );
					wp_delete_post( $p_id, true );
				}
			}

			$paged ++;
		} while ( $paged <= $indexer->max_num_pages );

		wp_reset_postdata();

		//stared to import
		foreach ( $data as $row ) {
			//import the post first
			$args = $row['post'];

			$id = wp_insert_post( array(
				'post_title'    => $args['post_title'],
				'post_type'     => 'mp_ms_indexer',
				'post_status'   => 'publish',
				'post_date'     => $args['post_date'],
				'post_date_gmt' => $args['post_date_gmt']
			) );
			update_post_meta( $id, 'blog_id', $row['blog_id'] );
			update_post_meta( $id, 'post_id', $args['ID'] );
			update_post_meta( $id, 'regular_price', $row['regular_price'] );
			update_post_meta( $id, 'mp_sales_count', $row['mp_sales_count'] );
		}
		//products done, now we process the tax
		update_site_option( 'mp_product_category', $categories );
		update_site_option( 'mp_product_tag', $tags );

		return array(
			'count' => count( $data )
		);
	}

	/**
	 * @param $atts
	 *
	 * @return string
	 */
	function mp_global_tag_cloud_sc( $atts ) {
		return mp_global_taxonomy_list( 'product_tag', $atts, false );
	}

	/**
	 * @param $atts
	 *
	 * @return string
	 * @since 1.0
	 */
	function mp_global_categories_list_sc( $atts ) {
		return mp_global_taxonomy_list( 'product_category', $atts, false );
	}

	/**
	 * @param $atts
	 *
	 * @return string
	 */
	function mp_list_global_products_sc( $atts ) {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		$atts['echo'] = false;
		if ( $var = get_query_var( 'mp_global_category' ) ) {
			$atts['category'] = $var;
		}
		if ( $var = get_query_var( 'mp_global_tag' ) ) {
			$atts['tag'] = $var;
		}
		$args = shortcode_atts( mp()->defaults['list_products'], $atts );

		return mp_global_list_products( $args );
	}

	/**
	 * @param $cart
	 * @param MP_Order $order
	 *
	 * @return mixed
	 */
	public function maybe_show_cart_global( $cart, MP_Order $order ) {
		//order not exist
		if ( ! $order->exists() || is_admin() ) {
			return $cart;
		}
		$id                 = $order->get_id();
		$global_order_index = get_site_option( 'mp_global_order_index', array() );

		if ( isset( $global_order_index[ $id ] ) ) {
			return $global_order_index[ $id ];
		}

		return $cart;
	}

	/**
	 * @since 1.0
	 */
	public function load_shipping_plugins() {
		/**
		 * Shipping plugin will load in very first runtime, and only for the single site. So in global cart,
		 * sometime it won't load the necessary, we need to check and load it
		 */
		MP_Shipping_API::load_active_plugins( true );
	}

	/**
	 * Drop old multisite tables
	 *
	 * @since 1.0
	 * @access public
	 * @global $wpdb
	 */
	public function drop_old_ms_tables() {
		global $wpdb;

		$table1 = $wpdb->base_prefix . 'mp_products';
		$table2 = $wpdb->base_prefix . 'mp_terms';
		$table3 = $wpdb->base_prefix . 'mp_term_relationships';

		$wpdb->query( "DROP TABLE IF EXISTS $table1, $table2, $table3" );
	}

	/**
	 * Truncate index table
	 *
	 * @since 1.0
	 * @access public
	 * @global $wpdb
	 */
	public function truncate_index_table() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->base_prefix}mp_terms WHERE type = 'product_category' OR type = 'product_tag' " );

		if ( function_exists( 'mp_invalidate_global_terms_cache' ) ) {
			mp_invalidate_global_terms_cache();
		}
	}

	/**
	 * Filter out gateways that aren't allowed according to network admin settings
	 *
	 * @since 1.0
	 * @access public
	 * @filter mp_gateway_api/get_gateways
	 */
	public function get_gateways( $gateways ) {
		if ( ! is_network_admin() ) {
			$use_global_gateway = mp_get_network_setting( 'global_cart' );

			if ( $use_global_gateway && ! is_admin() && mp_get_network_setting( 'advanced->hybrid_gateway_routing', 0 ) ) {
				$blog_ids = array();

				if ( function_exists( 'mp_cart' ) ) {
					$blog_ids = (array) mp_cart()->get_blog_ids();
				}

				if ( count( $blog_ids ) === 1 && (int) reset( $blog_ids ) !== (int) mp_root_blog_id() ) {
					$use_global_gateway = false;
				}
			}

			if ( $use_global_gateway ) {
				$code = mp_get_network_setting( 'global_gateway' );
				if ( ! empty( $code ) ) {
					$gateways = array( $code => $gateways[ $code ] );
				} else {
					//case no gateway picked in the admin
					//todo show info to admin
					$gateways = array();
				}
			} else {
				$allowed                = mp_get_network_setting( 'allowed_gateways' );
				$allowed['free_orders'] = 'full';//Always allow and activate it automatically later if needed

				if ( is_array( $allowed ) ) {
					foreach ( $gateways as $code => $gateway ) {
						if ( isset( $allowed[ $code ] ) && 'none' == $allowed[ $code ] ) {
							unset( $gateways[ $code ] );
						}
					}
				}
			}
		}

		return $gateways;
	}

	/**
	 * Render the optional network customer hub.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function mp_network_customer_hub_sc( $atts ) {
		if ( ! mp_is_main_site() ) {
			return '';
		}

		if ( ! mp_get_network_setting( 'advanced->network_customer_hub', 0 ) ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Bitte melde Dich an, um Deine zentrale Bestelluebersicht zu sehen.', 'mp' ) . '</p>';
		}

		$user_id  = get_current_user_id();
		$api      = MP_Customer_Portal_API::get_instance();
		$snapshot = $api->get_snapshot( 'network', array(
			'user_id'    => $user_id,
			'force_sync' => false,
		) );

		$currency        = isset( $snapshot['currency'] ) ? $snapshot['currency'] : mp_get_setting( 'currency' );
		$status_labels   = isset( $snapshot['status_labels'] ) && is_array( $snapshot['status_labels'] ) ? $snapshot['status_labels'] : array();
		$totals          = isset( $snapshot['totals'] ) && is_array( $snapshot['totals'] ) ? $snapshot['totals'] : array();
		$rows            = isset( $snapshot['rows'] ) && is_array( $snapshot['rows'] ) ? $snapshot['rows'] : array();
		$pending_reviews = isset( $snapshot['pending_reviews'] ) && is_array( $snapshot['pending_reviews'] ) ? $snapshot['pending_reviews'] : array();
		$recent_reviews  = isset( $snapshot['recent_reviews'] ) && is_array( $snapshot['recent_reviews'] ) ? $snapshot['recent_reviews'] : array();

		$html  = '<style>';
		$html .= '.mp-network-customer-hub{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,sans-serif;background:linear-gradient(180deg,#f8fbff 0%,#edf4fb 100%);border:1px solid #dbe6f2;border-radius:16px;padding:20px;color:#1f3346}';
		$html .= '.mp-network-customer-hub h2{margin:0 0 8px;font-size:24px;letter-spacing:.01em}';
		$html .= '.mp-hub-sub{margin:0 0 14px;color:#4a6278;font-size:13px}';
		$html .= '.mp-hub-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:14px}';
		$html .= '.mp-hub-kpi{background:#fff;border:1px solid #d7e4f0;border-radius:12px;padding:12px}';
		$html .= '.mp-hub-kpi span{display:block;font-size:11px;color:#59708a;text-transform:uppercase;letter-spacing:.04em}';
		$html .= '.mp-hub-kpi strong{display:block;margin-top:6px;font-size:20px;color:#16324b}';
		$html .= '.mp-hub-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:12px;margin-bottom:12px}';
		$html .= '.mp-hub-panel{background:#fff;border:1px solid #d7e4f0;border-radius:12px;padding:12px}';
		$html .= '.mp-hub-panel h3{margin:0 0 10px;font-size:13px;color:#35506b;text-transform:uppercase;letter-spacing:.04em}';
		$html .= '.mp-hub-list{list-style:none;margin:0;padding:0;display:grid;gap:8px}';
		$html .= '.mp-hub-list li{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px;border:1px solid #e4ecf4;border-radius:10px;background:#fbfdff}';
		$html .= '.mp-hub-meta{display:grid;gap:2px;font-size:12px;color:#4a6278}';
		$html .= '.mp-hub-meta strong{color:#1e354a}';
		$html .= '.mp-hub-cta{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid #2f5f8f;background:#2f5f8f;color:#fff;text-decoration:none;font-size:12px;font-weight:600}';
		$html .= '.mp-hub-empty{font-size:12px;color:#516981;margin:0}';
		$html .= '.mp-hub-orders{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d7e4f0;border-radius:12px;overflow:hidden}';
		$html .= '.mp-hub-orders th,.mp-hub-orders td{padding:10px;border-bottom:1px solid #edf2f7;text-align:left;font-size:12px}';
		$html .= '.mp-hub-orders th{font-size:11px;color:#607991;text-transform:uppercase;letter-spacing:.04em;background:#f7fbff}';
		$html .= '.mp-hub-orders tr:last-child td{border-bottom:0}';
		$html .= '@media (max-width:900px){.mp-hub-grid{grid-template-columns:1fr}}';
		$html .= '</style>';

		$html .= '<section class="mp-network-customer-hub">';
		$html .= '<h2>' . esc_html__( 'Zentrales Kundenportal', 'mp' ) . '</h2>';
		$html .= '<p class="mp-hub-sub">' . esc_html__( 'Netzwerkweit alle Bestellungen, offene Bewertungen und letzte Aktivitaeten auf einen Blick.', 'mp' ) . '</p>';

		$html .= '<div class="mp-hub-kpis">';
		$html .= '<div class="mp-hub-kpi"><span>' . esc_html__( 'Bestellungen', 'mp' ) . '</span><strong>' . intval( $totals['orders'] ) . '</strong></div>';
		$html .= '<div class="mp-hub-kpi"><span>' . esc_html__( 'Gesamtausgaben', 'mp' ) . '</span><strong>' . esc_html( mp_format_currency( $currency, $totals['value'] ) ) . '</strong></div>';
		$html .= '<div class="mp-hub-kpi"><span>' . esc_html__( 'Aktive Shops', 'mp' ) . '</span><strong>' . intval( $totals['shops'] ) . '</strong></div>';
		$html .= '<div class="mp-hub-kpi"><span>' . esc_html__( 'Offene Lieferung', 'mp' ) . '</span><strong>' . intval( $totals['open_shipping'] ) . '</strong></div>';
		$html .= '<div class="mp-hub-kpi"><span>' . esc_html__( 'Zu bewerten', 'mp' ) . '</span><strong>' . intval( $totals['to_review'] ) . '</strong></div>';
		$html .= '</div>';

		$html .= '<div class="mp-hub-grid">';
		$html .= '<section class="mp-hub-panel">';
		$html .= '<h3>' . esc_html__( 'Jetzt bewerten', 'mp' ) . '</h3>';
		if ( ! empty( $pending_reviews ) ) {
			$html .= '<ul class="mp-hub-list">';
			foreach ( array_slice( $pending_reviews, 0, 6 ) as $item ) {
				$status_label = isset( $status_labels[ $item['status'] ] ) ? $status_labels[ $item['status'] ] : ucfirst( str_replace( 'order_', '', $item['status'] ) );
				$html .= '<li>';
				$html .= '<div class="mp-hub-meta">';
				$html .= '<strong>' . esc_html( $item['product_name'] ) . '</strong>';
				$html .= '<span>' . sprintf( esc_html__( '%1$s · Bestellung #%2$s · %3$s', 'mp' ), esc_html( $item['shop'] ), esc_html( $item['order_id'] ), esc_html( $status_label ) ) . '</span>';
				$html .= '</div>';
				$html .= '<a class="mp-hub-cta" href="' . esc_url( $item['product_url'] ) . '">' . esc_html__( 'Bewerten', 'mp' ) . '</a>';
				$html .= '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p class="mp-hub-empty">' . esc_html__( 'Aktuell gibt es keine offenen Bewertungen fuer Dich.', 'mp' ) . '</p>';
		}
		$html .= '</section>';

		$html .= '<section class="mp-hub-panel">';
		$html .= '<h3>' . esc_html__( 'Deine letzten Bewertungen', 'mp' ) . '</h3>';
		if ( ! empty( $recent_reviews ) ) {
			$html .= '<ul class="mp-hub-list">';
			foreach ( array_slice( $recent_reviews, 0, 5 ) as $item ) {
				$html .= '<li>';
				$html .= '<div class="mp-hub-meta">';
				$html .= '<strong>' . esc_html( $item['product_name'] ) . '</strong>';
				$html .= '<span>' . sprintf( esc_html__( '%1$s · %2$s/5 Sterne', 'mp' ), esc_html( $item['shop'] ), intval( $item['rating'] ) ) . '</span>';
				$html .= '</div>';
				$html .= '<a class="mp-hub-cta" href="' . esc_url( $item['product_url'] ) . '">' . esc_html__( 'Ansehen', 'mp' ) . '</a>';
				$html .= '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p class="mp-hub-empty">' . esc_html__( 'Du hast noch keine Produktbewertungen abgegeben.', 'mp' ) . '</p>';
		}
		$html .= '</section>';
		$html .= '</div>';

		$html .= '<section class="mp-hub-panel">';
		$html .= '<h3>' . esc_html__( 'Letzte Bestellungen', 'mp' ) . '</h3>';
		if ( ! empty( $rows ) ) {
			$html .= '<table class="mp-hub-orders"><thead><tr>';
			$html .= '<th>' . esc_html__( 'Shop', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Bestellung', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Status', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Betrag', 'mp' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( array_slice( $rows, 0, 20 ) as $row ) {
				$status_label = isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : ucfirst( str_replace( 'order_', '', $row['status'] ) );
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $row['shop'] ) . '</td>';
				$html .= '<td><a href="' . esc_url( $row['url'] ) . '">#' . esc_html( $row['order'] ) . '</a></td>';
				$html .= '<td>' . esc_html( $status_label ) . '</td>';
				$html .= '<td>' . esc_html( mp_format_currency( $currency, $row['total'] ) ) . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table>';
		} else {
			$html .= '<p class="mp-hub-empty">' . esc_html__( 'Noch keine netzwerkweiten Bestellungen gefunden.', 'mp' ) . '</p>';
		}
		$html .= '</section>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * Render the optional shop performance overview for shop admins.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function mp_network_shop_performance_sc( $atts ) {
		if ( ! mp_get_network_setting( 'advanced->network_shop_performance', 0 ) ) {
			return '';
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_store_settings' ) ) {
			return '<p>' . esc_html__( 'Diese Seite ist nur fuer Shopadmins verfuegbar.', 'mp' ) . '</p>';
		}

		$current_blog_id = (int) get_current_blog_id();
		$sites           = get_sites( array( 'fields' => 'ids' ) );
		$currency        = mp_get_setting( 'currency' );
		$root_blog_id    = function_exists( 'mp_root_blog_id' ) ? (int) mp_root_blog_id() : 1;
		$global_cart     = (bool) mp_get_network_setting( 'global_cart', 0 );
		$hybrid_enabled  = (bool) mp_get_network_setting( 'advanced->hybrid_gateway_routing', 0 );

		$flow_labels = array(
			'local'          => __( 'Lokaler Flow', 'mp' ),
			'hybrid_subshop' => __( 'Hybrid Subshop', 'mp' ),
			'network_global' => __( 'Netzwerk Mainshop', 'mp' ),
			'network_multi'  => __( 'Netzwerk Multi-Shop', 'mp' ),
		);

		$flow_filter = sanitize_key( (string) mp_get_get_value( 'flow', '' ) );
		if ( $flow_filter && ! isset( $flow_labels[ $flow_filter ] ) ) {
			$flow_filter = '';
		}

		$network_totals = array(
			'orders'       => 0,
			'revenue'      => 0.0,
			'flow_counts'  => array_fill_keys( array_keys( $flow_labels ), 0 ),
			'status_counts' => array(
				'order_received' => 0,
				'order_paid'     => 0,
				'order_shipped'  => 0,
				'order_closed'   => 0,
			),
		);
		$shop_totals = array();

		foreach ( $sites as $blog_id ) {
			$blog_id = (int) $blog_id;
			switch_to_blog( $blog_id );

			$orders = get_posts( array(
				'post_type'        => 'mp_order',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			) );

			$shop_totals[ $blog_id ] = array(
				'orders'  => 0,
				'revenue' => 0.0,
				'name'    => get_option( 'blogname' ),
			);

			foreach ( $orders as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					continue;
				}

				$flow_mode = $this->get_order_flow_mode_for_performance( (int) $post_id, $blog_id, $root_blog_id, $global_cart, $hybrid_enabled );
				if ( isset( $network_totals['flow_counts'][ $flow_mode ] ) ) {
					$network_totals['flow_counts'][ $flow_mode ] ++;
				}

				if ( $flow_filter && $flow_mode !== $flow_filter ) {
					continue;
				}

				$order_total = (float) get_post_meta( $post_id, 'mp_order_total', true );
				$shop_totals[ $blog_id ]['orders'] ++;
				$shop_totals[ $blog_id ]['revenue'] += $order_total;

				$network_totals['orders'] ++;
				$network_totals['revenue'] += $order_total;

				if ( isset( $network_totals['status_counts'][ $post->post_status ] ) ) {
					$network_totals['status_counts'][ $post->post_status ] ++;
				}
			}

			restore_current_blog();
		}

		$current = isset( $shop_totals[ $current_blog_id ] ) ? $shop_totals[ $current_blog_id ] : array(
			'orders'  => 0,
			'revenue' => 0.0,
			'name'    => get_option( 'blogname' ),
		);

		$active_shops = 0;
		foreach ( $shop_totals as $shop_total ) {
			if ( ! empty( $shop_total['orders'] ) ) {
				$active_shops ++;
			}
		}

		$network_aov = $network_totals['orders'] > 0 ? ( $network_totals['revenue'] / $network_totals['orders'] ) : 0;
		$current_aov = $current['orders'] > 0 ? ( $current['revenue'] / $current['orders'] ) : 0;
		$shop_share  = $network_totals['revenue'] > 0 ? ( $current['revenue'] / $network_totals['revenue'] ) * 100 : 0;

		$base_url   = get_permalink( get_the_ID() );
		$flow_links = array();
		$flow_links[] = '<a class="' . ( '' === $flow_filter ? 'is-active' : '' ) . '" href="' . esc_url( remove_query_arg( 'flow', $base_url ) ) . '">' . esc_html__( 'Alle Flows', 'mp' ) . '</a>';
		foreach ( $flow_labels as $flow_key => $flow_label ) {
			$flow_links[] = '<a class="' . ( $flow_filter === $flow_key ? 'is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'flow', $flow_key, $base_url ) ) . '">' . esc_html( $flow_label ) . '</a>';
		}

		uasort( $shop_totals, function( $a, $b ) {
			$revenue_cmp = (float) $b['revenue'] <=> (float) $a['revenue'];
			if ( 0 !== $revenue_cmp ) {
				return $revenue_cmp;
			}

			$order_cmp = (int) $b['orders'] <=> (int) $a['orders'];
			if ( 0 !== $order_cmp ) {
				return $order_cmp;
			}

			return strcmp( (string) $a['name'], (string) $b['name'] );
		} );
		$top_rows = array_slice( $shop_totals, 0, 6, true );

		$html  = '<style>';
		$html .= '.mp-network-shop-performance{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,sans-serif;background:linear-gradient(180deg,#f8fbff 0%,#edf4fb 100%);border:1px solid #dbe6f2;border-radius:16px;padding:20px;color:#1f3346}';
		$html .= '.mp-network-shop-performance h2{margin:0 0 8px;font-size:24px;letter-spacing:.01em}';
		$html .= '.mp-perf-sub{margin:0 0 14px;color:#4a6278;font-size:13px}';
		$html .= '.mp-flow-filter{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}';
		$html .= '.mp-flow-filter a{display:inline-block;padding:7px 10px;border-radius:999px;border:1px solid #c6d6e8;background:#fff;color:#35506b;text-decoration:none;font-size:12px;font-weight:600}';
		$html .= '.mp-flow-filter a.is-active{background:#2f5f8f;color:#fff;border-color:#2f5f8f}';
		$html .= '.mp-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:14px}';
		$html .= '.mp-kpi-card{background:#fff;border:1px solid #d7e4f0;border-radius:12px;padding:12px}';
		$html .= '.mp-kpi-card span{display:block;font-size:11px;color:#59708a;text-transform:uppercase;letter-spacing:.04em}';
		$html .= '.mp-kpi-card strong{display:block;margin-top:6px;font-size:20px;color:#16324b}';
		$html .= '.mp-perf-layout{display:grid;grid-template-columns:2fr 1fr;gap:12px}';
		$html .= '.mp-perf-panel{background:#fff;border:1px solid #d7e4f0;border-radius:12px;padding:12px}';
		$html .= '.mp-perf-panel h3{margin:0 0 10px;font-size:13px;color:#35506b;text-transform:uppercase;letter-spacing:.04em}';
		$html .= '.mp-flow-bars{display:grid;gap:8px}';
		$html .= '.mp-flow-row{display:grid;grid-template-columns:130px 1fr 48px;align-items:center;gap:8px;font-size:12px;color:#3e5972}';
		$html .= '.mp-flow-track{height:8px;border-radius:999px;background:#e8f0f8;overflow:hidden}';
		$html .= '.mp-flow-fill{height:100%;background:linear-gradient(90deg,#4f89bf 0%,#6fb0de 100%)}';
		$html .= '.mp-shop-table{width:100%;border-collapse:collapse;font-size:12px}';
		$html .= '.mp-shop-table th,.mp-shop-table td{padding:8px;border-bottom:1px solid #edf2f7;text-align:left}';
		$html .= '.mp-shop-table th{font-size:11px;color:#607991;text-transform:uppercase;letter-spacing:.04em}';
		$html .= '@media (max-width:900px){.mp-perf-layout{grid-template-columns:1fr}}';
		$html .= '</style>';

		$html .= '<section class="mp-network-shop-performance">';
		$html .= '<h2>' . esc_html__( 'Shopuser Performance', 'mp' ) . '</h2>';
		$html .= '<p class="mp-perf-sub">' . esc_html__( 'E-Commerce Dashboard fuer deinen Shop im Netzwerk mit Flow-Filter und Live-Kennzahlen.', 'mp' ) . '</p>';
		$html .= '<div class="mp-flow-filter">' . implode( '', $flow_links ) . '</div>';

		$html .= '<div class="mp-kpi-grid">';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Dein Shop', 'mp' ) . '</span><strong>' . esc_html( $current['name'] ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Eigene Bestellungen', 'mp' ) . '</span><strong>' . intval( $current['orders'] ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Eigener Umsatz', 'mp' ) . '</span><strong>' . esc_html( mp_format_currency( $currency, $current['revenue'] ) ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Netzwerk-Bestellungen', 'mp' ) . '</span><strong>' . intval( $network_totals['orders'] ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Netzwerk-Umsatz', 'mp' ) . '</span><strong>' . esc_html( mp_format_currency( $currency, $network_totals['revenue'] ) ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Shop-Anteil Umsatz', 'mp' ) . '</span><strong>' . esc_html( number_format_i18n( $shop_share, 1 ) ) . '%</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'AOV Shop', 'mp' ) . '</span><strong>' . esc_html( mp_format_currency( $currency, $current_aov ) ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'AOV Netzwerk', 'mp' ) . '</span><strong>' . esc_html( mp_format_currency( $currency, $network_aov ) ) . '</strong></div>';
		$html .= '</div>';

		$total_flow_orders = array_sum( $network_totals['flow_counts'] );

		$html .= '<div class="mp-perf-layout">';
		$html .= '<div class="mp-perf-panel">';
		$html .= '<h3>' . esc_html__( 'Top Shops im Netzwerk', 'mp' ) . '</h3>';
		$html .= '<table class="mp-shop-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'Shop', 'mp' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Bestellungen', 'mp' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Umsatz', 'mp' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		if ( empty( $top_rows ) ) {
			$html .= '<tr><td colspan="3">' . esc_html__( 'Keine Daten fuer den gewaelten Flow vorhanden.', 'mp' ) . '</td></tr>';
		} else {
			foreach ( $top_rows as $shop_row ) {
				if ( empty( $shop_row['orders'] ) ) {
					continue;
				}
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $shop_row['name'] ) . '</td>';
				$html .= '<td>' . intval( $shop_row['orders'] ) . '</td>';
				$html .= '<td>' . esc_html( mp_format_currency( $currency, $shop_row['revenue'] ) ) . '</td>';
				$html .= '</tr>';
			}
		}

		$html .= '</tbody></table>';
		$html .= '</div>';

		$html .= '<div class="mp-perf-panel">';
		$html .= '<h3>' . esc_html__( 'Flow-Verteilung', 'mp' ) . '</h3>';
		$html .= '<div class="mp-flow-bars">';
		foreach ( $flow_labels as $flow_key => $flow_label ) {
			$flow_count = isset( $network_totals['flow_counts'][ $flow_key ] ) ? (int) $network_totals['flow_counts'][ $flow_key ] : 0;
			$flow_pct   = $total_flow_orders > 0 ? ( $flow_count / $total_flow_orders ) * 100 : 0;
			$html .= '<div class="mp-flow-row">';
			$html .= '<span>' . esc_html( $flow_label ) . '</span>';
			$html .= '<div class="mp-flow-track"><div class="mp-flow-fill" style="width:' . esc_attr( number_format( $flow_pct, 2, '.', '' ) ) . '%"></div></div>';
			$html .= '<strong>' . intval( $flow_count ) . '</strong>';
			$html .= '</div>';
		}
		$html .= '</div>';

		$html .= '<h3 style="margin-top:14px">' . esc_html__( 'Status-Mix (gefiltert)', 'mp' ) . '</h3>';
		$html .= '<div class="mp-flow-bars">';
		foreach ( $network_totals['status_counts'] as $status_key => $status_count ) {
			$status_label = ucfirst( str_replace( 'order_', '', $status_key ) );
			$status_pct   = $network_totals['orders'] > 0 ? ( $status_count / $network_totals['orders'] ) * 100 : 0;
			$html .= '<div class="mp-flow-row">';
			$html .= '<span>' . esc_html( $status_label ) . '</span>';
			$html .= '<div class="mp-flow-track"><div class="mp-flow-fill" style="width:' . esc_attr( number_format( $status_pct, 2, '.', '' ) ) . '%"></div></div>';
			$html .= '<strong>' . intval( $status_count ) . '</strong>';
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<p class="mp-perf-sub" style="margin-top:14px">';
		$html .= esc_html__( 'Aktive Shops im Filter:', 'mp' ) . ' ' . intval( $active_shops ) . ' · ';
		$html .= esc_html__( 'Flow-Kontext:', 'mp' ) . ' ' . esc_html( $flow_filter && isset( $flow_labels[ $flow_filter ] ) ? $flow_labels[ $flow_filter ] : __( 'Alle Flows', 'mp' ) );
		$html .= '</p>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * Resolve flow mode for one network order to support flow-based performance metrics.
	 *
	 * @param int  $order_id
	 * @param int  $blog_id
	 * @param int  $root_blog_id
	 * @param bool $global_cart
	 * @param bool $hybrid_enabled
	 *
	 * @return string
	 */
	private function get_order_flow_mode_for_performance( $order_id, $blog_id, $root_blog_id, $global_cart, $hybrid_enabled ) {
		if ( ! is_multisite() || ! $global_cart ) {
			return 'local';
		}

		$order    = new MP_Order( (int) $order_id );
		$cart     = $order->get_cart();
		$blog_ids = array();

		if ( is_object( $cart ) && method_exists( $cart, 'get_blog_ids' ) ) {
			$blog_ids = array_filter( array_map( 'intval', (array) $cart->get_blog_ids() ) );
		}

		if ( empty( $blog_ids ) ) {
			$blog_ids = array( (int) $blog_id );
		}

		$blog_ids = array_values( array_unique( $blog_ids ) );

		if ( $hybrid_enabled && 1 === count( $blog_ids ) && (int) reset( $blog_ids ) !== (int) $root_blog_id ) {
			return 'hybrid_subshop';
		}

		if ( count( $blog_ids ) > 1 ) {
			return 'network_multi';
		}

		return 'network_global';
	}

	/**
	 * Check to see if install sequence needs to be run
	 *
	 * @since 1.0
	 * @access public
	 */
	public function maybe_install() {
		$build = (int) get_site_option( 'mp_network_build', 1 );

		//check if installed
		if ( $this->build === $build ) {
			return;
		}

		//$this->drop_old_ms_tables();
		$this->maybe_create_ms_tables();


		update_site_option( 'mp_network_build', $this->build );
	}

	function maybe_create_ms_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_product = $wpdb->base_prefix . 'mp_products';
		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_product ) ) == $table_product ) {
			$table_1 = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}mp_products` (
								`id` bigint(20) unsigned NOT NULL auto_increment,
								`site_id` bigint(20),
								`blog_id` bigint(20),
								`blog_public` int(2),
								`post_id` bigint(20),
								`post_author` bigint(20) unsigned NOT NULL DEFAULT '0',
								`post_title` text NOT NULL,
								`post_content` longtext NOT NULL,
								`post_excerpt` longtext NOT NULL,
								`post_permalink` text NOT NULL,
								`post_status` varchar(20) NOT NULL,
								`post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
								`post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
								`post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
								`post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
								`price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00',
								`sales_count` bigint(20) unsigned NOT NULL DEFAULT '0',
								PRIMARY KEY	 (`id`)
							) ENGINE=MyISAM	 DEFAULT CHARSET=utf8;";
			dbDelta( $table_1 );
		}
		$table_terms = $wpdb->base_prefix . 'mp_terms';
		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_terms ) ) == $table_terms ) {
			$table_2 = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}mp_terms` (
								`term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
								`name` varchar(200) NOT NULL DEFAULT '',
								`slug` varchar(200) NOT NULL DEFAULT '',
								`type` varchar(20) NOT NULL DEFAULT 'product_category',
								`count` bigint(10) NOT NULL DEFAULT '0',
								PRIMARY KEY (`term_id`),
								KEY `name` (`name`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
			dbDelta( $table_2 );
		}

		$table_relations = $wpdb->base_prefix . 'mp_term_relationships';
		if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_relations ) ) == $table_relations ) {
			$table_3 = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}mp_term_relationships` (
								`post_id` bigint(20) unsigned NOT NULL,
								`blog_id` bigint(20) unsigned NOT NULL,
								`term_id` bigint(20) unsigned NOT NULL,
								`public`  boolean NOT NULL DEFAULT 1,
								PRIMARY KEY ( `post_id` , `term_id` ),
								KEY (`term_id`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
			dbDelta( $table_3 );
		}
	}

	/**
	 * Add/update network settings
	 *
	 * @since 1.0
	 * @access public
	 */
	public function ms_settings() {
		$settings = get_site_option( 'mp_network_settings', array() );

		$default_settings = array(
			'global_cart'      => 0,
			'allowed_gateways' => array(),
			'global_gateway'   => 'paypal_express',
			'allowed_themes'   => array(
				'default' => 'full',
			),
			'advanced'         => array(
				'hybrid_gateway_routing' => 0,
				'network_customer_hub'   => 0,
				'network_shop_performance' => 0,
				'settlement_enabled'     => 0,
				'settlement_auto_release' => 0,
				'settlement_hold_days'   => 14,
			),
		);

		if ( ! class_exists( 'MP_Gateway_API' ) ) {
			require_once mp_plugin_dir( 'includes/common/payment-gateways/class-mp-gateway-api.php' );
		}

		$gateways = MP_Gateway_API::get_gateways();
		foreach ( $gateways as $code => $gateway ) {
			$access = ( $gateway->plugin_name != 'paypal_express' ) ? 'none' : 'full';
			mp_push_to_array( $default_settings, "allowed_gateways->{$code}", $access );
		}

		$new_settings = array_replace_recursive( $default_settings, $settings );

		update_site_option( 'mp_network_settings', $new_settings );
	}

	/**
	 * Make sure product post types are indexed by Post Indexer
	 *
	 * @since 1.0
	 * @access public
	 */
	public function post_indexer_set_post_types() {
		$pi_post_types = (array) get_site_option( 'postindexer_globalposttypes', array( 'post' ) );
		$changed       = false;

		foreach ( mp()->post_types as $post_type ) {
			if ( ! in_array( $post_type, $pi_post_types ) ) {
				$pi_post_types[] = $post_type;
				$changed         = true;
			}
		}

		if ( $changed ) {
			update_site_option( 'postindexer_globalposttypes', $pi_post_types );
		}
	}

	/**
	 * Get a canonical product URL for a blog/product pair.
	 *
	 * @since 1.0
	 * @access public
	 */
	protected function get_canonical_product_url( $product_id, $blog_id = null ) {
		$current_blog_id = get_current_blog_id();

		if ( null === $blog_id ) {
			$blog_id = $current_blog_id;
		}

		if ( function_exists( 'get_blog_permalink' ) ) {
			return get_blog_permalink( $blog_id, $product_id );
		}

		if ( $blog_id !== $current_blog_id ) {
			switch_to_blog( $blog_id );
		}

		$url = get_permalink( $product_id );

		if ( $blog_id !== $current_blog_id ) {
			restore_current_blog();
		}

		return $url;
	}

	/**
	 * Resolve the canonical permalink for an indexed product.
	 *
	 * If the stored permalink is stale, refresh it in the index so global product
	 * listings gradually self-heal after deploy.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_indexed_product_url( $blog_id, $product_id, $fallback_url = '' ) {
		global $wpdb;

		$index         = $this->find_index( $blog_id, $product_id );
		$canonical_url = $this->get_canonical_product_url( $product_id, $blog_id );

		if ( ! empty( $canonical_url ) ) {
			if ( $index && $index->post_permalink !== $canonical_url ) {
				$wpdb->update(
					$wpdb->base_prefix . 'mp_products',
					array( 'post_permalink' => $canonical_url ),
					array(
						'post_id' => $product_id,
						'blog_id' => $blog_id,
					)
				);
			}

			return $canonical_url;
		}

		if ( $index && ! empty( $index->post_permalink ) ) {
			return $index->post_permalink;
		}

		return $fallback_url;
	}

	/**
	 * Build a rewrite-independent product URL for a given blog/product.
	 *
	 * Using query args keeps links stable even when permastructs differ across
	 * subsites or when rewrite state is stale in switched blog contexts.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function get_reliable_product_url( $blog_id, $product_id ) {
		$current_blog_id = get_current_blog_id();

		if ( $blog_id !== $current_blog_id ) {
			switch_to_blog( $blog_id );
		}

		$post_type = get_post_type( $product_id );
		if ( empty( $post_type ) ) {
			$post_type = MP_Product::get_post_type();
		}

		$url = add_query_arg(
			array(
				'p'         => absint( $product_id ),
				'post_type' => $post_type,
			),
			home_url( '/' )
		);

		if ( $blog_id !== $current_blog_id ) {
			restore_current_blog();
		}

		return $url;
	}

	/**
	 * Get the correct product url when global cart is enabled.
	 *
	 * Prefer the canonical permalink stored in the multisite index so global
	 * product links stay stable even when subsites use different product rewrite
	 * structures.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function product_url( $url, $product ) {
		$blog_id = get_current_blog_id();

		if ( $product->is_variation() && $product->get_parent() !== false ) {
			$parent = $product->get_parent();
			$parent_url = $this->get_reliable_product_url( $blog_id, $parent->ID );

			if ( ! empty( $parent_url ) ) {
				return trailingslashit( $parent_url ) . 'variation/' . $product->ID;
			}

			return $url;
		}

		$product_url = $this->get_reliable_product_url( $blog_id, $product->ID );
		if ( ! empty( $product_url ) ) {
			return $product_url;
		}

		return $url;
	}

	/**
	 * Reload MP settings after switching blogs
	 *
	 * When using switch_to_blog auto-loaded options aren't refreshed which causes
	 * mp_settings to not update accordingly which affects things like tax and
	 * shipping rates.
	 *
	 * @since 1.0
	 * @access public
	 * @action switch_blog
	 */
	public function refresh_autoloaded_options() {
		wp_load_alloptions();
	}

}

MP_Multisite::get_instance();