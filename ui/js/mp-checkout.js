var mp_checkout;

mp_checkout = {
  /**
   * Initialize event listeners
   *
   * @since 3.0
   */
  initListeners: function () {
    this.initShippingAddressListeners && this.initShippingAddressListeners();
    this.initAccountRegistrationListeners &&
      this.initAccountRegistrationListeners();
    this.initPaymentOptionListeners && this.initPaymentOptionListeners();
    this.initUpdateStateFieldListeners && this.initUpdateStateFieldListeners();
    this.initCardValidation && this.initCardValidation();
    this.initCheckoutSteps && this.initCheckoutSteps();
    this.listenToLogin && this.listenToLogin();
    // Event-Handling für Schrittwechsel ggf. mit CustomEvent nachbauen
  },
  /**
   * Update state list/zipcode field when country changes
   *
   * @since 3.0
   */
  initUpdateStateFieldListeners: function () {
    document
      .querySelectorAll('[name="billing[country]"], [name="shipping[country]"]')
      .forEach((input) => {
        input.addEventListener("change", function () {
          const url = mp_i18n.ajaxurl + "?action=mp_update_states_dropdown";
          const isBilling = input.name.indexOf("billing") === 0;
          const state = document.querySelector(
            isBilling ? '[name="billing[state]"]' : '[name="shipping[state]"]',
          );
          const zip = document.querySelector(
            isBilling ? '[name="billing[zip]"]' : '[name="shipping[zip]"]',
          );
          const type = isBilling ? "billing" : "shipping";
          const row = state.closest(".mp_checkout_fields");
          import("./mp-loading-overlay.js").then((overlay) => {
            overlay.showLoadingOverlay();
            fetch(url, {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: `country=${encodeURIComponent(input.value)}&type=${type}`,
            })
              .then((res) => res.json())
              .then((resp) => {
                overlay.hideLoadingOverlay();
                if (resp.success) {
                  if (resp.data.states) {
                    state.innerHTML = resp.data.states;
                    state.dispatchEvent(new Event("change"));
                    state.closest(".mp_checkout_column").style.display = "";
                  } else {
                    state.closest(".mp_checkout_column").style.display = "none";
                  }
                  if (resp.data.show_zipcode) {
                    zip.closest(".mp_checkout_column").style.display = "";
                  } else {
                    zip.closest(".mp_checkout_column").style.display = "none";
                  }
                }
              });
          });
        });
      });
  },
  /**
   * Show the checkout form
   *
   * @since 3.0
   */
  showForm: function () {
    var form = document.getElementById("mp-checkout-form");
    if (form) form.style.display = "";
  },
  /**
   * Get a value from a hashed query string
   *
   * @since 3.0
   * @param string what The name of the variable to retrieve.
   * @param mixed defaultVal Optional, what to return if the variable doesn't exist. Defaults to false.
   * @return mixed
   */
  getHash: function (what, defaultVal) {
    var hash = window.location.hash;

    if (undefined === defaultVal) {
      defaultVal = false;
    }

    if (0 > hash.indexOf("#!") || undefined === defaultVal) {
      return defaultVal;
    }

    var hashParts = hash.substr(2).split("&"),
      hashPairs = {};

    $.each(hashParts, function (index, value) {
      var tmp = value.split("=");
      hashPairs[tmp[0]] = tmp[1];
    });

    if (undefined === hashPairs[what]) {
      return defaultVal;
    }

    return hashPairs[what];
  },
  /**
   * Show/hide checkout section error summary
   *
   * @since 3.0
   * @param string action Either "show" or "hide".
   * @param int count The number of errors.
   */
  // errorSummary: Funktion ggf. auf Vanilla JS umstellen, falls benötigt
  /**
   * Execute when on the last step of checkout
   *
   * @since 3.0
   * @event mp_checkout/step_changed
   */
  // lastStep: Funktion ggf. auf Vanilla JS umstellen, falls benötigt
  /**
   * Go to next step in checkout
   *
   * @since 3.0
   */
  // nextStep: Funktion ggf. auf Vanilla JS umstellen, falls benötigt
  /**
   * Change checkout steps
   *
   * @since 3.0
   * @param jQuery $out The jquery object being transitioned FROM
   * @param jQuery $in The jquery object being transitioned TO
   */
  changeStep: function (outElem, inElem) {
    // Tooltips schließen (wenn Vanilla Tooltip vorhanden)
    // ... ggf. eigene Tooltip-Logik hier ...
    var outContent = outElem.querySelector('.mp_checkout_section_content');
    var inContent = inElem.querySelector('.mp_checkout_section_content');
    if (outContent) {
      outContent.style.display = 'none';
    }
    outElem.classList.remove('current');
    if (inContent) {
      inContent.style.display = '';
    }
    inElem.classList.add('current');
    // CustomEvent für Schrittwechsel
    var event = new CustomEvent('mp_checkout/step_changed', {
      detail: { outElem: outElem, inElem: inElem }
    });
    document.dispatchEvent(event);
    mp_checkout.initActivePaymentMethod && mp_checkout.initActivePaymentMethod();
  },
  /**
   * Initialize checkout steps
   *
   * @since 3.0
   */
  initCheckoutSteps: function () {
    var checkoutForm = document.getElementById("mp-checkout-form");
    // Validierung beim Klick auf Next Step Button (auch wenn kein Submit erfolgt)
    if (checkoutForm) {
      checkoutForm.querySelectorAll('.mp_button-checkout-next-step').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          // Nur Felder im aktuellen Schritt prüfen
          const current = checkoutForm.querySelector('.mp_form-checkout .current');
          let valid = true;
          let firstInvalid = null;
          if (current) {
            // Vor Validierung: Alle Fehlerklassen und Hinweise im aktuellen Schritt entfernen
            current.querySelectorAll('.mp_form_input_error').forEach(el => el.classList.remove('mp_form_input_error'));
            current.querySelectorAll('.mp_field_required_hint').forEach(el => el.remove());
            current.querySelectorAll('[required], .mp_field_required').forEach(function(input) {
              let el = input;
              if (el.tagName === 'SPAN' && el.classList.contains('mp_field_required')) {
                el = el.closest('.mp_checkout_field').querySelector('input, select, textarea');
              }
              // Nur sichtbare Felder prüfen
              if (!el || el.offsetParent === null) return;
              // Hinweis-Element suchen oder erstellen
              let hint = null;
              if (el) {
                hint = el.closest('.mp_checkout_field')?.querySelector('.mp_field_required_hint');
              }
              if (!el.value) {
                el.classList.add('mp_form_input_error');
                valid = false;
                if (!firstInvalid) firstInvalid = el;
                if (!hint) {
                  hint = document.createElement('span');
                  hint.className = 'mp_field_required_hint';
                  hint.style.color = 'red';
                  hint.style.display = 'block';
                  hint.style.fontSize = '0.9em';
                  hint.textContent = 'Dieses Feld ist erforderlich';
                  el.parentNode.appendChild(hint);
                } else {
                  hint.style.display = 'block';
                }
              } else {
                el.classList.remove('mp_form_input_error');
                if (hint) {
                  hint.style.display = 'none';
                }
              }
            });
          }
          if (!valid) {
            e.preventDefault();
            alert('Bitte fülle alle benötigten Felder aus!');
            if (firstInvalid) firstInvalid.focus();
          }
        });
      });
    }
        // Validierung beim Klick auf Next Step (Submit-Button)
        if (checkoutForm) {
          checkoutForm.addEventListener('submit', function(e) {
            let valid = true;
            let firstInvalid = null;
            // Prüfe ALLE Pflichtfelder im gesamten Formular
            checkoutForm.querySelectorAll('[required], .mp_field_required').forEach(function(input) {
              let el = input;
              if (el.tagName === 'SPAN' && el.classList.contains('mp_field_required')) {
                el = el.closest('.mp_checkout_field').querySelector('input, select, textarea');
              }
              // Nur sichtbare Felder prüfen
              if (!el || el.offsetParent === null) return;
              if (!el.value) {
                el.classList.add('mp_form_input_error');
                valid = false;
                if (!firstInvalid) firstInvalid = el;
              } else {
                el.classList.remove('mp_form_input_error');
              }
            });
            if (!valid) {
              e.preventDefault();
              alert('Bitte fülle alle benötigten Felder aus!');
              if (firstInvalid) firstInvalid.focus();
            }
          });
        }
    var checkoutForm = document.getElementById("mp-checkout-form");
    if (!checkoutForm) return;


    // Schrittwechsel per Klick auf Überschrift mit Pflichtfeld-Validierung
    checkoutForm
      .querySelectorAll(".mp_checkout_section_heading-link")
      .forEach((link) => {
        link.addEventListener("click", function (e) {
          const section = link.closest(".mp_checkout_section");
          const current = checkoutForm.querySelector(
            ".mp_form-checkout .current",
          );
          // Prüfe Pflichtfelder im aktuellen Schritt
          let valid = true;
          let firstInvalid = null;
          if (current) {
            current.querySelectorAll('[required], .mp_field_required').forEach(function(input) {
              let el = input;
              // Falls .mp_field_required ein <span> ist, hole das zugehörige Input-Feld
              if (el.tagName === 'SPAN' && el.classList.contains('mp_field_required')) {
                el = el.closest('.mp_checkout_field').querySelector('input, select, textarea');
              }
              if (el && !el.value) {
                el.classList.add('mp_form_input_error');
                valid = false;
                if (!firstInvalid) firstInvalid = el;
              } else if (el) {
                el.classList.remove('mp_form_input_error');
              }
            });
          }
          if (!valid) {
            e.preventDefault();
            alert('Bitte fülle alle benötigten Felder aus!');
            if (firstInvalid) firstInvalid.focus();
            return;
          }
          if (current) {
            mp_checkout.changeStep(current, section);
          }
        });
      });

    // Formular-Submit: Kein preventDefault mehr, damit ein echter Seiten-Reload erfolgt!
    // (Optional: Validierung kann als onsubmit-Handler im Template erfolgen, aber nicht per JS blockieren)
  },
  /**
   * Initialize the active/selected payment method
   *
   * @since 3.0
   */
  initActivePaymentMethod: function () {
    var inputs = document.querySelectorAll('input[name="payment_method"]');
    var found = false;
    inputs.forEach(function (input) {
      if (input.checked && !found) {
        input.dispatchEvent(new Event("click", { bubbles: true }));
        found = true;
      }
    });
    if (!found && inputs.length > 0) {
      inputs[0].dispatchEvent(new Event("click", { bubbles: true }));
    }
  },
  /**
   * Initialize credit card validation events/rules
   *
   * @since 3.0
   */
  initCardValidation: function () {
    import("./mp-creditcard-validate.js").then((cc) => {
      // Formatierung beim Tippen
      document.querySelectorAll(".mp-input-cc-num").forEach((input) => {
        input.addEventListener("input", (e) => {
          input.value = cc.formatCardNumber(input.value);
        });
      });
      document.querySelectorAll(".mp-input-cc-exp").forEach((input) => {
        input.addEventListener("input", (e) => {
          input.value = cc.formatCardExpiry(input.value);
        });
      });
      // CVC: Nur Ziffern zulassen
      document.querySelectorAll(".mp-input-cc-cvc").forEach((input) => {
        input.addEventListener("input", (e) => {
          input.value = input.value.replace(/\D/g, "");
        });
      });
    });
  },
  /**
   * Init events related to toggling payment options
   *
   * @since 3.0
   * @access public
   */
  initPaymentOptionListeners: function () {
    // Vanilla JS: Payment-Optionen toggeln
    document
      .querySelectorAll('.mp_checkout_section input[name="payment_method"]')
      .forEach(function (input) {
        input.addEventListener("click", handlePaymentMethodChange);
        input.addEventListener("change", handlePaymentMethodChange);
      });
    function handlePaymentMethodChange(e) {
      var val = this.value;
      var target = document.getElementById("mp-gateway-form-" + val);
      var checkout = document.getElementById("mp-checkout-form");
      if (!checkout.classList.contains("last-step")) return;
      // Alle Gateway-Formulare ausblenden
      document.querySelectorAll(".mp_gateway_form").forEach(function (form) {
        form.style.display = "none";
      });
      // Aktuelles Gateway-Formular einblenden
      if (target) target.style.display = "";
      // Fehlerbehandlung und Button-Text (vereinfachte Vanilla-Version)
      var submit = checkout.querySelector(
        'button[type="submit"], input[type="submit"]',
      );
      if (!submit) return;
      if (!submit.dataset.mpOriginalHtml) {
        submit.dataset.mpOriginalHtml = submit.textContent;
      }
      if (this.getAttribute("data-mp-use-confirmation-step") === "true") {
        submit.textContent =
          submit.getAttribute("data-mp-alt-html") ||
          submit.dataset.mpOriginalHtml;
      } else {
        submit.textContent = submit.dataset.mpOriginalHtml;
      }
      // Fehleranzeige ggf. nachbauen
      mp_checkout.errorSummary && mp_checkout.errorSummary("hide");
    }
    this.initActivePaymentMethod && this.initActivePaymentMethod();
  },
  /**
   * Enable/disable shipping address fields
   *
   * @since 3.0
   */
  toggleShippingAddressFields: function () {
    var cb = document.querySelector('input[name="enable_shipping_address"]');
    var shippingInfo = document.getElementById("mp-checkout-column-shipping-info");
    var billingInfo = document.getElementById("mp-checkout-column-billing-info");
    if (!cb || !shippingInfo) return;
    if (cb.checked) {
      //billingInfo && billingInfo.classList.remove('fullwidth');
      setTimeout(function () {
        shippingInfo.style.display = '';
        shippingInfo.style.opacity = 1;
        shippingInfo.style.transition = 'opacity 0.5s';
      }, 550);
    } else {
      shippingInfo.style.transition = 'opacity 0.5s';
      shippingInfo.style.opacity = 0;
      setTimeout(function () {
        shippingInfo.style.display = 'none';
        //billingInfo && billingInfo.classList.add('fullwidth');
      }, 500);
    }
  },
  /**
   * Enable/disable registration fields
   *
   * @since 3.0
   */
  toggleRegistrationFields: function () {
    var cb = document.querySelector('input[name="enable_registration_form"]');
    var accountContainer = document.getElementById("mp-checkout-column-registration");
    if (!cb || !accountContainer) return;
    if (cb.checked) {
      accountContainer.style.display = '';
      accountContainer.style.opacity = 1;
      accountContainer.style.transition = 'opacity 0.5s';
    } else {
      accountContainer.style.transition = 'opacity 0.5s';
      accountContainer.style.opacity = 0;
      setTimeout(function () {
        accountContainer.style.display = 'none';
      }, 500);
    }
  },
  /**
   * Initialize events related to registration fields
   *
   * @since 3.0
   */
  initAccountRegistrationListeners: function () {
    var enableRegistration = document.querySelector(
      'input[name="enable_registration_form"]',
    );
    if (enableRegistration) {
      enableRegistration.addEventListener("change", function () {
        mp_checkout.toggleRegistrationFields &&
          mp_checkout.toggleRegistrationFields();
      });
    }
  },
  /**
   * Initialize events related to shipping address fields
   *
   * @since 3.0
   */
  initShippingAddressListeners: function () {
    var enableShippingAddress = document.querySelector(
      'input[name="enable_shipping_address"]',
    );
    if (enableShippingAddress) {
      enableShippingAddress.addEventListener("change", function () {
        mp_checkout.toggleShippingAddressFields &&
          mp_checkout.toggleShippingAddressFields();
      });
    }
    // Copy billing field to shipping field (if shipping address isn't enabled)
    var billingFields = document.querySelectorAll('[name^="billing["]');
    billingFields.forEach(function (billingField) {
      billingField.addEventListener("change", function (e) {
        if (enableShippingAddress && enableShippingAddress.checked) {
          return;
        }
        var name = billingField.getAttribute("name");
        var shippingField = document.querySelector(
          '[name="' + name.replace("billing", "shipping") + '"]',
        );
        if (!shippingField) return;
        shippingField.value = billingField.value;
        var event = new Event("change", { bubbles: true });
        shippingField.dispatchEvent(event);
      });
      billingField.addEventListener("keyup", function (e) {
        if (enableShippingAddress && enableShippingAddress.checked) {
          return;
        }
        var name = billingField.getAttribute("name");
        var shippingField = document.querySelector(
          '[name="' + name.replace("billing", "shipping") + '"]',
        );
        if (!shippingField) return;
        shippingField.value = billingField.value;
        var event = new Event("change", { bubbles: true });
        shippingField.dispatchEvent(event);
      });
    });
  },
  /**
   * Trigger step change event
   *
   * @since 3.0
   */
  triggerStepChange: function () {
    var current = document.querySelector('.mp_checkout_section.current');
    if (!current) return;
    var event = new CustomEvent('mp_checkout/step_changed', {
      detail: { outElem: current, inElem: current }
    });
    document.dispatchEvent(event);
  },
  /**
   * Because we have 2 context in login pharse, so we will have to determine which button click to add/removerules
   *
   */
  listenToLogin: function () {
    // Login-Button: Login-Formular absenden
    document
      .querySelectorAll(".mp_button-checkout-login")
      .forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          var form = btn.closest("form");
          if (!form) return;
          // Entferne evtl. vorhandenes #is_checkout_as_guest
          var guest = form.querySelector("#is_checkout_as_guest");
          if (guest) guest.remove();
          form.submit();
        });
      });
    // Gast-Button: als Gast fortfahren
    document.querySelectorAll(".mp_continue_as_guest").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        var form = btn.closest("form");
        if (!form) return;
        if (!form.querySelector("#is_checkout_as_guest")) {
          var input = document.createElement("input");
          input.id = "is_checkout_as_guest";
          input.type = "hidden";
          form.appendChild(input);
        }
        form.submit();
      });
    });
    // Enter-Taste im Formular deaktivieren
    var checkoutForm = document.getElementById("mp-checkout-form");
    if (checkoutForm) {
      checkoutForm.addEventListener("keyup", function (e) {
        if (e.key === "Enter" || e.keyCode === 13) {
          e.preventDefault();
          return false;
        }
      });
      checkoutForm.addEventListener("keypress", function (e) {
        if (e.key === "Enter" || e.keyCode === 13) {
          e.preventDefault();
          return false;
        }
      });
    }
  },
};

