/*
 * jQuery Repeatable Fields v1.3.1
 * http://www.rhyzz.com/repeatable-fields.html
 *
 * Copyright (c) 2014-2015 Rhyzz
 * License MIT
 */

( function( $ ) {
    $.fn.repeatable_fields = function( custom_settings ) {
        var default_settings = {
            wrapper: '.wrapper',
            container: '.container',
            row: '.row',
            add: '.add',
            remove: '.remove',
            move: '.move',
            template: '.template',
            is_sortable: true,
            before_add: null,
            after_add: after_add,
            before_remove: null,
            after_remove: null,
            sortable_options: null,
        }

        var settings = $.extend( default_settings, custom_settings );

        // Initialize all repeatable field wrappers
        initialize( this );

        function initialize( parent ) {
            $( parent ).find( settings.wrapper ).each( function( index, element ) {
                var wrapper = this;

                var container = $( wrapper ).children( settings.container );

                // Disable all form elements inside the row template
                $( container ).children( settings.template ).hide().find( ':input' ).each( function() {
                    $( this ).prop( 'disabled', true );
                } );

                var row_count = $( container ).children( settings.row ).filter( function() {
                    return !$( this ).hasClass( settings.template.replace( '.', '' ) );
                } ).length;

                $( container ).attr( 'data-rf-row-count', row_count );

                $( wrapper ).on( 'click', settings.add, function( event ) {
                    event.stopImmediatePropagation();

                    var row_template = $( $( container ).children( settings.template ).clone().removeClass( settings.template.replace( '.', '' ) )[0].outerHTML );

                    // Enable all form elements inside the row template
                    $( row_template ).find( ':input' ).each( function() {
                        $( this ).prop( 'disabled', false );
                    } );

                    if ( typeof settings.before_add === 'function' ) {
                        settings.before_add( container );
                    }

                    var new_row = $( row_template ).show().appendTo( container );

                    if ( typeof settings.after_add === 'function' ) {
                        settings.after_add( container, new_row );
                    }

                    // The new row might have it's own repeatable field wrappers so initialize them too
                    initialize( new_row );
                } );

                $( wrapper ).on( 'click', settings.remove, function( event ) {
                    event.stopImmediatePropagation();

                    var row = $( this ).parents( settings.row ).first();

                    if ( typeof settings.before_remove === 'function' ) {
                        settings.before_remove( container, row );
                    }

                    row.remove();

                    if ( typeof settings.after_remove === 'function' ) {
                        settings.after_remove( container );
                    }
                } );

                if ( settings.is_sortable === true ) {
                    enableNativeDragDrop( $( wrapper ).find( settings.container ), settings.row, settings.move );
                }
            } );
        }

        function after_add( container, new_row ) {
            var row_count = $( container ).attr( 'data-rf-row-count' );

            row_count++;

            new_row.addClass( 'mp-variation-row' );
            new_row.addClass( 'mp-variation-row-count-' + ( ( $( '.repeat .row' ).length ) - 1 ) );
            new_row.addClass( 'variation_row_color_' + ( ( $( '.repeat .row' ).length ) - 1 ) );

            var current_class = '.mp-variation-row-count-' + ( ( $( '.repeat .row' ).length ) - 1 );

            $( '*', new_row ).each( function() {
                //$(this).addClass( 'mp-variation-row-count-'+row_count );
                $.each( this.attributes, function( index, element ) {
                    this.value = this.value.replace( /{{row-count-placeholder}}/, row_count - 1 );
                } );
            } );

            $( current_class ).trigger( 'mp:variation-row-added' );
        }

        function enableNativeDragDrop( container, rowSelector, moveSelector ) {
            var draggedElement = null;

            container.on( 'dragstart', moveSelector, function( e ) {
                draggedElement = $( this ).closest( rowSelector );
                draggedElement.css( 'opacity', '0.5' );
                if ( e.originalEvent && e.originalEvent.dataTransfer ) {
                    e.originalEvent.dataTransfer.effectAllowed = 'move';
                }
            } );

            container.on( 'dragend', moveSelector, function( e ) {
                if ( draggedElement ) {
                    draggedElement.css( 'opacity', '1' );
                    draggedElement = null;
                }
            } );

            container.on( 'dragover', rowSelector, function( e ) {
                if ( e.originalEvent && e.originalEvent.dataTransfer ) {
                    e.originalEvent.dataTransfer.dropEffect = 'move';
                    e.preventDefault();
                }
                return false;
            } );

            container.on( 'drop', rowSelector, function( e ) {
                if ( e.originalEvent ) {
                    e.preventDefault();
                }
                
                if ( draggedElement && draggedElement[0] !== this ) {
                    var targetRow = $( this );
                    // Swap rows by moving dragged element before target
                    draggedElement.insertBefore( targetRow );
                }
                return false;
            } );

            // Enable draggable attribute on move handles
            container.on( 'mouseenter', moveSelector, function() {
                $( this ).closest( rowSelector ).attr( 'draggable', 'true' );
            } );
        }
    }
} )( jQuery );