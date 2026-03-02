/* eslint-disable no-undef */

/**
 * Firebase Cloud Messaging Service Worker
 * 
 * Este archivo maneja:
 * - Notificaciones en segundo plano
 * - Clicks en notificaciones
 * - Ciclo de vida del Service Worker
 */

console.log('[SW] Service Worker script loaded and active');

/**
 * Lifecycle: Install
 * Ocurre cuando el Service Worker se instala por primera vez
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Service Worker installing');
    // skipWaiting permite que el nuevo SW tome control inmediatamente
    self.skipWaiting();
});

/**
 * Lifecycle: Activate
 * Ocurre cuando el navegador está usando este Service Worker
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Service Worker activating');
    // claim() permite que este SW controle todos los clientes
    event.waitUntil(self.clients.claim());
});

/**
 * Push Notification Handler
 * Recibe notificaciones push en segundo plano
 */
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received:', event);

    if (!event.data) {
        console.warn('[SW] Push event without data');
        return;
    }

    let notificationData = {};

    try {
        notificationData = event.data.json();
    } catch (jsonError) {
        const rawText = event.data.text();

        try {
            notificationData = JSON.parse(rawText);
        } catch (textError) {
            console.warn('[SW] Failed to parse push data, using text payload:', rawText);
            notificationData = {
                title: 'Nueva notificación',
                body: rawText,
            };
        }
    }

    const options = {
        body: notificationData.body || notificationData.notification?.body || '',
        icon: '/pwa-192x192.png',
        badge: '/pwa-192x192.png',
        tag: 'fcm-notification',
        data: notificationData.data || notificationData || {},
        actions: [
            {
                action: 'open',
                title: 'Abrir',
            },
        ],
    };

    event.waitUntil(
        self.registration.showNotification(
            notificationData.title || notificationData.notification?.title || 'Nueva notificación',
            options
        ).then(() => {
            console.log('[SW] Notification displayed successfully');
        }).catch((error) => {
            console.error('[SW] Failed to display notification:', error);
        })
    );
});

/**
 * Notification Click Handler
 * Se dispara cuando el usuario hace clic en una notificación
 */
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.notification.title);
    
    // Cerrar la notificación
    event.notification.close();

    // Acciones específicas según qué botón se clickeó
    if (event.action === 'open' || event.action === '') {
        console.log('[SW] Opening or focusing main window');

        const targetPath = event.notification?.data?.url || '/';
        const targetUrl = new URL(targetPath, self.location.origin).href;
        
        // Esperar a que los clientes se abran o enfoquen
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
                console.log('[SW] Found', clientList.length, 'clients');
                
                // Buscar un cliente ya abierto
                for (const client of clientList) {
                    if (client.url === targetUrl) {
                        console.log('[SW] Focusing existing client');
                        client.focus();
                        return;
                    }
                }
                
                // Si no hay cliente abierto, abrir uno nuevo
                if (self.clients.openWindow) {
                    console.log('[SW] Opening new window');
                    return self.clients.openWindow(targetUrl);
                }
            })
        );
    }
});

/**
 * Message Handler
 * Recibe mensajes del cliente (main app)
 */
self.addEventListener('message', (event) => {
    console.log('[SW] Message received from client:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        console.log('[SW] SKIP_WAITING message received, skipping waiting state');
        self.skipWaiting();
    }
});

console.log('[SW] Service Worker fully initialized');


