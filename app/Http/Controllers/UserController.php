<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Obtener la fotografía del usuario autenticado
     */
    public function getProfilePhoto(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Cargar la relación del empleado
        $user->load('empleado');

        if (!$user->empleado) {
            return response()->json(['error' => 'Empleado no encontrado'], 404);
        }

        if (!$user->empleado->ID_Fotografia) {
            return response()->json(['error' => 'Fotografía no encontrada en BD'], 404);
        }

        $imageData = $user->empleado->ID_Fotografia;

        // Verificar si los datos son válidos
        if (empty($imageData)) {
            return response()->json(['error' => 'Datos de imagen vacíos'], 404);
        }

        // Si es un string hexadecimal, convertir a binario
        if (is_string($imageData)) {
            // Si empieza con 0x, quitarlo
            if (str_starts_with($imageData, '0x') || str_starts_with($imageData, '0X')) {
                $imageData = hex2bin(substr($imageData, 2));
            }
            // Si parece ser hexadecimal puro
            elseif (ctype_xdigit($imageData) && strlen($imageData) % 2 === 0) {
                $imageData = hex2bin($imageData);
            }
            // Si no es UTF-8 válido, asumimos que ya es binario
            elseif (!mb_check_encoding($imageData, 'UTF-8')) {
                // Ya es binario, no hacer nada
            }
        }

        // Detectar el tipo de imagen
        $contentType = 'image/jpeg'; // Por defecto
        if (substr($imageData, 0, 4) === "\xFF\xD8\xFF") {
            $contentType = 'image/jpeg';
        } elseif (substr($imageData, 0, 8) === "\x89PNG\r\n\x1A\n") {
            $contentType = 'image/png';
        }

        // Retornar la imagen con los headers correctos
        return response($imageData)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=3600'); // Cache por 1 hora
    }

    /**
     * Obtener los datos completos del perfil del usuario
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Cargar relaciones
        $user->load([
            'empleado.persona',
            'empleado.campus',
            'campus'
        ]);

        return response()->json([
            'user' => [
                'id' => $user->ID_Usuario,
                'usuario' => $user->Usuario,
                'email' => $user->email,
                'nombre_completo' => $user->nombre_completo,
                'campus_nombre' => $user->campus ? $user->campus->Campus : null,
                'empleado' => $user->empleado ? [
                    'id' => $user->empleado->ID_Empleado,
                    'area' => $user->empleado->Area,
                    'tiene_fotografia' => !empty($user->empleado->ID_Fotografia),
                    'persona' => $user->empleado->persona ? [
                        'nombre' => $user->empleado->persona->Nombre,
                        'paterno' => $user->empleado->persona->Paterno,
                        'materno' => $user->empleado->persona->Materno,
                    ] : null,
                ] : null,
            ]
        ]);
    }

    /**
     * Debug: Obtener información del usuario para depurar
     */
    public function debugUserData(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Cargar relaciones
        $user->load([
            'empleado.persona',
            'empleado.campus',
            'campus',
            'roles'
        ]);

        $rolPrincipal = $user->roles->first();

        return response()->json([
            'user_id' => $user->ID_Usuario,
            'usuario' => $user->Usuario,
            'tiene_empleado' => !!$user->empleado,
            'empleado_id' => $user->empleado ? $user->empleado->ID_Empleado : null,
            'tiene_fotografia' => $user->empleado && !empty($user->empleado->ID_Fotografia),
            'fotografia_info' => $user->empleado && $user->empleado->ID_Fotografia ? [
                'tipo' => gettype($user->empleado->ID_Fotografia),
                'longitud' => is_string($user->empleado->ID_Fotografia) ? strlen($user->empleado->ID_Fotografia) : null,
                'es_binario' => is_string($user->empleado->ID_Fotografia) && !mb_check_encoding($user->empleado->ID_Fotografia, 'UTF-8'),
                'primeros_bytes_hex' => is_string($user->empleado->ID_Fotografia) ? bin2hex(substr($user->empleado->ID_Fotografia, 0, 10)) : null,
            ] : null,
            'persona' => $user->empleado && $user->empleado->persona ? [
                'nombre' => $user->empleado->persona->Nombre,
                'paterno' => $user->empleado->persona->Paterno,
                'materno' => $user->empleado->persona->Materno,
            ] : null,
            'campus' => $user->campus ? $user->campus->Campus : null,
            'roles_count' => $user->roles->count(),
            'rol_principal' => $rolPrincipal ? [
                'id' => $rolPrincipal->ID_Rol,
                'descripcion' => $rolPrincipal->Descripcion,
            ] : null,
        ]);
    }    /**
     * Debug simple: Obtener información básica del usuario sin fotografía
     */
    public function debugSimple(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Cargar relaciones
        $user->load([
            'empleado.persona',
            'empleado.campus',
            'campus',
            'roles'
        ]);

        $rolPrincipal = $user->roles->first();

        return response()->json([
            'user_id' => $user->ID_Usuario,
            'usuario' => $user->Usuario,
            'tiene_empleado' => !!$user->empleado,
            'empleado_id' => $user->empleado ? $user->empleado->ID_Empleado : null,
            'tiene_fotografia' => $user->empleado && !empty($user->empleado->ID_Fotografia),
            'persona' => $user->empleado && $user->empleado->persona ? [
                'nombre' => $user->empleado->persona->Nombre,
                'paterno' => $user->empleado->persona->Paterno,
                'materno' => $user->empleado->persona->Materno,
            ] : null,
            'campus' => $user->campus ? $user->campus->Campus : null,
            'roles_count' => $user->roles->count(),
            'roles' => $user->roles->map(function($rol) {
                return [
                    'id' => $rol->ID_Rol,
                    'descripcion' => $rol->Descripcion,
                ];
            }),
            'rol_principal' => $rolPrincipal ? [
                'id' => $rolPrincipal->ID_Rol,
                'descripcion' => $rolPrincipal->Descripcion,
            ] : null,
        ]);
    }
}
