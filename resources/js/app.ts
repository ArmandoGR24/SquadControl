import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import type { PluginOptions} from "vue-toastification";
import Toast, { POSITION } from "vue-toastification";
import { initializeTheme } from './composables/useAppearance';
import { saveFCMToken, saveFCMTokenForced, retryPendingFCMToken } from './composables/useFCMToken';
import "vue-toastification/dist/index.css";
import {
    initializeFirebaseAnalytics,
    initializeFirebaseMessaging,
    onForegroundFirebaseMessage,
    refreshFirebaseMessagingToken,
} from './lib/firebase';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const setupPullToRefresh = () => {
    if (typeof window === 'undefined') return;

    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    if (!isTouchDevice) return;

    let startY = 0;
    let pullDistance = 0;
    let isPulling = false;
    let shouldRefresh = false;
    let isRefreshing = false;
    let hapticTriggered = false;
    let activeScrollContainer: HTMLElement | null = null;

    const minPullDistance = 55;
    const maxIndicatorTravel = 88;

    const indicator = document.createElement('div');
    indicator.className = 'pull-refresh-indicator';
    indicator.setAttribute('aria-hidden', 'true');
    document.body.appendChild(indicator);

    const setIndicatorState = (distance: number, armed: boolean) => {
        const clamped = Math.max(0, Math.min(distance, maxIndicatorTravel));
        const opacity = Math.min(1, clamped / minPullDistance);

        indicator.style.setProperty('--pull-distance', `${clamped}px`);
        indicator.style.setProperty('--pull-opacity', opacity.toString());
        indicator.classList.toggle('is-visible', clamped > 0);
        indicator.classList.toggle('is-armed', armed);
    };

    const resetIndicator = () => {
        indicator.style.setProperty('--pull-distance', '0px');
        indicator.style.setProperty('--pull-opacity', '0');
        indicator.classList.remove('is-visible', 'is-armed', 'is-refreshing');
    };

    const getScrollableAncestor = (node: HTMLElement | null): HTMLElement | null => {
        let current = node;

        while (current && current !== document.body) {
            const style = window.getComputedStyle(current);
            const overflowY = style.overflowY;
            const canScroll = /(auto|scroll|overlay)/.test(overflowY);

            if (canScroll && current.scrollHeight > current.clientHeight) {
                return current;
            }

            current = current.parentElement;
        }

        return null;
    };

    const canStartPull = (event: TouchEvent) => {
        if (event.touches.length !== 1) return false;

        const target = event.target as HTMLElement | null;
        if (
            target?.closest(
                'input, textarea, select, [contenteditable="true"], video, iframe, [data-no-pull-refresh="true"]',
            )
        ) {
            return false;
        }

        activeScrollContainer = getScrollableAncestor(target);

        if (activeScrollContainer) {
            return activeScrollContainer.scrollTop <= 0;
        }

        return window.scrollY <= 0;
    };

    const onTouchStart = (event: TouchEvent) => {
        isPulling = false;
        shouldRefresh = false;
        hapticTriggered = false;
        pullDistance = 0;
        activeScrollContainer = null;
        resetIndicator();

        if (!canStartPull(event)) return;

        startY = event.touches[0].clientY;
        isPulling = true;
    };

    const onTouchMove = (event: TouchEvent) => {
        if (!isPulling) return;

        if (activeScrollContainer && activeScrollContainer.scrollTop > 0) {
            isPulling = false;
            shouldRefresh = false;
            return;
        }

        if (!activeScrollContainer && window.scrollY > 0) {
            isPulling = false;
            shouldRefresh = false;
            return;
        }

        const currentY = event.touches[0]?.clientY ?? startY;
        pullDistance = currentY - startY;

        if (pullDistance <= 0) {
            shouldRefresh = false;
            setIndicatorState(0, false);
            return;
        }

        shouldRefresh = pullDistance >= minPullDistance;
        setIndicatorState(pullDistance, shouldRefresh);

        if (shouldRefresh && !hapticTriggered && typeof navigator !== 'undefined' && 'vibrate' in navigator) {
            navigator.vibrate(10);
            hapticTriggered = true;
        }

        if (!shouldRefresh) {
            hapticTriggered = false;
        }

        event.preventDefault();
    };

    const onTouchEnd = () => {
        if (!isPulling) return;

        if (shouldRefresh && !isRefreshing) {
            isRefreshing = true;
            indicator.classList.add('is-refreshing');
            router.reload({
                onFinish: () => {
                    isRefreshing = false;
                    resetIndicator();
                },
            });
        } else {
            resetIndicator();
        }

        isPulling = false;
        shouldRefresh = false;
        hapticTriggered = false;
        pullDistance = 0;
        activeScrollContainer = null;
    };

    window.addEventListener('touchstart', onTouchStart, { passive: true });
    window.addEventListener('touchmove', onTouchMove, { passive: false });
    window.addEventListener('touchend', onTouchEnd, { passive: true });
    window.addEventListener('touchcancel', onTouchEnd, { passive: true });
};

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
setupPullToRefresh();

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
router.on('finish', () => {
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
