const CACHE_NAME = 'ltecobike-panel-pwa-v3-push-attention';

const DEFAULT_ICON = '/lteco-panel/assets/icons/icon-192.png';
const DEFAULT_BADGE = '/lteco-panel/assets/icons/icon-192.png';
const DEFAULT_VIBRATION = [300, 150, 300, 150, 500];

function internalTarget(value) {
  try {
    const target = new URL(value || '/lteco-panel/inicio.php', self.location.origin);
    if (target.origin !== self.location.origin || !target.pathname.startsWith('/lteco-panel/')) {
      return new URL('/lteco-panel/inicio.php', self.location.origin).href;
    }
    return target.href;
  } catch (error) {
    return new URL('/lteco-panel/inicio.php', self.location.origin).href;
  }
}

function normalizeVibration(value) {
  if (!Array.isArray(value) || value.length === 0) return DEFAULT_VIBRATION;
  const pattern = value
    .map(item => Number(item))
    .filter(item => Number.isFinite(item) && item >= 0 && item <= 1000)
    .slice(0, 10);
  return pattern.length > 0 ? pattern : DEFAULT_VIBRATION;
}

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(key => key !== CACHE_NAME)
          .map(key => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});

self.addEventListener('push', event => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (error) {
    payload = { title: 'ERP', body: event.data ? event.data.text() : 'Nueva alerta' };
  }
  if (!payload || typeof payload !== 'object') payload = {};

  const title = String(payload.title || 'ERP').trim() || 'ERP';
  const body = String(payload.body || 'Nueva alerta del panel.').trim() || 'Nueva alerta del panel.';
  const target = internalTarget(payload.url);
  const timestamp = Number.isFinite(Number(payload.timestamp)) ? Number(payload.timestamp) : Date.now();

  event.waitUntil(self.registration.showNotification(title, {
    body,
    icon: payload.icon || DEFAULT_ICON,
    badge: payload.badge || DEFAULT_BADGE,
    tag: payload.tag || ('lteco-alert-' + timestamp),
    renotify: payload.renotify !== false,
    requireInteraction: payload.requireInteraction !== false,
    silent: payload.silent === true,
    vibrate: normalizeVibration(payload.vibrate),
    timestamp,
    data: {
      url: target,
      order_id: payload.order_id || null,
      pedidoId: payload.pedidoId || payload.order_id || null,
      receivedAt: Date.now(),
    },
  }));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = internalTarget(event.notification.data && event.notification.data.url);
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windows => {
      for (const client of windows) {
        if ('focus' in client) {
          return Promise.resolve(client.navigate(target)).then(() => client.focus());
        }
      }
      return clients.openWindow ? clients.openWindow(target) : undefined;
    })
  );
});
