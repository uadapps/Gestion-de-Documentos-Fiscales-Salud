<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

class HandleCsrfTokenMismatch
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (TokenMismatchException $e) {
            // Si es una petición AJAX, devolver JSON con error de sesión expirada
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Sesión expirada',
                    'message' => 'Tu sesión ha expirado. Por favor, recarga la página.',
                    'csrf_token' => csrf_token()
                ], 419);
            }

            // Si es una petición normal, redirigir al login con mensaje
            return redirect()->route('login')->with('error', 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.');
        }
    }
}
