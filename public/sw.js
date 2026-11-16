/*
 * KIINI SAWA — service worker de l'application installable.
 * - Pages : toujours depuis le réseau (données personnelles, jamais mises en cache) ; hors connexion : page « hors ligne ».
 * - Fichiers compilés (/assets/…, noms versionnés) et icônes : mis en cache pour un chargement rapide.
 */
const VERSION = 'ks-v1';
const OFFLINE_URL = '/hors-ligne';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/apple-touch-icon.png'];

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

  // Navigation : réseau d'abord, page hors ligne si pas de connexion
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
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
