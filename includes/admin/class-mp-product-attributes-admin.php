<?php

class MP_Product_Attributes_Admin {

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
			self::$_instance = new MP_Product_Attributes_Admin();
		}
		return self::$_instance;
	}

	/**
	 * Displays the product attributes
	 *
	 * @since 1.0
	 * @access private
	 */
	public static function display_product_attributes() {
		?>
		<style type="text/css">
			th.column-ID {
				width: 50px;
			}
		</style>
		<div class="wrap mp-wrap">
			<?php
			require_once mp_plugin_dir( 'includes/admin/class-mp-product-attributes-list-table.php' );
			$list_table = new MP_Product_Attributes_List_Table();
			$list_table->prepare_items();
			?>
			<div class="icon32"><img src="<?php echo mp_plugin_url( 'ui/images/settings.png' ); ?>" /></div>
			<h2 class="mp-settings-title"><?php _e( 'Produktattribute', 'mp' ); ?> <a class="add-new-h2" href="<?php echo add_query_arg( array( 'action' => 'mp_add_product_attribute' ) ); ?>"><?php _e( 'Attribut hinzufügen', 'mp' ); ?></a></h2>
			<div class="clear"></div>
			<div class="mp-settings">
				<form method="get">
					<input type="hidden" name="page" value="<?php echo $_REQUEST[ 'page' ]; ?>" />
					<?php $list_table->display(); ?>
				</form>
			</div>
			<?php
			// Bearbeitungsformular NUR anzeigen, wenn Attribut ausgewählt ist
			if (isset($_GET['attribute_id']) && !empty($_GET['attribute_id']) && isset($_GET['action']) && $_GET['action'] === 'mp_edit_product_attribute') {
				do_action('mp_product_attribute_edit_box');
			}
			?>
		</div>
		<?php
	}

	/**
	 * Add product attribute metaboxes
	 *
	 * @since 1.0
	 * @access public
	 */
	public static function add_product_attribute_metaboxes() {
		$metabox	 = new PSOURCE_Metabox( array(
			'id'		 => 'mp-store-settings-product-attributes-add',
			'title'		 => __( 'Produktattribut hinzufügen', 'mp' ),
			'page_slugs' => array( 'store-settings-productattributes' ),
		) );
		$metabox->add_field( 'text', array(
			'name'		 => 'product_attribute_name',
			'label'		 => array( 'text' => __( 'Attributname', 'mp' ) ),
			'desc'		 => __( 'Der Name des Attributs (z. B. Farbe, Größe, etc.)', 'mp' ),
			'validation' => array(
				'required' => true,
			),
		) );
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'product_attribute_terms_sort_by',
			'label'			 => array( 'text' => __( 'Sortieren nach', 'mp' ) ),
			'default_value'	 => 'ID',
			'desc'			 => __( 'Wähle, wie die Optionen sortiert werden sollen.', 'mp' ),
			'options'		 => array(
				'ID'	 => __( 'ID', 'mp' ),
				'ALPHA'	 => __( 'Alphabetisch', 'mp' ),
				'CUSTOM' => __( 'Benutzerdefiniert', 'mp' ),
			),
		) );
		mp()->register_custom_types();
		$cats		 = get_terms( 'product_category', array(
			'hide_empty' => false,
			'fields'	 => 'id=>name'
		) );
				$metabox->add_field('advanced_select', array(
					'name' => 'product_attribute_categories',
					'label' => array('text' => __('Produktkategorien', 'mp')),
					'placeholder' => __( 'Produktkategorien auswählen', 'mp' ),
					'desc' => __( 'Wähle die Produktkategorie(n) aus, für die dieses Attribut verfügbar sein soll. Wenn keine Kategorien ausgewählt werden, gilt dieses Attribut für alle Produktkategorien.', 'mp' ),
					'options' => $cats,
				));
		$metabox->add_field( 'radio_group', array(
			'name'			 => 'product_attribute_terms_sort_order',
			'label'			 => array( 'text' => __( 'Sortierreihenfolge', 'mp' ) ),
			'default_value'	 => 'ASC',
			'options'		 => array(
				'ASC'	 => __( 'Aufsteigend', 'mp' ),
				'DESC'	 => __( 'Absteigend', 'mp' ),
			),
			'conditional'	 => array(
				'name'	 => 'product_attribute_terms_sort_by',
				'value'	 => 'CUSTOM',
				'action' => 'hide',
			),
			'width'			 => '33%',
		) );
		$repeater	 = $metabox->add_field( 'repeater', array(
			'name'			 => 'product_attribute_terms',
			'layout'		 => 'table',
			'add_row_label'	 => __( 'Option hinzufügen', 'mp' ),
			'label'			 => array( 'text' => __( 'Attributoptionen', 'mp' ) ),
			'desc'			 => __( 'Verwende die Zahlen auf der linken Seite, um zu sortieren. Zum Löschen - klicke auf das "X" rechts von jeder Zeile.', 'mp' ),
		) );

		if ( $repeater instanceof PSOURCE_Field ) {
			$repeater->add_sub_field( 'text', array(
				'name'		 => 'name',
				'label'		 => array( 'text' => __( 'Name', 'mp' ) ),
				'desc'		 => __( 'Maximal 45 Zeichen.', 'mp' ),
				'custom'	 => array( 'maxlength' => 45 ),
				'validation' => array(
					'required' => true,
				),
			) );
			$repeater->add_sub_field( 'text', array(
				'name'						 => 'slug',
				'label'						 => array( 'text' => __( 'Slug', 'mp' ) ),
				'desc'						 => __( 'Wenn kein Slug eingegeben wird, wird er automatisch generiert. Maximal 32 Zeichen.', 'mp' ),
				'custom'					 => array( 'maxlength' => 32 ),
				'validation'				 => array(
					'custom' => '[a-z\-]',
				),
				'custom_validation_message'	 => __( 'Nur Kleinbuchstaben und Bindestriche (-) sind erlaubt.', 'mp' ),
			) );
		}
	}

	public static function get_product_attributes() {
		global $wpdb;
		$table_name	 = $wpdb->prefix . 'mp_product_attributes';
		$results	 = $wpdb->get_results( 'SELECT * FROM ' . $table_name, OBJECT );

		return $results;
	}

	public static function get_product_attributes_select( $name = '', $value_type = 'id', $id = '', $class = 'mp_product_attributes_select' ) {
		$product_attributes = MP_Product_Attributes_Admin::get_product_attributes();
		?>
		<select name="<?php echo $name; ?>" <?php if ( !empty( $id ) ) { ?>id="<?php echo $id; ?>"<?php } ?> class="<?php echo $class; ?>">
			<option value="-1"><?php _e( '- Neue Variation erstellen -', 'mp' ); ?></option>
			<?php foreach ( $product_attributes as $product_attribute ) {
				$tags = '';
				if( $attribute_terms = MP_Product_Attributes_Admin::get_product_attribute_terms( $product_attribute->attribute_id ) ){
					$tags = mp_array_column( $attribute_terms, 'name' );
					$tags = implode( ',', $tags );
				}

				?>
				<option data-tags='<?php echo $tags ?>' value="<?php
				if ( $value_type == 'id' ) {
					echo $product_attribute->attribute_id;
				} else {
					echo sanitize_key( $product_attribute->attribute_name );
				};
				?>"><?php echo $product_attribute->attribute_name; ?></option>
						<?php
					}
					?>
		</select>
		<?php
	}

	public static function get_product_attribute_color( $attribute_id, $order = false ) {
		if ( $order ) {
			$attribute_id = $order;
		} else {
			if ( $attribute_id > 10 ) {
				$attribute_id = substr( $attribute_id, -1 );
			}
		}
		return 'variation_color_' . $attribute_id;
	}


