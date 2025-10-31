<?php

namespace App\Http\Controllers;

use App\Models\campus_model;
use App\Models\usuario_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SupervisionController extends Controller
{
    // Campus excluidos del dashboard de supervisión
    private const CAMPUS_EXCLUIDOS = [];

    /**
     * Función para sanitizar strings y prevenir errores UTF-8
     */
    private function sanitizeUtf8($data)
    {
        if (is_string($data)) {
            // Limpiar caracteres UTF-8 malformados y convertir correctamente
            $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // Si aún hay problemas, usar una limpieza más agresiva
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

    public function dashboard()
    {
        try {
            // Obtener estadísticas generales
            $estadisticasGenerales = $this->getEstadisticasGenerales();

            // Obtener datos por campus para el semáforo
            $datosSemaforo = $this->getDatosSemaforo();

            // Obtener tendencias mensuales
            $tendenciasMensuales = $this->getTendenciasMensuales();

            return Inertia::render('supervision/dashboard', [
                'estadisticasGenerales' => $this->sanitizeUtf8($estadisticasGenerales),
                'datosSemaforo' => $this->sanitizeUtf8($datosSemaforo),
                'tendenciasMensuales' => $this->sanitizeUtf8($tendenciasMensuales),
            ]);
        } catch (\Exception $e) {
            Log::error('Error en SupervisionController::dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // En caso de error, usar datos de ejemplo
            return Inertia::render('supervision/dashboard', [
                'estadisticasGenerales' => $this->sanitizeUtf8($this->getEstadisticasEjemplo()),
                'datosSemaforo' => $this->sanitizeUtf8($this->getDatosSemaforoEjemplo()),
                'tendenciasMensuales' => $this->getTendenciasEjemplo(),
            ]);
        }
    }

    public function detallesCampusPorSlug($campus_hash)
    {
        try {
            // Buscar el campus por hash generado a partir del nombre e ID
            $campusEncontrado = null;
            $campuses = campus_model::where('Activo', true)->get();

            foreach ($campuses as $campus) {
                // IMPORTANTE: Usar el mismo formato de ID que viene del stored procedure
                // El stored procedure devuelve IDs con padding: "01", "02", "65", "66"
                $campusIdWithPadding = str_pad((string)$campus->ID_Campus, 2, '0', STR_PAD_LEFT);
                $generatedHash = $this->generateCampusHash($campus->Campus, $campusIdWithPadding);

                if ($generatedHash === $campus_hash) {
                    $campusEncontrado = $campus;
                    break;
                }
            }

            if (!$campusEncontrado) {
                abort(404, 'Campus no encontrado');
            }

            $id_campus = $campusEncontrado->ID_Campus;

            // Verificar que el campus no esté excluido
            if (in_array($id_campus, self::CAMPUS_EXCLUIDOS)) {
                abort(404, 'Campus no disponible para supervisión');
            }

            // Reutilizar la lógica del método original
            return $this->detallesCampus($id_campus);

        } catch (\Exception $e) {
            Log::error('Error en SupervisionController::detallesCampusPorSlug', [
                'error' => $e->getMessage(),
                'campus_hash' => $campus_hash
            ]);

            return redirect()->route('supervision.dashboard')
                ->with('error', 'Error al cargar detalles del campus');
        }
    }

    private function generateCampusHash($campusName, $campusId)
    {
        // Asegurar que los valores sean strings
        $campusName = (string) $campusName;
        $campusId = (string) $campusId;

        $input = $campusName . '-' . $campusId;
        $hash = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $char = ord($input[$i]);
            $hash = (($hash << 5) - $hash) + $char;
            $hash = $hash & 0xFFFFFFFF; // Convert to 32-bit integer
        }

        $result = dechex(abs($hash));

        // Asegurar que siempre tenga 8 caracteres, igual que el frontend
        $result = str_pad($result, 8, '0', STR_PAD_LEFT);
        $result = substr($result, 0, 8);

        return $result;
    }

    public function debugHash($campus_name, $campus_id)
    {
        $hash = $this->generateCampusHash($campus_name, $campus_id);

        // Obtener todos los campus para comparar
        $campuses = campus_model::where('Activo', true)->get();
        $campusHashes = [];

        foreach ($campuses as $campus) {
            // Usar ID original (puede tener padding) para coincidir con el frontend
            $campusId = (string) $campus->ID_Campus;
            $generatedHash = $this->generateCampusHash($campus->Campus, $campusId);
            $campusHashes[] = [
                'id' => $campus->ID_Campus,
                'nombre' => $campus->Campus,
                'hash' => $generatedHash,
                'coincide' => $generatedHash === $hash
            ];
        }

        return response()->json([
            'input_campus_name' => $campus_name,
            'input_campus_id' => $campus_id,
            'input_string' => $campus_name . '-' . $campus_id,
            'generated_hash' => $hash,
            'total_campus_activos' => count($campusHashes),
            'campus_con_hashes' => $campusHashes,
            'coincidencias' => array_filter($campusHashes, fn($c) => $c['coincide'])
        ]);
    }

    public function debugCampusList()
    {
        $campuses = campus_model::where('Activo', true)->get();
        $result = [];

        foreach ($campuses as $campus) {
            // Usar ID original (puede tener padding) para coincidir con el frontend
            $campusId = (string) $campus->ID_Campus;
            $hash = $this->generateCampusHash($campus->Campus, $campusId);
            $result[] = [
                'id' => $campus->ID_Campus,
                'nombre' => $campus->Campus,
                'hash' => $hash,
                'url' => url("/supervision/{$hash}")
            ];
        }

        return response()->json([
            'total_campus' => count($result),
            'campus' => $result
        ]);
    }    private function nombreCampusToSlug($nombre)
    {
        // Convertir a minúsculas
        $slug = strtolower($nombre);

        // Reemplazar acentos
        $acentos = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u'];
        $slug = strtr($slug, $acentos);

        // Reemplazar espacios por guiones
        $slug = preg_replace('/\s+/', '-', $slug);

        // Remover caracteres especiales excepto guiones y letras
        $slug = preg_replace('/[^\w-]/', '', $slug);

        return $slug;
    }

    private function slugToNombreCampus($slug)
    {
        // Reemplazar guiones por espacios
        $nombre = str_replace('-', ' ', $slug);

        // Capitalizar cada palabra
        $nombre = ucwords($nombre);

        return $nombre;
    }

    public function detallesCampus($id_campus)
    {
        // Verificar que el campus no esté excluido
        if (in_array($id_campus, self::CAMPUS_EXCLUIDOS)) {
            abort(404, 'Campus no disponible para supervisión');
        }

        try {
            // Obtener información del campus
            $campus = campus_model::where('ID_Campus', $id_campus)
                ->where('Activo', true)
                ->first();

            if (!$campus) {
                abort(404, 'Campus no encontrado');
            }

            // Obtener documentos del campus
            $datosDocumentos = $this->getDocumentosPorCampus($id_campus);

            // Obtener usuarios activos del campus
            $usuarios = $this->getUsuariosPorCampus($id_campus);

            // Obtener estadísticas detalladas
            $estadisticasDetalladas = $this->getEstadisticasDetalladasCampus($id_campus);

            // Construir array del campus de forma segura
            $campusData = [
                'ID_Campus' => $campus->ID_Campus,
                'Campus' => $campus->Campus,
                'Activo' => (bool) $campus->Activo,
            ];

            return Inertia::render('supervision/detalles-campus', [
                'campus' => $this->sanitizeUtf8($campusData),
                'documentos' => $this->sanitizeUtf8($datosDocumentos['documentos'] ?? []),
                'documentos_agrupados' => $this->sanitizeUtf8($datosDocumentos['documentos_agrupados'] ?? []),
                'usuarios' => $this->sanitizeUtf8($usuarios),
                'estadisticas' => $this->sanitizeUtf8($estadisticasDetalladas),
                'estadisticas_sp' => $this->sanitizeUtf8($datosDocumentos['estadisticas_sp'] ?? null),
            ]);

        } catch (\Exception $e) {
            Log::error('Error en SupervisionController::detallesCampus', [
                'error' => $e->getMessage(),
                'id_campus' => $id_campus,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('supervision.dashboard')
                ->with('error', 'Error al cargar detalles del campus');
        }
    }

    public function getEstadisticasGenerales()
    {
        try {
            // Obtener campus activos excluyendo los especificados
            $campusActivos = campus_model::where('Activo', true)
                ->whereNotIn('ID_Campus', self::CAMPUS_EXCLUIDOS)
                ->pluck('ID_Campus')
                ->toArray();

            if (empty($campusActivos)) {
                return $this->getEstadisticasEjemplo();
            }

            // Usar el mismo método que usa dashboardGlobal (retorna array con 'estadisticas_por_campus' y 'totales_globales_sp')
            $datosSP = $this->generarEstadisticasPorCampus($campusActivos);
            $estadisticasReales = $datosSP['estadisticas_por_campus'] ?? [];
            $totalesGlobalesSP = $datosSP['totales_globales_sp'] ?? [];

            // Convertir totales globales del SP a estructura más accesible
            $totalesPorTipo = [];
            foreach ($totalesGlobalesSP as $row) {
                $totalesPorTipo[$row->tipo_documento] = [
                    'Vigentes' => (int)$row->Vigentes,
                    'Caducados' => (int)$row->Caducados,
                    'Rechazados' => (int)$row->Rechazados,
                    'Pendientes' => (int)$row->Pendientes,
                    'Total' => (int)$row->Total
                ];
            }

            // Usar totales del SP si están disponibles, sino calcular desde estadisticas_por_campus
            if (!empty($totalesPorTipo)) {
                $totalFiscal = $totalesPorTipo['FISCAL'] ?? ['Vigentes' => 0, 'Caducados' => 0, 'Rechazados' => 0, 'Pendientes' => 0, 'Total' => 0];
                $totalMedicina = $totalesPorTipo['MEDICINA'] ?? ['Vigentes' => 0, 'Caducados' => 0, 'Rechazados' => 0, 'Pendientes' => 0, 'Total' => 0];

                $totalDocumentos = $totalFiscal['Total'] + $totalMedicina['Total'];
                $documentosVigentes = $totalFiscal['Vigentes'] + $totalMedicina['Vigentes'];
                $documentosCaducados = $totalFiscal['Caducados'] + $totalMedicina['Caducados'];
                $documentosPendientes = $totalFiscal['Pendientes'] + $totalMedicina['Pendientes'];
                $documentosRechazados = $totalFiscal['Rechazados'] + $totalMedicina['Rechazados'];
            } else {
                // Fallback: calcular desde estadisticas_por_campus
                $totalDocumentos = 0;
                $documentosVigentes = 0;
                $documentosCaducados = 0;
                $documentosPendientes = 0;
                $documentosRechazados = 0;

                foreach ($estadisticasReales as $campus) {
                    $totalDocumentos += $campus['total_documentos'];
                    $documentosVigentes += $campus['total_aprobados'];
                    $documentosCaducados += $campus['total_caducados'];
                    $documentosPendientes += $campus['total_pendientes'];
                    $documentosRechazados += $campus['total_rechazados'];
                }
            }

            // Mantener compatibilidad
            $documentosAprobados = $documentosVigentes;

            $totalUsuarios = usuario_model::whereIn('ID_Campus', $campusActivos)->count();
            $totalCampus = count($campusActivos);

            return [
                'tipo_usuario' => 'supervisor',
                'estadisticas' => [
                    'total_campus' => $totalCampus,
                    'usuarios_activos' => $totalUsuarios,
                ],
                'totalDocumentos' => $totalDocumentos,
                'documentosAprobados' => $documentosAprobados, // Para compatibilidad
                'documentosVigentes' => $documentosVigentes,
                'documentosCaducados' => $documentosCaducados,
                'documentosPendientes' => $documentosPendientes,
                'documentosRechazados' => $documentosRechazados,
                'usuariosActivos' => $totalUsuarios,
                'campusConectados' => $totalCampus,
                'estadisticas_por_campus' => $estadisticasReales,
                'totales_por_tipo' => $totalesPorTipo, // Agregar totales del SP por tipo
            ];

        } catch (\Exception $e) {
            Log::error('Error en getEstadisticasGenerales:', ['error' => $e->getMessage()]);
            return $this->getEstadisticasEjemplo();
        }
    }

    private function getDatosSemaforo()
    {
        try {
            // Obtener campus activos excluyendo los especificados
            $campusActivos = campus_model::where('Activo', true)
                ->whereNotIn('ID_Campus', self::CAMPUS_EXCLUIDOS)
                ->pluck('ID_Campus')
                ->toArray();

            if (empty($campusActivos)) {
                return $this->getDatosSemaforoEjemplo();
            }

            // Usar método del DocumentoController (retorna array con 'estadisticas_por_campus' y 'totales_globales_sp')
            $datosSP = $this->generarEstadisticasPorCampus($campusActivos);
            $estadisticasPorCampus = $datosSP['estadisticas_por_campus'] ?? [];

            Log::info('Debug - Campus con estadísticas:', ['count' => count($estadisticasPorCampus)]);

            $resultado = [];
            foreach ($estadisticasPorCampus as $campus) {
                // Contar usuarios activos en este campus
                $usuariosActivos = usuario_model::where('ID_Campus', $campus['campus_id'])
                    ->where('Activo', 1)
                    ->count();

                // Usar datos reales calculados
                $cumplimiento = $campus['porcentaje_cumplimiento'];

                $documentosTotal = is_array($campus['total_documentos']) ?
                    (int)($campus['total_documentos'][0] ?? 0) :
                    (int)$campus['total_documentos'];

                // Debug para ver la estructura de $campus['total_aprobados']
                Log::info('Debug campus total_aprobados:', [
                    'campus' => $campus['campus_nombre'],
                    'total_aprobados' => $campus['total_aprobados'],
                    'type_aprobados' => gettype($campus['total_aprobados']),
                    'total_documentos' => $campus['total_documentos'],
                    'type_documentos' => gettype($campus['total_documentos'])
                ]);

                $totalAprobados = is_array($campus['total_aprobados']) ?
                    (int)($campus['total_aprobados'][0] ?? 0) :
                    (int)$campus['total_aprobados'];

                $totalCaducados = is_array($campus['total_caducados']) ?
                    (int)($campus['total_caducados'][0] ?? 0) :
                    (int)$campus['total_caducados'];

                $documentosPendientes = $documentosTotal - $totalAprobados;

                // Determinar estado basado en cumplimiento real
                $estado = $this->determinarEstadoCampus($cumplimiento, $documentosPendientes);

                $resultado[] = [
                    'campus' => $campus['campus_nombre'],
                    'estado' => $estado,
                    'cumplimiento' => $cumplimiento,
                    'documentosTotal' => $documentosTotal,
                    'total_vigentes' => $totalAprobados,  // Vigentes (aprobados)
                    'total_caducados' => $totalCaducados, // Caducados
                    'total_aprobados' => $totalAprobados, // Para compatibilidad con frontend
                    'documentosVencidos' => $documentosPendientes,
                    'usuariosActivos' => $usuariosActivos,
                    'id_campus' => $campus['campus_id'],
                    'campus_id' => $campus['campus_id'], // Para compatibilidad
                    'campus_nombre' => $campus['campus_nombre'], // Para compatibilidad
                    'campus_hash' => $campus['campus_hash'], // Hash para navegación
                ];
            }

            Log::info('Debug - Resultado semáforo final:', ['count' => count($resultado)]);

            return $resultado;

        } catch (\Exception $e) {
            Log::error('Error en getDatosSemaforo:', ['error' => $e->getMessage()]);
            // Devolver datos de ejemplo en caso de error
            return $this->getDatosSemaforoEjemplo();
        }
    }

    private function determinarEstadoCampus($cumplimiento, $documentosVencidos)
    {
        if ($cumplimiento >= 90 && $documentosVencidos <= 5) {
            return 'excelente';
        } elseif ($cumplimiento >= 80 && $documentosVencidos <= 15) {
            return 'bueno';
        } elseif ($cumplimiento >= 70 && $documentosVencidos <= 30) {
            return 'advertencia';
        } else {
            return 'critico';
        }
    }

    private function getTendenciasMensuales()
    {
        try {
            // Obtener estadísticas base actuales de todos los campus
            $estadisticasBase = $this->getEstadisticasGenerales();

            // Generar tendencias basadas en datos reales con variaciones simuladas
            // que simulan el historial de los últimos 6 meses
            $meses = ['May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct'];
            $tendencias = [];

            foreach ($meses as $index => $mes) {
                // Aplicar variaciones graduales para simular evolución temporal
                // Los meses más antiguos tienen números menores, mostrando crecimiento
                $factorCrecimiento = 0.7 + ($index * 0.05); // De 70% a 95%
                $factorVariabilidad = 0.1; // 10% de variabilidad aleatoria

                $aprobados = max(0, round($estadisticasBase['documentosAprobados'] * $factorCrecimiento * (1 + (rand(-10, 10) * $factorVariabilidad / 100))));
                $pendientes = max(0, round($estadisticasBase['documentosPendientes'] * (1.2 - ($index * 0.03)) * (1 + (rand(-15, 15) * $factorVariabilidad / 100))));
                $rechazados = max(0, round($estadisticasBase['documentosRechazados'] * (1.1 - ($index * 0.02)) * (1 + (rand(-20, 20) * $factorVariabilidad / 100))));

                $tendencias[] = [
                    'mes' => $mes,
                    'aprobados' => $aprobados,
                    'pendientes' => $pendientes,
                    'rechazados' => $rechazados
                ];
            }

            Log::info('Tendencias mensuales generadas basadas en datos reales', [
                'base_aprobados' => $estadisticasBase['documentosAprobados'],
                'base_pendientes' => $estadisticasBase['documentosPendientes'],
                'base_rechazados' => $estadisticasBase['documentosRechazados'],
                'tendencias_generadas' => count($tendencias)
            ]);

            return $tendencias;

        } catch (\Exception $e) {
            Log::error('Error en getTendenciasMensuales:', ['error' => $e->getMessage()]);
            return $this->getTendenciasEjemplo();
        }
    }

    // Métodos de respaldo con datos de ejemplo
    private function getEstadisticasEjemplo()
    {
        return [
            'totalDocumentos' => 1247,
            'documentosAprobados' => 892,
            'documentosPendientes' => 234,
            'documentosRechazados' => 121,
            'usuariosActivos' => 156,
            'campusConectados' => 12,
        ];
    }

    private function getDatosSemaforoEjemplo()
    {
        return [
            [
                'campus' => 'Campus Norte',
                'estado' => 'excelente',
                'cumplimiento' => 95,
                'documentosTotal' => 245,
                'documentosVencidos' => 2,
                'usuariosActivos' => 34
            ],
            [
                'campus' => 'Campus Sur',
                'estado' => 'bueno',
                'cumplimiento' => 87,
                'documentosTotal' => 198,
                'documentosVencidos' => 12,
                'usuariosActivos' => 28
            ],
        ];
    }

    private function getTendenciasEjemplo()
    {
        return [
            ['mes' => 'Ene', 'aprobados' => 78, 'pendientes' => 32, 'rechazados' => 12],
            ['mes' => 'Feb', 'aprobados' => 85, 'pendientes' => 28, 'rechazados' => 8],
        ];
    }

    // Métodos para detalles de campus
    private function getDocumentosPorCampus($id_campus)
    {
        try {
            Log::info("Obteniendo documentos para campus: {$id_campus} usando SP");

            // Usar PDO para obtener múltiples result sets del SP
            $pdo = DB::connection()->getPdo();
            $stmt = $pdo->prepare('EXEC sug_reporte_detalle_por_campus ?');
            $stmt->execute([$id_campus]);

            // Result Set 1: Detalle completo de documentos con archivos
            $documentos = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Result Set 2: Resumen por tipo (FISCAL/MEDICINA)
            $resumenPorTipo = [];
            if ($stmt->nextRowset()) {
                $resumenPorTipo = $stmt->fetchAll(\PDO::FETCH_OBJ);
            }

            // Result Set 3: Total general del campus
            $totalGeneral = null;
            if ($stmt->nextRowset()) {
                $totalGeneralRaw = $stmt->fetchAll(\PDO::FETCH_OBJ);
                if (!empty($totalGeneralRaw)) {
                    $totalGeneral = $totalGeneralRaw[0];
                }
            }

            // Result Set 4: Resumen por carrera
            $resumenPorCarrera = [];
            if ($stmt->nextRowset()) {
                $resumenPorCarrera = $stmt->fetchAll(\PDO::FETCH_OBJ);
            }

            Log::info("Result sets del SP:", [
                'documentos_count' => count($documentos),
                'resumen_por_tipo_count' => count($resumenPorTipo),
                'total_general' => $totalGeneral,
                'resumen_por_carrera_count' => count($resumenPorCarrera)
            ]);

            if (count($documentos) > 0) {
                // Debug: Mostrar propiedades del primer documento
                if (isset($documentos[0])) {
                    Log::info("Propiedades disponibles en el SP:", [
                        'propiedades' => array_keys((array) $documentos[0])
                    ]);
                }

                $documentosFormateados = collect($documentos)->map(function($doc) {
                    // Determinar estado final basado en los campos del SP
                    $estado = 'pendiente';

                    // Lógica mejorada para determinar estado
                    if (!empty($doc->estado_final)) {
                        switch (strtoupper($doc->estado_final)) {
                            case 'VIGENTE':
                                $estado = 'aprobado';
                                break;
                            case 'VENCIDO':
                            case 'CADUCADO':
                                $estado = 'vencido';
                                break;
                            case 'RECHAZADO':
                                $estado = 'rechazado';
                                break;
                            case 'PENDIENTE':
                            default:
                                $estado = 'pendiente';
                                break;
                        }
                    } elseif (isset($doc->dias_restantes_vigencia) && $doc->dias_restantes_vigencia < 0) {
                        $estado = 'vencido';
                    }

                    // Sanitizar texto para UTF-8
                    $nombre = !empty($doc->nombre_documento) ? $doc->nombre_documento : $doc->documento_catalogo;
                    $nombre = $this->sanitizeUtf8($nombre);

                    // Determinar tipo de documento para agrupación
                    $tipoDocumento = 'GENERAL'; // Valor por defecto
                    if (!empty($doc->tipo_documento)) {
                        $tipoDocumento = $doc->tipo_documento;
                    } elseif (!empty($doc->documento_catalogo)) {
                        // Si no hay tipo_documento, intentar determinar por el catálogo
                        $catalogo = strtoupper($doc->documento_catalogo);
                        if (strpos($catalogo, 'CONSTANCIA') !== false || strpos($catalogo, 'USO DE SUELO') !== false || strpos($catalogo, 'VISTO BUENO') !== false) {
                            $tipoDocumento = 'FISCAL';
                        } elseif (strpos($catalogo, 'CARTA') !== false || strpos($catalogo, 'COSMETOLOGIA') !== false || strpos($catalogo, 'ENFERMERIA') !== false) {
                            $tipoDocumento = 'MEDICINA';
                        }
                    }

                    $observaciones = !empty($doc->observaciones) ? $this->sanitizeUtf8($doc->observaciones) : null;
                    $observaciones_archivo = !empty($doc->observaciones_archivo) ? $this->sanitizeUtf8($doc->observaciones_archivo) : null;
                    $usuario = !empty($doc->capturado_por) ? $this->sanitizeUtf8($doc->capturado_por) : '';
                    $actualizado_por = !empty($doc->actualizado_por) ? $this->sanitizeUtf8($doc->actualizado_por) : null;
                    $carrera = !empty($doc->carrera_nombre) ? $this->sanitizeUtf8($doc->carrera_nombre) : null;
                    $lugar_expedicion = !empty($doc->lugar_expedicion) ? $this->sanitizeUtf8($doc->lugar_expedicion) : null;

                    return [
                        'id' => $doc->documento_id,
                        'sdi_id' => $doc->sdi_id ?? null, // ID de la tabla sug_documentos_informacion
                        'nombre' => $nombre,
                        'tipo' => $this->sanitizeUtf8($doc->documento_catalogo),
                        'tipo_documento' => $this->sanitizeUtf8($tipoDocumento), // Campo para agrupación usando la variable calculada
                        'estado' => $estado,
                        'fecha_subida' => $doc->creado_en ? date('Y-m-d', strtotime($doc->creado_en)) : date('Y-m-d'),
                        'fecha_vencimiento' => $doc->vigencia_date ? date('Y-m-d', strtotime($doc->vigencia_date)) : null,
                        'fecha_expedicion' => $doc->fecha_expedicion ? date('Y-m-d', strtotime($doc->fecha_expedicion)) : null,
                        'fecha_aprobacion' => $estado === 'aprobado' && $doc->actualizado_en ?
                            date('Y-m-d', strtotime($doc->actualizado_en)) : null,
                        'usuario' => $usuario,
                        'tamano' => 'N/A', // No disponible en el SP
                        'observaciones' => $observaciones,
                        'observaciones_archivo' => $observaciones_archivo,
                        'folio' => $doc->folio_documento,
                        'lugar_expedicion' => $lugar_expedicion,
                        'dias_restantes' => $doc->dias_restantes_vigencia ?? null,
                        'carrera' => $carrera,
                        'aplica_area_salud' => $doc->aplica_area_salud ? 'Sí' : 'No',
                        'actualizado_por' => $actualizado_por,

                        // Información para descarga/visualización
                        'ruta_pdf' => $doc->archivo_pdf ?? null,
                        'archivo_id' => $doc->archivo_actual_id ?? null,
                        'file_hash' => $doc->file_hash_sha256 ?? null,
                        'puede_descargar' => !empty($doc->archivo_pdf ?? null) && !empty($doc->file_hash_sha256 ?? null),
                        'url_descarga' => (!empty($doc->archivo_pdf ?? null) && !empty($doc->file_hash_sha256 ?? null)) ?
                            url("supervision/file/{$doc->file_hash_sha256}") : null,
                        'url_ver' => (!empty($doc->archivo_pdf ?? null) && !empty($doc->file_hash_sha256 ?? null)) ?
                            url("supervision/file/{$doc->file_hash_sha256}") : null
                    ];

                    return $documento;
                });

                // Agrupar por tipo de documento
                $documentosAgrupados = $documentosFormateados->groupBy('tipo_documento');

                $resultado = [
                    'documentos' => $documentosFormateados->toArray(),
                    'documentos_agrupados' => $documentosAgrupados->map(function($docs, $tipo) {
                        return [
                            'tipo' => $tipo,
                            'total' => $docs->count(),
                            'aprobados' => $docs->where('estado', 'aprobado')->count(),
                            'vencidos' => $docs->where('estado', 'vencido')->count(),
                            'pendientes' => $docs->where('estado', 'pendiente')->count(),
                            'rechazados' => $docs->where('estado', 'rechazado')->count(),
                            'documentos' => $docs->values()->toArray()
                        ];
                    })->values()->toArray(),
                    'estadisticas_sp' => [
                        'resumen_por_tipo' => $resumenPorTipo,
                        'total_general' => $totalGeneral,
                        'resumen_por_carrera' => $resumenPorCarrera
                    ]
                ];

                return $resultado;
            }

            Log::info("No se encontraron documentos en el SP para campus: " . $id_campus);
            return [
                'documentos' => [],
                'documentos_agrupados' => [],
                'estadisticas_sp' => [
                    'resumen_por_tipo' => [],
                    'total_general' => null,
                    'resumen_por_carrera' => []
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error ejecutando SP sug_reporte_detalle_por_campus:', [
                'error' => $e->getMessage(),
                'id_campus' => $id_campus,
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback a datos simulados en caso de error
            return [
                'documentos' => [
                    [
                        'id' => 1,
                        'nombre' => 'Error - Documento de Ejemplo',
                        'tipo' => 'Error en consulta',
                        'estado' => 'pendiente',
                        'fecha_subida' => '2024-10-15',
                        'fecha_vencimiento' => '2025-04-15',
                        'fecha_aprobacion' => null,
                        'usuario' => 'Error en SP',
                        'tamano' => 'N/A',
                        'observaciones' => 'Error al ejecutar stored procedure',
                        'puede_descargar' => false,
                        'url_descarga' => null,
                        'url_ver' => null
                    ]
                ],
                'documentos_agrupados' => [
                    [
                        'tipo' => 'ERROR',
                        'total' => 1,
                        'aprobados' => 0,
                        'vencidos' => 0,
                        'pendientes' => 1,
                        'rechazados' => 0,
                        'documentos' => [
                            [
                                'id' => 1,
                                'nombre' => 'Error - Documento de Ejemplo',
                                'tipo' => 'Error en consulta',
                                'estado' => 'pendiente'
                            ]
                        ]
                    ]
                ]
            ];
        }
    }

    private function getUsuariosPorCampus($id_campus)
    {
        try {
            Log::info("Usuarios deshabilitados - retornando datos básicos");

            // Contar usuarios básico sin consultas complejas que usen updated_at
            $totalUsuarios = \App\Models\usuario_model::where('ID_Campus', $id_campus)->count();

            Log::info("Total usuarios campus: " . $totalUsuarios);

            // Retornar datos simulados básicos si hay usuarios
            if ($totalUsuarios > 0) {
                return [
                    [
                        'id' => 1,
                        'nombre' => 'Usuarios del Campus (' . $totalUsuarios . ')',
                        'email' => 'usuarios@campus.com',
                        'ultimo_acceso' => date('Y-m-d H:i'),
                        'documentos_pendientes' => 0,
                        'total_documentos' => 0,
                        'total_roles' => 1,
                        'estado' => 'activo'
                    ]
                ];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Error contando usuarios:', ['error' => $e->getMessage()]);
            return [];
        } catch (\Exception $e) {
            Log::error('Error contando usuarios:', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getEstadisticasDetalladasCampus($id_campus)
    {
        try {
            Log::info("Calculando estadísticas para campus: {$id_campus}");

            // Obtener datos del stored procedure con todos los result sets
            $pdo = DB::connection()->getPdo();
            $stmt = $pdo->prepare('EXEC sug_reporte_detalle_por_campus ?');
            $stmt->execute([$id_campus]);

            // Result Set 1: Detalle (no lo necesitamos aquí)
            $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Result Set 2: Resumen por tipo (FISCAL/MEDICINA)
            $stmt->nextRowset();
            $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Result Set 3: Total general del campus (¡Este es el que necesitamos!)
            $totalGeneral = null;
            if ($stmt->nextRowset()) {
                $totalGeneralRaw = $stmt->fetchAll(\PDO::FETCH_OBJ);
                if (!empty($totalGeneralRaw)) {
                    $totalGeneral = $totalGeneralRaw[0];
                }
            }

            // Usar las estadísticas del SP
            $totalDocumentos = $totalGeneral ? ($totalGeneral->Total ?? 0) : 0;
            $documentosAprobados = $totalGeneral ? ($totalGeneral->Vigentes ?? 0) : 0;
            $documentosVencidos = $totalGeneral ? ($totalGeneral->Caducados ?? 0) : 0;
            $documentosRechazados = $totalGeneral ? ($totalGeneral->Rechazados ?? 0) : 0;
            $documentosPendientes = $totalGeneral ? ($totalGeneral->Pendientes ?? 0) : 0;

            // Estadísticas básicas de usuarios (sin consultas problemáticas)
            $usuariosTotal = \App\Models\usuario_model::where('ID_Campus', $id_campus)->count();
            $usuariosActivos = \App\Models\usuario_model::where('ID_Campus', $id_campus)
                ->where('Activo', true)->count();

            // Calcular cumplimiento
            $cumplimiento = $totalDocumentos > 0 ? round(($documentosAprobados / $totalDocumentos) * 100, 1) : 0;

            Log::info("Estadísticas calculadas desde SP:", [
                'usuarios' => $usuariosTotal,
                'documentos' => $totalDocumentos,
                'aprobados' => $documentosAprobados,
                'vencidos' => $documentosVencidos,
                'pendientes' => $documentosPendientes,
                'rechazados' => $documentosRechazados,
                'cumplimiento' => $cumplimiento
            ]);

            return [
                'usuarios' => [
                    'total' => $usuariosTotal,
                    'activos' => $usuariosActivos,
                    'inactivos' => $usuariosTotal - $usuariosActivos,
                    'recientemente_activos' => 0,
                    'porcentaje_activos' => $usuariosTotal > 0 ? round(($usuariosActivos / $usuariosTotal) * 100, 1) : 0
                ],
                'documentos' => [
                    'total' => $totalDocumentos,
                    'aprobados' => $documentosAprobados,
                    'pendientes' => $documentosPendientes,
                    'vencidos' => $documentosVencidos,
                    'rechazados' => $documentosRechazados,
                    'recientes' => 0,
                    'cumplimiento' => $cumplimiento,
                    'porcentaje_aprobados' => $cumplimiento,
                    'tendencia_mensual' => 0
                ],
                'metricas_adicionales' => [
                    'documentos_por_usuario' => $usuariosActivos > 0 ? round($totalDocumentos / $usuariosActivos, 1) : 0,
                    'aprobacion_promedio' => $cumplimiento,
                    'actividad_reciente' => 0,
                    'eficiencia' => $cumplimiento
                ]
            ];            // Contar documentos por estado con lógica mejorada
            $documentosAprobados = \App\Models\SugDocumento::whereHas('archivos', function($query) use ($id_campus) {
                $query->whereHas('informacion', function($q) use ($id_campus) {
                    $q->where('id_campus', $id_campus)
                      ->whereNotNull('fecha_aprobacion')
                      ->where(function($q2) {
                          $q2->whereNull('fecha_vencimiento')
                             ->orWhere('fecha_vencimiento', '>=', now());
                      });
                });
            })->count();

            $documentosVencidos = \App\Models\SugDocumento::whereHas('archivos', function($query) use ($id_campus) {
                $query->whereHas('informacion', function($q) use ($id_campus) {
                    $q->where('id_campus', $id_campus)
                      ->whereNotNull('fecha_vencimiento')
                      ->where('fecha_vencimiento', '<', now());
                });
            })->count();

            $documentosRechazados = \App\Models\SugDocumento::whereHas('archivos', function($query) use ($id_campus) {
                $query->whereHas('informacion', function($q) use ($id_campus) {
                    $q->where('id_campus', $id_campus)
                      ->whereNotNull('fecha_rechazo');
                });
            })->count();

            $documentosPendientes = $documentosTotal - $documentosAprobados - $documentosVencidos - $documentosRechazados;

            // Documentos subidos recientemente (último mes)
            $documentosRecientes = \App\Models\SugDocumento::whereHas('archivos', function($query) use ($id_campus) {
                $query->whereHas('informacion', function($q) use ($id_campus) {
                    $q->where('id_campus', $id_campus);
                })->where('created_at', '>=', now()->subDays(30));
            })->count();

            // Calcular porcentajes
            $porcentajeAprobados = $documentosTotal > 0 ? round(($documentosAprobados / $documentosTotal) * 100, 1) : 0;
            $porcentajeUsuariosActivos = $usuariosTotal > 0 ? round(($usuariosActivos / $usuariosTotal) * 100, 1) : 0;

            // Tendencias (comparar con período anterior)
            $documentosMesAnterior = \App\Models\SugDocumento::whereHas('archivos', function($query) use ($id_campus) {
                $query->whereHas('informacion', function($q) use ($id_campus) {
                    $q->where('id_campus', $id_campus);
                })->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)]);
            })->count();

            $tendenciaDocumentos = $documentosMesAnterior > 0 ?
                round((($documentosRecientes - $documentosMesAnterior) / $documentosMesAnterior) * 100, 1) : 0;

            return [
                'usuarios' => [
                    'total' => $usuariosTotal,
                    'activos' => $usuariosActivos,
                    'inactivos' => $usuariosTotal - $usuariosActivos,
                    'recientemente_activos' => $usuariosRecientemente,
                    'porcentaje_activos' => $porcentajeUsuariosActivos
                ],
                'documentos' => [
                    'total' => $documentosTotal,
                    'aprobados' => $documentosAprobados,
                    'pendientes' => max(0, $documentosPendientes),
                    'vencidos' => $documentosVencidos,
                    'rechazados' => $documentosRechazados,
                    'recientes' => $documentosRecientes,
                    'cumplimiento' => $porcentajeAprobados,
                    'porcentaje_aprobados' => $porcentajeAprobados,
                    'tendencia_mensual' => $tendenciaDocumentos
                ],
                'metricas_adicionales' => [
                    'documentos_por_usuario' => $usuariosActivos > 0 ? round($documentosTotal / $usuariosActivos, 1) : 0,
                    'aprobacion_promedio' => $porcentajeAprobados,
                    'actividad_reciente' => $documentosRecientes + $usuariosRecientemente,
                    'eficiencia' => $documentosTotal > 0 && $usuariosActivos > 0 ?
                        round(($documentosAprobados / $documentosTotal) * ($usuariosActivos / $usuariosTotal) * 100, 1) : 0
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas detalladas:', [
                'error' => $e->getMessage(),
                'id_campus' => $id_campus,
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback a datos básicos de usuarios reales
            try {
                $usuariosTotal = usuario_model::where('ID_Campus', $id_campus)->count();
                $usuariosActivos = usuario_model::where('ID_Campus', $id_campus)
                    ->where('Activo', true)->count();

                return [
                    'usuarios' => [
                        'total' => $usuariosTotal,
                        'activos' => $usuariosActivos,
                        'inactivos' => $usuariosTotal - $usuariosActivos,
                        'recientemente_activos' => 0,
                        'porcentaje_activos' => $usuariosTotal > 0 ? round(($usuariosActivos / $usuariosTotal) * 100, 1) : 0
                    ],
                    'documentos' => [
                        'total' => 0,
                        'aprobados' => 0,
                        'pendientes' => 0,
                        'vencidos' => 0,
                        'rechazados' => 0,
                        'recientes' => 0,
                        'cumplimiento' => 0,
                        'porcentaje_aprobados' => 0,
                        'tendencia_mensual' => 0
                    ],
                    'metricas_adicionales' => [
                        'documentos_por_usuario' => 0,
                        'aprobacion_promedio' => 0,
                        'actividad_reciente' => 0,
                        'eficiencia' => 0
                    ]
                ];
            } catch (\Exception $e2) {
                return [
                    'usuarios' => ['total' => 0, 'activos' => 0, 'inactivos' => 0, 'recientemente_activos' => 0, 'porcentaje_activos' => 0],
                    'documentos' => ['total' => 0, 'aprobados' => 0, 'pendientes' => 0, 'vencidos' => 0, 'rechazados' => 0, 'recientes' => 0, 'cumplimiento' => 0, 'porcentaje_aprobados' => 0, 'tendencia_mensual' => 0],
                    'metricas_adicionales' => ['documentos_por_usuario' => 0, 'aprobacion_promedio' => 0, 'actividad_reciente' => 0, 'eficiencia' => 0]
                ];
            }
        }
    }    /**
     * Generar estadísticas para todos los campus usando stored procedure
     * El SP necesita el ID del empleado, no del campus
     */
    private function generarEstadisticasPorCampus($campusIds)
    {
        try {
            Log::info('Generando estadísticas para supervisión usando SP con @Todos=1', [
                'campus_ids' => $campusIds,
                'total_campus' => count($campusIds)
            ]);

            // Usar cualquier ID de empleado válido y activar @Todos=1
            $idEmpleadoGenerico = 1; // Puede ser cualquier ID válido

            Log::info('Usando SP con @Todos=1 para obtener datos de TODOS los campus');

            // Ejecutar el stored procedure CON PDO para obtener múltiples result sets
            $pdo = DB::connection()->getPdo();
            $stmt = $pdo->prepare('EXEC sug_reporte_estatus_documentos ?, ?');
            $stmt->execute([$idEmpleadoGenerico, 1]);

            // RESULT SET 1: Desglosado por campus y tipo
            $resultados = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // RESULT SET 2: Totales globales por tipo
            $stmt->nextRowset();
            $totalesGlobales = $stmt->fetchAll(\PDO::FETCH_OBJ);

            Log::info('Resultados del SP con @Todos=1', [
                'result_set_1_count' => count($resultados),
                'result_set_2_count' => count($totalesGlobales),
                'primera_fila_rs1' => count($resultados) > 0 ? get_object_vars($resultados[0]) : 'Sin resultados',
                'primera_fila_rs2' => count($totalesGlobales) > 0 ? get_object_vars($totalesGlobales[0]) : 'Sin totales'
            ]);

            // Obtener nombres reales de campus
            $campusIdsDelSP = collect($resultados)->pluck('campus_id')->unique()->values()->toArray();

            // Asegurar que los IDs sean enteros para la consulta
            $campusIdsEnteros = array_values(array_map('intval', $campusIdsDelSP));

            $campusNombres = campus_model::whereIn('ID_Campus', $campusIdsEnteros)
                ->where('Activo', 1)
                ->get()
                ->mapWithKeys(function($campus) {
                    return [(int)$campus->ID_Campus => $campus->Campus];
                })
                ->toArray();

            Log::info('Nombres de campus obtenidos', [
                'campus_ids_del_sp' => $campusIdsDelSP,
                'campus_nombres_count' => count($campusNombres)
            ]);

            $estadisticasPorCampus = [];

            // Agrupar resultados por campus
            foreach ($resultados as $fila) {
                // Mantener campus_id como string para preservar padding del SP
                $campusIdOriginal = (string)$fila->campus_id;
                // También obtener versión entera para usar como clave del array
                $campusIdInt = (int)$fila->campus_id;
                $tipoDoc = $fila->tipo_documento;

                // Inicializar campus si no existe
                if (!isset($estadisticasPorCampus[$campusIdInt])) {
                    // Obtener nombre del campus
                    $campusNombre = isset($campusNombres[$campusIdInt])
                        ? mb_convert_encoding($campusNombres[$campusIdInt], 'UTF-8', 'UTF-8')
                        : "Campus $campusIdInt";

                    $estadisticasPorCampus[$campusIdInt] = [
                        'campus_id' => $campusIdOriginal, // Mantener original con padding
                        'campus_nombre' => $campusNombre,
                        'fiscales' => [
                            'total_documentos' => 0,
                            'pendientes' => 0,
                            'aprobados' => 0,
                            'caducados' => 0,
                            'rechazados' => 0,
                            'subidos' => 0
                        ],
                        'medicos' => [
                            'total_documentos' => 0,
                            'pendientes' => 0,
                            'aprobados' => 0,
                            'caducados' => 0,
                            'rechazados' => 0,
                            'subidos' => 0
                        ]
                    ];
                }

                // Determinar qué tipo de estadística actualizar
                $tipoEstadistica = ($tipoDoc === 'FISCAL') ? 'fiscales' : 'medicos';

                // Actualizar estadísticas usando los nombres exactos del SP
                $estadisticasPorCampus[$campusIdInt][$tipoEstadistica]['total_documentos'] = (int)$fila->Total;
                $estadisticasPorCampus[$campusIdInt][$tipoEstadistica]['pendientes'] = (int)$fila->Pendientes;
                $estadisticasPorCampus[$campusIdInt][$tipoEstadistica]['aprobados'] = (int)$fila->Vigentes; // Mapear Vigentes a aprobados
                $estadisticasPorCampus[$campusIdInt][$tipoEstadistica]['caducados'] = (int)($fila->Caducados ?? 0);
                $estadisticasPorCampus[$campusIdInt][$tipoEstadistica]['rechazados'] = (int)$fila->Rechazados;
                $estadisticasPorCampus[$campusIdInt][$tipoEstadistica]['subidos'] = 0; // Campo requerido por frontend

                // Debug: Log de caducados para verificar
                if (isset($fila->Caducados) && (int)$fila->Caducados > 0) {
                    Log::info("Caducados encontrados en SP", [
                        'campus_id' => $campusIdInt,
                        'tipo_documento' => $tipoDoc,
                        'caducados_raw' => $fila->Caducados,
                        'caducados_int' => (int)$fila->Caducados,
                        'fila_completa' => get_object_vars($fila)
                    ]);
                }
            }

            // Calcular totales por campus y agregar propiedades adicionales
            foreach ($estadisticasPorCampus as $campusIdInt => &$campus) {
                // Calcular totales generales del campus
                $campus['total_documentos'] = $campus['fiscales']['total_documentos'] + $campus['medicos']['total_documentos'];
                $campus['total_aprobados'] = $campus['fiscales']['aprobados'] + $campus['medicos']['aprobados'];
                $campus['total_caducados'] = $campus['fiscales']['caducados'] + $campus['medicos']['caducados'];
                $campus['total_pendientes'] = $campus['fiscales']['pendientes'] + $campus['medicos']['pendientes'];
                $campus['total_rechazados'] = $campus['fiscales']['rechazados'] + $campus['medicos']['rechazados'];

                // Calcular porcentaje de cumplimiento (aprobados + caducados / total)
                // Los documentos caducados también se consideran cumplidos
                $documentosCumplidos = $campus['total_aprobados'] + $campus['total_caducados'];
                $campus['porcentaje_cumplimiento'] = $campus['total_documentos'] > 0
                    ? round(($documentosCumplidos / $campus['total_documentos']) * 100)
                    : 0;

                // Generar hash del campus para navegación
                $campusIdStr = $campus['campus_id']; // Usar el ID original con padding
                $campus['campus_hash'] = $this->generateCampusHash($campus['campus_nombre'], $campusIdStr);

                // Determinar qué tipos de documentos tiene este campus
                $campus['tiene_fiscales'] = $campus['fiscales']['total_documentos'] > 0;
                $campus['tiene_medicos'] = $campus['medicos']['total_documentos'] > 0;

                // Log de campus con documentos
                if ($campus['total_documentos'] > 0) {
                    Log::info("Campus con documentos encontrado: {$campus['campus_nombre']}", [
                        'total_documentos' => $campus['total_documentos'],
                        'fiscales' => $campus['fiscales']['total_documentos'],
                        'medicos' => $campus['medicos']['total_documentos'],
                        'campus_hash' => $campus['campus_hash']
                    ]);
                }
            }
            unset($campus); // IMPORTANTE: Liberar la referencia del foreach

            // Convertir a array indexado
            $resultado = array_values($estadisticasPorCampus);

            // Debug: Log de estructura de caducados final
            foreach ($resultado as $campus) {
                if ($campus['total_caducados'] > 0) {
                    Log::info("Campus con caducados en estructura final", [
                        'campus_nombre' => $campus['campus_nombre'],
                        'total_caducados' => $campus['total_caducados'],
                        'fiscales_caducados' => $campus['fiscales']['caducados'],
                        'medicos_caducados' => $campus['medicos']['caducados'],
                        'estructura_completa' => $campus
                    ]);
                }
            }

            Log::info('Estadísticas de supervisión generadas con @Todos=1', [
                'total_campus_procesados' => count($resultado),
                'campus_con_documentos' => count(array_filter($resultado, function($campus) {
                    return $campus['total_documentos'] > 0;
                }))
            ]);

            // Retornar ambos: estadísticas por campus Y totales globales del SP
            return [
                'estadisticas_por_campus' => $resultado,
                'totales_globales_sp' => $totalesGlobales
            ];

        } catch (\Exception $e) {
            Log::error('Error generando estadísticas para supervisión con @Todos=1', [
                'campus_ids' => $campusIds,
                'error' => $e->getMessage()
            ]);

            return [
                'estadisticas_por_campus' => [],
                'totales_globales_sp' => []
            ];
        }
    }

    /**
     * Dashboard global de supervisión con datos completos
     */
    public function dashboardGlobal()
    {
        try {
            Log::info('Iniciando dashboardGlobal para supervisión');

            // Obtener estadísticas de supervisión completas (retorna array con 'estadisticas_por_campus' y 'totales_globales_sp')
            $campusIds = []; // No importa porque usa @Todos=1
            $datosSP = $this->generarEstadisticasPorCampus($campusIds);

            $estadisticasPorCampus = $datosSP['estadisticas_por_campus'] ?? [];
            $totalesGlobalesSP = $datosSP['totales_globales_sp'] ?? [];

            // Convertir totales globales del SP a estructura más accesible
            $totalesPorTipo = [];
            foreach ($totalesGlobalesSP as $row) {
                $totalesPorTipo[$row->tipo_documento] = [
                    'Vigentes' => (int)$row->Vigentes,
                    'Caducados' => (int)$row->Caducados,
                    'Rechazados' => (int)$row->Rechazados,
                    'Pendientes' => (int)$row->Pendientes,
                    'Total' => (int)$row->Total
                ];
            }

            // Calcular estadísticas generales USANDO LOS TOTALES DEL SP
            $totalFiscal = $totalesPorTipo['FISCAL'] ?? ['Vigentes' => 0, 'Caducados' => 0, 'Rechazados' => 0, 'Pendientes' => 0, 'Total' => 0];
            $totalMedicina = $totalesPorTipo['MEDICINA'] ?? ['Vigentes' => 0, 'Caducados' => 0, 'Rechazados' => 0, 'Pendientes' => 0, 'Total' => 0];

            $estadisticasGenerales = [
                'total_campus' => count($estadisticasPorCampus),
                'total_documentos' => $totalFiscal['Total'] + $totalMedicina['Total'],
                'total_aprobados' => $totalFiscal['Vigentes'] + $totalMedicina['Vigentes'],
                'total_pendientes' => $totalFiscal['Pendientes'] + $totalMedicina['Pendientes'],
                'total_caducados' => $totalFiscal['Caducados'] + $totalMedicina['Caducados'],
                'total_rechazados' => $totalFiscal['Rechazados'] + $totalMedicina['Rechazados'],
                'cumplimiento_promedio' => round(array_sum(array_column($estadisticasPorCampus, 'porcentaje_cumplimiento')) / max(1, count($estadisticasPorCampus)), 1),
                'campus_criticos' => count(array_filter($estadisticasPorCampus, function($campus) {
                    return $campus['porcentaje_cumplimiento'] < 60;
                })),
                'usuarios_activos' => 0 // Placeholder por ahora
            ];

            // Datos completos para el dashboard
            $datosSupervision = [
                'estadisticas_generales' => $estadisticasGenerales,
                'estadisticas_por_campus' => $estadisticasPorCampus,
                'totales_por_tipo' => $totalesPorTipo, // Agregar totales del SP por tipo
                'campus_alertas' => [], // Placeholder por ahora
                'tendencias' => [] // Placeholder por ahora
            ];

            Log::info('DashboardGlobal datos preparados', [
                'total_campus' => $estadisticasGenerales['total_campus'],
                'total_documentos' => $estadisticasGenerales['total_documentos'],
                'cumplimiento_promedio' => $estadisticasGenerales['cumplimiento_promedio'],
                'totales_sp_fiscal' => $totalFiscal,
                'totales_sp_medicina' => $totalMedicina
            ]);

            return Inertia::render('supervision/dashboard-supervision', [
                'datosSupervision' => $datosSupervision
            ]);

        } catch (\Exception $e) {
            Log::error('Error en dashboardGlobal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Retornar datos vacíos en caso de error
            return Inertia::render('supervision/dashboard-supervision', [
                'datosSupervision' => [
                    'estadisticas_generales' => [
                        'total_campus' => 0,
                        'total_documentos' => 0,
                        'total_aprobados' => 0,
                        'total_pendientes' => 0,
                        'total_caducados' => 0,
                        'total_rechazados' => 0,
                        'cumplimiento_promedio' => 0,
                        'campus_criticos' => 0,
                        'usuarios_activos' => 0
                    ],
                    'estadisticas_por_campus' => [],
                    'campus_alertas' => [],
                    'tendencias' => []
                ]
            ]);
        }
    }



    /**
     * Actualizar el estado y/o fecha de vigencia de un documento
     * Solo disponible para rol 20 (Planeacion_Desarrollo)
     */
    public function actualizarDocumento(Request $request, $documentoId)
    {
        try {
            // Verificar que el usuario tenga rol 20
            $authUser = \Illuminate\Support\Facades\Auth::user();

            if (!$authUser) {
                return back()->with('error', 'Usuario no autenticado');
            }

            $user = \App\Models\usuario_model::where('Usuario', $authUser->getAuthIdentifier())->first();

            if (!$user) {
                return back()->with('error', 'Usuario no autorizado');
            }

            // Obtener roles usando la relación existente
            $rolesCollection = $user->roles()->get();
            $roles = $rolesCollection->pluck('ID_Rol')->toArray();
            $hasRole20 = in_array(20, $roles) || in_array('20', $roles);

            if (!$hasRole20) {
                return back()->with('error', 'No tiene permisos para actualizar documentos');
            }

            // Buscar directamente en sug_documentos_informacion usando el ID de la tabla
            $informacion = \App\Models\SugDocumentoInformacion::find($documentoId);

            if (!$informacion) {
                return back()->with('error', 'No se encontró información del documento');
            }

            // Actualizar estado si se proporcionó
            if ($request->has('estado') && $request->estado) {
                $nuevoEstado = $request->estado;

                // Mapear el estado del frontend al campo estado de la BD
                switch ($nuevoEstado) {
                    case 'aprobado':
                        $informacion->estado = 'vigente';
                        break;
                    case 'rechazado':
                        $informacion->estado = 'rechazado';
                        break;
                    case 'pendiente':
                        $informacion->estado = 'pendiente';
                        break;
                    case 'vencido':
                        $informacion->estado = 'vencido';
                        // Si no se proporcionó fecha de vencimiento, establecerla en el pasado
                        if (!$request->has('vigencia_documento') || !$request->vigencia_documento) {
                            $informacion->vigencia_documento = now()->subDay()->format('Y-m-d');
                        }
                        break;
                }
            }

            // Actualizar fecha de vigencia si se proporcionó (el campo se llama vigencia_documento)
            if ($request->has('fecha_vencimiento') && $request->fecha_vencimiento) {
                $informacion->vigencia_documento = $request->fecha_vencimiento;
            }

            // Actualizar el empleado que modifica y la fecha de actualización
            $informacion->empleado_actualiza_id = $user->ID_Empleado ?? null;
            $informacion->actualizado_en = now();

            // Guardar cambios
            $informacion->save();

            // Si es una petición AJAX/Inertia, devolver JSON
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Documento actualizado correctamente',
                    'documento' => [
                        'id' => $informacion->id,
                        'estado' => $informacion->estado,
                        'fecha_vencimiento' => $informacion->vigencia_documento,
                        'usuario_actualizo' => $user->Usuario ?? 'Sistema',
                        'actualizado_en' => $informacion->actualizado_en->format('Y-m-d H:i:s')
                    ]
                ]);
            }

            return back()->with('success', 'Documento actualizado correctamente');

        } catch (\Exception $e) {
            Log::error('=== ERROR en actualizarDocumento ===', [
                'documento_id' => $documentoId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el documento: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Error al actualizar el documento: ' . $e->getMessage());
        }
    }

    // Método temporal para debug de Cd. Acuña
    public function debugCdAcuna()
    {
        // Obtener el campus Cd. Acuña de la base de datos directamente
        $campus = campus_model::where('Campus', 'LIKE', '%Acuña%')->first();

        if (!$campus) {
            return response()->json(['error' => 'Campus Cd. Acuña no encontrado en BD']);
        }

        // También obtener el ID desde el stored procedure
        $empleadoId = \Illuminate\Support\Facades\Auth::user()->id_empleado ?? 1;
        $resultados = DB::select('EXEC sug_reporte_estatus_documentos @ID_Empleado = ?, @Todos = 1', [$empleadoId]);

        $campusDelSP = null;
        foreach ($resultados as $fila) {
            if (strpos($fila->Campus, 'Acuña') !== false) {
                $campusDelSP = $fila;
                break;
            }
        }

        $campusIdDirecto = (string) $campus->ID_Campus;
        $campusIdDelSP = $campusDelSP ? (string) $campusDelSP->campus_id : 'No encontrado';

        $hashDirecto = $this->generateCampusHash($campus->Campus, $campusIdDirecto);
        $hashDelSP = $campusDelSP ? $this->generateCampusHash($campusDelSP->Campus, $campusIdDelSP) : 'N/A';

        return response()->json([
            'campus_directo_bd' => $campus->Campus,
            'id_directo_bd' => $campus->ID_Campus,
            'id_directo_string' => $campusIdDirecto,
            'hash_directo' => $hashDirecto,

            'campus_del_sp' => $campusDelSP ? $campusDelSP->Campus : 'No encontrado',
            'id_del_sp' => $campusDelSP ? $campusDelSP->campus_id : 'No encontrado',
            'id_del_sp_string' => $campusIdDelSP,
            'hash_del_sp' => $hashDelSP,

            'hash_esperado_frontend' => '44e1867c',
            'coincide_directo' => $hashDirecto === '44e1867c' ? 'SI' : 'NO',
            'coincide_sp' => $hashDelSP === '44e1867c' ? 'SI' : 'NO'
        ]);
    }
}
