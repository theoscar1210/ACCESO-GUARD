// ACCESO·GUARD Service Worker

const CACHE_NAME = 'acceso-guard-v1';
const OFFLINE_URL = '/offline';

// Install
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

// Activate
self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

// Push notifications
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let data;
    try {
        data = event.data.json();
    } catch (_) {
        data = { title: 'ACCESO·GUARD', body: event.data.text() };
    }

    const options = {
        body: data.body ?? '',
        icon: '/icon-192.png',
        badge: '/badge-72.png',
        vibrate: [100, 50, 100],
        // El servidor envía la URL en data.url (WebPushMessage) o en url
        data: { url: data.url ?? data.data?.url ?? '/' },
        actions: [
            { action: 'open', title: 'Ver' },
            { action: 'close', title: 'Cerrar' },
        ],
    };

    event.waitUntil(
        self.registration.showNotification(data.title ?? 'ACCESO·GUARD', options)
    );
});

// Notification click
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'close') return;

    const url = event.notification.data?.url ?? '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