document.addEventListener("DOMContentLoaded", function () {
  mp_checkout.showForm && mp_checkout.showForm();
  mp_checkout.initListeners && mp_checkout.initListeners();
  mp_checkout.toggleShippingAddressFields && mp_checkout.toggleShippingAddressFields();
  mp_checkout.toggleRegistrationFields && mp_checkout.toggleRegistrationFields();
  mp_checkout.triggerStepChange && mp_checkout.triggerStepChange();

  // Pflichtfeld-Validierung direkt beim Laden der Seite und nach Autofill
  var checkoutForm = document.getElementById("mp-checkout-form");
  function validateRequiredFields() {
    if (checkoutForm) {
      checkoutForm.querySelectorAll('[required], .mp_field_required').forEach(function(input) {
        let el = input;
        if (el.tagName === 'SPAN' && el.classList.contains('mp_field_required')) {
          el = el.closest('.mp_checkout_field').querySelector('input, select, textarea');
        }
        let hint = null;
        if (el) {
          hint = el.closest('.mp_checkout_field')?.querySelector('.mp_field_required_hint');
        }
        if (el && !el.value) {
          el.classList.add('mp_form_input_error');
          if (!hint) {
            hint = document.createElement('span');
            hint.className = 'mp_field_required_hint';
            hint.style.color = 'red';
            hint.style.display = 'block';
            hint.style.fontSize = '0.9em';
            hint.textContent = 'Dieses Feld ist erforderlich';
            el.parentNode.appendChild(hint);
          } else {
            hint.style.display = 'block';
          }
        } else if (el) {
          el.classList.remove('mp_form_input_error');
          if (hint) {
            hint.style.display = 'none';
          }
        }
      });
    }
  }
  validateRequiredFields();
  // Nach jedem Input/Change/Autofill Pflichtfelder prüfen
  if (checkoutForm) {
    checkoutForm.querySelectorAll('input, select, textarea').forEach(function(input) {
      input.addEventListener('input', validateRequiredFields);
      input.addEventListener('change', validateRequiredFields);
      input.addEventListener('blur', validateRequiredFields);
    });
  }
  // Nach Seitenreload (z.B. durch Browser-Autofill)
  window.addEventListener('pageshow', validateRequiredFields);
});
