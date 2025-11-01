<?php

namespace App\Http\Controllers;

use App\Models\SugObservacion;
use App\Models\SugDocumentoInformacion;
use App\Models\CampusContador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ObservacionesController extends Controller
{
    /**
     * Obtener observaciones de un documento específico
     */
    public function obtenerObservacionesDocumento($documentoInformacionId)
    {
        try {
            $observaciones = DB::table('sug_observaciones as o')
                ->leftJoin('Empleados as e', 'o.creado_por', '=', 'e.ID_Empleado')
                ->leftJoin('Personas as p', 'e.ID_Persona', '=', 'p.ID_Persona')
                ->where('o.documento_informacion_id', $documentoInformacionId)
                ->where('o.activo', true)
                ->orderBy('o.creado_en', 'desc')
                ->select(
                    'o.*',
                    DB::raw("CONCAT(COALESCE(p.Nombre, ''), ' ', COALESCE(p.Paterno, ''), ' ', COALESCE(p.Materno, '')) as creado_por_nombre")
                )
                ->get();

            return response()->json([
                'success' => true,
                'observaciones' => $observaciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener observaciones del documento', [
                'error' => $e->getMessage(),
                'documento_informacion_id' => $documentoInformacionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener observaciones'
            ], 500);
        }
    }

    /**
     * Obtener observaciones generales de un campus
     */
    public function obtenerObservacionesCampus($campusId)
    {
        try {
            $observaciones = DB::table('sug_observaciones as o')
                ->leftJoin('Empleados as e', 'o.creado_por', '=', 'e.ID_Empleado')
                ->leftJoin('Personas as p', 'e.ID_Persona', '=', 'p.ID_Persona')
                ->where('o.campus_id', $campusId)
                ->whereNull('o.documento_informacion_id')
                ->where('o.activo', true)
                ->orderBy('o.creado_en', 'desc')
                ->select(
                    'o.*',
                    DB::raw("CONCAT(COALESCE(p.Nombre, ''), ' ', COALESCE(p.Paterno, ''), ' ', COALESCE(p.Materno, '')) as creado_por_nombre")
                )
                ->get();

            return response()->json([
                'success' => true,
                'observaciones' => $observaciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener observaciones del campus', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener observaciones'
            ], 500);
        }
    }

    /**
     * Obtener TODAS las observaciones de un campus (generales y de documentos)
     */
    public function obtenerTodasObservacionesCampus($campusId)
    {
        try {
            $observaciones = DB::table('sug_observaciones as o')
                ->leftJoin('Empleados as e', 'o.creado_por', '=', 'e.ID_Empleado')
                ->leftJoin('Personas as p', 'e.ID_Persona', '=', 'p.ID_Persona')
                ->where('o.campus_id', $campusId)
                ->where('o.activo', true)
                ->orderBy('o.creado_en', 'desc')
                ->select(
                    'o.*',
                    DB::raw("CONCAT(COALESCE(p.Nombre, ''), ' ', COALESCE(p.Paterno, ''), ' ', COALESCE(p.Materno, '')) as creado_por_nombre")
                )
                ->get();

            return response()->json([
                'success' => true,
                'observaciones' => $observaciones
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener todas las observaciones del campus', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener observaciones'
            ], 500);
        }
    }

    /**
     * Crear una nueva observación de documento
     */
    public function crearObservacionDocumento(Request $request)
    {
        try {
            $validated = $request->validate([
                'documento_informacion_id' => 'required|integer|exists:sug_documentos_informacion,id',
                'campus_id' => 'required|string',
                'tipo_observacion' => 'nullable|string|max:50',
                'observacion' => 'required|string',
                'estatus' => 'nullable|string|in:pendiente,atendido,rechazado'
            ]);

            $user = Auth::user();
            $empleadoId = $user->empleado_id ?? $user->Empleado_ID ?? $user->ID_Empleado ?? null;

            $observacion = SugObservacion::create([
                'campus_id' => $validated['campus_id'],
                'documento_informacion_id' => $validated['documento_informacion_id'],
                'tipo_observacion' => $validated['tipo_observacion'] ?? 'Observación',
                'observacion' => $validated['observacion'],
                'estatus' => $validated['estatus'] ?? 'pendiente',
                'creado_por' => $empleadoId,
                'creado_en' => now(),
                'activo' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Observación creada exitosamente',
                'observacion' => $observacion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear observación del documento', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear observación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva observación general del campus
     */
    public function crearObservacionCampus(Request $request)
    {
        try {
            $validated = $request->validate([
                'campus_id' => 'required|string',
                'tipo_observacion' => 'nullable|string|max:50',
                'observacion' => 'required|string',
                'estatus' => 'nullable|string|in:pendiente,atendido,rechazado'
            ]);

            $user = Auth::user();
            $empleadoId = $user->empleado_id ?? $user->Empleado_ID ?? $user->ID_Empleado ?? null;

            $observacion = SugObservacion::create([
                'campus_id' => $validated['campus_id'],
                'documento_informacion_id' => null, // NULL = observación general
                'tipo_observacion' => $validated['tipo_observacion'] ?? 'Observación general',
                'observacion' => $validated['observacion'],
                'estatus' => $validated['estatus'] ?? 'pendiente',
                'creado_por' => $empleadoId,
                'creado_en' => now(),
                'activo' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Observación general creada exitosamente',
                'observacion' => $observacion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear observación del campus', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear observación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el estatus de una observación
     */
    public function actualizarEstatus(Request $request, $observacionId)
    {
        try {
            $validated = $request->validate([
                'estatus' => 'required|string|in:pendiente,atendido,rechazado'
            ]);

            $user = Auth::user();
            $empleadoId = $user->empleado_id ?? $user->Empleado_ID ?? $user->ID_Empleado ?? null;

            $observacion = SugObservacion::findOrFail($observacionId);

            $observacion->update([
                'estatus' => $validated['estatus'],
                'actualizado_por' => $empleadoId,
                'actualizado_en' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estatus actualizado exitosamente',
                'observacion' => $observacion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estatus de observación', [
                'error' => $e->getMessage(),
                'observacion_id' => $observacionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estatus'
            ], 500);
        }
    }

    /**
     * Desactivar (eliminar lógicamente) una observación
     */
    public function eliminar($observacionId)
    {
        try {
            $user = Auth::user();
            $empleadoId = $user->empleado_id ?? $user->Empleado_ID ?? $user->ID_Empleado ?? null;

            $observacion = SugObservacion::findOrFail($observacionId);

            $observacion->update([
                'activo' => false,
                'actualizado_por' => $empleadoId,
                'actualizado_en' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Observación eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar observación', [
                'error' => $e->getMessage(),
                'observacion_id' => $observacionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar observación'
            ], 500);
        }
    }

    /**
     * Marcar todas las observaciones pendientes de un documento como atendidas
     */
    public function marcarAtendidasDocumento($documentoInformacionId)
    {
        try {
            $user = Auth::user();
            $empleadoId = $user->empleado_id ?? $user->Empleado_ID ?? $user->ID_Empleado ?? null;

            SugObservacion::where('documento_informacion_id', $documentoInformacionId)
                ->where('estatus', 'pendiente')
                ->where('activo', true)
                ->update([
                    'estatus' => 'atendido',
                    'actualizado_por' => $empleadoId,
                    'actualizado_en' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Observaciones marcadas como atendidas'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar observaciones como atendidas', [
                'error' => $e->getMessage(),
                'documento_informacion_id' => $documentoInformacionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar observaciones'
            ], 500);
        }
    }

    /**
     * Marcar todas las observaciones pendientes generales del campus como atendidas
     */
    public function marcarAtendidasCampus($campusId)
    {
        try {
            $user = Auth::user();
            $empleadoId = $user->empleado_id ?? $user->Empleado_ID ?? $user->ID_Empleado ?? null;

            SugObservacion::where('campus_id', $campusId)
                ->whereNull('documento_informacion_id')
                ->where('estatus', 'pendiente')
                ->where('activo', true)
                ->update([
                    'estatus' => 'atendido',
                    'actualizado_por' => $empleadoId,
                    'actualizado_en' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Observaciones del campus marcadas como atendidas'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar observaciones del campus como atendidas', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar observaciones'
            ], 500);
        }
    }

    /**
     * Contar las observaciones generales pendientes de un campus
     */
    public function contarObservacionesGeneralesCampus($campusId)
    {
        try {
            $count = SugObservacion::where('campus_id', $campusId)
                ->whereNull('documento_informacion_id')
                ->where('estatus', 'pendiente')
                ->where('activo', true)
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Error al contar observaciones generales del campus', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al contar observaciones',
                'count' => 0
            ], 500);
        }
    }

    /**
     * Obtener todas las observaciones pendientes del usuario actual
     * (basado en sus campus asignados)
     */
    public function obtenerTodasPendientes()
    {
        try {
            $user = Auth::user();
            $empleadoId = $user->empleado_id ?? $user->Empleado_ID ?? $user->ID_Empleado ?? null;

            Log::info('Obteniendo observaciones pendientes', [
                'empleado_id' => $empleadoId,
                'user' => $user
            ]);

            if (!$empleadoId) {
                return response()->json([
                    'success' => true,
                    'observaciones' => [],
                    'message' => 'No se encontró empleado_id'
                ]);
            }

            // Obtener los campus del usuario usando CampusContador
            $campusIds = CampusContador::where('ID_Empleado', $empleadoId)
                ->pluck('ID_Campus')
                ->toArray();

            Log::info('Campus encontrados para empleado', [
                'empleado_id' => $empleadoId,
                'campus_ids' => $campusIds,
                'total_campus' => count($campusIds)
            ]);

            if (empty($campusIds)) {
                return response()->json([
                    'success' => true,
                    'observaciones' => [],
                    'message' => 'No se encontraron campus asignados'
                ]);
            }

            // Obtener observaciones pendientes sin eager loading primero
            $observaciones = SugObservacion::whereIn('campus_id', $campusIds)
                ->where('estatus', 'pendiente')
                ->where('activo', true)
                ->orderBy('creado_en', 'desc')
                ->get();

            Log::info('Observaciones encontradas (sin procesar)', [
                'total' => $observaciones->count()
            ]);

            // Mapear manualmente para evitar problemas de relaciones
            $observacionesFormateadas = $observaciones->map(function ($obs) {
                try {
                    // Obtener información del campus
                    $campus = DB::table('Campus')
                        ->where('ID_Campus', $obs->campus_id)
                        ->first();

                    // Obtener información del documento si existe
                    $documentoNombre = null;
                    if ($obs->documento_informacion_id) {
                        $docInfo = DB::table('sug_documentos_informacion')
                            ->where('id', $obs->documento_informacion_id)
                            ->first();

                        if ($docInfo && $docInfo->documento_id) {
                            $documento = DB::table('sug_documentos')
                                ->where('id', $docInfo->documento_id)
                                ->first();
                            $documentoNombre = $documento->nombre ?? null;
                        }
                    }

                    return [
                        'id' => $obs->id,
                        'campus_id' => $obs->campus_id,
                        'documento_informacion_id' => $obs->documento_informacion_id,
                        'tipo_observacion' => $obs->tipo_observacion,
                        'observacion' => $obs->observacion,
                        'estatus' => $obs->estatus,
                        'creado_por' => $obs->creado_por,
                        'creado_en' => $obs->creado_en,
                        'campus_nombre' => $campus->Campus ?? 'Sin nombre',
                        'documento_nombre' => $documentoNombre,
                    ];
                } catch (\Exception $e) {
                    Log::warning('Error procesando observación', [
                        'observacion_id' => $obs->id,
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
            })->filter(); // Filtrar nulls

            Log::info('Observaciones pendientes formateadas', [
                'total' => $observacionesFormateadas->count()
            ]);

            return response()->json([
                'success' => true,
                'observaciones' => $observacionesFormateadas->values()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener todas las observaciones pendientes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener observaciones pendientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
