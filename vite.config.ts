import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            // No index.html for VitePWA to inject into (Laravel/Blade renders the shell),
            // so the manifest link + SW registration are added manually in app.blade.php / app.tsx.
            injectRegister: false,
            // laravel-vite-plugin builds hashed assets into public/build/ (served at /build/*),
            // but the manifest + service worker must live at the site root so the SW's default
            // scope covers the whole app, not just /build/. Pointing outDir at the real public
            // root (which already contains build/) makes the plugin write sw.js/manifest there
            // while still finding the hashed assets under build/ for precaching.
            outDir: 'public',
            base: '/',
            manifestFilename: 'manifest.webmanifest',
            registerType: 'autoUpdate',
            // Custom service worker (resources/js/sw.ts) handles push notifications;
            // vite-plugin-pwa injects the Workbox precache manifest into it at build time.
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            includeAssets: ['favicon.ico', 'apple-touch-icon-180x180.png'],
            manifest: {
                name: 'PAYRO',
                short_name: 'PAYRO',
                description: 'Payroll, attendance, and employee self-service portal',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                theme_color: '#4B5563',
                background_color: '#ffffff',
                icons: [
                    { src: '/pwa-64x64.png', sizes: '64x64', type: 'image/png' },
                    { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            injectManifest: {
                globPatterns: ['**/*.{js,css,woff2,png,svg}'],
            },
        }),
    ],
});
