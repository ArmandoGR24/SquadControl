import { getAnalytics, isSupported as isAnalyticsSupported } from 'firebase/analytics';
import { initializeApp } from 'firebase/app';
import {
    deleteToken,
    getMessaging,
    getToken,
    isSupported as isMessagingSupported,
    onMessage,
    type MessagePayload,
} from 'firebase/messaging';

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY || 'AIzaSyC0B2aOfGeyaf37TiMKtBEODg3bEh7bD_M',
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || 'squadcontrol-b5ab2.firebaseapp.com',
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID || 'squadcontrol-b5ab2',
    storageBucket:
        import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || 'squadcontrol-b5ab2.firebasestorage.app',
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || '769683780186',
    appId: import.meta.env.VITE_FIREBASE_APP_ID || '1:769683780186:web:2b0053017dd242ccbe1e63',
    measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID || 'G-NWH770HVBK',
};

let appInstance: ReturnType<typeof initializeApp> | null = null;

function getFirebaseApp() {
    if (!appInstance) {
        appInstance = initializeApp(firebaseConfig);
    }

    return appInstance;
}

export async function initializeFirebaseAnalytics() {
    const app = getFirebaseApp();

    if (!app) {
        return null;
    }

    if (!(await isAnalyticsSupported())) {
        return null;
    }

    return getAnalytics(app);
}

export async function initializeFirebaseMessaging() {
    const app = getFirebaseApp();

    if (!app) {
        return null;
    }

    if (typeof window === 'undefined' || !('Notification' in window)) {
        return null;
    }

    if (!(await isMessagingSupported()) || !('serviceWorker' in navigator)) {
        return null;
    }

    const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;

    if (!vapidKey) {
        console.error('[Firebase] VAPID key not found in environment');
        return null;
    }

    console.log('[Firebase] VAPID key available:', vapidKey.substring(0, 20) + '...');

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        console.warn('[Firebase] Notification permission not granted:', permission);
        return null;
    }

    console.log('[Firebase] Notifications permission granted');

    let registration: ServiceWorkerRegistration;
    
    try {
        console.log('[Firebase] Registering Service Worker...');
        // Registrar el Service Worker como classic script (no como module)
        registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', {
            scope: '/',
        });
        
        console.log('[Firebase] Service Worker registered successfully');
        
        // Esperar a que el Service Worker esté completamente activo
        await ensureServiceWorkerActive(registration);
        
    } catch (error) {
        console.error('[Firebase] Failed to register Service Worker:', error);
        throw new Error(`Failed to register Service Worker: ${error instanceof Error ? error.message : String(error)}`);
    }

    console.log('[Firebase] Service Worker is ready, attempting to get token...');
    
    const messaging = getMessaging(app);
    
    // Obtener token con reintentos
    let token: string | undefined;
    let lastError: Error | null = null;
    
    for (let attempt = 1; attempt <= 5; attempt++) {
        try {
            console.log(`[Firebase] Token attempt ${attempt}/5...`);
            
            // Esperar un poco más entre intentos
            if (attempt > 1) {
                await new Promise((resolve) => setTimeout(resolve, 500 * attempt));
            }
            
            // No pasar serviceWorkerRegistration - Firebase lo encontrará automáticamente
            token = await getToken(messaging, {
                vapidKey,
            });
            
            console.log('[Firebase] ✅ Token obtained successfully');
            break;
        } catch (error) {
            lastError = error instanceof Error ? error : new Error(String(error));
            console.warn(`[Firebase] ❌ Token attempt ${attempt} failed:`, lastError.message);
            
            if (attempt === 5) {
                console.error('[Firebase] Token acquisition failed after 5 attempts');
            }
        }
    }
    
    if (!token) {
        console.error('[Firebase] Failed to get token:', lastError?.message);
        throw lastError || new Error('Failed to get FCM token');
    }

    return { token, messaging, getMessaging };
}

export async function refreshFirebaseMessagingToken() {
    const initialized = await initializeFirebaseMessaging();

    if (!initialized) {
        return null;
    }

    const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;

    if (!vapidKey) {
        return {
            ...initialized,
            previousToken: null,
        };
    }

    const previousToken = initialized.token;

    try {
        await deleteToken(initialized.messaging);
    } catch (error) {
        console.warn('[Firebase] Could not delete existing token before refresh:', error);
    }

    try {
        const refreshedToken = await getToken(initialized.messaging, { vapidKey });

        if (refreshedToken) {
            return {
                ...initialized,
                token: refreshedToken,
                previousToken,
            };
        }
    } catch (error) {
        console.warn('[Firebase] Could not refresh token, using current token:', error);
    }

    return {
        ...initialized,
        previousToken,
    };
}

/**
 * Asegura que el Service Worker esté en estado 'activated' antes de continuar
 */
async function ensureServiceWorkerActive(registration: ServiceWorkerRegistration): Promise<void> {
    console.log('[Firebase] Ensuring Service Worker is active...');
    console.log(`[Firebase] SW state - active: ${!!registration.active}, installing: ${!!registration.installing}, waiting: ${!!registration.waiting}`);
    
    // Si ya está activo, listo
    if (registration.active) {
        console.log('[Firebase] Service Worker is already active');
        // Esperar un tiempo para que el SW esté 100% listo
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    
    // Si se está instalando, esperar a que se active
    if (registration.installing) {
        console.log('[Firebase] Service Worker is installing, waiting for activation...');
        
        await new Promise<void>((resolve) => {
            const timeout = setTimeout(() => {
                console.warn('[Firebase] Service Worker activation timeout, continuing with ready() check');
                resolve();
            }, 10000);
            
            const handleStateChange = () => {
                console.log(`[Firebase] SW state changed to: ${registration.installing?.state}`);
                if (registration.installing?.state === 'activated') {
                    clearTimeout(timeout);
                    registration.installing?.removeEventListener('statechange', handleStateChange);
                    console.log('[Firebase] Service Worker activated');
                    resolve();
                }
            };
            
            registration.installing?.addEventListener('statechange', handleStateChange);
        });
        
        // Esperar a que el SW esté 100% listo
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    
    // Si hay uno en espera, forzar activación
    if (registration.waiting) {
        console.log('[Firebase] Service Worker is waiting, forcing activation...');
        
        registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        
        await new Promise<void>((resolve) => {
            const timeout = setTimeout(() => {
                console.warn('[Firebase] Controller change timeout, continuing with ready() check');
                resolve();
            }, 10000);
            
            const handleControllerChange = () => {
                clearTimeout(timeout);
                navigator.serviceWorker.removeEventListener('controllerchange', handleControllerChange);
                console.log('[Firebase] Controller changed, new SW is active');
                resolve();
            };
            
            navigator.serviceWorker.addEventListener('controllerchange', handleControllerChange);
        });
        
        // Esperar a que el nuevo SW esté 100% listo
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    
    try {
        await Promise.race([
            navigator.serviceWorker.ready,
            new Promise<never>((_, reject) => {
                setTimeout(() => reject(new Error('Service Worker ready() timeout')), 12000);
            }),
        ]);
        console.log('[Firebase] navigator.serviceWorker.ready resolved');
    } catch (error) {
        console.warn('[Firebase] Service Worker ready() timeout, continuing anyway:', error);
    }
}

export function getFirebaseMessaging() {
    const app = getFirebaseApp();
    if (!app) {
        return null;
    }
    return getMessaging(app);
}

export function onForegroundFirebaseMessage(callback: (payload: MessagePayload) => void) {
    const app = getFirebaseApp();

    if (!app) {
        return () => {};
    }

    const messaging = getMessaging(app);

    return onMessage(messaging, callback);
}