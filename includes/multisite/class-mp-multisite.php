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
		add_filter( 'mp_multisite/global_product_url', array( &$this, 'filter_global_product_navigation_url' ), 10, 4 );

		add_filter( 'mp_gateway_api/get_gateways', array( &$this, 'get_gateways' ) );
		add_filter( 'mp_gateway_api/use_network_global_gateway', array( &$this, 'filter_use_network_global_gateway' ) );
		add_action( 'init', array( &$this, 'capture_network_multishop_checkout_choice' ), 1 );
		add_filter( 'mp_checkout/order_review', array( &$this, 'inject_multishop_checkout_selector' ) );
		add_filter( 'mp_can_checkout', array( &$this, 'guard_split_checkout_until_cart_partition' ), 10, 5 );
		add_action( 'mp_order/new_order', array( &$this, 'annotate_network_multishop_order' ), 20 );

		$settings = get_site_option( 'mp_network_settings', array() );
		if ( ( isset($settings['main_blog']) && mp_is_main_site() ) || isset($settings['main_blog']) && !$settings['main_blog'] ) {
			//shortcode
			add_shortcode( 'mp_list_global_products', array( &$this, 'mp_list_global_products_sc' ) );
			add_shortcode( 'mp_global_categories_list', array( &$this, 'mp_global_categories_list_sc' ) );
			add_shortcode( 'mp_global_tag_cloud', array( &$this, 'mp_global_tag_cloud_sc' ) );
		}

		add_shortcode( 'mp_network_customer_hub', array( &$this, 'mp_network_customer_hub_sc' ) );
		add_shortcode( 'mp_network_shop_performance', array( &$this, 'mp_network_shop_performance_sc' ) );
		add_shortcode( 'mp_network_shop_profile', array( &$this, 'mp_network_shop_profile_sc' ) );

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
		$blog_id  = absint( mp_get_post_value( 'blog_id', 0 ) );
		$limit    = absint( mp_get_post_value( 'limit', 0 ) );
		$list_view = mp_get_post_value( 'list_view', null );
		$filters  = absint( mp_get_post_value( 'filters', 0 ) );
		$paginate = absint( mp_get_post_value( 'paginate', 0 ) );
		$disable_category_filter = absint( mp_get_post_value( 'disable_category_filter', 0 ) );
		$exclude_post_ids = array_filter( array_map( 'absint', explode( ',', (string) mp_get_post_value( 'exclude_post_ids', '' ) ) ) );
		echo mp_global_list_products( array(
			'page'      => $page,
			'order_by'  => trim( $order_by ),
			'order'     => trim( $order ),
			'widget_id' => $widget_id,
			'category'  => $category,
			'blog_id'   => $blog_id,
			'limit'     => $limit,
			'list_view' => null === $list_view ? null : (bool) absint( $list_view ),
			'filters'   => (bool) $filters,
			'paginate'  => (bool) $paginate,
			'disable_category_filter' => (bool) $disable_category_filter,
			'exclude_post_ids' => $exclude_post_ids,
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
	 * Determine whether global product links should route to the network shop profile page.
	 *
	 * @return bool
	 */
	public function use_network_shop_profile_mode() {
		$mode = sanitize_key( (string) mp_get_network_setting( 'advanced->network_shop_presentation_mode', 'direct_product' ) );
		if ( 'shop_profile' !== $mode ) {
			return false;
		}

		$page_id = (int) mp_get_network_setting( 'pages->network_shop_profile', 0 );

		return $page_id > 0 && false !== get_post_status( $page_id );
	}

	/**
	 * Build a URL to the central network shop profile page.
	 *
	 * @param int $blog_id
	 * @param int $product_id
	 * @return string
	 */
	public function get_network_shop_profile_url( $blog_id, $product_id = 0 ) {
		$blog_id = $this->normalize_network_shop_id( $blog_id );

		$page_id = (int) mp_get_network_setting( 'pages->network_shop_profile', 0 );
		if ( $page_id <= 0 ) {
			return '';
		}

		$base_url = get_permalink( $page_id );
		if ( empty( $base_url ) ) {
			return '';
		}

		$args = array(
			'mp_network_shop' => absint( $blog_id ),
		);

		if ( absint( $product_id ) > 0 ) {
			$args['mp_network_product'] = absint( $product_id );
		}

		return add_query_arg( $args, $base_url );
	}

	/**
	 * Normalize network shop blog ID and recover from legacy/invalid values.
	 *
	 * @param int $blog_id
	 * @return int
	 */
	public function normalize_network_shop_id( $blog_id ) {
		$blog_id = absint( $blog_id );

		if ( $blog_id > 0 && get_blog_details( $blog_id ) ) {
			return $blog_id;
		}

		if ( defined( 'MP_ROOT_BLOG' ) && absint( MP_ROOT_BLOG ) > 0 ) {
			return absint( MP_ROOT_BLOG );
		}

		if ( function_exists( 'get_main_site_id' ) ) {
			$main_id = absint( get_main_site_id() );
			if ( $main_id > 0 ) {
				return $main_id;
			}
		}

		return 1;
	}

	/**
	 * Route global product links either to subshop products or to central shop profile URLs.
	 *
	 * @param string $url
	 * @param int    $blog_id
	 * @param int    $product_id
	 * @param array  $args
	 * @return string
	 */
	public function filter_global_product_navigation_url( $url, $blog_id, $product_id, $args = array() ) {
		if ( ! $this->use_network_shop_profile_mode() ) {
			return $url;
		}

		$blog_id     = $this->normalize_network_shop_id( $blog_id );
		$profile_url = $this->get_network_shop_profile_url( $blog_id, $product_id );
		if ( ! empty( $profile_url ) ) {
			$profile_url = add_query_arg( 'mp_profile_tab', 'products', $profile_url );
		}

		return ! empty( $profile_url ) ? $profile_url : $url;
	}

	/**
	 * Get configured network reviews policy mode.
	 *
	 * @return string
	 */
	public function get_network_reviews_policy_mode() {
		$mode = sanitize_key( (string) mp_get_network_setting( 'advanced->network_reviews_policy', 'advisory' ) );
		if ( ! in_array( $mode, array( 'off', 'advisory', 'required' ), true ) ) {
			$mode = 'advisory';
		}

		return $mode;
	}

	/**
	 * Check if reviews addon is enabled on a given blog.
	 *
	 * @param int $blog_id
	 * @return bool
	 */
	public function is_reviews_addon_enabled_for_blog( $blog_id ) {
		$blog_id = absint( $blog_id );
		if ( $blog_id <= 0 || ! function_exists( 'mp_addons' ) ) {
			return false;
		}

		$current_blog_id = get_current_blog_id();
		if ( $blog_id !== $current_blog_id ) {
			switch_to_blog( $blog_id );
		}

		$is_enabled = mp_addons()->is_addon_enabled( 'MP_MARKETPRESS_COMMENTS_Addon' );

		if ( $blog_id !== $current_blog_id ) {
			restore_current_blog();
		}

		return (bool) $is_enabled;
	}

	/**
	 * Build a network snapshot for reviews addon policy checks.
	 *
	 * @return array
	 */
	public function get_network_reviews_policy_snapshot() {
		$snapshot = array(
			'total'          => 0,
			'enabled'        => 0,
			'disabled'       => 0,
			'disabled_shops' => array(),
		);

		if ( ! is_multisite() || ! function_exists( 'get_sites' ) ) {
			return $snapshot;
		}

		$site_ids = get_sites(
			array(
				'fields'   => 'ids',
				'spam'     => 0,
				'deleted'  => 0,
				'archived' => 0,
			)
		);

		foreach ( (array) $site_ids as $site_id ) {
			$blog_id = absint( $site_id );
			if ( $blog_id <= 0 ) {
				continue;
			}

			$snapshot['total']++;
			if ( $this->is_reviews_addon_enabled_for_blog( $blog_id ) ) {
				$snapshot['enabled']++;
			} else {
				$snapshot['disabled']++;
				$details = get_blog_details( $blog_id );
				$snapshot['disabled_shops'][] = array(
					'id'   => $blog_id,
					'name' => $details ? $details->blogname : sprintf( __( 'Shop #%d', 'mp' ), $blog_id ),
				);
			}
		}

		return $snapshot;
	}

	/**
	 * Format coupon minimum order requirement with robust meta fallbacks.
	 *
	 * @param int $coupon_id
	 * @return string
	 */
	private function get_coupon_minimum_amount_text( $coupon_id ) {
		$meta_keys = array(
			'min_order_total',
			'min_cart_total',
			'minimum_order_amount',
			'minimum_amount',
			'min_amount',
		);

		foreach ( $meta_keys as $meta_key ) {
			$value = get_post_meta( $coupon_id, $meta_key, true );
			if ( '' === (string) $value || ! is_numeric( $value ) ) {
				continue;
			}

			return mp_format_currency( '', (float) $value );
		}

		return '';
	}

	/**
	 * Render central profile page for a selected network shop and product.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function mp_network_shop_profile_sc( $atts ) {
		global $wpdb;

		$atts = shortcode_atts(
			array(
				'shop_id'       => 0,
				'product_id'    => 0,
				'products_limit' => 6,
				'coupons_limit'  => 4,
			),
			$atts
		);

		$shop_id = absint( $atts['shop_id'] );
		if ( isset( $_GET['mp_network_shop'] ) ) {
			$shop_id = absint( wp_unslash( $_GET['mp_network_shop'] ) );
		}
		$shop_id = $this->normalize_network_shop_id( $shop_id );

		$product_id = absint( $atts['product_id'] );
		if ( isset( $_GET['mp_network_product'] ) ) {
			$product_id = absint( wp_unslash( $_GET['mp_network_product'] ) );
		}

		$active_tab = isset( $_GET['mp_profile_tab'] ) ? sanitize_key( wp_unslash( $_GET['mp_profile_tab'] ) ) : 'products';
		if ( ! in_array( $active_tab, array( 'products', 'coupons', 'reviews' ), true ) ) {
			$active_tab = 'products';
		}

		if ( $shop_id <= 0 || ! get_blog_details( $shop_id ) ) {
			return '<div class="mp-network-shop-profile mp-network-shop-profile--empty"><p>' . esc_html__( 'Bitte waehle zuerst einen Shop aus dem Netzwerk-Marktplatz.', 'mp' ) . '</p></div>';
		}

		$policy_mode      = $this->get_network_reviews_policy_mode();
		$policy_snapshot  = array( 'total' => 0, 'enabled' => 0, 'disabled' => 0, 'disabled_shops' => array() );
		$reviews_enabled  = $this->is_reviews_addon_enabled_for_blog( $shop_id );
		if ( 'off' !== $policy_mode ) {
			$policy_snapshot = $this->get_network_reviews_policy_snapshot();
		}
		$reviews_blocked = ( 'required' === $policy_mode && ! $reviews_enabled );

		$current_blog_id = get_current_blog_id();
		switch_to_blog( $shop_id );

		$shop_name       = get_bloginfo( 'name' );
		$shop_url        = home_url( '/' );
		$shop_addons_url = admin_url( 'admin.php?page=store-settings-addons&addon=MP_MARKETPRESS_COMMENTS_Addon' );
		$shop_profile_settings_url = admin_url( 'admin.php?page=store-settings-shop-profile' );

		$profile_settings = array();
		if ( function_exists( 'mp_network_shop_profile_get_settings' ) ) {
			$profile_settings = (array) mp_network_shop_profile_get_settings( $shop_id );
		}

		$profile_title   = ! empty( $profile_settings['display_name'] ) ? (string) $profile_settings['display_name'] : $shop_name;
		$profile_tagline = isset( $profile_settings['tagline'] ) ? (string) $profile_settings['tagline'] : '';
		$profile_about   = isset( $profile_settings['about'] ) ? (string) $profile_settings['about'] : '';
		$theme_primary   = isset( $profile_settings['theme_primary'] ) ? sanitize_hex_color( (string) $profile_settings['theme_primary'] ) : '';
		$theme_accent    = isset( $profile_settings['theme_accent'] ) ? sanitize_hex_color( (string) $profile_settings['theme_accent'] ) : '';
		$theme_bg_start  = isset( $profile_settings['theme_bg_start'] ) ? sanitize_hex_color( (string) $profile_settings['theme_bg_start'] ) : '';
		$theme_bg_end    = isset( $profile_settings['theme_bg_end'] ) ? sanitize_hex_color( (string) $profile_settings['theme_bg_end'] ) : '';
		$theme_card_bg   = isset( $profile_settings['theme_card_bg'] ) ? sanitize_hex_color( (string) $profile_settings['theme_card_bg'] ) : '';

		$theme_primary  = $theme_primary ? $theme_primary : '#2f6ca3';
		$theme_accent   = $theme_accent ? $theme_accent : '#1e3348';
		$theme_bg_start = $theme_bg_start ? $theme_bg_start : '#eef6ff';
		$theme_bg_end   = $theme_bg_end ? $theme_bg_end : '#f8fcff';
		$theme_card_bg  = $theme_card_bg ? $theme_card_bg : '#ffffff';

		$related_products_enabled = ! isset( $profile_settings['related_products_enabled'] ) || (bool) $profile_settings['related_products_enabled'];
		$related_products_title   = ! empty( $profile_settings['related_products_title'] ) ? (string) $profile_settings['related_products_title'] : __( 'Weitere Produkte aus diesem Shop', 'mp' );
		$related_products_limit   = max( 1, min( 12, absint( isset( $profile_settings['related_products_limit'] ) ? $profile_settings['related_products_limit'] : $atts['products_limit'] ) ) );
		$related_products_order_by = isset( $profile_settings['related_products_order_by'] ) ? sanitize_key( (string) $profile_settings['related_products_order_by'] ) : 'date';
		$related_products_order    = isset( $profile_settings['related_products_order'] ) ? strtoupper( sanitize_key( (string) $profile_settings['related_products_order'] ) ) : 'DESC';
		$related_products_list_view = isset( $profile_settings['related_products_list_view'] ) ? (bool) absint( $profile_settings['related_products_list_view'] ) : false;
		$related_products_filters   = ! empty( $profile_settings['related_products_filters'] );
		$related_products_prefilter_ids = array_filter( array_map( 'absint', explode( ',', (string) ( isset( $profile_settings['related_products_prefilter_categories'] ) ? $profile_settings['related_products_prefilter_categories'] : '' ) ) ) );
		$active_profile_category = isset( $_GET['mp_profile_category'] ) ? absint( wp_unslash( $_GET['mp_profile_category'] ) ) : 0;

		if ( ! in_array( $related_products_order_by, array( 'date', 'title', 'price', 'sales', 'rand' ), true ) ) {
			$related_products_order_by = 'date';
		}
		if ( ! in_array( $related_products_order, array( 'ASC', 'DESC' ), true ) ) {
			$related_products_order = 'DESC';
		}

		$profile_image_url = '';
		if ( ! empty( $profile_settings['hero_image_id'] ) ) {
			$image_src = wp_get_attachment_image_src( absint( $profile_settings['hero_image_id'] ), 'large' );
			if ( is_array( $image_src ) && ! empty( $image_src[0] ) ) {
				$profile_image_url = (string) $image_src[0];
			}
		}
		if ( empty( $profile_image_url ) && ! empty( $profile_settings['hero_image_url'] ) ) {
			$profile_image_url = (string) $profile_settings['hero_image_url'];
		}

		$related_prefilter_terms = array();
		if ( ! empty( $related_products_prefilter_ids ) ) {
			$term_objects = get_terms( array(
				'taxonomy'   => 'product_category',
				'hide_empty' => false,
				'include'    => $related_products_prefilter_ids,
			) );
			if ( ! is_wp_error( $term_objects ) ) {
				foreach ( (array) $term_objects as $term_object ) {
					$related_prefilter_terms[ (int) $term_object->term_id ] = $term_object;
				}
			}
			if ( $active_profile_category > 0 && ! isset( $related_prefilter_terms[ $active_profile_category ] ) ) {
				$active_profile_category = 0;
			}
		}

		$shop_rating_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_reviews, AVG(CAST(cm.meta_value AS DECIMAL(10,2))) AS avg_rating
				 FROM {$wpdb->comments} c
				 INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rating'
				 INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
				 WHERE p.post_type = %s AND p.post_status = 'publish' AND c.comment_approved = '1'",
				MP_Product::get_post_type()
			),
			ARRAY_A
		);
		$shop_avg_rating    = isset( $shop_rating_row['avg_rating'] ) ? round( (float) $shop_rating_row['avg_rating'], 1 ) : 0;
		$shop_total_reviews = isset( $shop_rating_row['total_reviews'] ) ? (int) $shop_rating_row['total_reviews'] : 0;

		if ( $product_id <= 0 ) {
			$fallback_products = get_posts(
				array(
					'post_type'      => MP_Product::get_post_type(),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			$product_id = ! empty( $fallback_products ) ? absint( $fallback_products[0] ) : 0;
		}

		$product_title = '';
		$product_html  = '';
		$product_url   = '';

		if ( $product_id > 0 ) {
			$product = new MP_Product( $product_id );
			if ( $product->exists() ) {
				$product_title = wp_strip_all_tags( $product->title( false ) );
				$product_url   = $this->get_reliable_product_url( $shop_id, $product_id );

				if ( mp_get_network_setting( 'global_cart', 0 ) ) {
					$product_html = mp_product( false, $product_id, true, 'full', 'single', true );
				} else {
					$product_html  = '<article class="mp-network-product-fallback">';
					$product_html .= '<h2>' . esc_html( $product_title ) . '</h2>';
					$product_html .= '<div class="mp-network-product-price">' . $product->display_price( false ) . '</div>';
					$product_html .= '<div class="mp-network-product-excerpt">' . $product->excerpt( null, null, '' ) . '</div>';
					$product_html .= '<p><a class="mp_button mp_link-buynow" href="' . esc_url( $product_url ) . '">' . esc_html__( 'Zum Produkt im Subshop', 'mp' ) . '</a></p>';
					$product_html .= '</article>';
				}
			}
		}

		$related_products_total = 0;
		$related_products_html  = '';

		$reviews_html           = '';
		$reviews_summary_html   = '';
		$reviews_setting_active = (bool) mp_get_network_setting( 'advanced->network_shop_profile_reviews', 0 );
		if ( ! $reviews_setting_active ) {
			$reviews_html  = '<section class="mp-network-profile-card">';
			$reviews_html .= '<h3>' . esc_html__( 'Bewertungen', 'mp' ) . '</h3>';
			$reviews_html .= '<p>' . esc_html__( 'Das Bewertungs-Widget ist in den Netzwerk-Einstellungen deaktiviert.', 'mp' ) . '</p>';
			$reviews_html .= '</section>';
		} elseif ( ! $reviews_enabled ) {
			$reviews_html  = '<section class="mp-network-profile-card mp-network-policy-warning">';
			$reviews_html .= '<h3>' . esc_html__( 'Bewertungen', 'mp' ) . '</h3>';
			$reviews_html .= '<p>' . esc_html__( 'Die Bewertungs-Erweiterung ist in diesem Subshop nicht aktiviert.', 'mp' ) . '</p>';
			$reviews_html .= '<p><a href="' . esc_url( $shop_addons_url ) . '">' . esc_html__( 'Add-on im Shop aktivieren', 'mp' ) . '</a></p>';
			$reviews_html .= '</section>';
		} elseif ( $product_id > 0 ) {
			$rating_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS total_reviews, AVG(CAST(cm.meta_value AS DECIMAL(10,2))) AS avg_rating
					 FROM {$wpdb->comments} c
					 INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rating'
					 WHERE c.comment_post_ID = %d AND c.comment_approved = '1'",
					$product_id
				),
				ARRAY_A
			);

			$avg_rating    = isset( $rating_row['avg_rating'] ) ? round( (float) $rating_row['avg_rating'], 1 ) : 0;
			$total_reviews = isset( $rating_row['total_reviews'] ) ? (int) $rating_row['total_reviews'] : 0;

			$reviews_summary_html  = '<section class="mp-network-profile-card">';
			$reviews_summary_html .= '<h3>' . esc_html__( 'Bewertungen', 'mp' ) . '</h3>';
			$reviews_summary_html .= '<p><strong>' . esc_html( $avg_rating ) . '/5</strong> · ' . sprintf( esc_html__( '%d Bewertungen', 'mp' ), $total_reviews ) . '</p>';
			$reviews_summary_html .= '</section>';

			$reviews_html  = '<section class="mp-network-profile-card">';
			$reviews_html .= '<h3>' . esc_html__( 'Bewertungen', 'mp' ) . '</h3>';
			$reviews_html .= '<p><strong>' . esc_html( $avg_rating ) . '/5</strong> · ' . sprintf( esc_html__( '%d Bewertungen', 'mp' ), $total_reviews ) . '</p>';

			$recent_reviews = get_comments(
				array(
					'post_id' => $product_id,
					'status'  => 'approve',
					'number'  => 6,
				)
			);

			if ( ! empty( $recent_reviews ) ) {
				$reviews_html .= '<ul class="mp-network-review-list">';
				foreach ( $recent_reviews as $review ) {
					$rating_value = (int) get_comment_meta( $review->comment_ID, 'rating', true );
					if ( $rating_value <= 0 ) {
						continue;
					}

					$reviews_html .= '<li><strong>' . esc_html( $review->comment_author ) . '</strong> · ' . esc_html( $rating_value ) . '/5';
					if ( ! empty( $review->comment_content ) ) {
						$reviews_html .= '<br><span>' . esc_html( wp_trim_words( $review->comment_content, 18, '...' ) ) . '</span>';
					}
					$reviews_html .= '</li>';
				}
				$reviews_html .= '</ul>';
			}

			$reviews_html .= '</section>';
		}

		$coupons_html = '';
		if ( post_type_exists( 'mp_coupon' ) ) {
			$coupons_limit = max( 1, absint( $atts['coupons_limit'] ) );
			$coupons_query = new WP_Query(
				array(
					'post_type'      => 'mp_coupon',
					'post_status'    => 'publish',
					'posts_per_page' => $coupons_limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);

			if ( $coupons_query->have_posts() ) {
				$coupons_html  = '<section class="mp-network-profile-card">';
				$coupons_html .= '<h3>' . esc_html__( 'Aktuelle Gutscheincodes', 'mp' ) . '</h3>';
				$coupons_html .= '<div class="mp-network-coupon-grid">';
				while ( $coupons_query->have_posts() ) {
					$coupons_query->the_post();
					$coupon_id         = get_the_ID();
					$discount          = get_post_meta( $coupon_id, 'discount', true );
					$start_date        = get_post_meta( $coupon_id, 'start_date', true );
					$has_end_date      = get_post_meta( $coupon_id, 'has_end_date', true );
					$end_date          = get_post_meta( $coupon_id, 'end_date', true );
					$applies_to        = get_post_meta( $coupon_id, 'applies_to', true );
					$require_login     = get_post_meta( $coupon_id, 'require_login', true );
					$product_limited   = get_post_meta( $coupon_id, 'product_count_limited', true );
					$min_products      = get_post_meta( $coupon_id, 'min_products', true );
					$minimum_amount    = $this->get_coupon_minimum_amount_text( $coupon_id );

					$applies_map = array(
						'all'      => __( 'gilt fuer den gesamten Shop', 'mp' ),
						'category' => __( 'gilt nur fuer ausgewaehlte Kategorien', 'mp' ),
						'product'  => __( 'gilt nur fuer ausgewaehlte Produkte', 'mp' ),
						'user'     => __( 'gilt nur fuer ausgewaehlte Benutzer', 'mp' ),
					);
					$applies_hint = isset( $applies_map[ $applies_to ] ) ? $applies_map[ $applies_to ] : __( 'gilt nach Shop-Konfiguration', 'mp' );

					$coupons_html .= '<article class="mp-network-coupon-item">';
					$coupons_html .= '<h4>' . esc_html( get_the_title() ) . '</h4>';
					if ( '' !== (string) $discount ) {
						$coupons_html .= '<p><strong>' . esc_html__( 'Rabatt', 'mp' ) . ':</strong> ' . esc_html( $discount ) . '</p>';
					}

					if ( ! empty( $start_date ) ) {
						$runtime = esc_html__( 'ab', 'mp' ) . ' ' . esc_html( $start_date );
						if ( ! empty( $has_end_date ) && ! empty( $end_date ) ) {
							$runtime .= ' - ' . esc_html( $end_date );
						}
						$coupons_html .= '<p><strong>' . esc_html__( 'Laufzeit', 'mp' ) . ':</strong> ' . $runtime . '</p>';
					}

					if ( ! empty( $minimum_amount ) ) {
						$coupons_html .= '<p><strong>' . esc_html__( 'Mindestwarenwert', 'mp' ) . ':</strong> ' . esc_html( $minimum_amount ) . '</p>';
					} elseif ( ! empty( $product_limited ) && is_numeric( $min_products ) && (int) $min_products > 0 ) {
						$coupons_html .= '<p><strong>' . esc_html__( 'Mindestmenge', 'mp' ) . ':</strong> ' . intval( $min_products ) . ' ' . esc_html__( 'Produkte', 'mp' ) . '</p>';
					}

					$shop_hint = $applies_hint;
					if ( 'yes' === $require_login ) {
						$shop_hint .= ' · ' . esc_html__( 'Login erforderlich', 'mp' );
					}
					$coupons_html .= '<p class="mp-network-coupon-hint"><strong>' . esc_html__( 'Shop-Hinweis', 'mp' ) . ':</strong> ' . esc_html( $shop_hint ) . '</p>';
					$coupons_html .= '</article>';
				}
				$coupons_html .= '</div></section>';
				wp_reset_postdata();
			}
		}

		restore_current_blog();

		$related_products_total = 0;
		if ( $related_products_enabled ) {
			$exclude_sql = $product_id > 0 ? $wpdb->prepare( ' AND post_id != %d', $product_id ) : '';
			$related_products_total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->base_prefix}mp_products WHERE post_status = 'publish' AND blog_id = %d{$exclude_sql}",
					$shop_id
				)
			);

			if ( $related_products_total > 0 ) {
				$related_products_html  = '<section class="mp-network-profile-card mp-network-related-products mp_global_product_list_widget">';
				$related_products_html .= '<h3>' . esc_html( $related_products_title ) . '</h3>';
				$related_products_html .= mp_global_list_products( array(
					'echo'                    => false,
					'blog_id'                 => $shop_id,
					'category'                => $active_profile_category > 0 ? $active_profile_category : null,
					'limit'                   => $related_products_limit,
					'order_by'                => $related_products_order_by,
					'order'                   => $related_products_order,
					'list_view'               => $related_products_list_view,
					'filters'                 => $related_products_filters,
					'paginate'                => false,
					'disable_category_filter' => true,
					'exclude_post_ids'        => $product_id > 0 ? array( $product_id ) : array(),
				) );
				$related_products_html .= '</section>';
			}
		}

		$base_profile_url = $this->get_network_shop_profile_url( $shop_id, $product_id );
		if ( empty( $base_profile_url ) ) {
			$base_profile_url = remove_query_arg( 'mp_profile_tab' );
		}
		$base_profile_url = remove_query_arg( 'mp_profile_category', $base_profile_url );
		$product_tab_args = array( 'mp_profile_tab' => 'products' );
		if ( $active_profile_category > 0 ) {
			$product_tab_args['mp_profile_category'] = $active_profile_category;
		}

		$tab_urls = array(
			'products' => add_query_arg( $product_tab_args, $base_profile_url ),
			'coupons'  => add_query_arg( 'mp_profile_tab', 'coupons', $base_profile_url ),
			'reviews'  => add_query_arg( 'mp_profile_tab', 'reviews', $base_profile_url ),
		);

		$policy_notice_html = '';
		if ( 'off' !== $policy_mode ) {
			$policy_notice_html .= '<section class="mp-network-profile-card mp-network-policy-card">';
			$policy_notice_html .= '<h3>' . esc_html__( 'Bewertungs-Policy', 'mp' ) . '</h3>';
			$policy_notice_html .= '<p>' . sprintf( esc_html__( 'Modus: %s', 'mp' ), esc_html( strtoupper( $policy_mode ) ) ) . '</p>';
			$policy_notice_html .= '<p>' . sprintf( esc_html__( 'Aktiv in %1$d von %2$d Shops', 'mp' ), intval( $policy_snapshot['enabled'] ), max( 1, intval( $policy_snapshot['total'] ) ) ) . '</p>';

			if ( ! empty( $policy_snapshot['disabled'] ) ) {
				$names = array();
				foreach ( array_slice( (array) $policy_snapshot['disabled_shops'], 0, 5 ) as $shop ) {
					$names[] = isset( $shop['name'] ) ? $shop['name'] : '';
				}
				$policy_notice_html .= '<p class="mp-network-policy-warning">' . sprintf( esc_html__( '%d Shops ohne aktivierte Bewertungen', 'mp' ), intval( $policy_snapshot['disabled'] ) ) . '</p>';
				if ( ! empty( $names ) ) {
					$policy_notice_html .= '<p class="mp-network-policy-warning">' . esc_html( implode( ', ', $names ) ) . '</p>';
				}
			}

			if ( $reviews_blocked ) {
				$policy_notice_html .= '<p class="mp-network-policy-warning">' . esc_html__( 'Fuer diesen Shop ist die Reviews-Erweiterung nicht aktiv. Im Pflichtmodus wird der Bewertungsbereich blockiert.', 'mp' ) . '</p>';
			}

			$policy_notice_html .= '</section>';
		}


		$related_prefilter_html = '';
		if ( ! empty( $related_prefilter_terms ) ) {
			$related_prefilter_html .= '<nav class="mp-network-related-prefilters" aria-label="' . esc_attr__( 'Produktkategorien', 'mp' ) . '">';
			$all_filter_url = add_query_arg( array( 'mp_profile_tab' => 'products' ), $base_profile_url );
			$related_prefilter_html .= '<a class="mp-network-related-prefilter' . ( 0 === $active_profile_category ? ' is-active' : '' ) . '" href="' . esc_url( $all_filter_url ) . '">' . esc_html__( 'Alle', 'mp' ) . '</a>';
			foreach ( $related_prefilter_terms as $term_id => $term_object ) {
				$filter_url = add_query_arg(
					array(
						'mp_profile_tab'      => 'products',
						'mp_profile_category' => $term_id,
					),
					$base_profile_url
				);
				$related_prefilter_html .= '<a class="mp-network-related-prefilter' . ( $active_profile_category === $term_id ? ' is-active' : '' ) . '" href="' . esc_url( $filter_url ) . '">' . esc_html( $term_object->name ) . '</a>';
			}
			$related_prefilter_html .= '</nav>';
		}

		$shop_summary_html  = '<section class="mp-network-profile-card mp-network-profile-shop-card">';
		$shop_summary_html .= '<div class="mp-network-profile-shop-header">';
		if ( ! empty( $profile_image_url ) ) {
			$shop_summary_html .= '<img class="mp-network-profile-shop-image" src="' . esc_url( $profile_image_url ) . '" alt="' . esc_attr( $profile_title ) . '">';
		}
		$shop_summary_html .= '<div class="mp-network-profile-shop-copy">';
		$shop_summary_html .= '<p class="mp-network-shop-kicker">' . esc_html__( 'Shop-Profil', 'mp' ) . '</p>';
		$shop_summary_html .= '<h2>' . esc_html( $profile_title ) . '</h2>';
		if ( ! empty( $profile_tagline ) ) {
			$shop_summary_html .= '<p class="mp-network-profile-tagline">' . esc_html( $profile_tagline ) . '</p>';
		}
		$shop_summary_html .= '</div>';
		$shop_summary_html .= '</div>';
		$shop_summary_html .= '<div class="mp-network-profile-actions">';
		$shop_summary_html .= '<a href="' . esc_url( $shop_url ) . '">' . esc_html__( 'Shop besuchen', 'mp' ) . '</a>';
		if ( mp_get_network_setting( 'global_cart', 0 ) ) {
			$shop_summary_html .= '<a href="' . esc_url( mp_cart_link( false, true ) ) . '">' . esc_html__( 'Zum Netzwerkwarenkorb', 'mp' ) . '</a>';
		}
		$shop_summary_html .= '</div>';
		$shop_summary_html .= '</section>';

		$html  = '<section class="mp-network-shop-profile" style="--mp-prof-primary:' . esc_attr( $theme_primary ) . ';--mp-prof-accent:' . esc_attr( $theme_accent ) . ';--mp-prof-bg-start:' . esc_attr( $theme_bg_start ) . ';--mp-prof-bg-end:' . esc_attr( $theme_bg_end ) . ';--mp-prof-card:' . esc_attr( $theme_card_bg ) . ';">';
		$html .= '<header class="mp-network-profile-toolbar">';
		$html .= '<div class="mp-network-profile-toolbar-copy">';
		$html .= '<p class="mp-network-shop-kicker">' . esc_html__( 'Netzwerk Shop-Profil', 'mp' ) . '</p>';
		$html .= '<h1>' . esc_html( $profile_title ) . '</h1>';
		if ( ! empty( $product_title ) ) {
			$html .= '<p>' . sprintf( esc_html__( 'Fokusprodukt: %s', 'mp' ), esc_html( $product_title ) ) . '</p>';
		}
		$html .= '</div>';
		$html .= '<nav class="mp-network-profile-tabs">';
		$html .= '<a class="mp-network-profile-tab' . ( 'products' === $active_tab ? ' is-active' : '' ) . '" href="' . esc_url( $tab_urls['products'] ) . '">' . esc_html__( 'Produkte', 'mp' ) . '</a>';
		$html .= '<a class="mp-network-profile-tab' . ( 'coupons' === $active_tab ? ' is-active' : '' ) . '" href="' . esc_url( $tab_urls['coupons'] ) . '">' . esc_html__( 'Gutscheine', 'mp' ) . '</a>';
		$html .= '<a class="mp-network-profile-tab' . ( 'reviews' === $active_tab ? ' is-active' : '' ) . '" href="' . esc_url( $tab_urls['reviews'] ) . '">' . esc_html__( 'Bewertungen', 'mp' ) . '</a>';
		$html .= '</nav>';
		$html .= '</header>';

		if ( 'coupons' === $active_tab ) {
			$html .= $shop_summary_html;
			$html .= $policy_notice_html;
			$html .= ! empty( $coupons_html )
				? $coupons_html
				: '<section class="mp-network-profile-card"><p>' . esc_html__( 'Aktuell sind keine aktiven Gutscheincodes fuer diesen Shop verfuegbar.', 'mp' ) . '</p></section>';
		} elseif ( 'reviews' === $active_tab ) {
			$html .= $shop_summary_html;
			$html .= $policy_notice_html;
			if ( $reviews_blocked ) {
				$html .= '<section class="mp-network-profile-card mp-network-policy-warning">';
				$html .= '<h3>' . esc_html__( 'Bewertungen blockiert', 'mp' ) . '</h3>';
				$html .= '<p>' . esc_html__( 'Der Pflichtmodus verlangt aktivierte Bewertungen im Subshop. Bitte aktiviere das Reviews-Add-on im betroffenen Shop.', 'mp' ) . '</p>';
				$html .= '<p><a href="' . esc_url( $shop_addons_url ) . '">' . esc_html__( 'Add-on-Einstellungen dieses Shops', 'mp' ) . '</a></p>';
				$html .= '</section>';
			} else {
				$html .= $reviews_html;
			}
		} else {
			$html .= '<div class="mp-network-profile-stage">';
			$html .= '<div class="mp-network-profile-feature">';
			$html .= '<div class="mp-network-profile-feature-label">' . esc_html__( 'Ausgewaehltes Produkt', 'mp' ) . '</div>';
			$html .= $product_html ? $product_html : '<p>' . esc_html__( 'In diesem Shop sind aktuell keine Produkte verfuegbar.', 'mp' ) . '</p>';
			$html .= '</div>';
			$html .= '<aside class="mp-network-profile-side">' . $shop_summary_html . $profile_meta_html . $reviews_summary_html . $policy_notice_html . '</aside>';
			$html .= '</div>';

			if ( ! empty( $related_prefilter_html ) ) {
				$html .= $related_prefilter_html;
			}

			if ( ! empty( $related_products_html ) ) {
				$html .= $related_products_html;
			}
		}

		$html .= '</section>';

		return $html;
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
			$use_global_gateway = $this->should_use_network_global_gateway();

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
	 * Keep gateway API network/global toggle in sync with multisite checkout policy.
	 *
	 * @param bool $use_global_gateway
	 * @return bool
	 */
	public function filter_use_network_global_gateway( $use_global_gateway ) {
		if ( ! is_multisite() || ! mp_get_network_setting( 'global_cart', 0 ) ) {
			return false;
		}

		return $this->should_use_network_global_gateway();
	}

	/**
	 * Persist customer checkout choice (bundle vs split) for multi-shop carts.
	 *
	 * @return void
	 */
	public function capture_network_multishop_checkout_choice() {
		if ( ! is_multisite() || ! mp_get_network_setting( 'global_cart', 0 ) ) {
			return;
		}

		$choice = '';
		if ( isset( $_POST['mp_network_checkout_mode'] ) ) {
			$choice = sanitize_key( wp_unslash( $_POST['mp_network_checkout_mode'] ) );
		} elseif ( isset( $_GET['mp_network_checkout_mode'] ) ) {
			$choice = sanitize_key( wp_unslash( $_GET['mp_network_checkout_mode'] ) );
		}

		if ( in_array( $choice, array( 'bundle', 'split' ), true ) ) {
			mp_update_session_value( 'mp_network_multishop_checkout_choice', $choice );
		}
	}

	/**
	 * Render checkout selector/notice for multi-shop carts.
	 *
	 * @param string $html
	 * @return string
	 */
	public function inject_multishop_checkout_selector( $html ) {
		if ( ! is_multisite() || ! mp_get_network_setting( 'global_cart', 0 ) || ! function_exists( 'mp_cart' ) ) {
			return $html;
		}

		$blog_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) mp_cart()->get_blog_ids() ) ) ) );
		if ( count( $blog_ids ) <= 1 ) {
			return $html;
		}

		$policy       = sanitize_key( (string) mp_get_network_setting( 'advanced->network_multishop_checkout_mode', 'bundle_only' ) );
		$active_mode  = $this->resolve_multishop_checkout_mode( $blog_ids );
		$shipping_key = sanitize_key( (string) mp_get_network_setting( 'advanced->network_bundle_shipping_mode', 'per_shop' ) );
		$shipping_map = array(
			'per_shop'          => __( 'Versand pro Subshop', 'mp' ),
			'combined'          => __( 'Versand kombiniert', 'mp' ),
			'combined_discount' => __( 'Versand kombiniert mit Rabatt', 'mp' ),
		);
		$shipping_label = isset( $shipping_map[ $shipping_key ] ) ? $shipping_map[ $shipping_key ] : $shipping_map['per_shop'];

		$block  = '<section class="mp_checkout_network_mode" style="margin:0 0 16px;padding:12px;border:1px solid #d7e4f0;border-radius:10px;background:#f7fbff">';
		$block .= '<h3 class="mp_sub_title" style="margin:0 0 8px">' . esc_html__( 'Netzwerk-Checkout', 'mp' ) . '</h3>';

		if ( 'customer_choice' === $policy ) {
			$block .= '<p style="margin:0 0 8px">' . esc_html__( 'Dieser Warenkorb enthaelt Produkte aus mehreren Subshops. Du kannst zwischen gebuendeltem Mainshop-Checkout und getrenntem Checkout waehlen.', 'mp' ) . '</p>';
			$block .= '<label style="display:inline-flex;gap:6px;align-items:center;margin-right:14px"><input type="radio" name="mp_network_checkout_mode" value="bundle"' . checked( 'bundle', $active_mode, false ) . '> ' . esc_html__( 'Mainshop-Buendelung', 'mp' ) . '</label>';
			$block .= '<label style="display:inline-flex;gap:6px;align-items:center"><input type="radio" name="mp_network_checkout_mode" value="split"' . checked( 'split', $active_mode, false ) . '> ' . esc_html__( 'Getrennt pro Subshop', 'mp' ) . '</label>';
		} elseif ( 'split_only' === $policy ) {
			$block .= '<p style="margin:0 0 8px">' . esc_html__( 'Dieser Warenkorb ist auf getrennten Subshop-Checkout erzwungen.', 'mp' ) . '</p>';
			$block .= '<input type="hidden" name="mp_network_checkout_mode" value="split">';
		} else {
			$block .= '<p style="margin:0 0 8px">' . esc_html__( 'Dieser Warenkorb wird gebuendelt im Mainshop ausgecheckt.', 'mp' ) . '</p>';
			$block .= '<input type="hidden" name="mp_network_checkout_mode" value="bundle">';
		}

		$block .= '<p style="margin:8px 0 0;color:#516981;font-size:12px">' . sprintf( esc_html__( 'Buendelung Versandregel: %s', 'mp' ), esc_html( $shipping_label ) ) . '</p>';
		$block .= '</section>';

		return $block . $html;
	}

	/**
	 * Prevent unintended combined payment when split mode is selected for multi-shop carts.
	 *
	 * @param bool        $can_checkout
	 * @param MP_Checkout $checkout
	 * @param MP_Cart     $cart
	 * @param array       $billing_info
	 * @param array       $shipping_info
	 * @return bool
	 */
	public function guard_split_checkout_until_cart_partition( $can_checkout, $checkout, $cart, $billing_info, $shipping_info ) {
		if ( ! $can_checkout || ! is_multisite() || ! mp_get_network_setting( 'global_cart', 0 ) ) {
			return $can_checkout;
		}

		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_blog_ids' ) ) {
			return $can_checkout;
		}

		$blog_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $cart->get_blog_ids() ) ) ) );
		if ( count( $blog_ids ) <= 1 ) {
			return $can_checkout;
		}

		if ( 'split' === $this->resolve_multishop_checkout_mode( $blog_ids ) ) {
			if ( is_object( $checkout ) && method_exists( $checkout, 'add_error' ) ) {
				$checkout->add_error( __( 'Getrennter Multi-Shop-Checkout ist aktiv. Die Warenkorb-Aufteilung pro Subshop wird als naechster Schritt verarbeitet; bitte wechsle vorerst auf Mainshop-Buendelung oder aktiviere die Split-Engine.', 'mp' ), 'order-review-payment' );
			}

			return false;
		}

		return $can_checkout;
	}

	/**
	 * Resolve whether current request should use network-global gateway routing.
	 *
	 * @return bool
	 */
	private function should_use_network_global_gateway() {
		if ( ! is_multisite() || ! mp_get_network_setting( 'global_cart', 0 ) ) {
			return false;
		}

		$blog_ids = array();
		if ( function_exists( 'mp_cart' ) ) {
			$blog_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) mp_cart()->get_blog_ids() ) ) ) );
		}

		$root_blog_id = (int) mp_root_blog_id();
		$hybrid       = (bool) mp_get_network_setting( 'advanced->hybrid_gateway_routing', 0 );

		if ( $hybrid && count( $blog_ids ) === 1 && (int) reset( $blog_ids ) !== $root_blog_id ) {
			return false;
		}

		if ( count( $blog_ids ) > 1 && 'split' === $this->resolve_multishop_checkout_mode( $blog_ids ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve effective checkout mode for carts containing multiple subshops.
	 *
	 * @param array $blog_ids
	 * @return string bundle|split
	 */
	private function resolve_multishop_checkout_mode( $blog_ids ) {
		$blog_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $blog_ids ) ) ) );
		if ( count( $blog_ids ) <= 1 ) {
			return 'bundle';
		}

		$policy = sanitize_key( (string) mp_get_network_setting( 'advanced->network_multishop_checkout_mode', 'bundle_only' ) );
		if ( 'split_only' === $policy ) {
			return 'split';
		}

		if ( 'customer_choice' === $policy ) {
			$choice = sanitize_key( (string) mp_get_session_value( 'mp_network_multishop_checkout_choice', '' ) );
			if ( ! in_array( $choice, array( 'bundle', 'split' ), true ) ) {
				$choice = sanitize_key( (string) mp_get_network_setting( 'advanced->network_multishop_checkout_default', 'bundle' ) );
			}

			return ( 'split' === $choice ) ? 'split' : 'bundle';
		}

		return 'bundle';
	}

	/**
	 * Persist orchestration metadata for network orders.
	 *
	 * @param MP_Order $order
	 * @return void
	 */
	public function annotate_network_multishop_order( $order ) {
		if ( ! is_multisite() || ! mp_get_network_setting( 'global_cart', 0 ) || ! is_object( $order ) || ! method_exists( $order, 'get_cart' ) ) {
			return;
		}

		$cart = $order->get_cart();
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_blog_ids' ) ) {
			return;
		}

		$blog_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $cart->get_blog_ids() ) ) ) );
		if ( count( $blog_ids ) <= 1 ) {
			return;
		}

		$checkout_mode = $this->resolve_multishop_checkout_mode( $blog_ids );
		$order->update_meta( '_mp_network_multishop_checkout_mode', $checkout_mode );
		$order->update_meta( '_mp_network_multishop_blog_ids', $blog_ids );

		if ( 'bundle' === $checkout_mode ) {
			$shipping_mode = sanitize_key( (string) mp_get_network_setting( 'advanced->network_bundle_shipping_mode', 'per_shop' ) );
			$hold_days     = max( 0, (int) mp_get_network_setting( 'advanced->settlement_hold_days', 14 ) );
			$auto_at       = time() + ( $hold_days * DAY_IN_SECONDS );

			$order->update_meta( '_mp_network_bundle_shipping_mode', $shipping_mode );
			$order->update_meta( '_mp_network_payout_status', 'pending' );
			$order->update_meta( '_mp_network_payout_auto_at', $auto_at );
		}
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
		$withdrawals     = isset( $snapshot['withdrawals'] ) && is_array( $snapshot['withdrawals'] ) ? $snapshot['withdrawals'] : array( 'counts' => array(), 'recent' => array() );
		$recent_withdrawals = isset( $withdrawals['recent'] ) && is_array( $withdrawals['recent'] ) ? $withdrawals['recent'] : array();
		$withdrawal_management_enabled = (bool) mp_get_network_setting( 'advanced->network_withdrawal_management', 0 ) && (bool) mp_get_setting( 'withdrawal->enabled', 1 );

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
		if ( $withdrawal_management_enabled ) {
			$html .= '<div class="mp-hub-kpi"><span>' . esc_html__( 'Offene Widerrufe', 'mp' ) . '</span><strong>' . intval( isset( $totals['withdrawal_open'] ) ? $totals['withdrawal_open'] : 0 ) . '</strong></div>';
		}
		$support_enabled_hub = class_exists( 'MP_Support_Addon' ) && mp_get_network_setting( 'advanced->network_support_enabled', 0 ) && mp_get_setting( 'support->enabled', 1 );
		if ( $support_enabled_hub ) {
			$open_tickets = $this->count_open_customer_tickets( $user_id );
			$html .= '<div class="mp-hub-kpi"><span>' . esc_html__( 'Offene Tickets', 'mp' ) . '</span><strong>' . intval( $open_tickets ) . '</strong></div>';
		}
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

		if ( $withdrawal_management_enabled ) {
			$html .= '<section class="mp-hub-panel" style="margin-bottom:12px;">';
			$html .= '<h3>' . esc_html__( 'Widerrufsstatus', 'mp' ) . '</h3>';
			if ( ! empty( $recent_withdrawals ) ) {
				$html .= '<ul class="mp-hub-list">';
				foreach ( array_slice( $recent_withdrawals, 0, 6 ) as $item ) {
					$html .= '<li>';
					$html .= '<div class="mp-hub-meta">';
					$html .= '<strong>' . sprintf( esc_html__( '%1$s · Bestellung #%2$s', 'mp' ), esc_html( isset( $item['shop'] ) ? $item['shop'] : '' ), esc_html( isset( $item['order_id'] ) ? $item['order_id'] : '' ) ) . '</strong>';
					$html .= '<span>' . esc_html( sprintf( __( '%1$s · %2$s', 'mp' ), isset( $item['status_label'] ) ? $item['status_label'] : __( 'Kein Widerruf', 'mp' ), isset( $item['reason_label'] ) && '' !== $item['reason_label'] ? $item['reason_label'] : __( 'Kein Grund angegeben', 'mp' ) ) ) . '</span>';
					$html .= '</div>';
					$html .= '<a class="mp-hub-cta" href="' . esc_url( isset( $item['tracking_url'] ) ? $item['tracking_url'] : '#' ) . '">' . esc_html__( 'Zum Auftrag', 'mp' ) . '</a>';
					$html .= '</li>';
				}
				$html .= '</ul>';
			} else {
				$html .= '<p class="mp-hub-empty">' . esc_html__( 'Aktuell liegen keine eingereichten Widerrufe vor.', 'mp' ) . '</p>';
			}
			$html .= '</section>';

			$html .= '<section class="mp-hub-panel" style="margin-bottom:12px;">';
			$html .= '<h3>' . esc_html__( 'Widerruf einreichen', 'mp' ) . '</h3>';
			if ( ! empty( $rows ) ) {
				$html .= '<ul class="mp-hub-list">';
				foreach ( array_slice( $rows, 0, 6 ) as $row ) {
					$tracking_url = isset( $row['url'] ) ? (string) $row['url'] : '';
					if ( '' === $tracking_url ) {
						continue;
					}

					$html .= '<li>';
					$html .= '<div class="mp-hub-meta">';
					$html .= '<strong>' . sprintf( esc_html__( '%1$s · Bestellung #%2$s', 'mp' ), esc_html( isset( $row['shop'] ) ? $row['shop'] : '' ), esc_html( isset( $row['order'] ) ? $row['order'] : '' ) ) . '</strong>';
					$html .= '<span>' . esc_html__( 'Widerruf direkt in der Kundenzone der Bestellung starten.', 'mp' ) . '</span>';
					$html .= '</div>';
					$html .= '<a class="mp-hub-cta" href="' . esc_url( $tracking_url . '#mp-customer-zone' ) . '">' . esc_html__( 'Widerruf starten', 'mp' ) . '</a>';
					$html .= '</li>';
				}
				$html .= '</ul>';
			} else {
				$html .= '<p class="mp-hub-empty">' . esc_html__( 'Noch keine Bestellungen für Widerrufe verfügbar.', 'mp' ) . '</p>';
			}
			$html .= '</section>';
		}

		if ( $support_enabled_hub ) {
			$user_tickets = $this->get_customer_hub_tickets( $user_id );
			$support_page_id = (int) mp_get_network_setting( 'pages->network_support_center', 0 );
			$support_url = $support_page_id ? get_permalink( $support_page_id ) : home_url( '/' );
			$html .= '<section class="mp-hub-panel" style="margin-bottom:12px;">';
			$html .= '<h3>' . esc_html__( 'Meine Support-Tickets', 'mp' ) . '</h3>';
			if ( ! empty( $user_tickets ) ) {
				$html .= '<ul class="mp-hub-list">';
				foreach ( array_slice( $user_tickets, 0, 5 ) as $ticket ) {
					$slabels   = array(
						'open'        => __( 'Offen', 'mp' ),
						'in_progress' => __( 'In Bearbeitung', 'mp' ),
						'resolved'    => __( 'Gel\u00f6st', 'mp' ),
						'closed'      => __( 'Geschlossen', 'mp' ),
					);
					$slabel = isset( $slabels[ $ticket['status'] ] ) ? $slabels[ $ticket['status'] ] : __( 'Offen', 'mp' );
					$html .= '<li>';
					$html .= '<div class="mp-hub-meta">';
					$html .= '<strong>' . esc_html( (string) $ticket['title'] ) . '</strong>';
					$html .= '<span>' . esc_html( $slabel ) . '</span>';
					$html .= '</div>';
					$html .= '<a class="mp-hub-cta" href="' . esc_url( $support_url ) . '">' . esc_html__( 'Zum Ticket', 'mp' ) . '</a>';
					$html .= '</li>';
				}
				$html .= '</ul>';
			} else {
				$html .= '<p class="mp-hub-empty">' . esc_html__( 'Noch keine Support-Tickets vorhanden.', 'mp' ) . '</p>';
			}
			$html .= '<p style="margin-top:8px;"><a href="' . esc_url( $support_url ) . '" class="mp-hub-cta">' . esc_html__( 'Neues Ticket erstellen', 'mp' ) . '</a></p>';
			$html .= '</section>';
		}

		$checkout_mode_labels = array(
			'bundle' => __( 'Buendelung', 'mp' ),
			'split'  => __( 'Getrennt', 'mp' ),
		);
		$payout_status_labels = array(
			'pending'   => __( 'Offen', 'mp' ),
			'started'   => __( 'Gestartet', 'mp' ),
			'confirmed' => __( 'Bestaetigt', 'mp' ),
			'paid'      => __( 'Ausgezahlt', 'mp' ),
		);

		$settlement_url = '';
		if ( function_exists( 'mp_root_blog_id' ) ) {
			$settlement_page_id = (int) mp_get_network_setting( 'pages->network_settlement_dashboard', 0 );
			if ( $settlement_page_id > 0 ) {
				$settlement_url = (string) get_permalink( $settlement_page_id );
			}
		}

		$html .= '<section class="mp-hub-panel">';
		$html .= '<h3>' . esc_html__( 'Letzte Bestellungen', 'mp' ) . '</h3>';
		if ( ! empty( $rows ) ) {
			$html .= '<table class="mp-hub-orders"><thead><tr>';
			$html .= '<th>' . esc_html__( 'Shop', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Bestellung', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Status', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Checkout', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Auszahlung', 'mp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Betrag', 'mp' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( array_slice( $rows, 0, 20 ) as $row ) {
				$status_label = isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : ucfirst( str_replace( 'order_', '', $row['status'] ) );
				$checkout_mode = isset( $row['checkout_mode'] ) ? sanitize_key( (string) $row['checkout_mode'] ) : '';
				$payout_status = isset( $row['payout_status'] ) ? sanitize_key( (string) $row['payout_status'] ) : '';
				$checkout_label = isset( $checkout_mode_labels[ $checkout_mode ] ) ? $checkout_mode_labels[ $checkout_mode ] : '&mdash;';
				$payout_label   = isset( $payout_status_labels[ $payout_status ] ) ? $payout_status_labels[ $payout_status ] : '&mdash;';
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $row['shop'] ) . '</td>';
				$html .= '<td><a href="' . esc_url( $row['url'] ) . '">#' . esc_html( $row['order'] ) . '</a></td>';
				$html .= '<td>' . esc_html( $status_label ) . '</td>';
				$html .= '<td>' . esc_html( $checkout_label ) . '</td>';
				if ( '' !== $settlement_url && '&mdash;' !== $payout_label ) {
					$html .= '<td><a href="' . esc_url( $settlement_url ) . '">' . esc_html( $payout_label ) . '</a></td>';
				} else {
					$html .= '<td>' . esc_html( $payout_label ) . '</td>';
				}
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
	 * Count open tickets for a customer (for hub KPI).
	 *
	 * @param int $user_id
	 * @return int
	 */
	private function count_open_customer_tickets( $user_id ) {
		if ( ! post_type_exists( 'mp_support_ticket' ) ) {
			return 0;
		}

		$q = new WP_Query( array(
			'post_type'      => 'mp_support_ticket',
			'post_status'    => 'publish',
			'author'         => (int) $user_id,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => false,
			'meta_query'     => array( array(
				'key'     => '_mp_support_status',
				'value'   => array( 'open', 'in_progress' ),
				'compare' => 'IN',
			) ),
		) );

		return (int) $q->found_posts;
	}

	/**
	 * Get recent tickets for a customer for the hub panel.
	 *
	 * @param int $user_id
	 * @return array
	 */
	private function get_customer_hub_tickets( $user_id ) {
		if ( ! post_type_exists( 'mp_support_ticket' ) ) {
			return array();
		}

		$q = new WP_Query( array(
			'post_type'      => 'mp_support_ticket',
			'post_status'    => 'publish',
			'author'         => (int) $user_id,
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		$result = array();
		foreach ( (array) $q->posts as $post ) {
			$result[] = array(
				'id'       => (int) $post->ID,
				'title'    => (string) $post->post_title,
				'status'   => (string) get_post_meta( $post->ID, '_mp_support_status', true ),
				'priority' => (string) get_post_meta( $post->ID, '_mp_support_priority', true ),
			);
		}

		return $result;
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
			'checkout_mode_counts' => array(
				'bundle' => 0,
				'split'  => 0,
			),
			'payout_status_counts' => array(
				'pending'   => 0,
				'started'   => 0,
				'confirmed' => 0,
				'paid'      => 0,
			),
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

				$checkout_mode = sanitize_key( (string) get_post_meta( $post_id, '_mp_network_multishop_checkout_mode', true ) );
				if ( isset( $network_totals['checkout_mode_counts'][ $checkout_mode ] ) ) {
					$network_totals['checkout_mode_counts'][ $checkout_mode ] ++;
				}

				$payout_status = sanitize_key( (string) get_post_meta( $post_id, '_mp_network_payout_status', true ) );
				if ( isset( $network_totals['payout_status_counts'][ $payout_status ] ) ) {
					$network_totals['payout_status_counts'][ $payout_status ] ++;
				}

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
				$html .= '<div class="mp-network-profile-shop-list-wrap">' . $related_prefilter_html . $related_products_html . '</div>';
		$html .= '.mp-kpi-card{background:#fff;border:1px solid #d7e4f0;border-radius:12px;padding:12px}';
		$html .= '.mp-kpi-card span{display:block;font-size:11px;color:#59708a;text-transform:uppercase;letter-spacing:.04em}';
		$html .= '.mp-kpi-card strong{display:block;margin-top:6px;font-size:20px;color:#16324b}';
		$html .= '.mp-perf-layout{display:grid;grid-template-columns:2fr 1fr;gap:12px}';
		$html .= '.mp-perf-panel{background:#fff;border:1px solid #d7e4f0;border-radius:12px;padding:12px}';
		$html .= '.mp-perf-panel h3{margin:0 0 10px;font-size:13px;color:#35506b;text-transform:uppercase;letter-spacing:.04em}';
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
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Buendel-Checkouts', 'mp' ) . '</span><strong>' . intval( $network_totals['checkout_mode_counts']['bundle'] ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Split-Checkouts', 'mp' ) . '</span><strong>' . intval( $network_totals['checkout_mode_counts']['split'] ) . '</strong></div>';
		$html .= '<div class="mp-kpi-card"><span>' . esc_html__( 'Offene Auszahlungen', 'mp' ) . '</span><strong>' . intval( $network_totals['payout_status_counts']['pending'] ) . '</strong></div>';
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
				'network_multishop_checkout_mode' => 'bundle_only',
				'network_multishop_checkout_default' => 'bundle',
				'network_bundle_shipping_mode' => 'per_shop',
				'network_customer_hub'   => 0,
				'network_support_enabled' => 0,
				'network_support_mode'   => 'autonomous',
				'network_withdrawal_management' => 0,
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

		if ( ! isset( $settings['advanced'] ) || ! is_array( $settings['advanced'] ) || ! array_key_exists( 'network_withdrawal_management', $settings['advanced'] ) ) {
			$new_settings['advanced']['network_withdrawal_management'] = ! empty( $new_settings['global_cart'] ) ? 1 : 0;
		}

		if ( ! isset( $settings['advanced'] ) || ! is_array( $settings['advanced'] ) || ! array_key_exists( 'network_multishop_checkout_mode', $settings['advanced'] ) ) {
			$new_settings['advanced']['network_multishop_checkout_mode'] = 'bundle_only';
		}

		if ( ! isset( $settings['advanced'] ) || ! is_array( $settings['advanced'] ) || ! array_key_exists( 'network_multishop_checkout_default', $settings['advanced'] ) ) {
			$new_settings['advanced']['network_multishop_checkout_default'] = 'bundle';
		}

		if ( ! isset( $settings['advanced'] ) || ! is_array( $settings['advanced'] ) || ! array_key_exists( 'network_bundle_shipping_mode', $settings['advanced'] ) ) {
			$new_settings['advanced']['network_bundle_shipping_mode'] = 'per_shop';
		}

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
