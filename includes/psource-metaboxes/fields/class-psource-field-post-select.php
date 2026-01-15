<?php

add_action( 'wp_ajax_psource_field_post_select_search_posts', array( 'PSOURCE_Field_Post_Select', 'search_posts' ) );

class PSOURCE_Field_Post_Select extends PSOURCE_Field {

	/**
	 * Runs on parent construct
	 *
	 * @since 1.0
	 * @access public
	 * @param array $args {
	 * 		An array of arguments.
	 *
	 * 		@type array $query @see WP_Query
	 * 		@type bool $multiple True, if selection of multiple posts is allowed.
	 * 		@type string $placeholder The text that shows up in the field before any posts are selected
	 * }
	 */
	public function on_creation( $args ) {
		$this->args = array_replace_recursive( array(
			'query'			 => array(),
			'multiple'		 => false,
			'placeholder'	 => __( 'Select Posts', 'mp' )
		), $args );

		$this->args[ 'class' ] .= ' psource-post-select';
		$this->args[ 'custom' ][ 'data-placeholder' ]	 = $this->args[ 'placeholder' ];
		$this->args[ 'custom' ][ 'data-multiple' ]		 = (int) $this->args[ 'multiple' ];
		$this->args[ 'custom' ][ 'data-query' ]			 = http_build_query( $this->args[ 'query' ] );
		// Entferne alte select2/mp_select2 Klassen
		if ( isset($this->args['class']) ) {
			$this->args['class'] = preg_replace('/\b(mp_select2|mp-select2|select2)\b/', '', $this->args['class']);
		}
	}

	/**
	 * Formats the field value for display.
	 *
	 * @since 1.0
	 * @access public
	 * @param mixed $value
	 * @param mixed $post_id
	 */
	public function format_value( $value, $post_id ) {
		if( ! is_array( $value ) ) {
			$values = explode( ',', $value );
		} else {
			$values = $value;
		}
		
		return parent::format_value( $values, $post_id );
	}

	/**
	 * Searches posts
	 *
	 * @since 1.0
	 * @access public
	 * @action wp_ajax_psource_search_posts
	 */
	public static function search_posts() {
		add_filter( 'posts_search', array( __CLASS__, 'search_by_title_only' ), 500, 2 );

		parse_str( $_GET[ 'query' ], $args );

		$args = array_replace_recursive( array(
			'posts_per_page' => get_option( 'posts_per_page' ),
		), $args );

		$query	 = new WP_Query( array_replace_recursive( array(
			'paged'					 => mp_arr_get_value( 'page', $_GET ),
			'posts_per_page'		 => $args[ 'posts_per_page' ],
			's'						 => mp_arr_get_value( 'search_term', $_GET ),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'				 => 'title',
			'order'					 => 'ASC',
			'post_status'			 => array( 'publish' ),
		), $args ) );
		$data = array();
		while ( $query->have_posts() ) : $query->the_post();
			$data[] = array( 'id' => get_the_ID(), 'title' => get_the_title() );
		endwhile;
		wp_send_json( $data );
	}

	/**
	 * Search by title only
	 *
	 * @since 1.0
	 * @access public
	 * @filter posts_search
	 * @param string $search
	 * @param WP_Query $wp_query
	 * @return string
	 */
	public static function search_by_title_only( $search, &$wp_query ) {
		global $wpdb;

		if ( empty( $search ) ) {
			return $search; // skip processing - no search term in query
		}

		$q			 = $wp_query->query_vars;
		$n			 = !empty( $q[ 'exact' ] ) ? '' : '%';
		$search		 = '';
		$searchand	 = '';

		foreach ( (array) $q[ 'search_terms' ] as $term ) {
			$term		 = esc_sql( $wpdb->esc_like( $term ) );
			$search .= "{$searchand}($wpdb->posts.post_title LIKE '{$n}{$term}{$n}')";
			$searchand	 = ' AND ';
		}

		if ( !empty( $search ) ) {
			$search = " AND ({$search}) ";
			if ( !is_user_logged_in() ) {
				$search .= " AND ($wpdb->posts.post_password = '') ";
			}
		}

		return $search;
	}

	/**
	 * Prints scripts
	 *
	 * @since 1.0
	 * @access public
	 */
	       public function print_scripts() {
		       ?>
			   <script type="text/javascript">
			   document.addEventListener('DOMContentLoaded', function() {
				   document.querySelectorAll('select.psource-post-select').forEach(function(el) {
					   var limit = parseInt(el.getAttribute('data-limit')) || 3;
					   if (typeof TomSelect !== 'undefined') {
						   if (!el.tomselect) {
							   el.tomselect = new TomSelect(el, {
								   maxItems: limit,
								   valueField: 'id',
								   labelField: 'title',
								   searchField: 'title',
								   plugins: ['remove_button'],
								   create: false,
								   load: function(query, callback) {
									   if (!query.length) return callback();
									   var url = (typeof marketpress_admin !== 'undefined' ? marketpress_admin.ajaxurl : ajaxurl);
									   fetch(url + '?action=psource_field_post_select_search_posts&search_term=' + encodeURIComponent(query))
										   .then(response => response.json())
										   .then(json => {
											   callback(json);
										   }).catch(() => callback());
								   },
								   render: {
									   option: function(item, escape) {
										   return '<div>' + escape(item.title) + '</div>';
									   },
									   item: function(item, escape) {
										   return '<div>' + escape(item.title) + '</div>';
									   }
								   }
							   });
						   }
					   }
				   });
			   });
			   </script>
		       <?php
		       parent::print_scripts();
	       }

	/**
	 * Displays the field
	 *
	 * @since 1.0
	 * @access public
	 * @param int $post_id
	 */
	public function display( $post_id ) {
		$value   = $this->get_value( $post_id );
		$ids     = is_array( $value ) ? $value : explode( ',', $value );
		$this->before_field();
		$limit = isset($this->args['custom']['related_limit']) ? intval($this->args['custom']['related_limit']) : 3;
		echo '<select class="psource-post-select" multiple="multiple" data-placeholder="' . esc_attr($this->args['custom']['data-placeholder']) . '" data-limit="' . $limit . '" style="width:100%">';
		foreach ($ids as $id) {
			if ($id && get_post($id)) {
				echo '<option value="' . esc_attr($id) . '" selected>' . esc_html(get_the_title($id)) . '</option>';
			}
		}
		echo '</select>';
		$this->after_field();
	}

	/**
	 * Enqueues the field's scripts
	 *
	 * @since 1.0
	 * @access public
	 */
	       public function enqueue_scripts() {
		       wp_enqueue_script( 'mp-slim-select' );
	       }

	/**
	 * Enqueues the field's styles
	 *
	 * @since 1.0
	 * @access public
	 */
	       public function enqueue_styles() {
		       wp_enqueue_style( 'mp-slim-select' );
	       }

}