
// Vanilla JS Custom Validation
function addCustomValidation() {
    const forms = document.querySelectorAll('form#post, form#mp-main-form, form.bulk-form');
    forms.forEach(form => {
        // Sofortige Fehlerentfernung beim Ausfüllen/Ändern
        form.addEventListener('input', function(e) {
            const field = e.target;
            if (field.classList && field.classList.contains('psource-error-message')) return;
            // Pflichtfeld prüfen
            if (field.matches('[data-rule-required="true"], [required]')) {
                removeError(field);
                if (!isFieldFilled(field, form)) {
                    showError(field, field.getAttribute('data-msg-required') || 'Dieses Feld ist erforderlich.');
                }
            }
        }, true);
        form.addEventListener('change', function(e) {
            const field = e.target;
            if (field.classList && field.classList.contains('psource-error-message')) return;
            if (field.matches('[data-rule-required="true"], [required]')) {
                removeError(field);
                if (!isFieldFilled(field, form)) {
                    showError(field, field.getAttribute('data-msg-required') || 'Dieses Feld ist erforderlich.');
                }
            }
        }, true);

        form.addEventListener('submit', function(e) {
            let valid = true;
            let debugEmptyFields = [];
            // Remove old errors
            form.querySelectorAll('.psource-error-message').forEach(el => el.remove());

            // 1. Pflichtfelder prüfen (data-rule-required oder required)
            const requiredFields = form.querySelectorAll('[data-rule-required="true"], [required]');
            requiredFields.forEach(field => {
                // Nur sichtbare und nicht deaktivierte Felder prüfen
                if (
                    field.offsetParent === null || // ausgeblendet (display:none, hidden ancestor)
                    field.disabled ||
                    field.readOnly ||
                    field.type === 'hidden'
                ) {
                    return;
                }
                removeError(field);
                if (!isFieldFilled(field, form)) {
                    valid = false;
                    showError(field, field.getAttribute('data-msg-required') || 'Dieses Feld ist erforderlich.');
                    debugEmptyFields.push({
                        name: field.name,
                        id: field.id,
                        type: field.type,
                        value: field.value,
                        outerHTML: field.outerHTML
                    });
                }
            });

            // 2. Custom-Validierungen prüfen
            form.querySelectorAll('[data-custom-validation]').forEach(field => {
                Array.from(field.attributes).forEach(attr => {
                    if(attr.name.startsWith('data-rule-custom-')) {
                        const ruleName = attr.name.replace('data-rule-custom-', '');
                        const ruleVal = attr.value;
                        const msg = field.getAttribute('data-msg-' + ruleName) || 'Ungültiger Wert';
                        let error = false;
                        if(ruleName === 'alphanumeric') {
                            if(!new RegExp('^[a-z0-9]+$', 'i').test(field.value)) error = true;
                        }
                        if(ruleName === 'lessthan') {
                            const parent = field.closest('.psource-subfield-group') || field.closest('.psource-field');
                            if(parent) {
                                const compare = parent.querySelector(ruleVal);
                                if(compare && field.value > compare.value) error = true;
                            }
                        }
                        // Add more rules as needed
                        if(error) {
                            valid = false;
                            showError(field, msg);
                            debugEmptyFields.push({
                                name: field.name,
                                id: field.id,
                                type: field.type,
                                value: field.value,
                                outerHTML: field.outerHTML
                            });
                        }
                    }
                });
            });
            if(debugEmptyFields.length > 0) {
                console.warn('MARKETPRESS VALIDATION DEBUG: Diese Felder wurden als leer/ungültig erkannt:', debugEmptyFields);
            }
            if(!valid) {
                e.preventDefault();
                alert(PSOURCE_Metaboxes.form_error_msg.replace(/%s1/g, debugEmptyFields.length + ' Fehler').replace(/%s2/g, 'sind aufgetreten'));
            }
        });
    });

    // Hilfsfunktionen
    function isFieldFilled(field, form) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            const group = form.querySelectorAll('[name="' + field.name + '"]');
            return Array.from(group).some(f => f.checked);
        }
        if (field.tagName === 'SELECT') {
            return field.value && field.value !== '';
        }
        return field.value && field.value.trim() !== '';
    }
    function showError(field, msg) {
        removeError(field);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'psource-error-message';
        errorDiv.style.color = 'red';
        errorDiv.textContent = msg;
        field.parentNode.appendChild(errorDiv);
    }
    function removeError(field) {
        if (!field.parentNode) return;
        const old = field.parentNode.querySelector('.psource-error-message');
        if (old) old.remove();
    }
}

