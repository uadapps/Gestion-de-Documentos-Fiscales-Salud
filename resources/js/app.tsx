import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';

// Importar configuración CSRF
import './lib/csrf';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Prevenir navegación hacia atrás al login cuando el usuario está autenticado
router.on('navigate', (event) => {
    const page = event.detail.page;
    const auth = page.props.auth as { user?: any } | undefined;

    // Si el usuario está autenticado y está intentando ir a una página de autenticación
    if (auth?.user && (page.component === 'auth/login' || page.url === '/')) {
        // Cancelar la navegación y redirigir al dashboard
        event.preventDefault();
        router.visit('/dashboard', { replace: true });
    }
});

// Listener global para prevenir caché en páginas de autenticación
window.addEventListener('pageshow', (event) => {
    // Si la página viene del bfcache (back-forward cache)
    if (event.persisted) {
        // Recargar la página para obtener datos frescos del servidor
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
