(function ($) {
  'use strict';

  function getI18nMessage(key, fallback) {
    return (window.mp_withdrawal_i18n && mp_withdrawal_i18n.messages && mp_withdrawal_i18n.messages[key]) || fallback;
  }

  function setFeedback($box, message, isError) {
    $box.removeClass('is-ok is-error').addClass(isError ? 'is-error' : 'is-ok').text(message);
  }

  function updateReasonBlock($form) {
    var selected = $form.find('input[name="items[]"]:checked').length;
    var $reasonBlock = $form.find('.mp_withdrawal_reason_block');

    if (!$reasonBlock.length) {
      return;
    }

    if (selected > 0) {
      $reasonBlock.prop('hidden', false);
    } else {
      $reasonBlock.prop('hidden', true);
      $form.find('select[name="reason_code"]').val('');
      $form.find('textarea[name="reason_note"]').val('');
      $form.find('.mp_withdrawal_note_counter').text('0/' + ($form.find('.mp_withdrawal_note_counter').data('max') || 300));
    }
  }

  $(document).on('click', '.mp_withdrawal_toggle', function () {
    var $toggle = $(this);
    var $form = $('#' + $toggle.attr('aria-controls'));
    var isOpen = $toggle.attr('aria-expanded') === 'true';

    if (!$form.length) {
      return;
    }

    $toggle.attr('aria-expanded', isOpen ? 'false' : 'true');
    $toggle.text(getI18nMessage(isOpen ? 'openForm' : 'closeForm', isOpen ? 'Widerruf starten' : 'Formular schließen'));

    if (isOpen) {
      $form.stop(true, true).slideUp(180, function () {
        $form.prop('hidden', true);
      });
      return;
    }

    $form.prop('hidden', false).hide().stop(true, true).slideDown(180, function () {
      $form.find('input[name="items[]"]:enabled').first().trigger('focus');
    });
  });

  $(document).on('change', '.mp_withdrawal_form input[name="items[]"]', function () {
    var $form = $(this).closest('.mp_withdrawal_form');
    updateReasonBlock($form);
  });

  $(document).on('input', '.mp_withdrawal_form textarea[name="reason_note"]', function () {
    var $textarea = $(this);
    var $form = $textarea.closest('.mp_withdrawal_form');
    var maxLen = parseInt($form.attr('data-max-note'), 10) || 300;
    var value = $textarea.val() || '';
    if (value.length > maxLen) {
      value = value.substring(0, maxLen);
      $textarea.val(value);
    }

    $form.find('.mp_withdrawal_note_counter').text(value.length + '/' + maxLen);
  });

  $(document).on('submit', '.mp_withdrawal_form', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $submit = $form.find('.mp_withdrawal_submit');
    var $feedback = $form.find('.mp_withdrawal_feedback');
    var selected = $form.find('input[name="items[]"]:checked').length;
    var reason = $form.find('select[name="reason_code"]').val();
    var note = $form.find('textarea[name="reason_note"]').val() || '';
    var maxLen = parseInt($form.attr('data-max-note'), 10) || 300;
    var payload = $form.serialize();

    if (!selected) {
      setFeedback($feedback, getI18nMessage('selectItems', 'Bitte wähle mindestens eine Position aus.'), true);
      return;
    }

    if (!reason) {
      setFeedback($feedback, getI18nMessage('selectReason', 'Bitte wähle einen Widerrufsgrund aus.'), true);
      return;
    }

    if (note.length > maxLen) {
      setFeedback($feedback, getI18nMessage('noteTooLong', 'Bitte kürze die Begründung auf die maximal erlaubte Länge.'), true);
      return;
    }

    $submit.prop('disabled', true);

    $.post((window.mp_withdrawal_i18n && mp_withdrawal_i18n.ajaxurl) || window.ajaxurl, payload)
      .done(function (resp) {
        if (resp && resp.success) {
          setFeedback($feedback, (resp.data && resp.data.message) || 'Widerruf wurde übermittelt.', false);
          $form.find('input[name="items[]"]').prop('checked', false);
          $form.find('select[name="reason_code"]').val('');
          $form.find('textarea[name="reason_note"]').val('');
          $form.find('.mp_withdrawal_note_counter').text('0/' + maxLen);
          updateReasonBlock($form);
          return;
        }

        setFeedback($feedback, (resp && resp.data && resp.data.message) || getI18nMessage('submitError', 'Widerruf konnte nicht übermittelt werden.'), true);
      })
      .fail(function () {
        setFeedback($feedback, 'Serverfehler beim Senden des Widerrufs.', true);
      })
      .always(function () {
        $submit.prop('disabled', false);
      });
  });

  $(function () {
    $('.mp_withdrawal_form').each(function () {
      updateReasonBlock($(this));
    });
  });
})(jQuery);
