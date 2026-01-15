<?php

class PSOURCE_Field_User_Select extends PSOURCE_Field {

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
	 * 		@type array $options An array of $key => $value pairs of the available options.
	 * 		@type string $format_dropdown_header The text to show in the dropdown header (e.g. select all, select none)
	 * }	 
	 */
	public function on_creation( $args ) {
		$this->args = array_replace_recursive( array(
			'multiple'				 => true,
			'placeholder'			 => __( 'Select Some Users', 'mp' ),
			'format_dropdown_header' => '',
		), $args );

		$this->args[ 'class' ] .= ' psource-user-select';
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
			       var selects = document.querySelectorAll('select.psource-user-select, input.psource-user-select');
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
		       parent::print_scripts();
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
	 * @uses $wpdb
	 * @param int $post_id
	 */
	public function display( $post_id ) {
		global $wpdb;

		$value	 = $this->get_value( $post_id );
		$vals	 = is_array( $value ) ? $value : explode( ',', $value );
		$values	 = $options = array();

		$users = wp_cache_get( 'users', 'psource_user_select_field' );
		if ( false === $users ) {
			$users = $wpdb->get_results( "
				SELECT ID, user_login
				FROM $wpdb->users
			" );

			wp_cache_set( 'users', $users, 'psource_user_select_field' );
		}

		foreach ( $users as $user ) {
			$options[] = $user->ID . '=' . $user->user_login;
		}

		$this->before_field();

		if ( $this->args[ 'multiple' ] ) :
			$this->args[ 'custom' ][ 'data-options' ] = implode( '||', $options );
			echo '<input type="hidden" ' . $this->parse_atts() . ' value="' . implode( ',', $vals ) . '" />';
		else :
			?>
			<select <?php echo $this->parse_atts(); ?>>
				<?php
				foreach ( $options as $val => $label ) :
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
