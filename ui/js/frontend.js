( function( $ ) {
    /**
     * Preload loading icon
     *
     * @since 1.0
     */
    $( '<img>' ).get( 0 ).src = mp_i18n.loadingImage;
    /**
     * Add or remove cart ajax loading icon
     *
     * @since 1.0
     * @param string action Optional, either "show" or "hide". Defaults to "show".
     */
    $.fn.ajaxLoading = function( action ) {
        if ( typeof ( action ) == 'undefined' ) {
            var action = 'show';
        }

        return this.each( function( ) {
            if ( 'show' == action ) {
                $( this ).hide( ).after( '<img src="' + mp_i18n.loadingImage + '" alt="">' );
            } else {
                $( this ).show( ).next( 'img' ).remove( );
            }
        } );
    };

	if ( $.validator !== undefined ) {
		$.extend($.validator.messages, {
			required: mp_i18n.validation.required,
			remote: mp_i18n.validation.remote,
			email: mp_i18n.validation.email,
			url: mp_i18n.validation.url,
			date: mp_i18n.validation.date,
			dateISO: mp_i18n.validation.dateISO,
			number: mp_i18n.validation.number,
			digits: mp_i18n.validation.digits,
			creditcard: mp_i18n.validation.creditcard,
			equalTo: mp_i18n.validation.equalTo,
			accept: mp_i18n.validation.accept,
			maxlength: $.validator.format( mp_i18n.validation.maxlength ),
			minlength: $.validator.format( mp_i18n.validation.minlength ),
			rangelength: $.validator.format( mp_i18n.validation.rangelength ),
			range: $.validator.format( mp_i18n.validation.range ),
			max: $.validator.format( mp_i18n.validation.max ),
			min: $.validator.format( mp_i18n.validation.min )
		});
	}

}( jQuery ) );
var marketpress = { };
( function( $ ) {
	
    marketpress = {
        /**
         * Show or hide the loading overlay
         *
         * @since 1.0
         * @param string action Either show/hide. Optional.
         */
        loadingOverlay: function( action ) {
            var $overlay = $( '#colorbox' ).is( ':visible' ) ? $( '#cboxLoadingOverlay' ) : $( '#mp-loading-overlay' );
            if ( action === undefined ) {
                var action = 'show';
            }

            if ( $overlay.length == 0 ) {
                $( 'body' ).append( '<div id="mp-loading-overlay" style="display:none"></div>' );
                $overlay = $( '#mp-loading-overlay' );
            }

            switch ( action ) {
                case 'show' :
                    $overlay.show( );
                    break;
                case 'hide' :
                    $overlay.hide( );
                    break;
            }
        },
        /**
         * Initialize tooltips using browser native title attribute
         *
         * @since 1.0
         */
        initToolTips: function( ) {
            // Use native browser tooltips via title attribute
            $( document ).on( 'mouseenter', '.mp_tooltip-help, .mp_tooltip', function() {
                var $this = $( this );
                var content = $this.is( '.mp_tooltip' ) ? $this.next( '.mp_tooltip_content' ).html() : $this.attr('data-tooltip');
                if (content) {
                    $this.attr( 'title', content );
                }
            });
        },
        getViewportSize: function() {
			
			function setMiniCartMaxHeight() {
				
				var viewportHeight = $(window).height(),
					//bodyHeight = $('body').height(),
					//documentHeight = $(document).height(),
					//getNonVisibleSize = (bodyHeight - viewportHeight),
					miniCart = $('#mp-floating-cart'),
					miniCartRibbon = miniCart.find('.mp_mini_cart_ribbon'),
					miniCartRibbonHeight = miniCartRibbon.height(),
					miniCartContent = miniCart.find('.mp_mini_cart_content'),
					miniCartContentHeight = miniCartContent.height(),
					miniCartHeight = (miniCartRibbonHeight + miniCartContentHeight),
					miniCartMaxHeight = (viewportHeight - (miniCartRibbonHeight * 2) - 100);
				
				if( miniCartHeight > viewportHeight || miniCartHeight > miniCartMaxHeight ) {
					
					miniCart.each(function() {
						var $this = $(this);
						
						$this.find('.mp_mini_cart_items').css({
							'margin-top' : '50px'
						});
						
						$this.find('.mp_button-mini-cart').css({
							"position" : "absolute",
							"top" : "15px",
							"left" : 0,
							"right" : 0,
							"margin" : "auto 30px"
						});
						
					});
					
				}
				
				miniCartContent.css({
					"max-height" : miniCartMaxHeight + 'px'
				});
				
			}
			
			setMiniCartMaxHeight();
			
			$(window).on('resize', function() {
				setMiniCartMaxHeight();
			});
			
        },
        /**
         * Initialize Slim Select elements (statt select2)
         *
         * @since 1.0
         */
        initSelect2: function() {
            var selects = document.querySelectorAll('select.mp_select2, select.mp-select2, select.mp_select2_search');
            selects.forEach(function(el) {
                if (typeof SlimSelect !== 'undefined') {
                    if (!el.slimSelect) {
                        el.slimSelect = new SlimSelect({
                            select: el,
                            placeholder: el.getAttribute('placeholder') || '',
                            allowDeselect: true,
                            showSearch: !el.classList.contains('mp_select2') || el.classList.contains('mp_select2_search'),
                            closeOnSelect: !el.hasAttribute('multiple'),
                        });
                    }
                }
            });
        },
        /**
         * Initialize order look up
         *
         * @since 1.0
         */
        initOrderLookup: function( ) {
            var $form = $( '#mp-order-lookup-form' );
            var $btn = $form.find( '[type="submit"]' );
            var $input = $form.find( 'input[type="text"]' );
            $form.on( 'submit', function( e ) {
                e.preventDefault( );
                if ( $btn.is( ':hidden' ) ) {
                    // Already searching for an order - bail
                    return;
                }

                var data = $form.serialize( );
                $btn.ajaxLoading( 'show' );
                $input.prop( 'disabled', true );
                $.post( $form.attr( 'action' ), data ).done( function( resp ) {
                    if ( resp.success ) {
                        window.location.href = resp.data.redirect_url;
                    } else {
                        var $tooltip = $input.prev( '.mp_tooltip' );
                        $btn.ajaxLoading( 'hide' );
                        $input.prop( 'disabled', false );
                        if ( $tooltip.length == 0 ) {
                            $input.before( '<div class="mp_tooltip"></div>' );
                            $tooltip = $input.prev( '.mp_tooltip' );
                            // Set error tooltip content
                            $tooltip.attr( 'title', resp.data.error_message ).addClass( 'error' );
                        }

                        $tooltip.attr( 'title', resp.data.error_message ).addClass( 'error' );
                    }
                } );
            } );
        },
        /**
         * Show inline status messages for free download button.
         *
         * @since 1.0
         */
        initFreeDownloadStatus: function() {
            $( document ).on( 'click', '.mp_button-free-download', function() {
                var $btn = $( this );
                var $status = $btn.nextAll( '.mp_free_download_status' ).first();

                if ( $status.length ) {
                    $status
                        .removeClass( 'mp_free_download_status-error mp_free_download_status-success' )
                        .addClass( 'mp_free_download_status-success' )
                        .text( 'Download erfolgreich gestartet.' )
                        .show();
                }
            } );
        },
        /**
         * Initialize content tabs on the single product template
         *
         * @since 1.0
         */
        initProductTabs: function( ) {

            $( 'body' ).on( 'click', '.mp_product_meta a.more-link', function( e ) {
                e.preventDefault( );
                // $( '#mp-single-product a.mp_product_tab_label_link.mp-product-overview' ).click( );
                $( '.mp-single-product a.mp_product_tab_label_link.mp-product-overview' ).click( );
                $( 'html, body' ).animate( {
                    scrollTop: $( "a.mp_product_tab_label_link.mp-product-overview" ).offset( ).top - 30
                }, 500 );
            } );
            $( '.mp_product_tab_label_link' ).on('click', function( e ) {
                e.preventDefault( );
                var $this = $( this ),
                    $tab = $this.parent( ),
                    $target = $( $this.attr( 'href' ) );
                $tab.addClass( 'current' ).siblings( ).removeClass( 'current' );
                //$target.show().siblings( '.mp_product_tab_content' ).hide();
                $target.addClass( 'mp_product_tab_content-current' ).siblings( '.mp_product_tab_content' ).removeClass( 'mp_product_tab_content-current' );
                //equal_heights( $( '#mp-related-products .mp_products-grid .mp_product_meta' ) );
            } );
            if ( window.location.hash.length > 0 ) {
                var $target = $( '[href="' + window.location.hash + '"]' );
                if ( $target.length > 0 ) {
                    $target.trigger( 'click' );
                }
            }
        },
        /**
         * Trigger events after an ajax request
         *
         * @since 1.0
         * @param string event The base event string.
         * @param object resp The ajax response object.
         * @param object $scope Optional, the scope for triggered events. Defaults to document.
         */
        ajaxEvent: function( event, resp, $scope ) {
            if ( $scope === undefined ) {
                var $scope = $( document );
            }

            var successEvent = event + '/success';
            var errorEvent = event + '/error';
            /**
             * Fires whether the response was successful or not
             *
             * @since 1.0
             * @param object The response data.
             */
            $scope.trigger( event, [ resp ] );
            if ( resp.success ) {
                /**
                 * Fires on success
                 *
                 * @since 1.0
                 * @param object The response data object.
                 */
                $scope.trigger( successEvent.replace( '//', '/' ), [ resp.data ] );
            } else {
                var message = ( resp.data === undefined ) ? '' : resp.data.message;
                /**
                 * Fires on error
                 *
                 * @since 1.0
                 * @param string Any applicable error message.
                 */
                $scope.trigger( errorEvent.replace( '//', '/' ), [ message ] );
            }
        },
        /**
         * Initialize product image lightbox
         *
         * @since 1.0
         */
        initImageLightbox: function() {
            if ( typeof window.basicLightbox === 'undefined' ) {
                return;
            }

            // Vanilla JS: Öffnet basicLightbox für Produktbilder
            document.querySelectorAll('.mp_product_image_link.mp_lightbox').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var img = link.querySelector('img');
                    var src = link.getAttribute('href') || (img ? img.src : null);
                    if (!src) return;
                    var _lbContainer = document.createElement('div');
                    _lbContainer.className = 'mp-product-lightbox';
                    var _lbClose = document.createElement('div');
                    _lbClose.className = 'mp-product-lightbox-close';
                    _lbClose.textContent = '\u00d7';
                    var _lbImg = document.createElement('img');
                    _lbImg.src = src;
                    _lbImg.setAttribute('style', 'width:90vw;max-width:1200px;max-height:90vh;object-fit:contain;');
                    _lbContainer.appendChild(_lbClose);
                    _lbContainer.appendChild(_lbImg);
                    var instance = basicLightbox.create(_lbContainer, {
                        closable: true,
                        onShow: function() {
                            var closeBtn = document.querySelector('.mp-product-lightbox-close');
                            if (closeBtn) closeBtn.onclick = function() { instance.close(); };
                            document.addEventListener('keydown', function(ev) {
                                if (ev.key === 'Escape') instance.close();
                            }, { once: true });
                        }
                    });
                    instance.show();
                });
            });
        },
        /**
         * Initialize product filters/pagination
         *
         * @since 1.0
         */
        initProductFiltersPagination: function( ) {
            var $form = $( '#mp-products-filter-form' );
            $form.on( 'change', 'select', function( e ) {
                var $this = $( this );
                // Redirect if product category dropdown changed
                if ( 'product_category' == $this.attr( 'name' ) ) {
                    var val = $this.val( );
                    marketpress.loadingOverlay( 'show' );
                    if ( typeof ( mp_i18n.productCats[ val ] ) == 'undefined' ) {
                        window.location.href = mp_i18n.productsURL;
                    } else {
                        window.location.href = mp_i18n.productCats[ val ];
                    }

                    return;
                } else {
                    marketpress.updateProductList( );
                }
            } );
            /*$( '#mp_product_nav' ).parent( ).on( 'click', '#mp_product_nav a', function( e ) {
                e.preventDefault( );
                var $this = $( this );
                var href = $this.attr( 'href' );
                var query = marketpress.unserialize( href );
                $form.find( 'input[name="page"]' ).val( query.paged );
                marketpress.updateProductList( );
            } );*/
        },
        /**
         * Update product list
         *
         * @since 1.0
         * @access public
         */
        updateProductList: function( ) {
            var $form = $( '#mp-products-filter-form' );
            var data = $form.serialize( );
            var url = mp_i18n.ajaxurl + '?action=mp_update_product_list';
            marketpress.loadingOverlay( 'show' );
            $.post( url, data ).done( function( resp ) {
                marketpress.loadingOverlay( 'hide' );
                $( '.mp_listings_nav' ).remove( );
                $( '#mp-products' ).replaceWith( resp );
                mp_cart.initCartListeners( );
            } );
        },
        /**
         * Unserialize a string
         *
         * @since 1.0
         * @param string str A serialized string or a url containing a querystring
         * @return object
         */
        unserialize: function( str ) {
            if ( str.indexOf( '?' ) >= 0 ) {
                var strParts = str.split( '?' );
                str = strParts[1];
            }

            var dataPairs = str.split( '&' );
            var obj = { };
            $.each( dataPairs, function( index, value ) {
                var tmp = value.split( '=' );
                obj[ tmp[0] ] = tmp[1];
            } );
            return obj;
        },
        /**
         * Initialize global product filters/pagination
         *
         * @since 1.0
         */
        initGlobalProductFiltersPagination: function( ) {
            var $form = $( '#mp_global_product_list_refine' );
            $form.on( 'change', 'select', function( e ) {
                var $this = $( this );
                // Redirect if product category dropdown changed
                if ( 'product_category' == $this.attr( 'name' ) ) {
                    var val = $this.val( );
                    marketpress.loadingOverlay( 'show' );
                    if ( typeof ( mp_global.cat_urls[ val ] ) == 'undefined' ) {
                        window.location.href = mp_global.cat_url;
                    } else {
                        window.location.href = mp_global.cat_urls[ val ];
                    }

                    return;
                } else {
                    marketpress.updateGlobalProductList( );
                }
            } );
        },
        /**
         * Update global product list
         *
         * @since 1.0
         * @access public
         */
        updateGlobalProductList: function( ) {
            var $form = $( '#mp_global_product_list_refine' );
            var data = $form.serialize( );
            var url = mp_i18n.ajaxurl + '?action=mp_global_update_product_list';
            marketpress.loadingOverlay( 'show' );
            $.post( url, data ).done( function( resp ) {
                marketpress.loadingOverlay( 'hide' );
                $( '.mp_listings_nav' ).remove( );
                $( '.mp_global_product_list_widget .mp_products' ).replaceWith( resp );
                mp_cart.initCartListeners( );
            } );
        },
    };
}( jQuery ) );
jQuery( document ).ready( function( ) {
    marketpress.initSelect2( );
    marketpress.initProductTabs( );
    marketpress.initToolTips( );
    marketpress.initOrderLookup( );
    marketpress.initFreeDownloadStatus( );
    marketpress.initImageLightbox( );
    marketpress.initProductFiltersPagination( );
    marketpress.initGlobalProductFiltersPagination();
    marketpress.getViewportSize();
} );