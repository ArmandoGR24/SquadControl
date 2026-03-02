import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';
import { saveFCMToken, saveFCMTokenForced, retryPendingFCMToken } from './composables/useFCMToken';
import Toast, { PluginOptions, POSITION } from "vue-toastification";
import "vue-toastification/dist/index.css";
import {
    initializeFirebaseAnalytics,
    initializeFirebaseMessaging,
    onForegroundFirebaseMessage,
    refreshFirebaseMessagingToken,
} from './lib/firebase';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const options: PluginOptions = {
    position: POSITION.TOP_RIGHT,
    timeout: 3000,
    closeOnClick: true,
    pauseOnFocusLoss: false,
    pauseOnHover: true,
    draggable: true,
    draggablePercent: 0.6,
    showCloseButtonOnHover: false,
    hideProgressBar: false,
    closeButton: "button",
    icon: true,
    rtl: false
};


createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(Toast, options)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

initializeFirebaseAnalytics().catch((error) => {
    console.error('Firebase Analytics initialization failed:', error);
});

// Inicializar Firebase Messaging
(async () => {
    try {
        console.log('[App] Starting Firebase Messaging initialization...');
        
        const result = await initializeFirebaseMessaging();
        
        if (!result?.token) {
            console.warn('[App] No FCM token generated');
            return;
        }

        console.log('✅ Firebase messaging token generated:', result.token);

        // Esperar a que la página esté completamente cargada
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Intentar guardar el token
        const saved = await saveFCMToken(result.token);
        
        if (saved) {
            console.log('✅ FCM token saved successfully');
            localStorage.setItem('fcm_token', result.token);
        } else {
            console.warn('⚠️ FCM token could not be saved, will retry after navigation');
        }
    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : String(error);
        console.error('❌ Firebase Messaging initialization failed:', errorMessage);
        
        // No bloquear la app si FCM falla
        console.log('[App] Continuing without FCM');
    }
})();

// Listener para reintentar guardar token FCM después de navegación
// Útil cuando el usuario hace login
router.on('finish', (event) => {
    // Reintentar guardar token pendiente si hay uno
    retryPendingFCMToken();

    // Además, forzar sincronización de token en cada navegación autenticada.
    // Esto cubre casos donde no existía pendingToken pero el login sí ocurrió.
    setTimeout(async () => {
        try {
            const result = await refreshFirebaseMessagingToken();

            if (!result?.token) {
                return;
            }

            const previousToken = localStorage.getItem('fcm_token');
            await saveFCMTokenForced(result.token, true, result.previousToken ?? previousToken);
            localStorage.setItem('fcm_token', result.token);
        } catch (error) {
            console.warn('[App] Post-navigation FCM sync failed:', error);
        }
    }, 800);
});

onForegroundFirebaseMessage((payload) => {
    console.info('Foreground message received:', payload);

    // Mostrar notificación en pantalla con Toast
    const title = payload.notification?.title || 'Nueva notificación';
    const body = payload.notification?.body || '';

    if (typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'granted') {
        try {
            const targetUrl = payload.data?.url || '/';
            const notification = new Notification(title, {
                body,
                icon: '/pwa-192x192.png',
                badge: '/pwa-192x192.png',
            });

            notification.onclick = () => {
                window.focus();
                window.location.href = String(targetUrl);
                notification.close();
            };
        } catch (error) {
            console.warn('No se pudo mostrar notificación nativa:', error);
        }
    }

    console.info(`[FCM Toast] ${title}: ${body}`);
});
