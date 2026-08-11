/*  */(function () {
  "use strict";

  function ready(fn) {
    if (document.body) {
      fn();
      return;
    }

    document.addEventListener("DOMContentLoaded", fn, { once: true });
  }

  function closeAlert(backdrop) {
    if (!backdrop) return;
    backdrop.remove();
  }

  function ltecoAlert(message, title) {
    var text = String(message || "");
    var cleanTitle = title || "Operación realizada";
    var normalizedTitle = cleanTitle.toLowerCase();
    var tone = /atenci[oó]n|advertencia|warning/.test(normalizedTitle)
      ? "warning"
      : /error|fall[oó]|problema/.test(normalizedTitle)
        ? "error"
        : "success";

    ready(function () {
      var old = document.querySelector(".lteco-alert-backdrop");
      if (old) old.remove();

      var backdrop = document.createElement("div");
      backdrop.className = "lteco-alert-backdrop";
      backdrop.setAttribute("role", "presentation");

      backdrop.innerHTML =
        '<div class="lteco-alert" role="dialog" aria-modal="true" aria-labelledby="lteco-alert-title">' +
          '<div class="lteco-alert__head">' +
            '<div class="lteco-alert__icon" aria-hidden="true">✓</div>' +
            '<h2 class="lteco-alert__title" id="lteco-alert-title"></h2>' +
          '</div>' +
          '<div class="lteco-alert__body"></div>' +
          '<div class="lteco-alert__actions">' +
            '<button type="button" class="lteco-alert__button">Aceptar</button>' +
          '</div>' +
        '</div>';

      backdrop.querySelector(".lteco-alert__title").textContent = cleanTitle;
      backdrop.querySelector(".lteco-alert__body").textContent = text;
      backdrop.querySelector(".lteco-alert").classList.add("lteco-alert--" + tone);
      backdrop.querySelector(".lteco-alert__icon").textContent = tone === "success" ? "✓" : "!";

      var button = backdrop.querySelector(".lteco-alert__button");

      button.addEventListener("click", function () {
        closeAlert(backdrop);
      });

      backdrop.addEventListener("click", function (event) {
        if (event.target === backdrop) closeAlert(backdrop);
      });

      document.addEventListener("keydown", function escHandler(event) {
        if (event.key === "Escape") {
          closeAlert(backdrop);
          document.removeEventListener("keydown", escHandler);
        }
      });

      document.body.appendChild(backdrop);
      button.focus();
    });
  }

  window.ltecoAlert = ltecoAlert;

  // Reemplaza alert() nativo por modal propio.
  window.alert = function (message) {
    ltecoAlert(message, "Operación realizada");
  };
})();