/**
	 * Gets the product attribute terms
	 *
	 * @since 1.0
	 * @access public
	 * @return string
	 */
	public static function get_product_attribute_terms( $attribute_id ) {

		if( !isset( $attribute_id ) || empty( $attribute_id ) || !is_numeric( $attribute_id ) ){
			return false; 
		}

		global $wpdb;

		$product_atts	 = MP_Product_Attributes::get_instance();
		$attribute_slug	 = $product_atts->generate_slug( $attribute_id );
		$terms			 = get_terms( $attribute_slug, array( 'hide_empty' => false ) );
		$value			 = array();

		if( empty( $terms ) || !is_array( $terms ) ){
			return false;
		}

		// Sort terms by term order
		$product_atts->sort_terms_by_custom_order( $terms );

		foreach ( $terms as $term ) {
			$value[] = array(
				'ID'	 => $term->term_id,
				'name'	 => $term->name,
				'slug'	 => $term->slug,
			);
		}

		return $value;
	}	

	/**
	 * Gets the product attribute terms
	 *
	 * @since 1.0
	 * @access public
	 * @filter psource_field_value
	 * @return string
	 */
	public static function get_product_attribute_value( $value, $post_id, $raw, $field ) {
		global $wpdb;

		switch ( $field->args[ 'name' ] ) {
			case 'product_attribute_name' :
			case 'product_attribute_terms_sort_by' :
			case 'product_attribute_terms_sort_order' :
				$table_name	 = $wpdb->prefix . 'mp_product_attributes';
				$cache_key	 = 'attribute_row_' . mp_get_get_value( 'attribute_id' );
				$attribute	 = wp_cache_get( $cache_key, 'mp_product_attributes' );

				if ( false === $attribute ) {
					$attribute = $wpdb->get_row( $wpdb->prepare( "
						SELECT * FROM $table_name
						WHERE attribute_id = %d", mp_get_get_value( 'attribute_id' )
					) );
					wp_cache_set( $cache_key, $attribute, 'mp_product_attributes' );
				}

				$key	 = str_replace( 'product_', '', $field->args[ 'name' ] );
				$value	 = $attribute->$key;
				break;

			case 'product_attribute_categories' :
				$table_name	 = $wpdb->prefix . 'mp_product_attributes_terms';
				$cache_key	 = 'attribute_categories_' . mp_get_get_value( 'attribute_id' );
				$results	 = wp_cache_get( $cache_key, 'mp_product_attributes' );

				if ( false === $results ) {
					$results = $wpdb->get_results( $wpdb->prepare( "
						SELECT term_id
						FROM $table_name
						WHERE attribute_id = %s", mp_get_get_value( 'attribute_id' )
					) );
					wp_cache_set( $cache_key, $results, 'mp_product_attributes' );
				}

				$value = wp_list_pluck( $results, 'term_id' );
				break;

			case 'product_attribute_terms' :
				$product_atts	 = MP_Product_Attributes::get_instance();
				$attribute_slug	 = $product_atts->generate_slug( mp_get_get_value( 'attribute_id' ) );
				$terms			 = get_terms( $attribute_slug, array( 'hide_empty' => false ) );
				$value			 = array();

				// Sort terms by term order
				$product_atts->sort_terms_by_custom_order( $terms );

				foreach ( $terms as $term ) {
					$value[] = array(
						'ID'	 => $term->term_id,
						'name'	 => $term->name,
						'slug'	 => $term->slug,
					);
				}
				break;
		}

		return $value;
	}

	/**
	 * Saves the product attribute
	 *
	 * @since 1.0
	 * @action psource_metabox_before_save_fields
	 * @uses $wpdb
	 */
	public static function save_product_attribute( $metabox ) {
				// DEBUG: POST-Daten und Metabox ausgeben
				   // Debug-Ausgaben entfernt, um Header-Fehler zu vermeiden
		global $wpdb;

		$product_atts		 = MP_Product_Attributes::get_instance();
		$table_name			 = MP_Product_Attributes::get_instance()->get_table_name();
		$table_name_terms	 = $wpdb->prefix . 'mp_product_attributes_terms';
		$redirect_url		 = remove_query_arg( array( 'action', 'action2' ) );

		// Robust: Terms aus verschachtelten Arrays extrahieren
		$terms = array();
		foreach ( $metabox->fields as $k => $field ) {
			if ( 'product_attribute_terms' == $field->args[ 'name' ] ) {
				$raw_terms = mp_get_post_value( 'product_attribute_terms' );
				// Format: [0][name][existing][id], [0][name][new][id], [0][slug][existing][id], ...
				if (is_array($raw_terms)) {
					foreach ($raw_terms as $row) {
						if (!is_array($row)) continue; // Nur Arrays verarbeiten
						// Sammle alle existing und new
						foreach (['existing', 'new'] as $type) {
							if (
								isset($row['name']) && is_array($row['name']) &&
								isset($row['name'][$type]) && is_array($row['name'][$type])
							) {
								foreach ($row['name'][$type] as $id => $name) {
									$slug = '';
									if (
										isset($row['slug']) && is_array($row['slug']) &&
										isset($row['slug'][$type]) && is_array($row['slug'][$type]) &&
										isset($row['slug'][$type][$id])
									) {
										$slug = $row['slug'][$type][$id];
									}
									$terms[] = array('id' => ($type == 'existing' ? '_' . $id : $id), 'name' => $name, 'slug' => $slug);
								}
							}
						}
					}
				}
				break;
			}
		}

		if ( mp_get_get_value( 'action' ) == 'mp_add_product_attribute' ) {
			$wpdb->insert( $table_name, array(
				'attribute_name'			 => mp_get_post_value( 'product_attribute_name', '' ),
				'attribute_terms_sort_by'	 => mp_get_post_value( 'product_attribute_terms_sort_by', '' ),
				'attribute_terms_sort_order' => mp_get_post_value( 'product_attribute_terms_sort_order', '' ),
			) );
			$attribute_id	 = $wpdb->insert_id;
			$attribute_slug	 = $product_atts->generate_slug( $attribute_id );

			//temporarily register the taxonomy - otherwise we won't be able to insert terms below
			register_taxonomy( $attribute_slug, MP_Product::get_post_type(), array(
				'show_ui'			 => false,
				'show_in_nav_menus'	 => false,
				'hierarchical'		 => true,
			) );

			//insert terms
					foreach ( $terms as $order => $term ) {
						if (!is_array($term) || !isset($term['name'])) continue;
						$term_slug = isset($term['slug']) ? $term['slug'] : '';
						$term_name = $term['name'];
						$id = isset($term['id']) ? $term['id'] : '';

						$term_args = array();

						if ( false === strpos( $id, '_' ) ) {
							if ( !empty( $term_slug ) ) {
								$term_args[ 'slug' ] = substr( sanitize_key( $term_slug ), 0, 32 );
							}

							$inserted = wp_insert_term( $term_name, $attribute_slug, $term_args );

							if ( !is_wp_error( $inserted ) ) {
								$term_ids[]  = $term_id  = $inserted[ 'term_id' ];
							} else {
								// term slug already exists, get existing term slug
								$term_ids[]  = $term_id  = $inserted->error_data[ 'term_exists' ];
							}
						} else {
							$term_id             = $term_ids[]             = substr( $id, 1 );
							$term_args[ 'slug' ] = $term_slug;
							$term_args[ 'name' ] = $term_name;

							if ( !empty( $term_args[ 'slug' ] ) ) {
								$term_args[ 'slug' ] = substr( sanitize_key( $term_args[ 'slug' ] ), 0, 32 );
							}

							wp_update_term( $term_id, $attribute_slug, $term_args );
						}

						// Update term order
						if (isset($term_id)) {
							$wpdb->update( $wpdb->terms, array(
								'term_order' => ($order + 1)
							), array(
								'term_id' => $term_id
							) );
						}
					}

			//insert product categories
			if ( $cats = mp_get_post_value( 'product_attribute_categories' ) ) {
				$cats	 = explode( ',', $cats );
				$sql	 = "INSERT INTO $table_name_terms (attribute_id, term_id) VALUES ";

				foreach ( $cats as $term_id ) {
					$sql .= $wpdb->prepare( "(%s, %s),", $attribute_id, $term_id );
				}

				$sql = rtrim( $sql, ',' ); //remove trailing comma
				$wpdb->query( $sql );
			}

			//redirect
			wp_redirect( add_query_arg( array(
				'attribute_id'	 => $attribute_id,
				'action'		 => 'mp_edit_product_attribute',
				'mp_message'	 => 'mp_product_attribute_added'
			), $redirect_url ) );
		} else {
			$term_ids		 = array();
			$attribute_id	 = mp_get_get_value( 'attribute_id' );
			$attribute_slug	 = $product_atts->generate_slug( $attribute_id );
			$wpdb->update( $table_name, array(
				'attribute_name'			 => mp_get_post_value( 'product_attribute_name', '' ),
				'attribute_terms_sort_by'	 => mp_get_post_value( 'product_attribute_terms_sort_by', '' ),
				'attribute_terms_sort_order' => mp_get_post_value( 'product_attribute_terms_sort_order', '' ),
			), array(
				'attribute_id' => $attribute_id
			) );

			//insert terms
			foreach ( $terms as $order => $term ) {
				// Robust: Typprüfung und Validierung
				if (!is_array($term)) continue;
				$id = isset($term['id']) ? $term['id'] : '';
				$term_name = isset($term['name']) && is_string($term['name']) ? $term['name'] : '';
				$term_slug = isset($term['slug']) && is_string($term['slug']) ? $term['slug'] : '';

				// Nur verarbeiten, wenn Name vorhanden
				if (empty($term_name)) continue;

				$term_args = array();

				if ( false === strpos( $id, '_' ) ) {
					if ( !empty( $term_slug ) ) {
						$term_args[ 'slug' ] = substr( sanitize_key( $term_slug ), 0, 32 );
					}

					$inserted = wp_insert_term( $term_name, $attribute_slug, $term_args );

					if ( !is_wp_error( $inserted ) ) {
						$term_ids[]  = $term_id  = $inserted[ 'term_id' ];
					} else {
						// term slug already exists, get existing term slug
						$term_ids[]  = $term_id  = $inserted->error_data[ 'term_exists' ];
					}
				} else {
					$term_id             = $term_ids[]             = substr( $id, 1 );
					$term_args[ 'slug' ] = $term_slug;
					$term_args[ 'name' ] = $term_name;

					if ( !empty( $term_args[ 'slug' ] ) ) {
						$term_args[ 'slug' ] = substr( sanitize_key( $term_args[ 'slug' ] ), 0, 32 );
					}

					wp_update_term( $term_id, $attribute_slug, $term_args );
				}

				// Update term order
				if (isset($term_id)) {
					$wpdb->update( $wpdb->terms, array(
						'term_order' => ($order + 1)
					), array(
						'term_id' => $term_id
					) );
				}
			}
			$unused_terms = get_terms( $attribute_slug, array(
				'hide_empty' => false,
				'exclude'	 => $term_ids
			) );
			foreach ( $unused_terms as $term ) {
				if (is_object($term) && isset($term->term_id)) {
					wp_delete_term( $term->term_id, $attribute_slug );
				}
			}

			//update product categories
			$wpdb->delete( $table_name_terms, array( 'attribute_id' => $attribute_id ) );
			if ( $cats = mp_get_post_value( 'product_attribute_categories' ) ) {
				$cats	 = explode( ',', $cats );
				$sql	 = "INSERT INTO $table_name_terms (attribute_id, term_id) VALUES ";

				foreach ( $cats as $term_id ) {
					$sql .= $wpdb->prepare( "(%s, %s),", $attribute_id, $term_id );
				}

				$sql = rtrim( $sql, ',' ); //remove trailing comma
				$wpdb->query( $sql );
			}

			//redirect
			wp_redirect( add_query_arg( array(
				'attribute_id'	 => $attribute_id,
				'action'		 => 'mp_edit_product_attribute',
				'mp_message'	 => 'mp_product_attribute_updated'
			), $redirect_url ) );
		}

		exit;
	}

	/**
	 * Print custom scripts for the product attribute repeater field
	 *
	 * @since 1.0
	 * @access public
	 * @param PSOURCE_Field $field
	 */
	function product_attribute_scripts( $field ) {
		if ( $field->args[ 'name' ] != 'product_attribute_terms' ) {
			return;
		}
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			var subfields = document.querySelectorAll('.psource-subfields');
			// Slug generieren bei Blur
			subfields.forEach(function(subfieldWrap) {
				subfieldWrap.addEventListener('blur', function(e) {
					var input = e.target;
					if (!input.matches('input[name^="product_attribute_terms"][name*="[name]"]')) return;
					var subfield = input.closest('.psource-subfield');
					var nextSubfield = subfield ? subfield.nextElementSibling : null;
					var slugField = nextSubfield ? nextSubfield.querySelector('input') : null;
					if (!slugField) return;
					if (slugField.value.trim().length > 0) return;
					var slug = input.value.toLowerCase().replace(/ /g, '-').replace(/[^a-z0-9-]/g, '');
					slugField.value = slug;
				}, true);
			});
			// Slug generieren beim Speichern
			var form = document.querySelector('form');
			if (form) {
				form.addEventListener('submit', function() {
					var nameInputs = form.querySelectorAll('input[name^="product_attribute_terms"][name*="[name]"]');
					nameInputs.forEach(function(input) {
						var subfield = input.closest('.psource-subfield');
						var nextSubfield = subfield ? subfield.nextElementSibling : null;
						var slugField = nextSubfield ? nextSubfield.querySelector('input') : null;
						if (slugField && slugField.value.trim().length === 0) {
							var slug = input.value.toLowerCase().replace(/ /g, '-').replace(/[^a-z0-9-]/g, '');
							slugField.value = slug;
						}
					});
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Constructor
	 *
	 * @since 1.0
	 * @access private
	 */
	private function __construct() {
		add_action( 'psource_field/print_scripts', array( &$this, 'product_attribute_scripts' ) );
	}

}

MP_Product_Attributes_Admin::get_instance();
