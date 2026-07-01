/**
 * sw.js — DersPROS Service Worker (Web Push + PWA)
 * Kapsam: /
 */

const CACHE_NAME = 'derspros-v1';

// ── Kurulum ─────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

// ── Push Bildirim Alma ───────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'DersPROS', body: event.data ? event.data.text() : '' };
    }

    const title   = data.title   || 'DersPROS';
    const options = {
        body:    data.body    || 'Yeni bir bildiriminiz var.',
        icon:    data.icon    || '/assets/images/favicon.png',
        badge:   data.badge   || '/assets/images/favicon.png',
        tag:     data.tag     || 'derspros-' + Date.now(),
        renotify: data.renotify || false,
        requireInteraction: data.requireInteraction || false,
        data:    { url: data.url || '/' }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ── Bildirime Tıklama ────────────────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Zaten açık sekme varsa ona odaklan
            for (const client of windowClients) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            // Yoksa yeni sekme aç
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// ── Push Abonelik Değişikliği (tarayıcı tarafından yenileme) ─────────────────
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        self.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: event.oldSubscription
                ? event.oldSubscription.options.applicationServerKey
                : null
        }).then((newSubscription) => {
            // Yeni aboneliği backend'e gönder
            return fetch('/ajax/push_subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subscription: newSubscription.toJSON(), action: 'resubscribe' })
            });
        }).catch(() => {})
    );
});
