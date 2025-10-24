<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\campus_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupervisionApiController extends Controller
{
    // Campus excluidos del dashboard de supervisión
    private const CAMPUS_EXCLUIDOS = ['67', '25', '13', '17', '29', '35', '62'];

    /**
     * Función para sanitizar strings y prevenir errores UTF-8
     */
    private function sanitizeUtf8($data)
    {
        if (is_string($data)) {
            $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            if (!mb_check_encoding($cleaned, 'UTF-8')) {
                $cleaned = mb_convert_encoding($data, 'UTF-8', 'auto');
            }
            return $cleaned;
        }

        if (is_array($data)) {
            return array_map([$this, 'sanitizeUtf8'], $data);
        }

        if (is_object($data)) {
            $array = (array) $data;
            return (object) array_map([$this, 'sanitizeUtf8'], $array);
        }

        return $data;
    }

    /**
     * Obtener estadísticas globales de supervisión
     */
    public function estadisticasGlobales()
    {
        try {
            Log::info('SupervisionApiController::estadisticasGlobales - Iniciando');

            // Obtener estadísticas usando el SP con @Todos=1
            $estadisticasPorCampus = $this->generarEstadisticasConSP();

            // Calcular estadísticas generales
            $estadisticasGenerales = $this->calcularEstadisticasGenerales($estadisticasPorCampus);

            // Generar alertas del sistema
            $alertas = $this->generarAlertas($estadisticasPorCampus);

            // Calcular tendencias (simuladas por ahora)
            $tendencias = $this->calcularTendencias($estadisticasPorCampus);

            $response = [
                'estadisticas_generales' => $estadisticasGenerales,
                'estadisticas_por_campus' => array_values($estadisticasPorCampus),
                'campus_alertas' => $alertas,
                'tendencias' => $tendencias
            ];

            Log::info('SupervisionApiController::estadisticasGlobales - Respuesta generada', [
                'total_campus' => count($estadisticasPorCampus),
                'estadisticas_generales' => $estadisticasGenerales
            ]);

            return response()->json($this->sanitizeUtf8($response));

        } catch (\Exception $e) {
            Log::error('Error en SupervisionApiController::estadisticasGlobales', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al obtener estadísticas de supervisión',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar estadísticas usando stored procedure
     */
    private function generarEstadisticasConSP()
    {
        try {
            Log::info('Ejecutando SP con @Todos=1 para supervisión');

            // Usar cualquier ID de empleado válido y activar @Todos=1
            $idEmpleadoGenerico = 1;
            $resultados = DB::select('EXEC sug_reporte_estatus_documentos ?, ?', [$idEmpleadoGenerico, 1]);

            Log::info('Resultados del SP para supervisión', [
                'total_resultados' => count($resultados),
                'sample' => count($resultados) > 0 ? get_object_vars($resultados[0]) : null
            ]);

            // Obtener nombres reales de campus
            $campusIdsDelSP = collect($resultados)->pluck('campus_id')->unique()->toArray();
            $campusIdsEnteros = array_map('intval', $campusIdsDelSP);

            // Excluir campus no deseados
            $campusIdsValidos = array_diff($campusIdsEnteros, array_map('intval', self::CAMPUS_EXCLUIDOS));

            $campusNombres = campus_model::whereIn('ID_Campus', $campusIdsValidos)
                ->where('Activo', 1)
                ->pluck('Campus', 'ID_Campus')
                ->toArray();

            Log::info('Campus válidos para supervisión', [
                'campus_ids_sp' => $campusIdsDelSP,
                'campus_excluidos' => self::CAMPUS_EXCLUIDOS,
                'campus_validos' => $campusIdsValidos,
                'campus_nombres_count' => count($campusNombres)
            ]);

            $estadisticasPorCampus = [];

            // Procesar resultados del SP
            foreach ($resultados as $fila) {
                $campusId = $fila->campus_id;

                // Saltar campus excluidos
                if (in_array($campusId, array_map('intval', self::CAMPUS_EXCLUIDOS))) {
                    continue;
                }

                $tipoDoc = $fila->tipo_documento;

                // Inicializar campus si no existe
                if (!isset($estadisticasPorCampus[$campusId])) {
                    $campusNombre = isset($campusNombres[$campusId])
                        ? $this->sanitizeUtf8($campusNombres[$campusId])
                        : "Campus $campusId";

                    $estadisticasPorCampus[$campusId] = [
                        'campus_id' => (int)$campusId,
                        'campus_nombre' => $campusNombre,
                        'fiscales' => [
                            'total_documentos' => 0,
                            'pendientes' => 0,
                            'aprobados' => 0,
                            'caducados' => 0,
                            'rechazados' => 0
                        ],
                        'medicos' => [
                            'total_documentos' => 0,
                            'pendientes' => 0,
                            'aprobados' => 0,
                            'caducados' => 0,
                            'rechazados' => 0
                        ]
                    ];
                }

                // Determinar qué tipo de estadística actualizar
                $tipoEstadistica = ($tipoDoc === 'FISCAL') ? 'fiscales' : 'medicos';

                // Actualizar estadísticas usando los nombres exactos del SP
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['total_documentos'] = (int)$fila->Total;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['pendientes'] = (int)$fila->Pendientes;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['aprobados'] = (int)$fila->Vigentes;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['caducados'] = (int)($fila->Caducados ?? 0);
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['rechazados'] = (int)$fila->Rechazados;
            }

            // Calcular totales por campus y propiedades adicionales
            foreach ($estadisticasPorCampus as $campusId => &$campus) {
                // Totales generales del campus
                $campus['total_documentos'] = $campus['fiscales']['total_documentos'] + $campus['medicos']['total_documentos'];
                $campus['total_aprobados'] = $campus['fiscales']['aprobados'] + $campus['medicos']['aprobados'];
                $campus['total_caducados'] = $campus['fiscales']['caducados'] + $campus['medicos']['caducados'];
                $campus['total_pendientes'] = $campus['fiscales']['pendientes'] + $campus['medicos']['pendientes'];
                $campus['total_rechazados'] = $campus['fiscales']['rechazados'] + $campus['medicos']['rechazados'];

                // Porcentaje de cumplimiento
                $campus['porcentaje_cumplimiento'] = $campus['total_documentos'] > 0
                    ? round(($campus['total_aprobados'] / $campus['total_documentos']) * 100)
                    : 0;

                // Indicadores de tipos de documentos
                $campus['tiene_fiscales'] = $campus['fiscales']['total_documentos'] > 0;
                $campus['tiene_medicos'] = $campus['medicos']['total_documentos'] > 0;
            }

            Log::info('Estadísticas procesadas para supervisión', [
                'total_campus_procesados' => count($estadisticasPorCampus),
                'campus_con_documentos' => count(array_filter($estadisticasPorCampus, function($campus) {
                    return $campus['total_documentos'] > 0;
                }))
            ]);

            return $estadisticasPorCampus;

        } catch (\Exception $e) {
            Log::error('Error generando estadísticas con SP para supervisión', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [];
        }
    }

    /**
     * Calcular estadísticas generales a partir de datos por campus
     */
    private function calcularEstadisticasGenerales($estadisticasPorCampus)
    {
        $totalCampus = count($estadisticasPorCampus);
        $totalDocumentos = 0;
        $totalAprobados = 0;
        $totalPendientes = 0;
        $totalCaducados = 0;
        $totalRechazados = 0;
        $campusCriticos = 0;

        foreach ($estadisticasPorCampus as $campus) {
            $totalDocumentos += $campus['total_documentos'];
            $totalAprobados += $campus['total_aprobados'];
            $totalPendientes += $campus['total_pendientes'];
            $totalCaducados += $campus['total_caducados'];
            $totalRechazados += $campus['total_rechazados'];

            // Campus crítico si cumplimiento < 60%
            if ($campus['porcentaje_cumplimiento'] < 60) {
                $campusCriticos++;
            }
        }

        $cumplimientoPromedio = $totalCampus > 0
            ? round(array_sum(array_column($estadisticasPorCampus, 'porcentaje_cumplimiento')) / $totalCampus)
            : 0;

        return [
            'total_campus' => $totalCampus,
            'total_documentos' => $totalDocumentos,
            'total_aprobados' => $totalAprobados,
            'total_pendientes' => $totalPendientes,
            'total_caducados' => $totalCaducados,
            'total_rechazados' => $totalRechazados,
            'cumplimiento_promedio' => $cumplimientoPromedio,
            'campus_criticos' => $campusCriticos,
            'usuarios_activos' => 0 // Por implementar si es necesario
        ];
    }

    /**
     * Generar alertas del sistema
     */
    private function generarAlertas($estadisticasPorCampus)
    {
        $alertas = [];

        foreach ($estadisticasPorCampus as $campus) {
            // Alerta crítica: cumplimiento < 50%
            if ($campus['porcentaje_cumplimiento'] < 50 && $campus['total_documentos'] > 0) {
                $alertas[] = [
                    'campus_nombre' => $campus['campus_nombre'],
                    'tipo_alerta' => 'critico',
                    'mensaje' => 'Cumplimiento crítico: ' . $campus['porcentaje_cumplimiento'] . '%. Requiere intervención inmediata.',
                    'documentos_afectados' => $campus['total_pendientes'] + $campus['total_caducados'] + $campus['total_rechazados']
                ];
            }
            // Alerta de advertencia: cumplimiento entre 50-70%
            elseif ($campus['porcentaje_cumplimiento'] < 70 && $campus['total_documentos'] > 0) {
                $alertas[] = [
                    'campus_nombre' => $campus['campus_nombre'],
                    'tipo_alerta' => 'advertencia',
                    'mensaje' => 'Cumplimiento bajo: ' . $campus['porcentaje_cumplimiento'] . '%. Revisar documentos pendientes.',
                    'documentos_afectados' => $campus['total_pendientes'] + $campus['total_caducados']
                ];
            }
            // Documentos caducados
            elseif ($campus['total_caducados'] > 0) {
                $alertas[] = [
                    'campus_nombre' => $campus['campus_nombre'],
                    'tipo_alerta' => 'info',
                    'mensaje' => 'Documentos caducados requieren renovación.',
                    'documentos_afectados' => $campus['total_caducados']
                ];
            }
        }

        // Limitar a las 10 alertas más críticas
        usort($alertas, function($a, $b) {
            $orden = ['critico' => 3, 'advertencia' => 2, 'info' => 1];
            return $orden[$b['tipo_alerta']] - $orden[$a['tipo_alerta']];
        });

        return array_slice($alertas, 0, 10);
    }

    /**
     * Calcular tendencias (simuladas por ahora)
     */
    private function calcularTendencias($estadisticasPorCampus)
    {
        $tendencias = [];

        foreach ($estadisticasPorCampus as $campus) {
            // Simular tendencia basada en el cumplimiento actual
            $cumplimiento = $campus['porcentaje_cumplimiento'];

            if ($cumplimiento >= 80) {
                $tendencia = 'estable';
                $cambio = rand(-2, 3);
            } elseif ($cumplimiento >= 60) {
                $tendencia = 'subiendo';
                $cambio = rand(2, 8);
            } else {
                $tendencia = 'bajando';
                $cambio = rand(-10, -2);
            }

            $tendencias[] = [
                'campus_id' => $campus['campus_id'],
                'campus_nombre' => $campus['campus_nombre'],
                'tendencia' => $tendencia,
                'cambio_porcentual' => $cambio
            ];
        }

        return $tendencias;
    }
}
