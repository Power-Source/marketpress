<?php

class PSOURCE_Field_Taxonomy_Select extends PSOURCE_Field {

	/**
	 * Runs on parent construct
	 *
	 * @since 1.0
	 * @access public
	 * @param array $args {
	 * 		An array of arguments. Optional.
	 *
	 * 		@type bool $multiple Whether to allow multi-select or only one option.
	 * 		@type string $placeholder The text that shows up when the field is empty.
	 * 		@type array/string $taxonomy An array of taxonomies or a single taxonomy..
	 * 		@type string $format_dropdown_header The text to show in the dropdown header (e.g. select all, select none)
	 * }	 	 
	 */
	public function on_creation( $args ) {
		$this->args = array_replace_recursive( array(
			'multiple'				 => false,
			'placeholder'			 => __( 'Beiträge auswählen', 'mp' ),
			'taxonomy'				 => '',
			'format_dropdown_header' => '',
		), $args );

		$this->args[ 'class' ] .= ' psource-taxonomy-select';
		$this->args[ 'custom' ][ 'data-placeholder' ]			 = $this->args[ 'placeholder' ];
		$this->args[ 'custom' ][ 'data-multiple' ]				 = (int) $this->args[ 'multiple' ];
		$this->args[ 'custom' ][ 'data-format-dropdown-header' ] = $this->args[ 'format_dropdown_header' ];
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
		$values = explode( ',', $value );
		return parent::format_value( $values, $post_id );
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
			       var selects = document.querySelectorAll('select.psource-taxonomy-select, input.psource-taxonomy-select');
			       selects.forEach(function(el) {
				       if (typeof SlimSelect !== 'undefined') {
					       if (!el.slimSelect) {
						       el.slimSelect = new SlimSelect({
							       select: el,
							       placeholder: el.getAttribute('data-placeholder') || '',
							       allowDeselect: true,
							       showSearch: true,
							       closeOnSelect: !el.hasAttribute('multiple'),
						       });
					       }
				       }
			       });
		       });
		       </script>
		       <?php
	       }

	/**
	 * Sanitizes the field value before saving to database.
	 *
	 * @since 1.0
	 * @access public
	 * @param $value
	 * @param $post_id
	 */
	public function sanitize_for_db( $value, $post_id ) {
		$value = trim( $value, ',' );
		return parent::sanitize_for_db( $value, $post_id );
	}

	/**
	 * Displays the field
	 *
	 * @since 1.0
	 * @access public
	 * @param int $post_id
	 */
	public function display( $post_id ) {
		$value	 = $this->get_value( $post_id );
		$vals	 = is_array( $value ) ? $value : explode( ',', $value );
		$values	 = array();
		$options = array();
		$terms	 = get_terms( $this->args[ 'taxonomy' ], 'hide_empty=0' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$taxonomy = get_taxonomy( $term->taxonomy );

				$label = '';
				if ( isset( $taxonomy->labels->singular_name ) ) {
					$label = $taxonomy->labels->singular_name . ': ';
				} elseif ( isset( $taxonomy->singular_name ) ) {
					$label = isset( $taxonomy->singular_name ) . ': ';
				}

				$options[] = $term->term_id . '=' . $label . $term->name;
			}
		}

		$this->before_field();

		if ( $this->args[ 'multiple' ] ) :
			$this->args[ 'custom' ][ 'data-options' ] = implode( '||', $options );
			echo '<input type="hidden" ' . $this->parse_atts() . ' value="' . implode( ',', $vals ) . '" />';
		else :
			?>
			<select <?php echo $this->parse_atts(); ?>>
				<?php
				foreach ( $this->args[ 'options' ] as $val => $label ) :
					$value = empty( $val ) ? '' : ' value="' . $val . '"';
					?>
					<option<?php echo $value;
				echo in_array( $val, $vals ) ? ' selected' : ''; ?>><?php echo $label; ?></option>
				<?php endforeach; ?>
			</select>
		<?php
		endif;

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
