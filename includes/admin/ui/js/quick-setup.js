jQuery(document).ready(function ($) {

    // Vanilla Tabs Implementation
    function initTabs() {
        var $tabsContainer = $('#mp-quick-setup-tabs');
        var $tabPanes = $tabsContainer.find('.ui-tabs-panel');
        
        $tabPanes.hide();
        $tabPanes.first().show();
        
        var setActive = function(index) {
            $tabPanes.hide();
            if (index < $tabPanes.length) {
                $tabPanes.eq(index).show();
            }
        };
        
        // Expose setActive for tab navigation
        window.mpQuickSetupTabs = {
            setActive: setActive
        };
    }
    
    initTabs();

    $('#mp-quick-setup-tab-locations .mp_tab_navigation .mp_button_tab_nav-next').on('click', function (e) {
        window.mpQuickSetupTabs.setActive(1);
        e.preventDefault();
    });

    $('#mp-quick-setup-tab-currency-and-tax .mp_tab_navigation .mp_button_tab_nav-next').on('click', function (e) {
        window.mpQuickSetupTabs.setActive(2);
        e.preventDefault();
    });

    $('#mp-quick-setup-tab-metric-system .mp_tab_navigation .mp_button_tab_nav-next').on('click', function (e) {
        window.mpQuickSetupTabs.setActive(3);
        e.preventDefault();
    });

    $('#mp-quick-setup-tab-currency-and-tax .mp_tab_navigation .mp_button_tab_nav-prev').on('click', function (e) {
        window.mpQuickSetupTabs.setActive(0);
        e.preventDefault();
    });

    $('#mp-quick-setup-tab-metric-system .mp_tab_navigation .mp_button_tab_nav-prev').on('click', function (e) {
        window.mpQuickSetupTabs.setActive(1);
        e.preventDefault();
    });

    $('#mp-quick-setup-tab-payment-gateway .mp_tab_navigation .mp_button_tab_nav-prev').on('click', function (e) {
        window.mpQuickSetupTabs.setActive(2);
        e.preventDefault();
    });

    // Fields
    $(".mp_tab_content_locations").append($("#mp-quick-setup-wizard-location").html());
    $("#mp-quick-setup-wizard-location").remove();

    $(".mp_tab_content_countries").append($("#mp-quick-setup-wizard-countries").html());
    $("#mp-quick-setup-wizard-countries").remove();

    $(".mp_tab_content_currency").append($("#mp-quick-setup-wizard-currency").html());
    $("#mp-quick-setup-wizard-currency").remove();

    $(".mp_tab_content_tax").append($("#mp-quick-setup-wizard-tax").html());
    $("#mp-quick-setup-wizard-tax").remove();

    $(".mp_tab_content_metric_system").append($("#mp-quick-setup-wizard-measurement-system").html());
    $("#mp-quick-setup-wizard-measurement-system").remove();


    $(document).on('psource_fields_saved_field_base_country', function (e, data) {
        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'mp_preset_currency_base_country',
                country: data,
                ajax_nonce: mp_quick_setup_i18n.ajax_nonce
            },
            success: function (data) {
				// Reload page to update shipping methods
				location.reload();
            }
        })
        //we also need to reload the shipping tab
        $('#mp-settings-shipping-plugin-flat_rate').load(window.location.href + ' #mp-settings-shipping-plugin-flat_rate');
    });

    $('#mp-quick-setup-tab-metric-system').on('click', 'input[name="mp_charge_shipping"]', function () {
        if ($(this).val() == 1) {
            $('.mp_tab_content_details-shipping').slideDown();
        } else {
            $('.mp_tab_content_details-shipping').slideUp();
        }
    });
    $('input[name="mp_charge_shipping"]:checked').trigger('click');
    $('#mp-quick-setup-tab-payment-gateway').on('click', 'input[name="wizard_payment"]', function () {
        if ($(this).val() != 'other') {
            $('.mp_tab_content_details-payment-gateway').slideUp();
        } else {
            $('.mp_tab_content_details-payment-gateway').slideDown();
        }
    });

    $('input[name="wizard_payment"]:checked').trigger('click');
});