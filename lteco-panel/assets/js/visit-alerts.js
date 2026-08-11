(function () {
  "use strict";

  var config = window.LTECO_VISIT_ALERTS || {};
  if (!config.enabled || !config.endpoint) return;

  var storageKey = "lteco-last-visit-alert-id";
  var timer = null;
  var polling = false;

  function ensureIndicator() {
    var existing = document.querySelector("[data-visit-alert-indicator]");
    if (existing) return existing;

    var link = document.createElement("a");
    link.className = "visit-alert-indicator";
    link.href = config.alertsUrl || "#";
    link.dataset.visitAlertIndicator = "1";
    link.setAttribute("aria-label", "Ver alertas de visitas");
    link.title = "Alertas de visitas";
    link.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>' +
      '</svg>' +
      '<span class="visit-alert-indicator__count" data-visit-alert-count hidden>0</span>';
    document.body.appendChild(link);
    return link;
  }

  function updateIndicator(count) {
    var indicator = ensureIndicator();
    var badge = indicator.querySelector("[data-visit-alert-count]");
    badge.textContent = String(count || 0);
    badge.hidden = !count;
    indicator.classList.toggle("has-alerts", count > 0);
  }

  function closeModal(modal) {
    if (modal) modal.remove();
  }

  function showVisitModal(alert) {
    var old = document.querySelector(".visit-alert-modal-backdrop");
    if (old) old.remove();

    var backdrop = document.createElement("div");
    backdrop.className = "visit-alert-modal-backdrop";
    backdrop.innerHTML =
      '<div class="visit-alert-modal" role="dialog" aria-modal="true" aria-labelledby="visit-alert-modal-title">' +
        '<div class="visit-alert-modal__icon" aria-hidden="true">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>' +
        '</div>' +
        '<div class="visit-alert-modal__content">' +
          '<p class="eyebrow">Nueva visita</p>' +
          '<h2 id="visit-alert-modal-title"></h2>' +
          '<p data-visit-alert-body></p>' +
        '</div>' +
        '<div class="visit-alert-modal__actions">' +
          '<button type="button" class="btn-secondary" data-visit-alert-close>Cerrar</button>' +
          '<a class="btn" data-visit-alert-open>Ver agenda</a>' +
        '</div>' +
      '</div>';

    var pending = alert.kind === "visita_pendiente";
    backdrop.querySelector(".eyebrow").textContent = pending ? "Visita por confirmar" : "Nueva visita";
    backdrop.querySelector("#visit-alert-modal-title").textContent = alert.title || "Nueva visita al showroom";
    backdrop.querySelector("[data-visit-alert-body]").textContent = alert.body || "Se agendó una nueva visita.";
    backdrop.querySelector("[data-visit-alert-open]").textContent = pending ? "Ver alerta" : "Ver agenda";
    backdrop.querySelector("[data-visit-alert-open]").href = pending
      ? (config.alertsUrl || "#")
      : (config.agendaUrl || config.alertsUrl || "#");
    backdrop.querySelector("[data-visit-alert-close]").addEventListener("click", function () {
      closeModal(backdrop);
    });
    backdrop.addEventListener("click", function (event) {
      if (event.target === backdrop) closeModal(backdrop);
    });
    document.addEventListener("keydown", function escapeHandler(event) {
      if (event.key === "Escape") {
        closeModal(backdrop);
        document.removeEventListener("keydown", escapeHandler);
      }
    });
    document.body.appendChild(backdrop);
    backdrop.querySelector("[data-visit-alert-close]").focus();

    if ("Notification" in window && Notification.permission === "granted") {
      var notification = new Notification(alert.title || "Nueva visita al showroom", {
        body: alert.body || "Se agendó una nueva visita.",
        icon: "/lteco-panel/assets/icons/icon-192.png",
        badge: "/lteco-panel/assets/icons/icon-192.png",
        tag: "visit-alert-" + alert.id,
        renotify: true,
        requireInteraction: true,
        silent: false,
        vibrate: [300, 150, 300, 150, 500],
        timestamp: Date.now(),
      });
      notification.onclick = function () {
        window.focus();
        window.location.href = pending
          ? (config.alertsUrl || "#")
          : (config.agendaUrl || config.alertsUrl || "#");
      };
    }
  }

  function processPayload(payload) {
    updateIndicator(Number(payload.open_count || 0));
    if (!payload.latest || !payload.latest.id) return;

    var latestId = Number(payload.latest.id);
    var seenId = Number(localStorage.getItem(storageKey) || 0);
    if (latestId <= seenId) return;

    localStorage.setItem(storageKey, String(latestId));
    showVisitModal(payload.latest);
  }

  async function poll() {
    if (polling || document.hidden) return;
    polling = true;
    try {
      var response = await fetch(config.endpoint, {
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      });
      if (response.status === 401 || response.status === 403) {
        if (timer) window.clearInterval(timer);
        return;
      }
      if (!response.ok) return;
      processPayload(await response.json());
    } catch (error) {
      // El próximo sondeo vuelve a intentar sin interrumpir el panel.
    } finally {
      polling = false;
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    ensureIndicator();
    poll();
    timer = window.setInterval(poll, 15000);
  });
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) poll();
  });
})();
