<?php

class PSOURCE_Field_Colorpicker extends PSOURCE_Field {
	/**
	 * Use this to setup your child form field instead of __construct()
	 *
	 * @since 1.0
	 * @access public
	 * @param array $args
	 */
	public function on_creation( $args ) {
		$this->args['class'] .= ' psource-field-colorpicker-input';
		$this->args['style'] .= 'width:120px;';
	}

	/**
	 * Enqueue scripts
	 *
	 * @since 1.0
	 * @access public
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'psource-field-colorpicker-pickr', mp_plugin_url( 'ui/colorpicker/pickr.min.js' ), array(), PSOURCE_METABOX_VERSION, true );
	}
	
	/**
	 * Enqueue styles
	 *
	 * @since 1.0
	 * @access public
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'psource-field-colorpicker-pickr', mp_plugin_url( 'ui/colorpicker/pickr.min.css' ), array(), PSOURCE_METABOX_VERSION );
	}

	/**
	 * Prints inline javascript
	 *
	 * @since 1.0
	 * @access public
	 */	
	public function print_scripts() {
		?>
		<style>
		.postbox.psource-postbox .inside .psource-colorpicker-field {
			position: relative;
			display: inline-flex;
			align-items: center;
			gap: 10px;
			padding: 6px 10px;
			border: 1px solid #d7dee8;
			border-radius: 12px;
			background: linear-gradient(180deg, #ffffff 0%, #f6f9fc 100%);
			box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
		}

		.postbox.psource-postbox .inside .psource-colorpicker-field input.psource-field-colorpicker-input {
			width: 120px !important;
			min-width: 120px;
			margin: 0;
			padding: 6px 10px;
			border: 1px solid #cbd6e2;
			border-radius: 8px;
			background: #fff;
			box-shadow: none;
			font-family: monospace;
		}

		.postbox.psource-postbox .inside .psource-colorpicker-pickr {
			display: inline-flex;
			align-items: center;
			justify-content: center;
		}

		.postbox.psource-postbox .inside .psource-colorpicker-pickr .pcr-button {
			height: 34px;
			width: 34px;
			border-radius: 10px;
			border: 1px solid #cbd6e2;
			box-shadow: 0 3px 10px rgba(30, 51, 72, .12);
		}

		.pcr-app.psource-pickr-app[data-theme='classic'] {
			position: absolute !important;
			top: calc(100% + 8px) !important;
			left: 0 !important;
			padding: 10px;
			width: 248px;
			max-width: calc(100vw - 32px);
			margin: 0;
			border: 1px solid #d7dee8;
			border-radius: 14px;
			box-shadow: 0 18px 40px rgba(30, 51, 72, .18);
		}

		.pcr-app.psource-pickr-app[data-theme='classic'] .pcr-swatches {
			margin: 8px 0 6px;
		}

		.pcr-app.psource-pickr-app[data-theme='classic'] .pcr-interaction {
			gap: 6px;
		}

		.pcr-app.psource-pickr-app[data-theme='classic'] .pcr-selection .pcr-color-palette {
			height: 9.5em;
		}

		.pcr-app.psource-pickr-app[data-theme='classic'] .pcr-selection .pcr-color-chooser,
		.pcr-app.psource-pickr-app[data-theme='classic'] .pcr-selection .pcr-color-opacity {
			height: .9em;
		}
		</style>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			if (typeof Pickr === 'undefined') {
				return;
			}

			document.querySelectorAll('.psource-field-colorpicker-input').forEach(function(input) {
				if (input.dataset.pickrBound === '1') {
					return;
				}

				var pickrContainer = input.parentNode.querySelector('.psource-colorpicker-pickr');
				if (!pickrContainer) {
					return;
				}

				var pickr = Pickr.create({
					el: pickrContainer,
					theme: 'classic',
					appClass: 'psource-pickr-app',
					inline: true,
					padding: 8,
					autoReposition: false,
					default: input.value || '#ffffff',
					swatches: [
						'#1e3348', '#2f6ca3', '#4caf50', '#f2c14e', '#ef8354',
						'#e91e63', '#7b61ff', '#111827', '#ffffff'
					],
					comparison: false,
					sliders: 'h',
					components: {
						preview: true,
						palette: true,
						opacity: false,
						hue: true,
						interaction: {
							hex: true,
							rgba: false,
							input: true,
							save: true
						}
					}
				});

				pickr.on('save', function(color) {
					var hex = color ? color.toHEXA().toString() : '';
					input.value = hex;
					pickr.hide();
				});

				pickr.on('change', function(color) {
					input.value = color ? color.toHEXA().toString() : '';
				});

				input.addEventListener('input', function() {
					if (input.value) {
						pickr.setColor(input.value);
					}
				});

				input.dataset.pickrBound = '1';
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
		$this->before_field();
		?>
		<span class="psource-colorpicker-field">
			<input type="text" <?php echo $this->parse_atts(); ?> value="<?php echo $this->get_value($post_id); ?>" />
			<span class="psource-colorpicker-pickr"></span>
		</span>
		<?php
		$this->after_field();
	}
}