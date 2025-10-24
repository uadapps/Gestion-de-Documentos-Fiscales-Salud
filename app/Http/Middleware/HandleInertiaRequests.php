<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $enrichedUser = null;

        if ($user) {
            // Cargar las relaciones necesarias para obtener datos completos del usuario
            $user->load([
                'empleado.persona',
                'empleado.campus',
                'campus',
                'roles'
            ]);

            // Obtener el rol principal (primer rol)
            $rolPrincipal = $user->roles->first();

            $enrichedUser = [
                'id' => $user->ID_Usuario,
                'name' => $user->Usuario,
                'email' => $user->email,
                'nombre_completo' => $user->nombre_completo,
                'tiene_fotografia' => $user->empleado && !empty($user->empleado->ID_FOTOGRAFIA),
                'rol_descripcion' => $rolPrincipal ? $rolPrincipal->Descripcion : null,
                'campus' => $user->campus ? $user->campus->Campus : null,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => $user->two_factor_secret !== null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                // Agregar roles para el control de acceso
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->ID_Rol,
                        'rol' => $role->ID_Rol, // Para compatibilidad
                        'nombre' => $role->ID_Rol,
                        'descripcion' => $role->Descripcion,
                    ];
                })->toArray(),
                // Datos adicionales del empleado
                'empleado' => $user->empleado ? [
                    'id' => $user->empleado->ID_Empleado,
                    'area' => $user->empleado->Area,
                    'persona' => $user->empleado->persona ? [
                        'nombre' => $user->empleado->persona->Nombre,
                        'paterno' => $user->empleado->persona->Paterno,
                        'materno' => $user->empleado->persona->Materno,
                    ] : null,
                ] : null,
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $enrichedUser,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
