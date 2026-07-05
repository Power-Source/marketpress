( function( $ ) {
	jQuery(document).ready(function($){
		var setActiveAdminMenu = function(){
			$('#menu-posts-product, #menu-posts-product > a, #menu-posts-mp_product, #menu-posts-mp_product > a')
				.addClass('wp-menu-open wp-has-current-submenu')
				.find('a[href="edit.php?post_type=mp_order"]').parent().addClass('current');
		};
		
		var modifyBulkActionsInput = function(){
			var $select = $('select[name="action"],select[name="action2"]');
			var options = mp_admin_orders.bulk_actions;
					
			$select.find('option').remove();
			
			$.each(options, function(key, value){
				$select.append('<option value="' + key + '">' + value + '</option>');
			});
		};
		
		var initCopyBillingAddress = function() {
			$( '#mp-order-copy-billing-address' ).on( 'click', function( e ) {
				e.preventDefault();
				
				$( '[name^="mp[billing_info]"]' ).each( function() {
					var $this = $( this );
					var name = $this.attr( 'name' );
					var targetName = name.replace( 'billing_info', 'shipping_info' );
					var $targetField = $( '[name="' + targetName + '"]' );

					$targetField.is('select') && $targetField.html($this.html()) && $targetField.val( $this.val() ) && $targetField.trigger("change") || $targetField.val( $this.val() );
				} );
			} );
		};
		
		var initSelect2Fields = function() {
			var $fields = $( 'select.mp-select2' ).not( '.select2-offscreen' );

			if ( typeof $fields.mp_select2 !== 'function' ) {
				return;
			}

			$fields.mp_select2( {
				dropdownAutoWidth : false,
				width : "element"
			} );
		};
		
		var initUpdateStatesDropdown = function() {
			$( '[name="mp[billing_info][country]"], [name="mp[shipping_info][country]"]' ).on( 'change', function() {
				var $this = $( this );
				var url = ajaxurl + '?action=mp_update_states_dropdown';
				
				if ( $this.attr( 'name' ).indexOf( 'billing_info' ) >= 0 ) {
					var $target = $( '[name="mp[billing_info][state]"]' );
					var type = 'billing';
				} else {
					var $target = $( '[name="mp[shipping_info][state]"]' );
					var type = 'shipping';
				}
				
				var data = {
					country : $this.val(),
					type : type
				}
				
				var old_value = $target.val();
				var hasMpSelect2 = ( typeof $.fn.mp_select2 === 'function' );

				if ( hasMpSelect2 ) {
					$target.mp_select2( 'destroy' );
				}

				$target.hide().next( 'img' ).show();

				if ( hasMpSelect2 ) {
					$this.mp_select2( 'disable' );
				}
						
				$.post( url, data ).done( function( resp ) {
					if ( hasMpSelect2 ) {
						$this.mp_select2( 'enable' );
					}
					
					if ( resp.success ) {
						if ( resp.data.states ) {
							$target.html( resp.data.states ).show().next( 'img' ).hide();
							$target.closest( 'tr' ).show();
							$target.val(old_value);
							initSelect2Fields();
						} else {
							$target.closest( 'tr' ).hide();
						}
					}
				} );
			} ).change();
		};
		
		var initCustomerInfoLightbox = function() {
			$( '.column-mp_orders_name' ).find( 'a' ).on('click', function() {
				var $content = $( this ).parent().find( '.mp-customer-info-lb' );

				if ( window.basicLightbox && $content.length ) {
					basicLightbox.create( '<div class="mp-admin-lightbox-content">' + $content.html() + '</div>' ).show();
					return;
				}

				if ( $.colorbox ) {
					$.colorbox( {
						href : $content,
						inline : true,
						maxWidth : "90%",
						opacity : .7,
						width : 640
					} );
				}
			} );
		};

		var initStatusActionFeedback = function() {
			$( document ).on( 'click', '.mp-order-status-action', function( e ) {
				e.preventDefault();

				var $link = $( this );
				var $card = $link.closest( '.mp-order-status-card' );
				var postId = parseInt( $card.data( 'order-id' ), 10 );
				var targetStatus = $link.data( 'order-status' );

				if ( ! postId || ! targetStatus ) {
					return;
				}

				$card.addClass( 'is-loading' );

				$.post( mp_admin_orders.ajax_url, {
					action: 'mp_change_order_status_inline',
					ajax_nonce: mp_admin_orders.ajax_nonce,
					post_id: postId,
					order_status: targetStatus
				} ).done( function( resp ) {
					if ( resp && resp.success && resp.data && resp.data.html ) {
						$card.replaceWith( resp.data.html );
						document.dispatchEvent( new CustomEvent( 'mp:orderStatusUpdated' ) );
						return;
					}

					$card.removeClass( 'is-loading' );
					alert( 'Status konnte nicht aktualisiert werden.' );
				} ).fail( function() {
					$card.removeClass( 'is-loading' );
					alert( 'Status konnte nicht aktualisiert werden.' );
				} );
			} );
		};

		var handleOtherShippingMethod = function () {
			$('select[name="mp[tracking_info][shipping_method]"]').on('change', function (e) {
				if ($(this).val() == 'other') {
					$('.mp-order-custom-shipping-method').removeClass('mp-hide');
				} else {
					$('.mp-order-custom-shipping-method').addClass('mp-hide');
				}

				var option = $(e.target.options[e.target.selectedIndex]);
				if (option.data('original') == 1) {
					$('.mp-remove-custom-carrier').removeClass('mp-hide');
				} else {
					$('.mp-remove-custom-carrier').addClass('mp-hide');
				}

				if ($(this).val() == 'other' || option.data('original') == 1) {
					$('.mp-order-custom-tracking-link').removeClass('mp-hide');
				} else {
					$('.mp-order-custom-tracking-link').addClass('mp-hide');						
				}	

			}).change();
		};

		var removeCustomShippingMethod = function () {
			$('.mp-remove-custom-carrier').on('click', function (e) {
				e.preventDefault();
				var selected = $('select[name="mp[tracking_info][shipping_method]"]').val();
				var that = $(this);
				$.ajax({
					type: "POST",
					url: ajaxurl,
					data: {
						action: 'mp_remove_custom_shipping_method',
						id: selected,
						ajax_nonce: mp_admin_orders.ajax_nonce
					},
					beforeSend: function () {
						that.attr('disabled', 'disabled');
					},
					success: function (data) {
						if (data.status == 'success') {
							$('select[name="mp[tracking_info][shipping_method]"] option[value="' + selected + '"]').remove();
							$('select[name="mp[tracking_info][shipping_method]"]').change();
						} else {
							alert(data.err);
						}
					}
				})
			})
		};
		
		setActiveAdminMenu();
		modifyBulkActionsInput();
		initCopyBillingAddress();
		initSelect2Fields();
		initUpdateStatesDropdown();
		initCustomerInfoLightbox();
		initStatusActionFeedback();
		handleOtherShippingMethod();
		removeCustomShippingMethod();
	});
}( jQuery ) );
