jQuery( document ).ready( function( $ ) {
    var mp_product_admin_i18n = $.extend( {
        ajaxurl: '',
        ajax_nonce: '',
        creating_vatiations_message: 'Creating variations, please wait...',
        bulk_update_prices_multiple_title: '',
        bulk_update_prices_single_title: '',
        bulk_update_inventory_multiple_title: '',
        bulk_update_inventory_single_title: '',
        bulk_delete_multiple_title: '',
        bulk_delete_single_title: '',
        message_input_required: 'Input is required',
        message_valid_number_required: 'Valid number is required',
        saving_message: 'Please wait...saving in progress...',
        placeholder_image: ''
    }, window.mp_product_admin_i18n || {} );

    var mpVariationModalInstance = null;

    function closeMpVariationModal() {
        if ( mpVariationModalInstance ) {
            mpVariationModalInstance.close();
            return;
        }

        if ( window.parent && window.parent.jQuery && window.parent.jQuery.colorbox ) {
            window.parent.jQuery.colorbox.close();
        } else if ( window.jQuery && window.jQuery.colorbox ) {
            window.jQuery.colorbox.close();
        }
    }

    function createModalShell( title, contentNode ) {
        var shell = document.createElement( 'div' ),
            head = document.createElement( 'div' ),
            titleEl = document.createElement( 'strong' ),
            close = document.createElement( 'button' ),
            body = document.createElement( 'div' );

        shell.className = 'mp-variation-modal-shell';
        head.className = 'mp-variation-modal-head';
        body.className = 'mp-variation-modal-body';
        close.className = 'mp-variation-modal-close';
        close.type = 'button';
        close.textContent = 'x';

        titleEl.textContent = title || '';

        close.addEventListener( 'click', function() {
            closeMpVariationModal();
        } );

        head.appendChild( titleEl );
        head.appendChild( close );
        body.appendChild( contentNode );
        shell.appendChild( head );
        shell.appendChild( body );

        return shell;
    }

    function openInlineModal( $content, title ) {
        if ( typeof basicLightbox === 'undefined' || !$content.length ) {
            return;
        }

        closeMpVariationModal();

        var contentEl = $content.get( 0 ),
            originalParent = contentEl.parentNode,
            nextSibling = contentEl.nextSibling,
            shell = createModalShell( title, contentEl );

        contentEl.style.display = 'block';

        mpVariationModalInstance = basicLightbox.create( shell, {
            onClose: function() {
                contentEl.style.display = 'none';
                if ( originalParent ) {
                    if ( nextSibling && nextSibling.parentNode === originalParent ) {
                        originalParent.insertBefore( contentEl, nextSibling );
                    } else {
                        originalParent.appendChild( contentEl );
                    }
                }
                mpVariationModalInstance = null;
            }
        } );

        mpVariationModalInstance.show();
    }

    function openAjaxModal( url, title ) {
        if ( typeof basicLightbox === 'undefined' ) {
            return;
        }

        closeMpVariationModal();

        $.get( url ).done( function( responseHtml ) {
            var $response = $( '<div />' ).html( responseHtml );
            $response.find( 'script' ).remove();

            var shell = createModalShell( title, $response.get( 0 ) );

            mpVariationModalInstance = basicLightbox.create( shell, {
                onClose: function() {
                    mpVariationModalInstance = null;
                }
            } );

            mpVariationModalInstance.show();
            $( 'body' ).trigger( 'mp-variation-popup-loaded' );
        } );
    }

    window.mpCloseVariationModal = closeMpVariationModal;
    window.mpOpenInlineModal = openInlineModal;
    window.mpOpenAjaxModal = openAjaxModal;

    function uniqTags( tags ) {
        var out = [];

        $.each( tags || [], function( _, tag ) {
            tag = $.trim( tag || '' );
            if ( tag.length && $.inArray( tag, out ) === -1 ) {
                out.push( tag );
            }
        } );

        return out;
    }

    function getRowTextarea( $row ) {
        var $textareas = $row.find( 'textarea.variation_values' );

        if ( $textareas.length > 1 ) {
            $textareas.not( ':first' ).remove();
        }

        return $textareas.first();
    }

    function parseVariationValueString( raw ) {
        var decoded = null,
            tags = [];

        raw = raw || '';

        if ( $.isArray( raw ) ) {
            return uniqTags( raw );
        }

        raw = $.trim( String( raw ) );
        if ( !raw.length ) {
            return [];
        }

        if ( raw.charAt( 0 ) === '[' ) {
            try {
                decoded = JSON.parse( raw );
            } catch ( err ) {
                decoded = null;
            }
        }

        if ( $.isArray( decoded ) ) {
            tags = decoded;
        } else {
            tags = raw.split( /\r\n|\r|\n|,/ );
        }

        return uniqTags( tags );
    }

    function getVariationTagsFromSelect( $select ) {
        var selectedOption = $select.find( ':selected' ),
            variationTags = selectedOption.attr( 'data-tags' ) || '',
            variationTagsJson = selectedOption.attr( 'data-tags-json' ) || '[]',
            tagsFromJson = [],
            uniqueTags = [];

        try {
            tagsFromJson = JSON.parse( variationTagsJson );
            if ( !$.isArray( tagsFromJson ) ) {
                tagsFromJson = [];
            }
        } catch ( err ) {
            tagsFromJson = [];
        }

        $.each( tagsFromJson.concat( $.map( variationTags.split( ',' ), function( tag ) {
            tag = $.trim( tag );
            return tag.length ? tag : null;
        } ) ), function( _, tag ) {
            if ( $.inArray( tag, uniqueTags ) === -1 ) {
                uniqueTags.push( tag );
            }
        } );

        return uniqueTags;
    }

    function getExcludedVariationTags( $row ) {
        var raw = $row.attr( 'data-mp-excluded-tags' ) || '[]';

        try {
            raw = JSON.parse( raw );
            if ( !$.isArray( raw ) ) {
                raw = [];
            }
        } catch ( err ) {
            raw = [];
        }

        return $.map( raw, function( tag ) {
            tag = $.trim( tag );
            return tag.length ? tag : null;
        } );
    }

    function setExcludedVariationTags( $row, tags ) {
        $row.attr( 'data-mp-excluded-tags', JSON.stringify( tags ) );
    }

    function excludeVariationTag( $row, tag ) {
        var excluded = getExcludedVariationTags( $row );

        tag = $.trim( tag || '' );
        if ( !tag.length ) {
            return;
        }

        if ( $.inArray( tag, excluded ) === -1 ) {
            excluded.push( tag );
            setExcludedVariationTags( $row, excluded );
        }
    }

    function getEffectiveVariationTags( $row ) {
        var allTags = getVariationTagsFromSelect( $row.find( '.mp_product_attributes_select' ) ),
            excluded = getExcludedVariationTags( $row );

        return $.grep( allTags, function( tag ) {
            return $.inArray( tag, excluded ) === -1;
        } );
    }

    function getRowTags( $row ) {
        return uniqTags( $row.data( 'mpRowTags' ) || [] );
    }

    function setRowTags( $row, tags ) {
        var $textarea = getRowTextarea( $row );

        tags = uniqTags( tags );
        $row.data( 'mpRowTags', tags );
        $textarea.val( tags.join( ', ' ) ).trigger( 'change' );
    }

    function ensureVariationRowUi( $row ) {
        if ( $row.data( 'mpVariationUiReady' ) ) {
            return;
        }

        var $textarea = getRowTextarea( $row ),
            $secondCol = $row.find( '.variation-second-col' ),
            $ui = $( '<div class="mp-variation-input-ui"><div class="mp-variation-chip-list"></div><input type="text" class="mp-variation-chip-input" autocomplete="off" placeholder="Wert eingeben und ENTER druecken" /><div class="mp-variation-suggestions"></div></div>' );

        // Remove legacy Textext markup if present to prevent duplicated UI layers.
        $secondCol.find( '.mp-variation-input-ui' ).remove();
        $secondCol.find( '.text-core, .text-dropdown, .text-tags, .text-autocomplete' ).remove();
        $secondCol.find( 'input[type="hidden"][name="variation_values[]"]' ).remove();

        $textarea.addClass( 'mp-variation-values-native' ).hide().after( $ui );
        setRowTags( $row, parseVariationValueString( $textarea.val() ) );

        $ui.on( 'keydown', '.mp-variation-chip-input', function( e ) {
            if ( e.key === 'Enter' || e.key === ',' || e.keyCode === 13 || e.keyCode === 188 ) {
                e.preventDefault();
                addRowTag( $row, $( this ).val() );
                $( this ).val( '' );
                renderRowUi( $row );
            }
        } );

        $ui.on( 'input', '.mp-variation-chip-input', function() {
            renderRowSuggestions( $row );
        } );

        $ui.on( 'click', '.mp-variation-chip-remove', function( e ) {
            e.preventDefault();
            removeRowTag( $row, $( this ).attr( 'data-value' ) || '' );
        } );

        $ui.on( 'click', '.mp-variation-suggestion', function( e ) {
            e.preventDefault();
            addRowTag( $row, $( this ).attr( 'data-value' ) || '' );
            $ui.find( '.mp-variation-chip-input' ).val( '' ).focus();
            renderRowUi( $row );
        } );

        $row.data( 'mpVariationUiReady', true );
        renderRowUi( $row );
    }

    function addRowTag( $row, tag ) {
        var tags = getRowTags( $row );

        tag = $.trim( tag || '' );
        if ( !tag.length ) {
            return;
        }

        if ( $.inArray( tag, tags ) === -1 ) {
            tags.push( tag );
            setRowTags( $row, tags );
        }
    }

    function removeRowTag( $row, tag ) {
        var tags = getRowTags( $row );

        tag = $.trim( tag || '' );
        if ( !tag.length ) {
            return;
        }

        tags = $.grep( tags, function( current ) {
            return current !== tag;
        } );
        setRowTags( $row, tags );
        excludeVariationTag( $row, tag );
        renderRowUi( $row );
    }

    function renderRowTags( $row ) {
        var $list = $row.find( '.mp-variation-chip-list' ),
            html = '';

        $.each( getRowTags( $row ), function( _, tag ) {
            var safe = $( '<div />' ).text( tag ).html();
            html += '<span class="mp-variation-chip"><span class="mp-variation-chip-text">' + safe + '</span><button type="button" class="mp-variation-chip-remove" data-value="' + safe + '">x</button></span>';
        } );

        $list.html( html );
    }

    function renderRowSuggestions( $row ) {
        var tags = getRowTags( $row ),
            suggestions = getEffectiveVariationTags( $row ),
            $input = $row.find( '.mp-variation-chip-input' ),
            $box = $row.find( '.mp-variation-suggestions' ),
            query = $.trim( $input.val() || '' ).toLowerCase(),
            html = '';

        suggestions = $.grep( suggestions, function( tag ) {
            if ( $.inArray( tag, tags ) !== -1 ) {
                return false;
            }
            if ( !query.length ) {
                return true;
            }
            return tag.toLowerCase().indexOf( query ) !== -1;
        } );

        if ( !suggestions.length ) {
            $box.empty().hide();
            return;
        }

        $.each( suggestions, function( _, tag ) {
            var safe = $( '<div />' ).text( tag ).html();
            html += '<button type="button" class="mp-variation-suggestion" data-value="' + safe + '">' + safe + '</button>';
        } );

        $box.html( html ).show();
    }

    function renderRowUi( $row ) {
        renderRowTags( $row );
        renderRowSuggestions( $row );
    }

    function syncVariationValuesIntoField( $row, variationTags ) {
        var current = getRowTags( $row );

        if ( current.length === 0 && variationTags.length ) {
            setRowTags( $row, variationTags );
        }
    }

    function bindVariationSuggestions( $row ) {
        var $select = $row.find( '.mp_product_attributes_select' ),
            variationTags = getEffectiveVariationTags( $row );

        ensureVariationRowUi( $row );

        $row.find( '.mp-variation-known-values' ).remove();

        $row.find( '.mp-variation-add-all' ).toggle( $select.val() !== '-1' && variationTags.length > 0 );
        $row.find( '.mp-variation-attribute-name' ).toggle( $select.val() === '-1' );

        if ( $select.val() === '-1' || variationTags.length === 0 ) {
            renderRowUi( $row );
            return;
        }

        syncVariationValuesIntoField( $row, variationTags );
        renderRowUi( $row );
    }

    $( document ).on( 'keyup', '.mp-variation-row .mp-variation-attribute-name', function( e ) {
        if ( $( this ).val() == '' ) {
            $( this ).addClass( 'mp_variation_invalid' );
        } else {
            $( this ).removeClass( 'mp_variation_invalid' );
        }
    } );

    $( document ).on( 'click', '.mp-variation-row .mp-variation-input-ui', function() {
        $( this ).parent().find( '.mp-variation-field-required' ).removeClass( 'mp_variation_invalid' );
    } );

    $( '#poststuff' ).append( '<div class="mp-admin-overlay"><div class="mp-variation-loading-spin"></div><div class="mp-variation-loading-message">' + mp_product_admin_i18n.creating_vatiations_message + '</div></div>' );

    $( '#mp-product-type-select' ).on( 'change', function() {
        if ( $( this ).val() == 'external' || $( this ).val() == 'digital' ) {
            $( '[name="charge_shipping"]' ).attr( 'checked', false );
        }
    } );

    /* $( document ).on( 'change', '.mp_variations_select', function() {
     var has_variations = $( this );
     
     
     
     if ( $( '.mp_variations_box' ).length == 0 ) {//it's not auto-draft, hide variation content box
     
     if ( has_variations.val() == 'yes' ) {
     $( '#postdivrich' ).hide();
     exit;
     } else {
     
     $( '#postdivrich' ).css( 'opacity', '0' );
     $( '#postdivrich' ).css( 'visibility', 'hidden' );
     $( '#postdivrich' ).css( 'display', 'block' );
     
     $( 'html, body' ).animate( {
     scrollTop: $( ".meta-box-sortables.ui-sortable" ).offset().top + 50
     }, 100, function() {
     $( '#postdivrich' ).css( 'visibility', 'visible' );
     $( "#postdivrich" ).fadeTo( 400, 1, function() {
     } );
     } );
     
     
     exit;
     }
     } else {
     
     }
     } );*/

    function mp_variation_message( ) {
        $( '.mp-variation-loading-spin' ).css( {
            position: 'fixed',
            left: ( $( '.mp-admin-overlay' ).width( ) - $( '.mp-variation-loading-spin' ).outerWidth( ) ) / 2,
            top: ( $( '.mp-admin-overlay' ).height( ) - $( '.mp-variation-loading-spin' ).outerHeight( ) ) / 2
        } );
        var new_top = parseInt( $( '.mp-variation-loading-spin' ).css( 'top' ) );
        new_top = new_top + 50;
        $( '.mp-variation-loading-message' ).css( {
            position: 'absolute',
            left: ( $( '.mp-admin-overlay' ).width( ) - $( '.mp-variation-loading-message' ).outerWidth( ) ) / 2,
            top: new_top
        } );
    }

    $( window ).on('resize', function( ) {
        mp_variation_message();
    } );

    $( window ).trigger('resize');
    /* Variations product name set */
    $( '.mp_variations_product_name' ).html( $( '#title' ).val( ) );
    $( '#title' ).on('keyup', function( ) {
        $( '.mp_variations_product_name' ).html( $( '#title' ).val( ) );
    } );

    $( '.repeat' ).each( function( ) {
        $( this ).repeatable_fields( );
    } );

    $( document ).on( 'click', '.mp-variation-add-all', function( e ) {
        e.preventDefault();
        var $row = $( this ).closest( '.variation-row' ),
            variationTagsArray = getEffectiveVariationTags( $row ),
            existingTags = getRowTags( $row ),
            allTags = $.grep( variationTagsArray, function( tag ) {
                return $.inArray( tag, existingTags ) === -1;
            } );

        if ( allTags.length ) {
            setRowTags( $row, existingTags.concat( allTags ) );
            renderRowUi( $row );
        }
    } );

    $( document ).on( 'change', '.mp_product_attributes_select', function( ) {
        bindVariationSuggestions( $( this ).closest( '.variation-row' ) );
    } );

    $( document ).on( 'mp:variation-row-added', '.mp-variation-row', function() {
        bindVariationSuggestions( $( this ) );
    } );

    $( document ).on( 'click', '.select_attributes_filter a', function( event ) {
        $( '.select_attributes_filter a' ).removeClass( 'selected' );
        if ( $( this ).hasClass( 'selected' ) ) {
            $( this ).removeClass( 'selected' );
        } else {
            $( this ).addClass( 'selected' );
        }

//Select All link clicked
        if ( $( this ).hasClass( 'select_all_link' ) ) {
            $( '#cb-select-all' ).prop( "checked", true );
            $( '.check-column .check-column-box' ).prop( "checked", true );
        }

//Select None link clicked
        if ( $( this ).hasClass( 'select_none_link' ) ) {
            $( '#cb-select-all' ).prop( "checked", false );
            $( '.check-column .check-column-box' ).prop( "checked", false );
        }

//Variation filter clicked
        if ( !$( this ).hasClass( 'select_none_link' ) && !$( this ).hasClass( 'select_all_link' ) ) {
            var term_id = $( this ).parent( ).data( 'term-id' );
            $( '.check-column .check-column-box' ).prop( "checked", false );
            $( '.variation_term_' + term_id ).each( function( index ) {
                $( this ).closest( 'tr' ).find( '.check-column .check-column-box' ).prop( "checked", true );
            } );
        }

        event.preventDefault( );
    } );

    $( document ).on( 'focus', '.select_attributes_filter a', function( event ) {
        $( this ).blur( );
    } );

    $( document ).on( 'click', '#mp_make_combinations, #publishing-action #publish', function( event ) {//

        var caller_id = $( this ).attr( 'id' );

        if ( caller_id === 'mp_make_combinations' ) {
            var $form = $( '#post' );

            if ( !$form.find( 'input[name="mp_make_combinations"]' ).length ) {
                $form.append( '<input type="hidden" name="mp_make_combinations" value="1" />' );
            } else {
                $form.find( 'input[name="mp_make_combinations"]' ).val( '1' );
            }

            $form.find( 'input[name="has_variation"][value="yes"]' ).prop( 'checked', true ).trigger( 'change' );
        }

        if ( $( '.mp_variations_box' ).is( ":visible" ) ) {

            var variation_errors = 0;

            $( '.mp-variation-row .mp-variation-attribute-name' ).each( function( index ) {

                if ( $( this ).is( ":visible" ) ) {
                    if ( $( this ).val() == '' ) {
                        $( this ).addClass( 'mp_variation_invalid' );
                        variation_errors++;
                    } else {
                        $( this ).removeClass( 'mp_variation_invalid' );
                    }
                }

            } );

            $( '.mp-variation-row textarea.variation_values' ).each( function() {
                var rowTags = parseVariationValueString( $( this ).val() );
                if ( rowTags.length === 0 ) {
                    $( this ).closest( '.variation-second-col' ).find( '.mp-variation-field-required' ).addClass( 'mp_variation_invalid' );
                    variation_errors++;
                } else {
                    $( this ).closest( '.variation-second-col' ).find( '.mp-variation-field-required' ).removeClass( 'mp_variation_invalid' );
                }
            } );


            if ( variation_errors == 0 ) {
                if ( caller_id == 'mp_make_combinations' ) {
                    var $publishButton = $( '#publish' ),
                        $saveButton = $( '#save-post' );

                    $publishButton.removeAttr( 'disabled' );
                    $saveButton.removeAttr( 'disabled' );

                    if ( $publishButton.length ) {
                        $publishButton.trigger( 'click' );
                    } else if ( $saveButton.length ) {
                        $saveButton.trigger( 'click' );
                    } else {
                        $( '#post' ).trigger( 'submit' );
                    }

                    event.preventDefault();
                    return;
                }
            } else {
                event.preventDefault();
                $( 'html, body' ).animate( {
                    scrollTop: $( ".mp_variations_title" ).offset().top + 50
                }, 100 );
            }

        }

    } );

    $( '.mp-add-new-variation' ).trigger('click');
    $( '.mp-variation-row' ).each( function() {
        bindVariationSuggestions( $( this ) );
    } );

} );
/* INLINE EDIT */

