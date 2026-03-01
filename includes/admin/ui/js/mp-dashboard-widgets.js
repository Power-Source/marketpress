/* INLINE EDIT */


// Vanilla JS Inline Edit
document.addEventListener('DOMContentLoaded', function() {
    var $ = window.jQuery;

    if (typeof $ !== 'function') {
        return;
    }

    function selectRange(input, start, end) {
        if (input.setSelectionRange) {
            input.focus();
            input.setSelectionRange(start, end);
        }
    }

    function inlineEdit(elem, connectWith) {
        var inline_icon_edit = document.createElement('span');
        inline_icon_edit.className = 'inline-edit-icon';
        inline_icon_edit.innerHTML = '<i class="fa fa-pencil fa-lg"></i>';

        elem.addEventListener('mouseenter', function() {
            if (!elem.querySelector('.inline-edit-icon')) {
                elem.appendChild(inline_icon_edit.cloneNode(true));
            }
        });
        elem.addEventListener('mouseleave', function() {
            var icon = elem.querySelector('.inline-edit-icon');
            if (icon) icon.remove();
        });

        elem.addEventListener('click', function() {
            var orig_val = elem.innerHTML.replace(inline_icon_edit.outerHTML, '');
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'mp_inline_temp_value';
            input.value = orig_val.trim();

            var tr = elem.closest('tr');
            var post_id = tr ? tr.querySelector('.check-column .check-column-box')?.value : '';
            var data_meta = elem.getAttribute('data-meta');
            var data_sub_meta = elem.getAttribute('data-sub-meta');
            var td = elem.closest('td');
            var data_type = td ? td.getAttribute('data-field-type') : '';
            var data_default = elem.getAttribute('data-default');

            elem.style.display = 'none';
            elem.parentNode.insertBefore(input, elem.nextSibling);
            input.focus();
            selectRange(input, input.value.length * 2, input.value.length * 2);

            input.addEventListener('blur', function() {
                var val = input.value;
                if (val !== '') {
                    if (connectWith) {
                        connectWith.value = val;
                        connectWith.dispatchEvent(new Event('change'));
                    }
                    if (data_type === 'number') {
                        var numeric_value = val.replace(',', '');
                        if (!isNaN(numeric_value)) {
                            elem.textContent = numeric_value;
                        } else {
                            elem.textContent = '0';
                        }
                        save_inline_post_data(post_id, data_meta, numeric_value, data_sub_meta);
                    } else {
                        elem.textContent = val;
                        save_inline_post_data(post_id, data_meta, val, data_sub_meta);
                    }
                } else {
                    elem.textContent = data_default;
                    save_inline_post_data(post_id, data_meta, '', data_sub_meta);
                }
                input.remove();
                elem.style.display = '';
            });

            input.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    input.blur();
                }
                e.preventDefault();
            });
        });
    }

    document.querySelectorAll('.original_value').forEach(function(elem) {
        var connectWith = document.querySelector('input.editable_value');
        inlineEdit(elem, connectWith);
    });

    // ...existing code...

    $( '#mp-product-price-inventory-variants-metabox' ).on( 'keydown', function( event ) {
        if ( event.keyCode === 13 ) {
            event.preventDefault();
            return false;
        }
    } );


    function save_inline_post_data(post_id, meta_name, meta_value, sub_meta) {
        document.querySelectorAll('.mp-dashboard-widget-low-stock-wrap-overlay').forEach(function(el){ el.style.display = 'block'; });
        var data = new URLSearchParams();
        data.append('action', 'save_inline_post_data');
        data.append('post_id', post_id);
        data.append('meta_name', meta_name);
        data.append('meta_sub_name', sub_meta);
        data.append('meta_value', meta_value);
        data.append('ajax_nonce', mp_product_admin_i18n.ajax_nonce);

        fetch(mp_product_admin_i18n.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data.toString()
        })
        .then(function(response){ return response.text(); })
        .then(function(data){
            var form = document.querySelector('form#inventory_threshhold_form');
            if (!form) return;
            fetch(mp_product_admin_i18n.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(form)).toString()
            })
            .then(function(response){ return response.text(); })
            .then(function(data){
                document.querySelectorAll('.mp-dashboard-widget-low-stock-wrap-overlay').forEach(function(el){ el.style.display = 'none'; });
                var response;
                try { response = JSON.parse(data); } catch(e) { response = {}; }
                if (response.status_message !== '') {
                    document.querySelectorAll('.mp-dashboard-widget-low-stock-wrap').forEach(function(el){ el.innerHTML = response.output; });
                    document.querySelectorAll('.low_stock_value').forEach(function(el){ el.innerHTML = response.low_stock_value; });
                    document.querySelectorAll('.original_value').forEach(function(elem){
                        var connectWith = document.querySelector('input.editable_value');
                        inlineEdit(elem, connectWith);
                    });
                }
            });
        });
    }

    jQuery( '.mp_bulk_price' ).on( 'keyup', function() {
        if ( jQuery( '.mp_bulk_price' ).val() == '' || isNaN( jQuery( '.mp_bulk_price' ).val() ) ) {
            jQuery( '.mp_price_controls .save-bulk-form' ).attr( 'disabled', true );
        } else {
            jQuery( '.mp_price_controls .save-bulk-form' ).attr( 'disabled', false );
        }
    } );

    jQuery( '.mp_bulk_inventory' ).on( 'keyup', function() {
        if ( jQuery( '.mp_bulk_inventory' ).val() !== '' && isNaN( jQuery( '.mp_bulk_inventory' ).val() ) ) {
            jQuery( '.mp_inventory_controls .save-bulk-form' ).attr( 'disabled', true );
        } else {
            jQuery( '.mp_inventory_controls .save-bulk-form' ).attr( 'disabled', false );
        }
    } );

    //Price controls
    jQuery( '.mp_popup_controls.mp_price_controls a.save-bulk-form' ).on( 'click', function( e ) {

        var global_price_set = jQuery( '.mp_bulk_price' ).val();
        parent.jQuery.colorbox.close();

        $( '.check-column-box:checked' ).each( function( ) {
            $( this ).closest( 'tr' ).find( '.field_subtype_price' ).html( global_price_set );
            $( this ).closest( 'tr' ).find( '.editable_value_price' ).val( global_price_set );
            save_inline_post_data( $( this ).val(), 'regular_price', global_price_set, '' );
        } );

        return false;
        e.preventDefault();
    } );

    //Inventory controls
    jQuery( '.mp_popup_controls.mp_inventory_controls a.save-bulk-form' ).on( 'click', function( e ) {

        var global_inventory_set = jQuery( '.mp_bulk_inventory' ).val();

        if ( global_inventory_set == '' || isNaN( global_inventory_set ) ) {
            global_inventory_set = '&infin;';
        }

        parent.jQuery.colorbox.close();

        $( '.check-column-box:checked' ).each( function( ) {
            $( this ).closest( 'tr' ).find( '.field_subtype_inventory' ).html( global_inventory_set );
            $( this ).closest( 'tr' ).find( '.editable_value_price' ).val( global_inventory_set );

            save_inline_post_data( $( this ).val(), 'inventory', global_inventory_set, '' );
        } );

        return false;
        e.preventDefault();
    } );


    // Delete controls (Vanilla JS)
    document.querySelectorAll('.mp_popup_controls.mp_delete_controls a.delete-bulk-form').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            // Schließe ggf. Colorbox (Legacy)
            if (window.parent && window.parent.jQuery && window.parent.jQuery.colorbox) {
                window.parent.jQuery.colorbox.close();
            }
            document.querySelectorAll('.check-column-box:checked').forEach(function(box) {
                var tr = box.closest('tr');
                if (tr) tr.remove();
                save_inline_post_data(box.value, 'delete', '', '');
            });
            if (document.querySelectorAll('.check-column-box').length === 0) {
                var postIdInput = document.querySelector('[name="post_ID"]');
                if (postIdInput) save_inline_post_data(postIdInput.value, 'delete_variations', '', '');
                var origPub = document.getElementById('original_publish');
                if (origPub && origPub.value === 'Publish') {
                    var saveBtn = document.getElementById('save-post');
                    if (saveBtn) saveBtn.removeAttribute('disabled');
                    if (saveBtn) saveBtn.click();
                }
                if (origPub && origPub.value === 'Update') {
                    var pubBtn = document.getElementById('publish');
                    if (pubBtn) pubBtn.removeAttribute('disabled');
                    if (pubBtn) pubBtn.click();
                }
            }
            e.preventDefault();
            return false;
        });
    });

    // Close/cancel popup (Vanilla JS)
    document.querySelectorAll('.mp_popup_controls a.cancel').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (window.parent && window.parent.jQuery && window.parent.jQuery.colorbox) {
                window.parent.jQuery.colorbox.close();
            }
            e.preventDefault();
            return false;
        });
    });


    // Native Modal für Popups
    function openModal(content, title) {
        let modal = document.getElementById('mp-vanilla-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'mp-vanilla-modal';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100vw';
            modal.style.height = '100vh';
            modal.style.background = 'rgba(0,0,0,0.7)';
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            modal.style.zIndex = '99999';
            modal.innerHTML = '<div style="background:#fff;max-width:500px;width:90vw;max-height:90vh;overflow:auto;position:relative;padding:2em 1em 1em 1em;border-radius:8px;box-shadow:0 0 20px #0003;">'
                + '<button id="mp-vanilla-modal-close" style="position:absolute;top:10px;right:10px;font-size:1.5em;">&times;</button>'
                + '<div id="mp-vanilla-modal-title" style="font-weight:bold;margin-bottom:1em;"></div>'
                + '<div id="mp-vanilla-modal-content"></div>'
                + '</div>';
            document.body.appendChild(modal);
            modal.querySelector('#mp-vanilla-modal-close').onclick = function() {
                modal.remove();
            };
        }
        modal.querySelector('#mp-vanilla-modal-title').innerHTML = title || '';
        modal.querySelector('#mp-vanilla-modal-content').innerHTML = content;
        modal.style.display = 'flex';
    }

    // open_ajax Links
    document.querySelectorAll('a.open_ajax').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var variationId = link.getAttribute('data-popup-id');
            var url = mp_product_admin_i18n.ajaxurl + '?action=mp_variation_popup&variation_id=' + variationId;
            fetch(url)
                .then(function(resp){ return resp.text(); })
                .then(function(html){
                    var tr = link.closest('tr');
                    var title = '';
                    if (tr) {
                        var t = tr.querySelector('.field_more .variation_name');
                        if (t) title = t.innerHTML;
                    }
                    openModal(html, title);
                });
            e.preventDefault();
        });
    });

    // #variant_add Button
    var variantAddBtn = document.getElementById('variant_add');
    if (variantAddBtn) {
        variantAddBtn.addEventListener('click', function(e) {
            var url = mp_product_admin_i18n.ajaxurl + '?action=ajax_add_new_variant';
            var postIdInput = document.getElementById('post_ID');
            var parent_post_id = postIdInput ? postIdInput.value : '';
            var formData = new URLSearchParams();
            formData.append('action', 'ajax_add_new_variant');
            formData.append('parent_post_id', parent_post_id);
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(function(resp){ return resp.text(); })
            .then(function(data){
                var response;
                try { response = JSON.parse(data); } catch(e) { response = {}; }
                if (response && response.type === true) {
                    var popupUrl = mp_product_admin_i18n.ajaxurl + '?action=mp_variation_popup&variation_id=' + response.post_id + '&new_variation';
                    fetch(popupUrl)
                        .then(function(resp){ return resp.text(); })
                        .then(function(html){
                            openModal(html, '');
                        });
                } else {
                    alert('An error occured while trying to create a new variation post');
                }
            });
            e.preventDefault();
        });
    }

    $( 'body' ).on( 'mp-variation-popup-loaded', function() {

        $( '#variation_popup a.remove_popup_image' ).on( 'click', function( e ) {

            var placeholder_image = $( '#variation_popup .mp-variation-image img' );

            var post_id = $( '#variation_id' ).val();
            var table_placeholder_image = $( '#post-' + post_id ).find( '.mp-variation-image img' );

            table_placeholder_image.attr( 'src', mp_product_admin_i18n.placeholder_image );
            table_placeholder_image.attr( 'width', 30 );
            table_placeholder_image.attr( 'height', 30 );

            placeholder_image.attr( 'src', mp_product_admin_i18n.placeholder_image );
            placeholder_image.attr( 'width', 75 );
            placeholder_image.attr( 'height', 75 );

            save_inline_post_data( post_id, '_thumbnail_id', '', '' );
            e.preventDefault();


        } );

        $( '#variation_popup .mp-variation-image img' ).on( 'click', function() {
            var placeholder_image = $( this );
            var post_id = $( '#variation_id' ).val();
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

        $( '#file_url_button' ).on( 'click', function() {

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

        $( '.fieldset_check' ).each( function() {
            var controller = $( this ).find( '.has_controller' );
            if ( controller.is( ':checked' ) ) {
                $( this ).find( '.has_area' ).show();
            } else {
                $( this ).find( '.has_area' ).hide();
            }
        } );


        $( '.mp-date' ).each( function() {
            var $this = $( this );
            // Convert to HTML5 date input
            $this.attr( 'type', 'date' ).css( { 'cursor': 'pointer' } );
        } );

        var variation_content_type = $( "input[name='variation_content_type']:checked" ).val();

        if ( variation_content_type == 'html' ) {
            $( '.variation_description_button' ).show();
            $( '.variation_content_type_plain' ).hide();
        } else {//plain text
            $( '.variation_description_button' ).hide();
            $( '.variation_content_type_plain' ).show();
        }

        $( document ).on( 'change', "input[name='variation_content_type']", function() {
            var variation_content_type = $( "input[name='variation_content_type']:checked" ).val();
            if ( variation_content_type == 'html' ) {
                $( '.variation_description_button' ).show();
                $( '.variation_content_type_plain' ).hide();
            } else {//plain text
                $( '.variation_description_button' ).hide();
                $( '.variation_content_type_plain' ).show();
            }
        } );

    } );

    $( document ).on( 'change', '.has_controller', function() {
        var parent_holder = $( this ).closest( '.fieldset_check' );
        var controller = $( this );
        if ( controller.is( ':checked' ) ) {
            parent_holder.find( '.has_area' ).show();
        } else {
            parent_holder.find( '.has_area' ).hide();
        }
    } );


    $( document ).on( 'change', '#variation_popup input, #variation_popup textarea, #variation_popup select', function( e ) {
        // Setup form validation on the #register-form element

        $( "#variation_popup" ).validate( {
        } );

        $( '.mp-numeric' ).each( function() {
            $( this ).rules( 'add', {
                number: true,
                messages: {
                    number: mp_product_admin_i18n.message_valid_number_required
                }
            } );
        } );

        $( '.mp-required' ).each( function() {
            $( this ).rules( 'add', {
                required: true,
                messages: {
                    required: mp_product_admin_i18n.message_input_required
                }
            } );
        } );


    } );

    $( document ).on( 'click', '#save-variation-popup-data, .variation_description_button', function( e ) {
        var form = $( 'form#variation_popup' );

        $( '.mp_ajax_response' ).attr( 'class', 'mp_ajax_response' );
        $( '.mp_ajax_response' ).html( mp_product_admin_i18n.saving_message );

        $.post(
            //ajax_nonce: mp_product_admin_i18n.ajax_nonce
            //action: 'save_inline_post_data',
            mp_product_admin_i18n.ajaxurl, form.serialize()
            ).done( function( data, status ) {
            var response = $.parseJSON( data );

            if ( response.status_message !== '' ) {
                $( '.mp_ajax_response' ).html( response.status_message );
                $( '.mp_ajax_response' ).attr( 'class', 'mp_ajax_response' );
                $( '.mp_ajax_response' ).addClass( 'mp_ajax_response_' + response.status );
                if ( $( '#new_variation' ).val() == 'yes' ) {
                    //window.opener.location.reload( false );
                    parent.location.reload()
                }
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
            e.preventDefault();
        }
    } );


    $( document ).on( 'change', '#mp_dashboard_widget_inventory_threshhold', function( e ) {

        $( '.mp-dashboard-widget-low-stock-wrap-overlay' ).show();

        var form = $( 'form#inventory_threshhold_form' );
        $( '.mp_ajax_response' ).attr( 'class', 'mp_ajax_response' );
        $( '.mp_ajax_response' ).html( mp_product_admin_i18n.saving_message );
        $.post(
            //ajax_nonce: mp_product_admin_i18n.ajax_nonce
            //action: 'save_inline_post_data',
            mp_product_admin_i18n.ajaxurl, form.serialize( )
            ).done( function( data, status ) {
            $( '.mp-dashboard-widget-low-stock-wrap-overlay' ).hide();
            var response = $.parseJSON( data );

            if ( response.status_message !== '' ) {
                $( '.mp_ajax_response' ).html( response.status_message );
                $( '.mp_ajax_response' ).attr( 'class', 'mp_ajax_response' );
                $( '.mp_ajax_response' ).addClass( 'mp_ajax_response_' + response.status );
                $( '.mp-dashboard-widget-low-stock-wrap' ).html( response.output );
                $( '.low_stock_value' ).html( response.low_stock_value );

                $( ".original_value" ).each( function( index ) {
                    $( this ).inlineEdit( $( '<input name="temp" class="mp_inline_temp_value" type="text" value="" />' ), $( 'input.editable_value' ) );//' + $.trim( $( this ).html( ) ) + '
                } );
            }

            if ( status == 'success' ) {
                //console.log( response );
            } else {
                //alert( 'fail!' );
                //an error occured
            }
        } );
        e.preventDefault();
    } );

} );
