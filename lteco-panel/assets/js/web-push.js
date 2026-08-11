(function () {
  "use strict";

  var config = window.LTECO_WEB_PUSH || {};
  var settings = document.querySelector("[data-web-push-settings]");
  var supported = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
  var vibrationPattern = [300, 150, 300, 150, 500];

  if (!config.enabled || !config.publicKey || !supported) {
    if (settings) {
      var unavailable = document.querySelector("#web-push-status");
      if (unavailable) unavailable.textContent = !supported
        ? "Este navegador no admite Web Push. Probá con Chrome en Android o una PWA instalada en iPhone/iPad."
        : "Web Push no está habilitado en el servidor.";
      var activateUnavailable = document.querySelector("[data-web-push-activate]");
      if (activateUnavailable) activateUnavailable.disabled = true;
    }
    return;
  }

  function decodeKey(value) {
    var padding = "=".repeat((4 - (value.length % 4)) % 4);
    var raw = atob((value + padding).replace(/-/g, "+").replace(/_/g, "/"));
    return Uint8Array.from(raw, function (character) { return character.charCodeAt(0); });
  }

  function setStatus(message, kind) {
    var node = document.querySelector("#web-push-status");
    if (node) {
      node.textContent = message;
      node.className = "notice notice--" + (kind || "info");
    }
  }

  function attentionPulse() {
    if ("vibrate" in navigator) {
      navigator.vibrate(vibrationPattern);
    }
  }

  async function save(subscription) {
    var response = await fetch(config.subscribeUrl, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": config.csrfToken },
      body: JSON.stringify(subscription.toJSON())
    });
    var data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || "No se pudo guardar la suscripción.");
  }

  async function remove(subscription) {
    var response = await fetch(config.unsubscribeUrl, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": config.csrfToken },
      body: JSON.stringify({ endpoint: subscription.endpoint })
    });
    var data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || "No se pudo desactivar la suscripción.");
    await subscription.unsubscribe();
  }

  async function localTest(registration) {
    attentionPulse();
    await registration.showNotification("Notificaciones ERP activadas", {
      body: "Este dispositivo quedó vinculado al panel.",
      icon: "/lteco-panel/assets/icons/icon-192.png",
      badge: "/lteco-panel/assets/icons/icon-192.png",
      tag: "push-test-local-" + Date.now(),
      data: { url: "/lteco-panel/configuracion/notificaciones.php" },
      renotify: true,
      requireInteraction: true,
      silent: false,
      vibrate: vibrationPattern,
      timestamp: Date.now()
    });
  }

  async function serverTest() {
    attentionPulse();
    var response = await fetch(config.testUrl, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": config.csrfToken },
      body: "{}"
    });
    var data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || "No se pudo enviar la prueba.");
  }

  function ensureButton() {
    var button = document.querySelector("[data-web-push-toggle]");
    if (button) return button;
    button = document.createElement("button");
    button.type = "button";
    button.className = "web-push-toggle";
    button.dataset.webPushToggle = "1";
    button.title = "Activar notificaciones en este teléfono";
    button.setAttribute("aria-label", button.title);
    button.textContent = "🔔";
    document.body.appendChild(button);
    return button;
  }

  document.addEventListener("DOMContentLoaded", async function () {
    try {
      var registration = await navigator.serviceWorker.ready;
      var subscription = await registration.pushManager.getSubscription();
      var activate = document.querySelector("[data-web-push-activate]");
      var deactivate = document.querySelector("[data-web-push-deactivate]");
      var test = document.querySelector("[data-web-push-test]");
      var help = document.querySelector("[data-web-push-help]");
      var toggle = settings ? null : ensureButton();

      function update(active) {
        if (activate) activate.hidden = active;
        if (deactivate) deactivate.hidden = !active;
        if (test) test.disabled = !active;
        if (toggle) { var button = toggle; button.hidden = active; }
        if (help) help.textContent = active ? "Este dispositivo está activo. Podés enviar una prueba o desactivarlo." : "El permiso se solicita únicamente al tocar Activar notificaciones.";
        setStatus(active ? "Este dispositivo está suscrito." : "Este dispositivo todavía no está suscrito.", active ? "success" : "info");
      }

      update(Boolean(subscription));

      async function activatePush() {
        var permission = await Notification.requestPermission();
        if (permission !== "granted") throw new Error(permission === "denied" ? "El permiso está bloqueado. Habilitalo desde los ajustes del navegador." : "No se otorgó permiso para notificaciones.");
        subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeKey(config.publicKey) });
        await save(subscription);
        update(true);
        await localTest(registration);
      }

      async function deactivatePush() {
        if (!subscription) return;
        await remove(subscription);
        subscription = null;
        update(false);
      }

      if (activate) activate.addEventListener("click", function () { activatePush().catch(function (error) { setStatus(error.message, "error"); }); });
      if (deactivate) deactivate.addEventListener("click", function () { deactivatePush().catch(function (error) { setStatus(error.message, "error"); }); });
      if (test) test.addEventListener("click", function () { test.disabled = true; serverTest().then(function () { setStatus("Prueba enviada. Si Android no emite sonido, revisá la importancia del canal de Chrome/ERP.", "success"); }).catch(function (error) { setStatus(error.message, "error"); }).finally(function () { test.disabled = false; }); });
      if (toggle) toggle.addEventListener("click", function () { (subscription ? localTest(registration) : activatePush()).catch(function (error) { window.alert(error.message); }); });
    } catch (error) {
      setStatus("No se pudo inicializar el registro de notificaciones.", "error");
    }
  });
})();
