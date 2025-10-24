// Configuración global para incluir CSRF token en todas las peticiones AJAX

// Función para obtener el token CSRF del meta tag
function getCSRFToken(): string {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!token) {
        console.error('CSRF token not found');
        return '';
    }
    return token;
}

// Configurar interceptores para fetch API
const originalFetch = window.fetch;
window.fetch = function(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
    // Solo agregar CSRF token a peticiones POST, PUT, PATCH, DELETE
    const method = init?.method?.toUpperCase() || 'GET';
    const needsCSRF = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);

    if (needsCSRF && init) {
        const headers = new Headers(init.headers);

        // Solo agregar si no existe ya
        if (!headers.has('X-CSRF-TOKEN')) {
            headers.set('X-CSRF-TOKEN', getCSRFToken());
        }

        init.headers = headers;
    } else if (needsCSRF) {
        // Si no hay init, crearlo
        init = {
            ...init,
            headers: {
                'X-CSRF-TOKEN': getCSRFToken(),
                ...init?.headers
            }
        };
    }

    return originalFetch(input, init);
};

// Función helper para hacer peticiones con CSRF automático
export const csrfFetch = async (url: string, options: RequestInit = {}): Promise<Response> => {
    const method = options.method?.toUpperCase() || 'GET';
    const needsCSRF = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);

    const defaultHeaders: Record<string, string> = {};

    // Solo agregar Content-Type si no es FormData
    const isFormData = options.body instanceof FormData;
    if (!isFormData) {
        defaultHeaders['Content-Type'] = 'application/json';
    }

    if (needsCSRF) {
        defaultHeaders['X-CSRF-TOKEN'] = getCSRFToken();
    }

    const finalOptions: RequestInit = {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers
        }
    };

    try {
        const response = await fetch(url, finalOptions);

        // Si es 419 (CSRF mismatch), recargar la página para obtener nuevo token
        if (response.status === 419) {
            console.warn('CSRF token mismatch, reloading page...');
            window.location.reload();
            throw new Error('CSRF token mismatch');
        }

        return response;
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
};

export default csrfFetch;
