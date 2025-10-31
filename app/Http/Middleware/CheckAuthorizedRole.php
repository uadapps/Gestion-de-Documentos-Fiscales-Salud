<?php

namespace App\Http\Middleware;

use App\Models\usuario_model;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAuthorizedRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si el usuario está autenticado
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Verificar que es una instancia del modelo correcto
        if (!$user instanceof usuario_model) {
            Auth::logout();
            return redirect()->route('login');
        }

        // Cargar los roles del usuario
        $user->load('roles');

        // Roles autorizados para acceder al sistema (desde configuración)
        $rolesAutorizados = config('roles.authorized_roles');

        // Verificar si el usuario tiene al menos uno de los roles autorizados
        $tieneRolAutorizado = $user->roles->pluck('ID_Rol')->intersect($rolesAutorizados)->isNotEmpty();

        if (!$tieneRolAutorizado) {
            // Si llegó aquí significa que había una sesión previa pero los roles cambiaron
            // Cerrar sesión y redirigir al login
            Auth::logout();
            return redirect()->route('login')->with('error', 'Tu sesión ha expirado.');
        }

        // VALIDACIONES ESPECIALES DE USUARIOS Y ROLES
        $rolesUsuario = $user->roles->pluck('ID_Rol')->toArray();
        $usuarioNormalizado = strtolower(trim($user->Usuario));

        // VALIDACIÓN ESPECIAL: Si tiene rol 16 (Supervisor), debe ser usuario "rector"
        $tieneRol16 = in_array('16', $rolesUsuario);
        if ($tieneRol16 && $usuarioNormalizado !== 'rector') {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Acceso denegado para este usuario.');
        }

        // VALIDACIÓN ESPECIAL: Si tiene rol 20, debe ser usuario "planeacion_desarrollo"
        $tieneRol20 = in_array('20', $rolesUsuario);
        if ($tieneRol20 && $usuarioNormalizado !== 'planeacion_desarrollo') {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Acceso denegado para este usuario.');
        }

        return $next($request);
    }
}
