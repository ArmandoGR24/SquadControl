import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const tokenSaved = ref(false);
const pendingToken = ref<string | null>(null);

function getCsrfToken(): string | null {
    const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (metaToken) {
        return metaToken;
    }

    const xsrfCookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return xsrfCookie ? decodeURIComponent(xsrfCookie) : null;
}

async function parseResponseSafely(response: Response): Promise<any> {
    const contentType = response.headers.get('content-type') || '';
    const text = await response.text();

    if (contentType.includes('application/json')) {
        try {
            return JSON.parse(text);
        } catch {
            return { message: 'Respuesta JSON inválida del servidor' };
        }
    }

    return { message: text.slice(0, 180) || 'Respuesta no JSON del servidor' };
}

/**
 * Guarda el token FCM en el backend
 */
export async function saveFCMToken(token: string): Promise<boolean> {
    if (tokenSaved.value) {
        console.info('✅ FCM token already saved');
        return true;
    }

    try {
        const csrfToken = getCsrfToken();

        if (!csrfToken) {
            console.error('❌ CSRF token not found');
            pendingToken.value = token;
            return false;
        }

        const response = await fetch('/fcm/token', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token }),
        });

        const data = await parseResponseSafely(response);

        if (response.ok) {
            console.info('✅ FCM token saved successfully');
            tokenSaved.value = true;
            pendingToken.value = null;

            return true;
        } else {
            console.error('❌ Failed to save FCM token:', data);
            
            if (response.status === 401 || response.status === 419) {
                console.warn('⚠️ Usuario no autenticado, guardando token para después...');
                pendingToken.value = token;
            }
            
            return false;
        }
    } catch (error) {
        console.error('❌ Error saving FCM token:', error);
        pendingToken.value = token;
        return false;
    }
}

/**
 * Reintenta guardar el token si hay uno pendiente
 * Útil después de login
 */
export async function retryPendingFCMToken(): Promise<void> {
    if (pendingToken.value && !tokenSaved.value) {
        console.info('🔄 Reintentando guardar token FCM pendiente...');
        await saveFCMToken(pendingToken.value);
    }
}

/**
 * Verifica si el usuario está autenticado
 */
export function isAuthenticated(): boolean {
    const page = usePage();
    return !!page.props.auth?.user;
}

/**
 * Hook para inicializar FCM token
 * Se puede llamar desde cualquier componente Vue
 */
export function useFCMToken() {
    return {
        saveFCMToken,
        retryPendingFCMToken,
        tokenSaved,
        pendingToken,
        isAuthenticated,
    };
}
