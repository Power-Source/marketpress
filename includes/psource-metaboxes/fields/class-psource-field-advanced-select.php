<?php

class PSOURCE_Field_Advanced_Select extends PSOURCE_Field {

	/**
	 * Runs on parent construct
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param array $args {
	 *        An array of arguments. Optional.
	 *
	 * @type bool $multiple Whether to allow multi-select or only one option.
	 * @type string $placeholder The text that shows up when the field is empty.
	 * @type array $options An array of $key => $value pairs of the available options.
	 * @type string $format_dropdown_header The text to show in the dropdown header (e.g. select all, select none)
	 * }
	 */
	public function on_creation( $args ) {
		$this->args = array_replace_recursive( array(
			'multiple'               => true,
			'placeholder'            => __( 'Optionen auswählen', 'mp' ),
			'options'                => array(),
			'is_tag'                 => false,
			'format_dropdown_header' => '',
		), $args );

		$this->args['class'] .= ' psource-advanced-select';
		$this->args['custom']['data-placeholder']            = $this->args['placeholder'];
		$this->args['custom']['data-multiple']               = (int) $this->args['multiple'];
		$this->args['custom']['data-format-dropdown-header'] = $this->args['format_dropdown_header'];
		if ( isset( $this->args['custom']['is_tag'] ) ) {
			$this->args['is_tag'] = $this->args['custom']['is_tag'];
		}
	}

	/**
	 * Formats the field value for display.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param mixed $value
	 * @param mixed $post_id
	 */
	public function format_value( $value, $post_id ) {
		$values = ( is_array( $value ) ) ? $value : explode( ',', $value );

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
			       var selects = document.querySelectorAll('select.psource-advanced-select, input.psource-advanced-select');
			       selects.forEach(function(el) {
				       if (typeof SlimSelect !== 'undefined') {
					       if (!el.slimSelect) {
						       el.slimSelect = new SlimSelect({
							       select: el,
							       placeholder: el.getAttribute('data-placeholder') || '',
							       allowDeselect: true,
							       showSearch: true,
							       closeOnSelect: !el.hasAttribute('multiple'),
							       events: {
								       afterChange: function() {
									       el.dispatchEvent(new Event('change', { bubbles: true }));
								       }
							       },
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
	 * Sanitizes the field value before saving to database.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param $value
	 * @param $post_id
	 */
	public function sanitize_for_db( $value, $post_id ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}
		$value = trim( $value, ',' );

		return parent::sanitize_for_db( $value, $post_id );
	}

	/**
	 * Displays the field
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param int $post_id
	 */
	public function display( $post_id ) {
		$value   = $this->get_value( $post_id );
		$vals    = is_array( $value ) ? $value : explode( ',', $value );
		$values  = array();
		$options = array();

		foreach ( $this->args['options'] as $val => $label ) {
			$options[] = $val . '=' . $label;
		}

		$this->before_field();

		if ( $this->args['multiple'] ) :
			// Für PHP-Mehrfachauswahl den Namen mit [] ergänzen, damit alle Werte übertragen werden
			$orig_name              = $this->args['name'];
			$this->args['name']     = $orig_name . '[]';
			?>
			<select multiple <?php echo $this->parse_atts(); ?>>
				<?php foreach ( $this->args['options'] as $val => $label ) :
					$attr = empty( $val ) ? '' : ' value="' . esc_attr( $val ) . '"';
					?>
					<option<?php echo $attr; echo in_array( $val, $vals ) ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			$this->args['name'] = $orig_name;
		else :
			?>
			<select <?php echo $this->parse_atts(); ?>>
				<?php
				foreach ( $this->args['options'] as $val => $label ) :
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
