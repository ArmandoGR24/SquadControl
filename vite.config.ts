import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        ...(process.env.WAYFINDER_DISABLE === '1'
            ? []
            : [
                  wayfinder({
                      formVariants: true,
                  }),
              ]),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: 'auto',
            workbox: {
                navigateFallback: null,
            },
            
            // ACTIVAR EL SERVICE WORKER EN DESARROLLO
            devOptions: {
                enabled: true, 
                type: 'classic',
            },
            manifest: {
                name: 'ScuadControl',
                short_name: 'Scuad',
                description: 'Gestión de Cuadrillas de Trabajo',
                theme_color: '#ffffff',
                background_color: '#ffffff',
                display: 'standalone',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png'
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png'
                    }
                ]
            }
        })
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            // Cuando usas Cloudflare, el cliente debe conectar al dominio HTTPS del túnel
            host: process.env.VITE_HMR_HOST || 'pruebas.codigomaestro.org', 
            clientPort: 443, 
            protocol: 'wss'
        },
    },
});