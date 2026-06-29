(function ($) {
  'use strict';

  function setFeedback($box, message, isError) {
    $box.removeClass('is-ok is-error').addClass(isError ? 'is-error' : 'is-ok').text(message);
  }

  $(document).on('click', '.mp_withdrawal_prepare', function () {
    var $form = $(this).closest('.mp_withdrawal_form');
    var selected = $form.find('input[name="items[]"]:checked').length;
    var $feedback = $form.find('.mp_withdrawal_feedback');

    if (!selected) {
      setFeedback($feedback, (window.mp_withdrawal_i18n && mp_withdrawal_i18n.messages && mp_withdrawal_i18n.messages.selectItems) || 'Bitte waehle mindestens eine Position aus.', true);
      return;
    }

    $form.find('.mp_withdrawal_submit').prop('disabled', false).focus();
    setFeedback($feedback, (window.mp_withdrawal_i18n && mp_withdrawal_i18n.messages && mp_withdrawal_i18n.messages.confirmReady) || 'Bitte pruefe Deine Auswahl und sende den Widerruf verbindlich ab.', false);
  });

  $(document).on('submit', '.mp_withdrawal_form', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $submit = $form.find('.mp_withdrawal_submit');
    var $feedback = $form.find('.mp_withdrawal_feedback');
    var payload = $form.serialize();

    $submit.prop('disabled', true);

    $.post((window.mp_withdrawal_i18n && mp_withdrawal_i18n.ajaxurl) || window.ajaxurl, payload)
      .done(function (resp) {
        if (resp && resp.success) {
          setFeedback($feedback, (resp.data && resp.data.message) || 'Widerruf wurde uebermittelt.', false);
          $form.find('input[name="items[]"]').prop('checked', false);
          return;
        }

        setFeedback($feedback, (resp && resp.data && resp.data.message) || 'Widerruf konnte nicht uebermittelt werden.', true);
      })
      .fail(function () {
        setFeedback($feedback, 'Serverfehler beim Senden des Widerrufs.', true);
      })
      .always(function () {
        $submit.prop('disabled', false);
      });
  });
})(jQuery);
