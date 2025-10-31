<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClearLoginHistory
{
    /**
     * Handle an incoming request.
     * Este middleware limpia el historial del navegador después del login
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Si el usuario está autenticado y viene de una ruta de login/auth
        if (Auth::check() && ($request->is('login') || $request->is('/'))) {
            // Agregar header para reemplazar la entrada en el historial
            if ($response instanceof \Illuminate\Http\RedirectResponse) {
                // Usar JavaScript para reemplazar el historial
                $response->header('X-Inertia-Location', route('dashboard'));
            }
        }

        return $response;
    }
}
