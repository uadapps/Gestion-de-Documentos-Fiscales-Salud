<?php

namespace App\Http\Middleware;

use App\Models\usuario_model;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckSpecificRole
{
    public function handle(Request $request, Closure $next, ...$allowedRoles)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Verificar que es una instancia del modelo correcto
        if (!$user instanceof usuario_model) {
            Auth::logout();
            return redirect()->route('login');
        }

        try {
            // Cargar los roles del usuario
            $user->load('roles');

            // Obtener los roles del usuario - usar ID_Rol que es el campo correcto
            $userRoles = $user->roles->pluck('ID_Rol')->toArray();



            // Si no se especifican roles permitidos, permitir acceso
            if (empty($allowedRoles)) {
                return $next($request);
            }

            // Verificar si el usuario tiene al menos uno de los roles permitidos
            $hasPermission = !empty(array_intersect($userRoles, $allowedRoles));

            if (!$hasPermission) {
                Log::warning('Usuario sin permisos intentó acceder a ruta restringida', [
                    'user_id' => $user->id,
                    'user_roles' => $userRoles,
                    'required_roles' => $allowedRoles,
                    'route' => $request->path()
                ]);

                return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección.');
            }

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error verificando roles específicos', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'route' => $request->path()
            ]);

            return redirect()->route('dashboard')->with('error', 'Error verificando permisos.');
        }
    }
}
