// @ts-nocheck
// Service worker globals (self, ServiceWorkerGlobalScope, PushEvent, NotificationEvent) conflict
// with the DOM lib used by the rest of this project's tsconfig, so this file is intentionally
// excluded from type checking. Vite/esbuild still transpiles it normally at build time.
import { clientsClaim } from 'workbox-core';
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

self.skipWaiting();
clientsClaim();

cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : { title: 'Payroll Portal' };

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: payload.icon || '/pwa-192x192.png',
            data: payload.data,
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            const existing = clientList.find((client) => new URL(client.url).pathname === url);

            if (existing) {
                return existing.focus();
            }

            return self.clients.openWindow(url);
        }),
    );
});
