jQuery(function ($) {
    var $selector = $('#mp-capabilities-role-selector');
    var $boxes = $('.mp-capabilities-role-box[data-role]');
    var $summary = $('#mp-capabilities-summary');
    var $saveRoleButton = $('#mp-capabilities-save-role');
    var $feedback = $('#mp-capabilities-feedback');

    if ($selector.length === 0 || $boxes.length === 0) {
        return;
    }

    function getBoxByRole(role) {
        return $boxes.filter('[data-role="' + role + '"]');
    }

    function updateSummary($activeBox) {
        if (!$summary.length || !$activeBox.length) {
            return;
        }

        var $checks = $activeBox.find('.mp-capability-item input[type="checkbox"]');
        var total = $checks.length;
        var active = 0;
        var labels = [];

        $checks.each(function () {
            var $cb = $(this);
            if ($cb.is(':checked')) {
                active += 1;
                var labelText = $cb.closest('label').find('span').first().text().trim();
                if (labelText) {
                    labels.push(labelText);
                }
            }
        });

        var countText = (window.mpCapabilitiesI18n && mpCapabilitiesI18n.activeCount)
            ? mpCapabilitiesI18n.activeCount.replace('%1$d', active).replace('%2$d', total)
            : ('Aktiv: ' + active + ' von ' + total + ' Berechtigungen');

        var titleText = (window.mpCapabilitiesI18n && mpCapabilitiesI18n.summaryTitle)
            ? mpCapabilitiesI18n.summaryTitle
            : 'Schnelluebersicht';

        var emptyText = (window.mpCapabilitiesI18n && mpCapabilitiesI18n.noneActive)
            ? mpCapabilitiesI18n.noneActive
            : 'Aktuell ist keine Berechtigung aktiviert.';

        var html = '<p><strong>' + titleText + ':</strong> ' + countText + '</p>';

        if (labels.length) {
            html += '<ul class="mp-capabilities-list">';
            labels.forEach(function (text) {
                html += '<li>' + $('<div>').text(text).html() + '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p class="description">' + emptyText + '</p>';
        }

        $summary.html(html);
    }

    function showRole(role) {
        var $target = getBoxByRole(role);
        if (!$target.length) {
            return;
        }

        $boxes.hide().removeClass('is-active');
        $target.show().addClass('is-active');

        updateSummary($target);
    }

    function collectRoleCaps($box) {
        var caps = {};

        $box.find('.mp-capability-item input[type="checkbox"]').each(function () {
            var $cb = $(this);
            var name = $cb.attr('name') || '';
            var match = name.match(/\[([^\]]+)\]$/);
            if (!match) {
                return;
            }
            caps[match[1]] = $cb.is(':checked') ? 1 : 0;
        });

        return caps;
    }

    function setFeedback(type, text) {
        if (!$feedback.length) {
            return;
        }

        $feedback
            .removeClass('notice-success notice-error')
            .addClass(type === 'success' ? 'notice-success' : 'notice-error')
            .html('<p>' + $('<div>').text(text).html() + '</p>')
            .show();
    }

    $selector.on('change', function () {
        showRole($(this).val());
        if ($feedback.length) {
            $feedback.hide();
        }
    });

    $boxes.on('change', '.mp-capability-item input[type="checkbox"]', function () {
        var role = $selector.val();
        updateSummary(getBoxByRole(role));
    });

    $saveRoleButton.on('click', function () {
        var role = $selector.val();
        var $activeBox = getBoxByRole(role);

        if (!role || !$activeBox.length) {
            return;
        }

        var payload = {
            action: 'mp_save_role_caps',
            nonce: (window.mpCapabilitiesI18n && mpCapabilitiesI18n.nonce) ? mpCapabilitiesI18n.nonce : '',
            role: role,
            caps: collectRoleCaps($activeBox)
        };

        var savingText = (window.mpCapabilitiesI18n && mpCapabilitiesI18n.saving) ? mpCapabilitiesI18n.saving : 'Speichere...';
        var saveButtonText = (window.mpCapabilitiesI18n && mpCapabilitiesI18n.saveButton) ? mpCapabilitiesI18n.saveButton : 'Aktuelle Rolle speichern';

        $saveRoleButton.prop('disabled', true).text(savingText);

        $.post(ajaxurl, payload)
            .done(function (response) {
                if (response && response.success) {
                    var okText = (response.data && response.data.message)
                        ? response.data.message
                        : ((window.mpCapabilitiesI18n && mpCapabilitiesI18n.saveSuccess) ? mpCapabilitiesI18n.saveSuccess : 'Berechtigungen wurden gespeichert.');
                    setFeedback('success', okText);
                } else {
                    var errText = (response && response.data && response.data.message)
                        ? response.data.message
                        : ((window.mpCapabilitiesI18n && mpCapabilitiesI18n.saveError) ? mpCapabilitiesI18n.saveError : 'Speichern fehlgeschlagen.');
                    setFeedback('error', errText);
                }
            })
            .fail(function () {
                var errText = (window.mpCapabilitiesI18n && mpCapabilitiesI18n.saveError) ? mpCapabilitiesI18n.saveError : 'Speichern fehlgeschlagen.';
                setFeedback('error', errText);
            })
            .always(function () {
                $saveRoleButton.prop('disabled', false).text(saveButtonText);
            });
    });

    // Initial state
    showRole($selector.val());
});