/* === LTECOBIKE UI CONFIRM HANDLER === */
(function () {
  "use strict";

  function extractConfirmMessage(value) {
    if (!value) return "¿Confirmás esta acción?";

    var match = String(value).match(/confirm\s*\(\s*(['"])([\s\S]*?)\1\s*\)/);
    if (!match) return "¿Confirmás esta acción?";

    return match[2]
      .replace(/\\n/g, "\n")
      .replace(/\\"/g, '"')
      .replace(/\\'/g, "'");
  }

  function closeConfirm(backdrop) {
    if (backdrop) backdrop.remove();
  }

  function showConfirm(message, onAccept) {
    var old = document.querySelector(".lteco-confirm-backdrop");
    if (old) old.remove();

    var backdrop = document.createElement("div");
    backdrop.className = "lteco-confirm-backdrop";

    backdrop.innerHTML =
      '<div class="lteco-confirm" role="dialog" aria-modal="true" aria-labelledby="lteco-confirm-title">' +
        '<div class="lteco-confirm__head">' +
          '<div class="lteco-confirm__icon" aria-hidden="true">!</div>' +
          '<h2 class="lteco-confirm__title" id="lteco-confirm-title">Confirmar acción</h2>' +
        '</div>' +
        '<div class="lteco-confirm__body"></div>' +
        '<div class="lteco-confirm__actions">' +
          '<button type="button" class="lteco-confirm__cancel">Cancelar</button>' +
          '<button type="button" class="lteco-confirm__accept">Confirmar</button>' +
        '</div>' +
      '</div>';

    backdrop.querySelector(".lteco-confirm__body").textContent = message;

    var cancel = backdrop.querySelector(".lteco-confirm__cancel");
    var accept = backdrop.querySelector(".lteco-confirm__accept");

    cancel.addEventListener("click", function () {
      closeConfirm(backdrop);
    });

    accept.addEventListener("click", function () {
      closeConfirm(backdrop);
      onAccept();
    });

    backdrop.addEventListener("click", function (event) {
      if (event.target === backdrop) closeConfirm(backdrop);
    });

    document.addEventListener("keydown", function escHandler(event) {
      if (event.key === "Escape") {
        closeConfirm(backdrop);
        document.removeEventListener("keydown", escHandler);
      }
    });

    document.body.appendChild(backdrop);
    cancel.focus();
  }

  function enhanceConfirmForms() {
    document.querySelectorAll("form[onsubmit*='confirm'], form[onsubmit*=\"confirm\"]").forEach(function (form) {
      if (form.dataset.ltecoConfirmEnhanced === "1") return;

      var raw = form.getAttribute("onsubmit") || "";
      var message = extractConfirmMessage(raw);

      form.removeAttribute("onsubmit");
      form.dataset.ltecoConfirmEnhanced = "1";

      form.addEventListener("submit", function (event) {
        if (form.dataset.ltecoConfirmed === "1") {
          return;
        }

        event.preventDefault();

        showConfirm(message, function () {
          form.dataset.ltecoConfirmed = "1";
          if (typeof form.requestSubmit === "function") {
          form.requestSubmit();
        } else {
          HTMLFormElement.prototype.submit.call(form);
        }
        });
      });
    });
  }

  document.addEventListener("DOMContentLoaded", enhanceConfirmForms);
})();


/* === LTECOBIKE ANTI DOUBLE SUBMIT V2 === */
(function () {
  "use strict";

  function setSubmittingState(form) {
    var buttons = form.querySelectorAll("button[type='submit'], input[type='submit']");

    buttons.forEach(function (button) {
      if (button.dataset.ltecoOriginalText) return;

      if (button.tagName === "INPUT") {
        button.dataset.ltecoOriginalText = button.value || "Confirmar";
        button.value = "Procesando...";
      } else {
        button.dataset.ltecoOriginalText = button.textContent || "Confirmar";
        button.textContent = "Procesando...";
      }

      button.disabled = true;
      button.classList.add("is-loading");
      button.setAttribute("aria-busy", "true");
    });
  }

  function preserveSubmitterValue(form) {
    var submitter = form.__ltecoSubmitter;
    if (!submitter || !submitter.name) return;

    var hidden = form.querySelector("input[type='hidden'][data-lteco-submitter-value='1']");
    if (!hidden) {
      hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.dataset.ltecoSubmitterValue = "1";
      form.appendChild(hidden);
    }

    hidden.name = submitter.name;
    hidden.value = submitter.value;
  }

  // Guarda qué botón se tocó, útil para formularios con varios submit.
  document.addEventListener("click", function (event) {
    var button = event.target.closest("button[type='submit'], input[type='submit']");
    if (!button) return;

    var form = button.form;
    if (!form) return;

    form.__ltecoSubmitter = button;
  }, true);

  document.addEventListener("submit", function (event) {
    var form = event.target;

    if (!(form instanceof HTMLFormElement)) return;

    form.__ltecoSubmitter = event.submitter || form.__ltecoSubmitter;

    // Si el form usa confirm visual y todavía no fue confirmado,
    // no tocamos nada. Lo maneja el modal.
    if (form.dataset.ltecoConfirmEnhanced === "1" && form.dataset.ltecoConfirmed !== "1") {
      return;
    }

    // Si ya está enviando, bloquea doble click o doble enter.
    if (form.dataset.ltecoSubmitting === "1") {
      event.preventDefault();
      event.stopImmediatePropagation();
      return;
    }

    form.dataset.ltecoSubmitting = "1";
    preserveSubmitterValue(form);
    setSubmittingState(form);
  }, true);
})();