( function( $ ) {
    $( document ).ready( function() {
        // Preload working indicator
        $( 'body' ).append( '<img class="psource-metabox-working-indicator" style="display:none" src="' + PSOURCE_Metaboxes.spinner_url + '" alt="" />' );
    } );

    $.fn.isWorking = function( isLoading ) {
        var $spinner = $( '.psource-metabox-working-indicator' );

        return this.each( function() {
            var $this = $( this );

            if ( isLoading ) {
                if ( $this.hasClass( 'working' ) ) {
                    return;
                }

                if ( $this.is( 'input, select, textarea' ) ) {
                    $this.prop( 'disabled', true );
                }

                $this.addClass( 'working' );
                $spinner.insertAfter( $this );
                $spinner.show();
            } else {
                if ( $this.is( 'input, select, textarea' ) ) {
                    $this.prop( 'disabled', false );
                }

                $this.removeClass( 'working' );
                $spinner.hide();
            }
        } );
    };
}( jQuery ) );

( function( $ ) {
    window.onload = function() {
        /* initializing conditional logic here instead of document.ready() to prevent
         issues with wysiwyg editor not getting proper height */
        initConditionals();
        $( '.psource-postbox' ).find( ':checkbox, :radio, select' ).on('change', initConditionals );
    }


    function updateRepeaterIndexes() {
        $('.psource-subfields').each(function() {
            $(this).find('.psource-subfield-group-index span').each(function(idx) {
                $(this).text(idx + 1);
            });
        });
    }

    $( document ).on( 'psource_repeater_field/after_add_field_group', function( e ) {
        initConditionals();
        updateRepeaterIndexes();
    } );

    document.addEventListener('DOMContentLoaded', function() {
        addCustomValidation();
        initRowShading();
        initToolTips();
        initPostboxAccordions();
    });

    var initPostboxAccordions = function() {
        $( '#mp-main-form' ).find( '.psource-postbox' ).find( '.hndle, .handlediv' ).on('click', function() {
            var $this = $( this ),
                $postbox = $this.closest( '.psource-postbox' );

            $postbox.toggleClass( 'closed' );
            $( document ).trigger( 'postbox-toggled', $postbox );

            $.post( ajaxurl, {
                "action": "psource_metabox_save_state",
                "nonce":  PSOURCE_Metaboxes.nonce,
                "closed": $postbox.hasClass( 'closed' ),
                "id": $postbox.attr( 'id' )
            } );
        } );
    }

    var initToolTips = function() {
        $( '.psource-field' ).on( 'click', '.psource-metabox-tooltip', function() {
            var $this = $( this ),
                $button = $this.find( '.psource-metabox-tooltip-button' );

            if ( $button.length == 0 ) {
                $this.children( 'span' ).append( '<a class="psource-metabox-tooltip-button" href="#">x</a>' );
            }

            $this.children( 'span' ).css( 'display', 'block' ).position( {
                my: "left center",
                at: "right center",
                of: $this,
                using: function( pos, feedback ) {
                    $( this ).css( pos ).removeClass( 'right left' ).addClass( feedback.horizontal );
                }
            } );
        } );

        $( '.psource-field' ).on( 'click', '.psource-metabox-tooltip-button', function( e ) {
            e.preventDefault();
            e.stopPropagation();
            $( this ).parent().fadeOut( 250 );
        } );
    }

    var initRowShading = function() {
        $( '.psource-postbox' ).each( function() {
            var $rows = $( this ).find( '.psource-field:visible' );
            $rows.filter( ':odd' ).addClass( 'shaded' );
            $rows.filter( ':even' ).removeClass( 'shaded' );
        } );

        $( '.psource-field-section' ).each( function() {
            var $this = $( this ),
                shaded = $this.hasClass( 'shaded' ) ? true : false;

            if ( shaded ) {
                $this.nextUntil( '.psource-field-section' ).addClass( 'shaded' );
            } else {
                $this.nextUntil( '.psource-field-section' ).removeClass( 'shaded' );
            }
        } )
    }

    var testConditionals = function( conditionals, $obj ) {
        var numValids = 0;

        $.each( conditionals, function( i, conditional ) {
            if ( conditional.name.indexOf( '[' ) >= 0 && $obj.closest( '.psource-subfield-group' ).length ) {
                var nameParts = conditional.name.split( '[' );
                var $input = $obj.closest( '.psource-subfield-group' ).find( '[name^="' + nameParts[0] + '"][name*="[' + nameParts[1].replace( /\]/g, '' ) + ']"]' );
            } else {
                var $input = $( '[name="' + conditional.name + '"]' );
            }

            if ( !$input.is( ':radio' ) && !$input.is( ':checkbox' ) && !$input.is( 'select' ) ) {
                // Conditional logic only works for radios, checkboxes and select dropdowns
                return;
            }

            var val = getInputValue( $input );

            if ( $.inArray( val, conditional.value ) >= 0 ) {
                numValids++;
            }
        } );

        return numValids;
    };

    var parseConditionals = function( elm ) {
        var conditionals = [ ];
        $.each( elm.attributes, function( i, attrib ) {
            if ( attrib.name.indexOf( 'data-conditional-name' ) >= 0 ) {
                var index = attrib.name.replace( 'data-conditional-name-', '' );

                if ( conditionals[index] === undefined ) {
                    conditionals[index] = { };
                }

                conditionals[index]['name'] = attrib.value;
            }

            if ( attrib.name.indexOf( 'data-conditional-value' ) >= 0 ) {
                var index = attrib.name.replace( 'data-conditional-value-', '' );

                if ( conditionals[index] === undefined ) {
                    conditionals[index] = { };
                }

                conditionals[index]['value'] = attrib.value.split( '||' );
            }
        } );

        return conditionals;
    };

    var getInputValue = function( $input ) {
        if ( $input.is( 'select' ) ) {
            var val = $input.val();
        }

        if ( $input.is( ':checkbox' ) ) {
            var val = ( $input.prop( 'checked' ) ) ? $input.val() : "-1";
        }

        if ( $input.is( ':radio' ) ) {
            var val = $input.filter( ':checked' ).val();
        }

        return val;
    }

    var initConditionals = function() {
        $( '.psource-field-has-conditional, .psource-metabox-has-conditional' ).each( function() {
            var $this = $( this ),
                operator = $this.attr( 'data-conditional-operator' ),
                action = $this.attr( 'data-conditional-action' ),
                numValids = 0;

            if ( operator === undefined || action === undefined ) {
                // Skip elements that don't have conditional attributes defined
                return;
            }

            operator = operator.toUpperCase();
            action = action.toLowerCase();

            var conditionals = parseConditionals( this );

            if ( $this.hasClass( 'psource-metabox-has-conditional' ) ) {
                $container = $this;
            } else {
                $container = ( $this.closest( '.psource-subfield' ).length ) ? $this.closest( '.psource-subfield' ) : $this.closest( '.psource-field' )
            }

            if ( action == 'show' ) {
                if ( operator == 'AND' ) {
                    if ( testConditionals( conditionals, $this ) != conditionals.length ) {
                        hideContainer( $container );
                    } else {
                        showContainer( $container );
                    }
                } else {
                    if ( testConditionals( conditionals, $this ) == 0 ) {
                        $container.hide().next( 'p.submit' ).hide();
                    } else {
                        $container.fadeIn( 500 ).next( 'p.submit' ).fadeIn( 500 )
                    }
                }
            }

            if ( action == 'hide' ) {
                if ( operator == 'AND' ) {
                    if ( testConditionals( conditionals, $this ) == conditionals.length ) {
                        $container.hide().next( 'p.submit' ).hide();
                    } else {
                        $container.fadeIn( 500 ).next( 'p.submit' ).fadeIn( 500 )
                    }
                } else {
                    if ( testConditionals( conditionals, $this ) > 0 ) {
                        $container.hide().next( 'p.submit' ).hide();
                    } else {
                        $container.fadeIn( 500 ).next( 'p.submit' ).fadeIn( 500 )
                    }
                }
            }

            initRowShading();
        } );


        $( '.meta-box-sortables.store-settings_page_store-settings-payments' ).fadeTo( 0, 100 );

    };

    var hideContainer = function( $container ) {
        /**
         * Triggers right before a field container is hidden
         *
         * @since 1.0
         * @access public
         * @param jQuery $container The jQuery object to be hidden.
         */
        $( document ).trigger( 'psource_metaboxes/before_hide_field_container', [ $container ] );

        $container.hide().next( 'p.submit' ).hide();

        /**
         * Triggers right after a field container is hidden
         *
         * @since 1.0
         * @access public
         * @param jQuery $container The jQuery object that was hidden.
         */
        $( document ).trigger( 'psource_metaboxes/after_hide_field_container', [ $container ] );

    };

    var showContainer = function( $container ) {
        /**
         * Triggers right before a field container is show
         *
         * @since 1.0
         * @access public
         * @param jQuery $container The jQuery object to be shown.
         */
        $( document ).trigger( 'psource_metaboxes/before_show_field_container', [ $container ] );

        $container.fadeIn( 500, function() {
            /**
             * Triggers right after a field container is fully shown
             *
             * @since 1.0
             * @access public
             * @param jQuery $container The jQuery object that was shown.
             */
            $( document ).trigger( 'psource_metaboxes/after_show_field', [ $container ] );
        } ).next( 'p.submit' ).fadeIn( 500 )
    };

    // jQuery Validate-Logik entfernt, Vanilla JS übernimmt die Validierung

}( jQuery ) );