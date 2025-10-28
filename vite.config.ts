import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { existsSync } from 'fs';

// Función para detectar la ruta correcta de PHP
function getPhpCommand(): string {
    if (process.platform !== 'win32') {
        return 'php';
    }

    // Si hay una variable de entorno PHP_PATH, usarla
    if (process.env.PHP_PATH && existsSync(process.env.PHP_PATH)) {
        return process.env.PHP_PATH;
    }

    // Rutas comunes de Laragon y otros entornos
    const laravelPaths = [
        'C:\\laragon\\bin\\php\\php-8.3.26-Win32-vs16-x64\\php.exe',
        'C:\\laragon\\bin\\php\\php-8.3.21-Win32-vs16-x64\\php.exe',
        'C:\\laragon\\bin\\php\\php-8.3.12-Win32-vs16-x64\\php.exe',
        'C:\\laragon\\bin\\php\\php-8.3.0-Win32-vs16-x64\\php.exe',
        'C:\\laragon\\bin\\php\\php-8.2.12-Win32-vs16-x64\\php.exe',
        'C:\\laragon\\bin\\php\\php-8.2\\php.exe',
        'C:\\laragon\\bin\\php\\php-8.1\\php.exe',
        'C:\\xampp\\php\\php.exe',
        'C:\\wamp64\\bin\\php\\php8.3.0\\php.exe',
        'C:\\wamp64\\bin\\php\\php8.2.0\\php.exe',
    ];

    for (const path of laravelPaths) {
        if (existsSync(path)) {
            return path;
        }
    }

    // Fallback a php en PATH
    return 'php';
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        // wayfinder({
        //     // Usar comando personalizado con detección de PHP
        //     command: `${getPhpCommand()} artisan wayfinder:generate`,
        // }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
});
