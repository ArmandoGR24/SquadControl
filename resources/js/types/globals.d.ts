import type { AppPageProps } from './index';
import { AxiosInstance } from 'axios';
import Pusher from 'pusher-js';
import Echo from 'laravel-echo';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_FIREBASE_API_KEY?: string;
        readonly VITE_FIREBASE_AUTH_DOMAIN?: string;
        readonly VITE_FIREBASE_PROJECT_ID?: string;
        readonly VITE_FIREBASE_STORAGE_BUCKET?: string;
        readonly VITE_FIREBASE_MESSAGING_SENDER_ID?: string;
        readonly VITE_FIREBASE_APP_ID?: string;
        readonly VITE_FIREBASE_MEASUREMENT_ID?: string;
        readonly VITE_FIREBASE_VAPID_KEY?: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

declare module '@laravel/echo-vue';

declare global {
    interface Window {
        axios: AxiosInstance;
        Pusher: typeof Pusher;
        Echo: Echo;
    }
}
export {};
