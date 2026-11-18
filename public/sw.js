/*
 * KIINI SAWA — service worker de l'application installable.
 * - Pages : toujours depuis le réseau (données personnelles, jamais mises en cache) ; hors connexion : le mode rapide (/rapide, qui
 *   lit la copie gardée sur le téléphone) pour les écrans du quotidien, sinon la page « hors ligne ».
 * - Fichiers compilés (/assets/…, noms versionnés) et icônes : mis en cache pour un chargement rapide.
 */
const VERSION = 'ks-v3';
const OFFLINE_URL = '/hors-ligne';
// « Aujourd'hui en un geste » : la coquille (sans donnée personnelle) est gardée pour fonctionner sans réseau
const QUICK_URL = '/rapide';
const QUICK_FALLBACK = ['/dashboard', '/mes-plans/rituels', '/journal', '/bilan/du-jour', QUICK_URL];
const PRECACHE = [OFFLINE_URL, QUICK_URL, '/icons/icon-192.png', '/icons/apple-touch-icon.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Navigation : réseau d'abord ; sans réseau, le mode rapide pour les écrans du quotidien, sinon la page hors ligne
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).then((response) => {
        // La coquille du mode rapide est rafraîchie à chaque visite (elle ne contient aucune donnée personnelle)
        if (url.pathname === QUICK_URL && response.ok) {
          const copy = response.clone();
          caches.open(VERSION).then((cache) => cache.put(QUICK_URL, copy));
        }
        return response;
      }).catch(() => caches.match(QUICK_FALLBACK.includes(url.pathname) ? QUICK_URL : OFFLINE_URL).then((r) => r || caches.match(OFFLINE_URL)))
    );
    return;
  }

  // Fichiers statiques versionnés : cache d'abord
  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/lib/')) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(VERSION).then((cache) => cache.put(request, copy));
        }
        return response;
      }))
    );
  }
});

// Notifications sur le téléphone
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'KIINI SAWA', body: event.data ? event.data.text() : '' }; }
  event.waitUntil(self.registration.showNotification(data.title || 'KIINI SAWA', {
    body: data.body || '',
    icon: '/icons/icon-192.png',
    badge: '/icons/favicon-32.png',
    tag: data.tag || undefined,
    data: { url: data.url || '/dashboard' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = new URL((event.notification.data && event.notification.data.url) || '/dashboard', self.location.origin).href;
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
    for (const w of wins) { if (w.url === url && 'focus' in w) return w.focus(); }
    return self.clients.openWindow(url);
  }));
});
