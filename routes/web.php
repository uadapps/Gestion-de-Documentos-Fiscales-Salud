<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SupervisionController;
use App\Http\Controllers\DocumentoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        // Si está autenticado (ya pasó la validación de roles), ir al dashboard
        return redirect()->route('dashboard');
    }

    // Si no está autenticado, mostrar login
    return Inertia::render('auth/login');
})->name('home');

Route::get('access-denied', [AccessController::class, 'denied'])->name('access.denied');

Route::middleware(['auth', 'verified', 'authorized.role'])->group(function () {
    Route::get('debug-roles', function () {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json(['error' => 'No authenticated user']);
        }

        // Usar el campo Usuario (nombre) en lugar de ID_Usuario para buscar
        $user = \App\Models\usuario_model::where('Usuario', $authUser->getAuthIdentifier())->first();

        if (!$user) {
            return response()->json([
                'error' => 'User not found in usuario_model',
                'auth_user' => [
                    'identifier' => $authUser->getAuthIdentifier(),
                    'usuario' => $authUser->Usuario ?? 'N/A',
                    'id_usuario' => $authUser->ID_Usuario ?? 'N/A',
                    'class' => get_class($authUser)
                ]
            ]);
        }

        $roles = $user->roles()->get();

        return response()->json([
            'auth_user' => [
                'identifier' => $authUser->getAuthIdentifier(),
                'usuario' => $authUser->Usuario ?? 'N/A',
                'id_usuario' => $authUser->ID_Usuario ?? 'N/A'
            ],
            'usuario_model' => [
                'ID_Usuario' => $user->ID_Usuario,
                'Usuario' => $user->Usuario
            ],
            'roles' => $roles->map(function ($role) {
                return [
                    'ID_Rol' => $role->ID_Rol,
                    'Descripcion' => $role->Descripcion ?? 'N/A'
                ];
            }),
            'has_role_16' => in_array(16, $roles->pluck('ID_Rol')->toArray()),
            'has_role_20' => in_array(20, $roles->pluck('ID_Rol')->toArray())
        ]);
    });

    Route::get('dashboard', function () {
        Log::info('=== DASHBOARD ROUTE DEBUG START ===');

        // Verificar si el usuario autenticado tiene rol 16
        $authUser = Auth::user();

        if (!$authUser) {
            Log::warning('No authenticated user found');
            return Inertia::render('dashboard');
        }

        Log::info('Auth user found:', [
            'identifier' => $authUser->getAuthIdentifier(),
            'usuario' => $authUser->Usuario ?? 'N/A',
            'id_usuario' => $authUser->ID_Usuario ?? 'N/A',
            'user_class' => get_class($authUser)
        ]);

        try {
            // Buscar el usuario usando el campo Usuario (nombre) no ID_Usuario
            $user = \App\Models\usuario_model::where('Usuario', $authUser->getAuthIdentifier())->first();

            if (!$user) {
                Log::warning('Usuario_model not found for auth user:', [
                    'auth_identifier' => $authUser->getAuthIdentifier(),
                    'searching_field' => 'Usuario'
                ]);
                return Inertia::render('dashboard');
            }

            Log::info('Usuario_model found:', [
                'ID_Usuario' => $user->ID_Usuario,
                'Usuario' => $user->Usuario
            ]);

            // Verificar roles
            $roles = $user->roles()->get();
            $roleIds = $roles->pluck('ID_Rol')->toArray();

            Log::info('User roles detailed:', [
                'roles_count' => $roles->count(),
                'role_ids' => $roleIds,
                'roles_data' => $roles->toArray()
            ]);

            $hasRole16 = in_array(16, $roleIds) || in_array('16', $roleIds);
            $hasRole20 = in_array(20, $roleIds) || in_array('20', $roleIds);

            Log::info('Role check results:', [
                'has_role_16' => $hasRole16,
                'has_role_20' => $hasRole20,
                'check_method' => 'in_array',
                'role_ids_original' => $roleIds,
                'role_ids_types' => array_map('gettype', $roleIds),
                'usuario_actual' => $user->Usuario,
                'usuario_normalizado' => strtolower(trim($user->Usuario))
            ]);

            // Verificar si tiene roles de supervisión
            if (class_exists('App\Http\Controllers\SupervisionController')) {
                if ($hasRole16) {
                    Log::info('Redirecting to dashboardGlobal (Role 16)');
                    return app(SupervisionController::class)->dashboardGlobal();
                } elseif ($hasRole20) {
                    Log::info('Redirecting to supervision dashboard (Role 20)');
                    return app(SupervisionController::class)->dashboard();
                }
            }

            // Si no tiene rol 16 ni 20, mostrar dashboard normal
            Log::info('Serving default dashboard - No supervisory roles (16/20)');
            return Inertia::render('dashboard');

        } catch (\Exception $e) {
            Log::error('Exception in dashboard route:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            // En caso de error, mostrar dashboard normal
            return Inertia::render('dashboard');
        }
    })->name('dashboard');

    // API para estadísticas del dashboard
    Route::get('api/dashboard/estadisticas', [DocumentoController::class, 'getEstadisticasDashboard'])->name('api.dashboard.estadisticas');

    // API para estadísticas del dashboard de supervisión
    Route::get('api/supervision/estadisticas', [SupervisionController::class, 'getEstadisticasGenerales'])->name('api.supervision.estadisticas');

    // API específica para dashboard de supervisión global
    Route::get('api/supervision/estadisticas-globales', [\App\Http\Controllers\Api\SupervisionApiController::class, 'estadisticasGlobales'])->name('api.supervision.estadisticas-globales');

    // Rutas de usuario
    Route::prefix('api/user')->name('api.user.')->group(function () {
        Route::get('profile', [UserController::class, 'getProfile'])->name('profile');
        Route::get('photo', [UserController::class, 'getProfilePhoto'])->name('photo');
        Route::get('debug', [UserController::class, 'debugUserData'])->name('debug');
        Route::get('debug-simple', [UserController::class, 'debugSimple'])->name('debug-simple');
    });

    // Rutas de documentos - Solo roles 13 y 14
    Route::prefix('documentos')->name('documentos.')->middleware('role:13,14')->group(function () {
        Route::get('/', function () {
            return Inertia::render('documentos/index');
        })->name('index');
        Route::get('upload', [DocumentoController::class, 'upload'])->name('upload');
        Route::post('cambiar-campus', [DocumentoController::class, 'cambiarCampus'])->name('cambiar-campus');
        Route::post('subir-archivo', [DocumentoController::class, 'subirArchivo'])->name('subir-archivo');
        Route::get('debug-campus', [DocumentoController::class, 'debugCampusContadores'])->name('debug-campus');

        // Rutas para análisis de documentos
        Route::get('analisis/estado', [DocumentoController::class, 'obtenerEstadoAnalisis'])->name('analisis.estado');
        Route::post('analisis/reanalizar', [DocumentoController::class, 'reanalizar'])->name('analisis.reanalizar');

        // Rutas para servir archivos
        Route::get('archivo/{id}', [DocumentoController::class, 'verArchivo'])->name('archivo.ver');
        Route::get('archivo/{id}/descargar', [DocumentoController::class, 'descargarArchivo'])->name('archivo.descargar');

        // Ruta para servir archivos por hash (sin mostrar ID)
        Route::get('file/{hash}', [DocumentoController::class, 'verArchivoPorHash'])->name('archivo.ver.hash');

        // Verificar si campus tiene carreras médicas
        Route::get('campus/{campusId}/medico', [DocumentoController::class, 'verificarCampusMedico'])->name('campus.medico');

        // Obtener documentos por carrera específica
        Route::get('por-carrera', [DocumentoController::class, 'getDocumentosPorCarrera'])->name('por-carrera');

        // Obtener todos los documentos médicos optimizado (nueva ruta)
        Route::get('medicos-optimizado', [DocumentoController::class, 'getDocumentosMedicosOptimizado'])->name('medicos-optimizado');

        // Obtener documentos médicos usando stored procedure directamente
        Route::get('medicos-con-sp', [DocumentoController::class, 'getDocumentosMedicosConSP'])->name('medicos-con-sp');

        // Ruta de prueba para debug
        Route::post('test-upload', function (Request $request) {
            Log::info('TEST UPLOAD - Request recibido', $request->all());
            error_log('TEST UPLOAD - FUNCIONANDO');
            return response()->json(['test' => 'ok', 'data' => $request->all()]);
        })->name('test-upload');

        // Debug documentos en BD
        Route::get('debug-documentos', function () {
            $documentos = \App\Models\SugDocumento::all(['id', 'nombre']);
            return response()->json([
                'documentos_en_bd' => $documentos,
                'total' => $documentos->count()
            ]);
        })->name('debug-documentos');

        // Test simple de archivo
        Route::post('test-file', function (Request $request) {
            Log::info('TEST FILE - Info completa', [
                'hasFile' => $request->hasFile('archivo'),
                'allFiles' => $request->allFiles(),
                'allInput' => $request->all(),
                'fileInfo' => $request->hasFile('archivo') ? [
                    'name' => $request->file('archivo')->getClientOriginalName(),
                    'size' => $request->file('archivo')->getSize(),
                    'mime' => $request->file('archivo')->getMimeType(),
                ] : 'Sin archivo'
            ]);

            return response()->json([
                'success' => true,
                'hasFile' => $request->hasFile('archivo'),
                'data' => $request->all()
            ]);
        })->name('test-file');

        // Debug configuración PHP
        Route::get('debug-php-config', function () {
            return response()->json([
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'max_input_time' => ini_get('max_input_time'),
                'memory_limit' => ini_get('memory_limit'),
                'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
                'max_file_uploads' => ini_get('max_file_uploads'),
            ]);
        })->name('debug-php-config');
    });

    // Debug controller sin caracteres UTF-8 problematicos
    Route::get('debug-campus-clean', [App\Http\Controllers\DebugController::class, 'debugCampus'])->middleware('role:13,14')->name('debug-campus-clean');

    // Ver logs de debug
    Route::get('ver-logs', [DocumentoController::class, 'verLogs'])->middleware('role:13,14')->name('ver-logs');

    // Dashboard de supervisión - Roles 16 y 20
    Route::prefix('supervision')->name('supervision.')->middleware('role:16,20')->group(function () {
        Route::get('/', [SupervisionController::class, 'dashboard'])->name('dashboard');

        // Nuevo dashboard especializado para supervisión
        Route::get('/dashboard-global', [SupervisionController::class, 'dashboardGlobal'])->name('dashboard-global');

        // Ruta para actualizar documento (solo rol 16)
        Route::post('/actualizar-documento/{id}', [SupervisionController::class, 'actualizarDocumento'])->name('actualizar-documento');

        // Ruta para servir archivos por hash (sin mostrar ID)
        Route::get('file/{hash}', [DocumentoController::class, 'verArchivoPorHash'])->name('archivo.ver.hash');

        Route::get('debug-hash/{campus_name}/{campus_id}', [SupervisionController::class, 'debugHash'])->name('debug-hash');
        Route::get('debug-campus-list', [SupervisionController::class, 'debugCampusList'])->name('debug-campus-list');
        Route::get('debug-cd-acuna', [SupervisionController::class, 'debugCdAcuna'])->name('debug-cd-acuna');
        Route::get('{campus_hash}', [SupervisionController::class, 'detallesCampusPorSlug'])->name('detalles');
    });

    // Otras rutas del campus
    Route::get('calendario', function () {
        return Inertia::render('calendario/index');
    })->name('calendario');

    Route::get('reportes', function () {
        return Inertia::render('reportes/index');
    })->name('reportes');
});

require __DIR__ . '/test.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