jQuery( document ).ready( function( $ ) {

    var mp_product_admin_i18n = $.extend( {
        ajaxurl: '',
        ajax_nonce: '',
        creating_vatiations_message: 'Creating variations, please wait...',
        message_valid_number_required: 'Valid number is required',
        message_input_required: 'Input is required',
        saving_message: 'Please wait...saving in progress...',
        placeholder_image: ''
    }, window.mp_product_admin_i18n || {} );

    var closeMpVariationModal = window.mpCloseVariationModal || function() {};
    var openInlineModal = window.mpOpenInlineModal || function() {};
    var openAjaxModal = window.mpOpenAjaxModal || function() {};

    $.fn.selectRange = function( start, end ) {
        return this.each( function( ) {
            if ( this.setSelectionRange ) {
                this.focus( );
                this.setSelectionRange( start, end );
            } else if ( this.createTextRange ) {
                var range = this.createTextRange( );
                range.collapse( true );
                range.moveEnd( 'character', end );
                range.moveStart( 'character', start );
                range.select( );
            }
        } );
    };
    $.fn.inlineEdit = function( replaceWith, connectWith ) {
        var inline_icon_edit = '<span class="inline-edit-icon"><i class="fa fa-pencil fa-lg"></i></span>';
        $( this ).on('mouseenter', function( ) {
            $( this ).append( inline_icon_edit );
            $( this ).parent( ).find( '.currency' ).hide( );
        });

        $( this ).on('mouseleave', function( ) {
            $( this ).find( '.inline-edit-icon' ).remove( );
            if ( $( this ).parent( ).find( '.currency' ).hasClass( '.no_currency' ) ) {
                //Currency shouldn't be shown
            } else {
                $( this ).parent( ).find( '.currency' ).show( );
            }
        });

        $( this ).on( 'click', function( ) {

            var orig_val = $( this ).html( );
            orig_val = orig_val.replace( inline_icon_edit, "" );
            $( replaceWith ).val( $.trim( orig_val ) );
            var post_id = $( this ).closest( 'tr' ).find( '.check-column .check-column-box' ).val( );
            var data_meta = $( this ).attr( 'data-meta' );
            var data_sub_meta = $( this ).attr( 'data-sub-meta' );
            var data_type = $( this ).closest( 'td' ).attr( 'data-field-type' );
            var data_default = $( this ).attr( 'data-default' );
            var elem = $( this );
            elem.hide( );
            elem.after( replaceWith );
            replaceWith.focus( );
            var len = $( replaceWith ).val( ).length * 2; //has to be * 2 because how Opera counts carriage returns

            $( replaceWith ).selectRange( len, len );
            replaceWith.blur( function( ) {

                if ( $( this ).val( ) != "" ) {

                    $( this ).parent( ).find( '.currency' ).removeClass( '.no_currency' );
                    $( this ).parent( ).find( '.currency' ).show();
                    connectWith.val( $( this ).val( ) ).change( );
                    if ( data_type == 'number' ) {
                        var numeric_value = $( this ).val( ).trim( );
                        numeric_value = numeric_value.replace( ",", "." ); //convert comma to dot

                        // If the user enters a percentage, calculate the sale price
                        if( data_meta == 'sale_price_amount' && numeric_value.indexOf('%') == numeric_value.length -1 && parseFloat( numeric_value ) <= 100 ) {
                            numeric_value = parseFloat( numeric_value );
                            var original_value = parseFloat( elem.closest( 'tr' ).find( 'span.original_value[data-meta="regular_price"]' ).html( ) );
                            numeric_value = original_value - ( ( numeric_value / 100 ) * original_value );
                            numeric_value = '' + numeric_value;
                        }

                        numeric_value = numeric_value.replace( /[^\d.-]/g, '' ); //remove any non numeric value

                        if ( !isNaN( numeric_value ) ) {
                            elem.text( numeric_value );
                        } else {
                            if(numeric_value === '-' || numeric_value === '∞') {
                                if( numeric_value === '∞' ) { 
                                    elem.text( '∞' )
                                } else {
                                    elem.text( '-' )
                                }
                                numeric_value = '';
                            } else {
                                elem.text( 0 );
                            }
                        }
                        
                        if ( $( this ).parent().hasClass( 'field_editable' ) && $( this ).parent().hasClass( 'field_editable_sale_price_amount' ) ) {
                            if( numeric_value !== '' ) {
                                var reg_price = $( this ).parent().parent();
                                reg_price.find( '.field_editable.field_editable_price' ).addClass( 'mp_strikethrough' );
                            }
                        }

                        save_inline_post_data( post_id, data_meta, numeric_value, data_sub_meta );
                    } else {
                        elem.text( $( this ).val( ) );
                        save_inline_post_data( post_id, data_meta, $( this ).val( ), data_sub_meta );
                    }
                } else {

                    if ( $( this ).parent().hasClass( 'field_editable' ) && $( this ).parent().hasClass( 'field_editable_sale_price_amount' ) ) {
                        var reg_price = $( this ).parent().parent();
                        reg_price.find( '.field_editable.field_editable_price' ).removeClass( 'mp_strikethrough' );
                    }

                    $( this ).parent( ).find( '.currency' ).addClass( '.no_currency' );
                    $( this ).parent( ).find( '.currency' ).hide();
                    elem.text( data_default );
                    save_inline_post_data( post_id, data_meta, '', data_sub_meta );
                }

                $( this ).remove( );
                elem.show( );
            } );
        } );
    };
    $( ".original_value" ).each( function( index ) {
        $( this ).inlineEdit( $( '<input name="temp" class="mp_inline_temp_value" type="text" value="" />' ), $( 'input.editable_value' ) ); //' + $.trim( $( this ).html( ) ) + '
    } );
    $( document ).on( 'keyup', '.mp_inline_temp_value', function( e ) {
        if ( e.keyCode == 13 ) {
            $( this ).blur( );
        }
        e.preventDefault( );
    } );
    $( document ).on('keydown', '.mp_variations_table_box [name="selected_variation[]"]', function( e ) {
        if ( e.keyCode == 9 ) {
            e.preventDefault( );
            var parentContainer = $( this ).parent( 'th' );
            var nextContainer = $( this ).parent( 'th' ).next().next( 'td.field_editable' );
            nextContainer.find( '.original_value' ).trigger( 'click' );
            
           $( this ).blur( );
        }
    });
    $( document ).on( 'keydown', ".mp_inline_temp_value", function( e ) {
        if ( e.keyCode == 9 ) {
            e.preventDefault( );
            
            var parentContainer = $( this ).parent( );
            var nextContainer = $( this ).parent( ).next( 'td' );
            nextContainer.find( '.original_value' ).trigger( 'click' );
            
            $( this ).blur( );
        }
    });
    $( '#mp-product-price-inventory-variants-metabox' ).on( 'keydown', 'input, textarea, select', function( event ) {
        var $target = $( event.target );

        if ( event.key !== 'Enter' ) {
            return;
        }

        if ( $target.is( 'textarea' ) || $target.closest( '.variation-row .text-wrap, .variation-row .text-core' ).length ) {
            return true;
        }

        event.preventDefault();
        return false;
    } );
    $( '#variant_bulk_doaction' ).on('click', function( ) {
        var selected_variant_bulk_action = $( '.variant_bulk_selected' ).val( );
        var checked_variants = $( ".check-column-box:checked" ).length;
        if ( selected_variant_bulk_action == 'variant_update_prices' ) {

            if ( checked_variants > 0 ) {
                var mp_bulk_price_start_val = 0;
                $( '.check-column-box:checked' ).each( function( ) {
                    mp_bulk_price_start_val = $.trim( $( this ).closest( 'tr' ).find( '.original_value.field_subtype_price' ).html( ) );
                    mp_bulk_price_start_val = mp_bulk_price_start_val.replace( ",", "" )
                } );
                if ( checked_variants > 1 ) {
                    $( '#mp_bulk_price_title' ).html( mp_product_admin_i18n.bulk_update_prices_multiple_title );
                } else {
                    $( '#mp_bulk_price_title' ).html( mp_product_admin_i18n.bulk_update_prices_single_title );
                }

                $( '.mp_variants_selected' ).html( checked_variants );
                if ( $( '.mp_bulk_price' ).val( ) == '' ) {
                    $( '.mp_bulk_price' ).val( mp_bulk_price_start_val );
                }

                openInlineModal( $( '#mp_bulk_price' ), $( '#mp_bulk_price_title' ).html( ) );
            }
        }

        if ( selected_variant_bulk_action == 'variant_update_inventory' ) {

            if ( checked_variants > 0 ) {
                var mp_bulk_inventory_start_val = 0;
                $( '.check-column-box:checked' ).each( function( ) {
                    mp_bulk_inventory_start_val = $.trim( $( this ).closest( 'tr' ).find( '.original_value.field_subtype_inventory' ).html( ) );
                    mp_bulk_inventory_start_val = mp_bulk_inventory_start_val.replace( ",", "" );
                } );
                if ( isNaN( mp_bulk_inventory_start_val ) ) {
                    mp_bulk_inventory_start_val = 10; //example value
                }

                if ( checked_variants > 1 ) {
                    $( '#mp_bulk_inventory_title' ).html( mp_product_admin_i18n.bulk_update_inventory_multiple_title );
                } else {
                    $( '#mp_bulk_inventory_title' ).html( mp_product_admin_i18n.bulk_update_inventory_single_title );
                }

                $( '.mp_variants_selected' ).html( checked_variants );
                if ( $( '.mp_bulk_inventory' ).val( ) == '' ) {
                    $( '.mp_bulk_inventory' ).val( mp_bulk_inventory_start_val );
                }

                openInlineModal( $( '#mp_bulk_inventory' ), $( '#mp_bulk_inventory_title' ).html( ) );
            }
        }

        if ( selected_variant_bulk_action == 'variant_update_images' ) {

            if ( checked_variants > 0 ) {

                wp.media.string.props = function( props, attachment )
                {

                    $( '.check-column-box:checked' ).each( function( ) {

                        var placeholder_image = $( this ).closest( 'tr' ).find( '.mp-variation-image img' );
                        var post_id = $( this ).closest( 'tr' ).find( '.mp-variation-image' ).attr( 'data-post-image-id' );
                        placeholder_image.attr( 'src', attachment.url );
                        placeholder_image.attr( 'width', 30 );
                        placeholder_image.attr( 'height', 30 );
                        save_inline_post_data( post_id, '_thumbnail_id', attachment.id, '' );
                    } );
                    //save_inline_post_data( post_id, '_thumbnail_id', attachment.id, '' );
                }

                wp.media.editor.send.attachment = function( props, attachment )
                {
                    $( '.check-column-box:checked' ).each( function( ) {

                        var placeholder_image = $( this ).closest( 'tr' ).find( '.mp-variation-image img' );
                        var post_id = $( this ).closest( 'tr' ).find( '.mp-variation-image' ).attr( 'data-post-image-id' );
                        placeholder_image.attr( 'src', attachment.url );
                        placeholder_image.attr( 'width', 30 );
                        placeholder_image.attr( 'height', 30 );
                        save_inline_post_data( post_id, '_thumbnail_id', attachment.id, '' );
                    } );
                };
                wp.media.editor.open( this );
                return false;
            }
        }

        if ( selected_variant_bulk_action == 'variant_delete' ) {

            if ( checked_variants > 0 ) {

                if ( checked_variants > 1 ) {
                    $( '#mp_bulk_delete_title' ).html( mp_product_admin_i18n.bulk_delete_multiple_title );
                } else {
                    $( '#mp_bulk_delete_title' ).html( mp_product_admin_i18n.bulk_delete_single_title );
                }

                $( '.mp_variants_selected' ).html( checked_variants );
                openInlineModal( $( '#mp_bulk_delete' ), $( '#mp_bulk_delete_title' ).html( ) );
            }
        }

    } );
    $( '.mp-variation-image img' ).on( 'click', function( ) {

        var placeholder_image = $( this );
        var post_id = $( this ).closest( 'td' ).attr( 'data-post-image-id' );
        wp.media.string.props = function( props, attachment )
        {
            placeholder_image.attr( 'src', attachment.url );
            placeholder_image.attr( 'width', 30 );
            placeholder_image.attr( 'height', 30 );
            save_inline_post_data( post_id, '_thumbnail_id', attachment.id, '' );
        }

        wp.media.editor.send.attachment = function( props, attachment )
        {
            placeholder_image.attr( 'src', attachment.url );
            placeholder_image.attr( 'width', 30 );
            placeholder_image.attr( 'height', 30 );
            save_inline_post_data( post_id, '_thumbnail_id', attachment.id, '' );
        };
        wp.media.editor.open( this );
        return false;
    } );
    function save_inline_post_data( post_id, meta_name, meta_value, sub_meta ) {
        var data = {
            action: 'save_inline_post_data',
            post_id: post_id,
            meta_name: meta_name,
            meta_sub_name: sub_meta,
            meta_value: meta_value,
            ajax_nonce: mp_product_admin_i18n.ajax_nonce
        }

        $.post(
            mp_product_admin_i18n.ajaxurl, data
            ).done( function( data, status ) {
            if ( status == 'success' ) {
                //alert( 'success!' );
            } else {
                //alert( 'fail!' );
                //an error occured
            }
        } );
    }

    $( document ).on( 'keyup', '.mp_bulk_price', function( ) {
        if ( jQuery( '.mp_bulk_price' ).val( ) == '' || isNaN( jQuery( '.mp_bulk_price' ).val( ) ) ) {
            jQuery( '.mp_price_controls .save-bulk-form' ).attr( 'disabled', true );
        } else {
            jQuery( '.mp_price_controls .save-bulk-form' ).attr( 'disabled', false );
        }
    } );
    $( document ).on( 'keyup', '.mp_bulk_inventory', function( ) {
        if ( jQuery( '.mp_bulk_inventory' ).val( ) !== '' && isNaN( jQuery( '.mp_bulk_inventory' ).val( ) ) ) {
            jQuery( '.mp_inventory_controls .save-bulk-form' ).attr( 'disabled', true );
        } else {
            jQuery( '.mp_inventory_controls .save-bulk-form' ).attr( 'disabled', false );
        }
    } );
    //Price controls
    $( document ).on( 'click', '.mp_popup_controls.mp_price_controls a.save-bulk-form', function( e ) {
        //LINK can't disabled, so we have to check
        if ( $( this ).attr( 'disabled' ) == 'disabled' ) {
            e.preventDefault();
            return false;
        }

        var global_price_set = jQuery( '.mp_bulk_price' ).val( );
        closeMpVariationModal();
        $( '.check-column-box:checked' ).each( function( ) {
            $( this ).closest( 'tr' ).find( '.field_subtype_price' ).html( global_price_set );
            $( this ).closest( 'tr' ).find( '.editable_value_price' ).val( global_price_set );
            save_inline_post_data( $( this ).val( ), 'regular_price', global_price_set, '' );
        } );
        return false;
        e.preventDefault( );
    } );
    //Inventory controls
    $( document ).on( 'click', '.mp_popup_controls.mp_inventory_controls a.save-bulk-form', function( e ) {
        //LINK can't disabled, so we have to check
        if ( $( this ).attr( 'disabled' ) == 'disabled' ) {
            e.preventDefault();
            return false;
        }

        var global_inventory_set = jQuery( '.mp_bulk_inventory' ).val( );
        if ( global_inventory_set == '' || isNaN( global_inventory_set ) ) {
            global_inventory_set = '&infin;';
        }

        closeMpVariationModal();
        $( '.check-column-box:checked' ).each( function( ) {
            $( this ).closest( 'tr' ).find( '.field_subtype_inventory' ).html( global_inventory_set );
            $( this ).closest( 'tr' ).find( '.editable_value_price' ).val( global_inventory_set );
            save_inline_post_data( $( this ).val( ), 'inventory', global_inventory_set, '' );
        } );
        return false;
        e.preventDefault( );
    } );
    //Delete controls
    $( document ).on( 'click', '.mp_popup_controls.mp_delete_controls a.delete-bulk-form', function( e ) {
        e.preventDefault( );
        
        closeMpVariationModal();
        $( '.check-column-box:checked' ).each( function( ) {
            $( this ).closest( 'tr' ).remove( );
            save_inline_post_data( $( this ).val( ), 'delete', '', '' );
        } );
        if ( $( '.check-column-box' ).length == 0 ) {
            save_inline_post_data( $( '[name="post_ID"]' ).val( ), 'delete_variations', '', '' );
            setInterval(function(){ 
                $( '#publish' ).removeAttr( 'disabled' );
                $( '#publish' ).trigger('click');
            }, 500);
        }
        return false;
       
    } )

    /* Close thickbox window on link / cancel click */
    $( document ).on( 'click', '.mp_popup_controls a.cancel', function( e ) {
        closeMpVariationModal();
        return false;
        e.preventDefault( );
    } );
    $( document ).on( 'click', 'a.open_ajax', function( e ) {
        e.preventDefault();
        openAjaxModal(
            mp_product_admin_i18n.ajaxurl + '?action=mp_variation_popup&variation_id=' + ( $( this ).attr( 'data-popup-id' ) ) + '&ajax_nonce=' + encodeURIComponent( mp_product_admin_i18n.ajax_nonce ),
            $( this ).closest( 'tr' ).find( '.field_more .variation_name' ).html( )
        );
        return false;
    } );
    $( document ).on( 'click', '#variant_add', function( e ) {
        var url = mp_product_admin_i18n.ajaxurl + '?action=ajax_add_new_variant';
        $.post( url, {
            action: 'ajax_add_new_variant',
            parent_post_id: $( '#post_ID' ).val( ),
            ajax_nonce: mp_product_admin_i18n.ajax_nonce,
        } ).done( function( data, status ) {
            var response = jQuery.parseJSON( data );
            if ( response ) {
                if ( response.type == true ) {
                    openAjaxModal(
                        mp_product_admin_i18n.ajaxurl + '?action=mp_variation_popup&variation_id=' + response.post_id + '&new_variation&ajax_nonce=' + encodeURIComponent( mp_product_admin_i18n.ajax_nonce ),
                        ''
                    );
                } else {
                    alert( 'An error occured while trying to create a new variation post' );
                }
            }

        } );
        e.preventDefault( );
    } );
    $( 'body' ).on( 'mp-variation-popup-loaded', function( ) {

        $( '#variation_popup a.remove_popup_image' ).on( 'click', function( e ) {

            var placeholder_image = $( '#variation_popup .mp-variation-image img' );
            var post_id = $( '#variation_id' ).val( );
            var table_placeholder_image = $( '#post-' + post_id ).find( '.mp-variation-image img' );
            table_placeholder_image.attr( 'src', mp_product_admin_i18n.placeholder_image );
            table_placeholder_image.attr( 'width', 30 );
            table_placeholder_image.attr( 'height', 30 );
            placeholder_image.attr( 'src', mp_product_admin_i18n.placeholder_image );
            placeholder_image.attr( 'width', 75 );
            placeholder_image.attr( 'height', 75 );
            save_inline_post_data( post_id, '_thumbnail_id', '', '' );
            e.preventDefault( );
        } );
        $( '#variation_popup .mp-variation-image img' ).on( 'click', function( ) {
            var placeholder_image = $( this );
            var post_id = $( '#variation_id' ).val( );
            var table_placeholder_image = $( '#post-' + post_id ).find( '.mp-variation-image img' );
            wp.media.string.props = function( props, attachment )
            {
                table_placeholder_image.attr( 'src', attachment.url );
                table_placeholder_image.attr( 'width', 30 );
                table_placeholder_image.attr( 'height', 30 );
                placeholder_image.attr( 'src', attachment.url );
                placeholder_image.attr( 'width', 75 );
                placeholder_image.attr( 'height', 75 );
                save_inline_post_data( post_id, '_thumbnail_id', attachment.id, '' );
            }

            wp.media.editor.send.attachment = function( props, attachment )
            {
                table_placeholder_image.attr( 'src', attachment.url );
                table_placeholder_image.attr( 'width', 30 );
                table_placeholder_image.attr( 'height', 30 );
                placeholder_image.attr( 'src', attachment.url );
                placeholder_image.attr( 'width', 75 );
                placeholder_image.attr( 'height', 75 );
                save_inline_post_data( post_id, '_thumbnail_id', attachment.id, '' );
            };
            wp.media.editor.open( this );
            return false;
        } );
        $( '#file_url_button' ).on( 'click', function( ) {

            var field = $( this ).closest( '#file_url' );
            wp.media.string.props = function( props, attachment )
            {
                $( '#file_url' ).val( attachment.url );
            }

            wp.media.editor.send.attachment = function( props, attachment )
            {
                $( '#file_url' ).val( attachment.url );
            };
            wp.media.editor.open( this );
            return false;
        } );
        $( '.fieldset_check' ).each( function( ) {
            var controller = $( this ).find( '.has_controller' );
            if ( controller.is( ':checked' ) ) {
                $( this ).find( '.has_area' ).show( );
            } else {
                $( this ).find( '.has_area' ).hide( );
            }
        } );
        $( '.mp-date' ).each( function( ) {
            var $this = $( this );
            // Convert to HTML5 date input
            $this.attr( 'type', 'date' ).css( { 'cursor': 'pointer' } );
        } );
        var variation_content_type = $( "input[name='variation_content_type']:checked" ).val( );
        if ( variation_content_type == 'html' ) {
            $( '.variation_description_button' ).show( );
            $( '.variation_content_type_plain' ).hide( );
        } else {//plain text
            $( '.variation_description_button' ).hide( );
            $( '.variation_content_type_plain' ).show( );
        }

        $( document ).on( 'change', "input[name='variation_content_type']", function( ) {
            var variation_content_type = $( "input[name='variation_content_type']:checked" ).val( );
            if ( variation_content_type == 'html' ) {
                $( '.variation_description_button' ).show( );
                $( '.variation_content_type_plain' ).hide( );
            } else {//plain text
                $( '.variation_description_button' ).hide( );
                $( '.variation_content_type_plain' ).show( );
            }
        } );

        $target = $('#variation_popup');

        // Set a 10% discount automatically and avoid validation messages
        //$target.on( 'change', 'input[name="has_sale"]', function() {
        //    if( $( this ).is( ":checked" ) && !isFinite( percentage_discount ) ) {
        //        var percentage_discount = parseFloat( $target.find("input[name='sale_price\\[percentage\\]']").val() );
        //        $target.find("input[name='sale_price\\[percentage\\]']").val( '10' ).trigger("input");
        //    }
        //});

        $target.on('input', 'input', function() {
            var price = parseFloat( $target.find("input[name='regular_price']").val() );
            var sale_price = parseFloat( $target.find("input[name='sale_price\\[amount\\]']").val() );
            var percentage_discount = parseFloat( $target.find("input[name='sale_price\\[percentage\\]']").val() );

            switch($(this).attr('name')) {
                case 'regular_price':
                    var new_percentage = ( 100 - ( ( 100 / price ) * sale_price ) );
                    if(isFinite(new_percentage) && new_percentage >= 0.0) {
                        $target.find("input[name='sale_price\\[percentage\\]']").val( new_percentage.toFixed(2) );
                    }else{
                        $target.find("input[name='sale_price\\[percentage\\]']").val( '' );
                    }
                    break;
                case 'sale_price[amount]':
                    var new_percentage = ( 100 - ( ( 100 / price ) * sale_price ) );
                    if(isFinite(new_percentage) && new_percentage >= 0.0) {
                        $target.find("input[name='sale_price\\[percentage\\]']").val( new_percentage.toFixed(2) );
                    }else{
                        $target.find("input[name='sale_price\\[percentage\\]']").val( '' );
                    }
                    break;
                case 'sale_price[percentage]':
                    var new_sale_price = price - ( ( price / 100 ) * percentage_discount );
                    if(isFinite(new_sale_price) && new_sale_price <= price && new_sale_price > 0) {
                        $target.find("input[name='sale_price\\[amount\\]']").val( new_sale_price.toFixed(2) );
                    }else{
                        $target.find("input[name='sale_price\\[amount\\]']").val( '' );
                    }
                    break;
            }
        });
        $target.find("input[name='regular_price']").trigger('input');

        $( "#variation_popup" ).validate( {
            messages: {
                required: mp_product_admin_i18n.message_input_required
            }
        } );
        $( '.mp-numeric' ).each( function( ) {
            $( this ).rules( 'add', {
                number: true,
                messages: {
                    number: mp_product_admin_i18n.message_valid_number_required
                }
            } );
        } );
        $( '.mp-required' ).each( function( ) {
            $( this ).rules( 'add', {
                required: true,
                messages: {
                    required: mp_product_admin_i18n.message_input_required
                }
            } );
        } );

        $( document ).on( 'keypress', '#variation_popup input, #variation_popup textarea, #variation_popup select', function( e ) {
        
            $( '#save-variation-popup-data' ).toggleClass( "disabled", !$( 'form#variation_popup' ).valid() );
        
        } );

    } );
    
    $( document ).on( 'change', '.has_controller', function( ) {
        var parent_holder = $( this ).closest( '.fieldset_check' );
        var controller = $( this );
        if ( controller.is( ':checked' ) ) {
            parent_holder.find( '.has_area' ).show( );
        } else {
            parent_holder.find( '.has_area' ).hide( );
            if( controller.attr( 'name' ) == 'has_per_order_limit' ) $( "#per_order_limit" ).val( '' );
        }

    } );
    
    $( document ).on( 'click', '#save-variation-popup-data, .variation_description_button', function( e ) {
        var form = $( 'form#variation_popup' );
        if( !form.valid() ) {
            e.preventDefault( );
            return;
        }

        $( '.mp_ajax_response' ).attr( 'class', 'mp_ajax_response' );
        $( '.mp_ajax_response' ).html( mp_product_admin_i18n.saving_message );
        $.post(
            //ajax_nonce: mp_product_admin_i18n.ajax_nonce
            //action: 'save_inline_post_data',
            mp_product_admin_i18n.ajaxurl, form.serialize( )
            ).done( function( data, status ) {
            var response = $.parseJSON( data );
            if ( response.status_message !== '' ) {
                $( '.mp_ajax_response' ).html( response.status_message );
                $( '.mp_ajax_response' ).attr( 'class', 'mp_ajax_response' );
                $( '.mp_ajax_response' ).addClass( 'mp_ajax_response_' + response.status );
                if ( response.status == 'success' ) {
                    closeMpVariationModal();
                }
                if ( $( '#new_variation' ).val( ) == 'yes' ) {
                    //window.opener.location.reload( false );
                }
                // reload page on both new variation and update variation, as there's no way to dinamically update the variations table
                parent.location.reload( );
            }

            if ( status == 'success' ) {
                //console.log( response );
            } else {
                //alert( 'fail!' );
                //an error occured
            }
        } );
        if ( $( this ).attr( 'id' ) == 'variation_description_button' ) {

        } else {
            e.preventDefault( );
        }
    } );
    
    $target = $('#mp-product-price-inventory-variants-metabox');
    $target.on('input', 'input', function() {
        var price = parseFloat( $target.find("input[name='regular_price']").val() );
        var sale_price = parseFloat( $target.find("input[name='sale_price\\[amount\\]']").val() );
        var percentage_discount = parseFloat( $target.find("input[name='sale_price\\[percentage\\]']").val() );

        switch($(this).attr('name')) {
            case 'regular_price':
                var new_percentage = ( 100 - ( ( 100 / price ) * sale_price ) );
                if(isFinite(new_percentage) && new_percentage >= 0.0) {
                    $target.find("input[name='sale_price\\[percentage\\]']").val( new_percentage.toFixed(2) );
                }else{
                    $target.find("input[name='sale_price\\[percentage\\]']").val( '' );
                }
                break;
            case 'sale_price[amount]':
                var new_percentage = ( 100 - ( ( 100 / price ) * sale_price ) );
                if(isFinite(new_percentage) && new_percentage >= 0.0) {
                    $target.find("input[name='sale_price\\[percentage\\]']").val( new_percentage.toFixed(2) );
                }else{
                    $target.find("input[name='sale_price\\[percentage\\]']").val( '' );
                }
                break;
            case 'sale_price[percentage]':
                var new_sale_price = price - ( ( price / 100 ) * percentage_discount );
                if(isFinite(new_sale_price) && new_sale_price <= price && new_sale_price > 0) {
                    $target.find("input[name='sale_price\\[amount\\]']").val( new_sale_price.toFixed(2) );
                }else{
                    $target.find("input[name='sale_price\\[amount\\]']").val( '' );
                }
                break;
        }
    });
    $target.find("input[name='regular_price']").trigger('input');

    // Set default variant action
    $('#mp-product-price-inventory-variants-metabox').on('click', 'tr:not(".default") a.set-default', function(event) {
        event.preventDefault();
        $this = $( this );
        post_id = $this.attr('data-post-id');
        meta_name = 'default_variation';
        meta_value = $this.attr('data-child-id');
        var data = {
            action: 'save_inline_post_data',
            post_id: post_id,
            meta_name: meta_name,
            meta_value: meta_value,
            ajax_nonce: mp_product_admin_i18n.ajax_nonce
        }

        $this.children('.fa').addClass('fa-pulse');
        $.post(
            mp_product_admin_i18n.ajaxurl, 
            data
        ).done( function( data, status ) {
            $this.children('.fa').removeClass('fa-pulse');
            if ( status == 'success' ) {
                $this.parents('tr').addClass('default').siblings('tr').removeClass('default');
            }
        });
    });

} );
