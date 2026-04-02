(function($){
	var shortcodeModalInstance = null;

	var bindShortCodeBuilder = function($form) {
		if (!$form || $form.length === 0 || $form.data('mpShortcodeBuilderBound')) {
			return;
		}

		$form.data('mpShortcodeBuilderBound', true);

		$form.find('[name="shortcode"]').on('change', function(){
			var shortcode = $(this).val();
			var tableId;
			var $table;

			if (!shortcode) {
				$form.find('.form-table').hide();
				return;
			}

			tableId = shortcode.replace(/_/g, '-') + '-shortcode';
			$table = $form.find('.form-table').filter(function() {
				return this.id === tableId;
			});

			if ( $table.length == 0 ) {
				$form.find('.form-table').hide();
				return;
			}

			$table.show().siblings('.form-table').hide();
			refreshChosenFields($form);
		});

		$form.on('submit', function(e){
			e.preventDefault();

			var shortcode = '[' + $form.find('[name="shortcode"]').val();
			var atts = '';

			$form.find('.form-table').filter(':visible').find(':input').filter('[name]').each(function(){
				var $this = $(this);

				if ( ($.trim($this.val()).length == 0 || ($this.attr('data-default') !== undefined && $this.attr('data-default') == $.trim($this.val()))) && !($this.is(':radio') || $this.is(':checkbox')) ) {
					return;
				}

				if ( $this.is(':radio') || $this.is(':checkbox') ) {
					if ( $this.is(':checked') ) {
						atts += ' ' + $this.attr('name') + '="' + $this.val() + '"';
					} else if ( $this.val() === '1' ) {
						atts += ' ' + $this.attr('name') + '="0"';
					}
				} else {
					atts += ' ' + $this.attr('name') + '="' + $this.val() + '"';
				}
			});

			shortcode += atts + ']';

			window.send_to_editor(shortcode);
			if(shortcodeModalInstance) shortcodeModalInstance.close();
		});
	};

	$(document).ready(function($){
		initShortCodeBuilder();
		initShortcodeModal();
		initSelect2($(document));
		initProductSearchField($(document));
		initToolTips($(document));
	});

	var initToolTips = function($context){
		$context = $context || $(document);

		$context.find('.mp-tooltip').off('click.mpTooltip').on('click.mpTooltip', function(){
			var $this = $(this),
					$button = $this.find('.mp-tooltip-button');

			if ( $button.length == 0 ) {
				$this.children('span').append('<a class="mp-tooltip-button" href="#">x</a>');
			}

			$this.children('span').css({
				"display" : "block",
				"margin-top" : -($this.children('span').outerHeight() / 2)
			});
		});

		$context.find('.mp-tooltip').off('click.mpTooltipClose', '.mp-tooltip-button').on('click.mpTooltipClose', '.mp-tooltip-button', function(e){
			e.preventDefault();
			e.stopPropagation();
			$(this).parent().fadeOut(250);
		});
	}

	var initShortcodeModal = function() {
		$('body').on('click', '.mp-shortcode-builder-button', function(){
			var $form = $('#mp-shortcode-builder-form');
			if ($form.length === 0) return;
			var $modalForm = $($form[0].cloneNode(true));
			var $modalRoot = $('<div class="mp-shortcode-builder-modal-root"></div>').append($modalForm);

			bindShortCodeBuilder($modalForm);
			initSelect2($modalForm);
			initProductSearchField($modalForm);
			initToolTips($modalForm);

			shortcodeModalInstance = basicLightbox.create($modalRoot[0], {
				onShow: function(instance) {
					var $lightbox = $(instance.element());
					var $instanceForm = $(instance.element()).find('#mp-shortcode-builder-form');

					$lightbox.css({
						alignItems: 'flex-start',
						paddingTop: '4vh'
					});

					$instanceForm.css({
						marginTop: '0'
					});

					$instanceForm.find('[name="shortcode"]').trigger('change');
				}
			});
			shortcodeModalInstance.show();
		});
	};

 	var initShortCodeBuilder = function() {
		var $form = $('#mp-shortcode-builder-form');

		bindShortCodeBuilder($form);
	};

	var refreshChosenFields = function($context) {
		$context = $context || $(document);
		$context.find('.mp-chosen-select').trigger('chosen:updated');
	};

	var initSelect2 = function($context) {
		$context = $context || $(document);
		var selects = $context[0].querySelectorAll('.mp-chosen-select');
		selects.forEach(function(el) {
			if (typeof SlimSelect !== 'undefined') {
				if (!el.slimSelect) {
					el.slimSelect = new SlimSelect({
						select: el,
						placeholder: el.getAttribute('placeholder') || '',
						allowDeselect: true,
						showSearch: true,
						closeOnSelect: !el.hasAttribute('multiple'),
					});
				}
			}
		});
	};

	var initProductSearchField = function($context) {
		$context = $context || $(document);
		var selects = $context[0].querySelectorAll('select.mp-select-product');
		selects.forEach(function(el) {
			if (typeof SlimSelect !== 'undefined' && !el.slimSelect) {
				el.slimSelect = new SlimSelect({
					select: el,
					placeholder: (typeof MP_ShortCode_Builder !== 'undefined' && MP_ShortCode_Builder.select_product) ? MP_ShortCode_Builder.select_product : '',
					allowDeselect: true,
					showSearch: true,
					closeOnSelect: true
				});
			}
		});
	};
	
}(jQuery));
