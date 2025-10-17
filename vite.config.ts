import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            // En Windows Laragon el ejecutable php no siempre está en PATH cuando pnpm
            // arranca vite; pasar la ruta absoluta evita que el plugin falle al invocar
            // `php artisan wayfinder:generate`.
            command: "C:\\laragon\\bin\\php\\php-8.3.26-Win32-vs16-x64\\php.exe artisan wayfinder:generate",
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
});
