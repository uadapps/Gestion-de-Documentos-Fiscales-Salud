<?php

namespace App\Http\Controllers;

use App\Models\CampusContador;
use App\Models\campus_model;
use App\Models\empleado_model;
use App\Models\usuario_model;
use App\Models\SugDocumento;
use App\Models\SugDocumentoInformacion;
use App\Models\SugDocumentoArchivo;
use App\Services\DocumentAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DocumentoController extends Controller
{

    public function upload()
    {
        try {
            $user = Auth::user();
            $campusDelDirector = $this->getCampusDelDirector($user->ID_Usuario);

            $documentosRequeridos = $this->getDocumentosRequeridos();

            return Inertia::render('documentos/upload', [
                'campusDelDirector' => $campusDelDirector->toArray(),
                'documentosRequeridos' => $documentosRequeridos,
                'campusSeleccionado' => $campusDelDirector->first(),
            ]);

        } catch (\Exception $e) {
            return Inertia::render('documentos/upload', [
                'campusDelDirector' => [],
                'documentosRequeridos' => [],
                'campusSeleccionado' => null,
                'error' => 'Error al cargar los campus asignados'
            ]);
        }
    }

    private function getCampusDelDirector($userId)
    {
        try {
            $usuario = usuario_model::with('empleado')->find($userId);

            if (!$usuario || !$usuario->empleado) {
                return collect([]);
            }

            $empleadoId = $usuario->empleado->ID_Empleado;

            // Obtener los IDs de campus asignados
            $campusIds = CampusContador::where('ID_Empleado', $empleadoId)
                ->pluck('ID_Campus')
                ->toArray();

            if (empty($campusIds)) {
                return collect([]);
            }

            // Obtener los campus y limpiar cualquier caracter problematico
            $campus = campus_model::whereIn('ID_Campus', $campusIds)->get();

            // Limpiar los datos para evitar problemas UTF-8
            $campusLimpios = $campus->map(function($camp) {
                return [
                    'ID_Campus' => $camp->ID_Campus,
                    'Campus' => mb_convert_encoding($camp->Campus ?? 'Campus ' . $camp->ID_Campus, 'UTF-8', 'UTF-8'),
                    'Activo' => $camp->Activo ?? true
                ];
            });

            return $campusLimpios;

        } catch (\Exception $e) {
            Log::error('Error en getCampusDelDirector', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return collect([]);
        }
    }

    /**
     * Verifica si un campus tiene carreras del área médica
     */
    private function campusTieneCarrerasMedicas($campusId)
    {
        try {
            $carrerasMedicas = DB::select("
                SELECT DISTINCT
                    c.ID_Campus,
                    c.Campus,
                    E.ID_Especialidad,
                    e.Descripcion,
                    E.TipoUniv,
                    TP.DescripcionPlan
                FROM Campus c
                INNER JOIN especialidades E ON E.ID_Campus = c.id_campus
                INNER JOIN Tipo_Plan TP ON TP.Id_TipoPlan = E.TipoUniv
                INNER JOIN RVOE R ON R.Id_Campus = E.ID_Campus AND R.Id_Especialidad = E.ID_Especialidad
                INNER JOIN Grupo G ON G.ID_RVOE = R.Id_RVOE
                INNER JOIN Ciclo_Escolar CE ON CE.Id_CEscolar = G.ID_Periodo
                WHERE (e.descripcion LIKE '%MEDIC%' OR E.Descripcion LIKE '%ENFER%' OR E.Descripcion LIKE '%Méd%' OR E.Descripcion LIKE '%NUTRI%' OR E.Descripcion LIKE '%FISIO%' OR E.Descripcion LIKE '%ODONTO%' OR E.Descripcion LIKE '%COSME%')
                    AND E.Descripcion NOT LIKE '%DIPLO%' AND E.Descripcion NOT LIKE '%MAES%'
                    AND E.Activada = 1 AND c.Activo = 1 AND YEAR(CE.Fecha_Inicio) >= YEAR(GETDATE())
                    AND c.ID_Campus = ?
                ORDER BY C.ID_Campus, E.Descripcion
            ", [$campusId]);

            return count($carrerasMedicas) > 0;

        } catch (\Exception $e) {
            Log::error('Error verificando carreras médicas', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);
            return false;
        }
    }

    /**
     * Obtener lista de carreras médicas de un campus específico
     */
    private function obtenerCarrerasMedicasCampus($campusId)
    {
        try {
            $carrerasMedicas = DB::select("
                SELECT DISTINCT
                    c.ID_Campus,
                    c.Campus,
                    E.ID_Especialidad,
                    e.Descripcion,
                    E.TipoUniv,
                    TP.DescripcionPlan
                FROM Campus c
                INNER JOIN especialidades E ON E.ID_Campus = c.id_campus
                INNER JOIN Tipo_Plan TP ON TP.Id_TipoPlan = E.TipoUniv
                INNER JOIN RVOE R ON R.Id_Campus = E.ID_Campus AND R.Id_Especialidad = E.ID_Especialidad
                INNER JOIN Grupo G ON G.ID_RVOE = R.Id_RVOE
                INNER JOIN Ciclo_Escolar CE ON CE.Id_CEscolar = G.ID_Periodo
                WHERE (e.descripcion LIKE '%MEDIC%' OR E.Descripcion LIKE '%ENFER%' OR E.Descripcion LIKE '%Méd%' OR E.Descripcion LIKE '%NUTRI%' OR E.Descripcion LIKE '%FISIO%' OR E.Descripcion LIKE '%ODONTO%' OR E.Descripcion LIKE '%COSME%')
                    AND E.Descripcion NOT LIKE '%DIPLO%' AND E.Descripcion NOT LIKE '%MAES%'
                    AND E.Activada = 1 AND c.Activo = 1 AND YEAR(CE.Fecha_Inicio) >= YEAR(GETDATE())
                    AND c.ID_Campus = ?
                ORDER BY E.Descripcion
            ", [$campusId]);

            return collect($carrerasMedicas)->map(function($carrera) {
                return [
                    'ID_Especialidad' => $carrera->ID_Especialidad,
                    'Descripcion' => $carrera->Descripcion,
                    'TipoUniv' => $carrera->TipoUniv,
                    'DescripcionPlan' => $carrera->DescripcionPlan
                ];
            });

        } catch (\Exception $e) {
            Log::error('Error obteniendo carreras médicas', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);
            return collect([]);
        }
    }

    public function debugCampusContadores()
    {
        try {
            $user = Auth::user();
            $usuario = usuario_model::with('empleado')->find($user->ID_Usuario);

            $empleadoId = $usuario && $usuario->empleado ? $usuario->empleado->ID_Empleado : null;

            $totalRegistros = CampusContador::count();
            $primerosRegistros = CampusContador::limit(10)->get();

            $registrosEmpleado001040039 = CampusContador::where('ID_Empleado', '001040039')->get();

            $registrosEmpleadoActual = [];
            if ($empleadoId) {
                $registrosEmpleadoActual = CampusContador::where('ID_Empleado', $empleadoId)->get();
            }

            Log::info('Debug Campus_Contadores completo', [
                'total_registros_tabla' => $totalRegistros,
                'empleado_actual_id' => $empleadoId,
                'registros_empleado_001040039' => $registrosEmpleado001040039->toArray(),
                'registros_empleado_actual' => $registrosEmpleadoActual ? $registrosEmpleadoActual->toArray() : [],
                'primeros_10_registros' => $primerosRegistros->toArray()
            ]);

            return response()->json([
                'mensaje' => 'Debug Campus_Contadores',
                'total_registros_en_tabla' => $totalRegistros,
                'empleado_actual' => [
                    'id_empleado' => $empleadoId,
                    'usuario_completo' => $usuario ? $usuario->toArray() : null
                ],
                'busqueda_001040039' => [
                    'registros_encontrados' => $registrosEmpleado001040039->count(),
                    'registros' => $registrosEmpleado001040039->toArray()
                ],
                'registros_empleado_actual' => [
                    'count' => $registrosEmpleadoActual ? count($registrosEmpleadoActual) : 0,
                    'registros' => $registrosEmpleadoActual ? $registrosEmpleadoActual->toArray() : []
                ],
                'muestra_registros_tabla' => $primerosRegistros->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('Error en debug Campus_Contadores', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getDocumentosRequeridos($campusId = null, $carreraId = null)
    {
        try {
            // Obtener documentos activos de la base de datos (SIN FILTRAR - como estaba antes)
            $documentos = SugDocumento::where('activo', true)->get();

            $documentosFormateados = [];

            foreach ($documentos as $doc) {
                // Si se especifica un campus, buscar información específica del documento para ese campus
                $informacion = null;
                if ($campusId) {
                    $query = SugDocumentoInformacion::where('documento_id', $doc->id)
                        ->where('campus_id', $campusId);

                    // Si se especifica carrera_id, incluirlo en la consulta
                    if ($carreraId !== null) {
                        $query->where('carrera_id', $carreraId);
                        Log::info('Buscando documento con carrera específica', [
                            'documento_id' => $doc->id,
                            'campus_id' => $campusId,
                            'carrera_id' => $carreraId
                        ]);
                    }

                    $informacion = $query->with(['archivoActual', 'archivos'])->first();

                    if ($informacion) {
                        Log::info('Información encontrada', [
                            'informacion_id' => $informacion->id,
                            'documento_id' => $doc->id,
                            'campus_id' => $campusId,
                            'carrera_id' => $informacion->carrera_id,
                            'estado' => $informacion->estado
                        ]);
                    } else {
                        Log::info('No se encontró información', [
                            'documento_id' => $doc->id,
                            'campus_id' => $campusId,
                            'carrera_id' => $carreraId
                        ]);
                    }
                }

                // Calcular fecha límite basada en vigencia si es requerida
                $fechaLimite = null;
                if ($doc->requiere_vigencia && $doc->vigencia_meses) {
                    // Convertir a entero en caso de que venga como string
                    $meses = (int) $doc->vigencia_meses;
                    if ($meses > 0) {
                        $fechaLimite = now()->addMonths($meses)->format('Y-m-d');
                    }
                }

                // Determinar estado del documento
                $estado = 'pendiente';
                $archivos = [];

                if ($informacion) {
                    $estado = $informacion->estado ?? 'pendiente';

                    if ($informacion->archivoActual) {
                        // Obtener análisis si existe
                        $analisis = null;
                        $fechaExpedicion = null;
                        $vigenciaDocumento = null;
                        $diasRestantesVigencia = null;
                        $validacionIA = null;

                        // Primero intentar obtener del metadata_json de la información del documento
                        if ($informacion->metadata_json) {
                            try {
                                $metadata = json_decode($informacion->metadata_json, true);

                                // Los datos están en metadata_json según la BD
                                if (isset($metadata['metadatos'])) {
                                    $fechaExpedicion = $metadata['metadatos']['fecha_expedicion'] ?? null;
                                    $vigenciaDocumento = $metadata['metadatos']['vigencia_documento'] ?? null;
                                    $diasRestantesVigencia = $metadata['metadatos']['dias_restantes_vigencia'] ?? null;
                                }

                                // Extraer validación IA si existe
                                if (isset($metadata['documento'])) {
                                    $validacionIA = [
                                        'coincide' => $metadata['documento']['cumple_requisitos'] ?? false,
                                        'porcentaje' => 100, // Por defecto si cumple requisitos
                                        'razon' => $metadata['documento']['observaciones'] ?? '',
                                        'accion' => 'aprobar'
                                    ];
                                }

                            } catch (\Exception $e) {
                                Log::warning('Error procesando metadata_json del documento', [
                                    'informacion_id' => $informacion->id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

                        // Si no hay metadata_json, intentar con analisis_completo del archivo como fallback
                        if (!$fechaExpedicion && $informacion->archivoActual && $informacion->archivoActual->analisis_completo) {
                            try {
                                $analisis = json_decode($informacion->archivoActual->analisis_completo, true);

                                // Extraer fechas del análisis
                                $fechaExpedicion = $analisis['documento']['fecha_expedicion'] ?? null;
                                $vigenciaDocumento = $analisis['documento']['vigencia_documento'] ?? null;
                                $diasRestantesVigencia = $analisis['documento']['dias_restantes_vigencia'] ?? null;

                                // Extraer validación IA
                                if (isset($analisis['validacion'])) {
                                    $validacionIA = [
                                        'coincide' => $analisis['validacion']['coincide'] ?? false,
                                        'porcentaje' => $analisis['validacion']['porcentaje_coincidencia'] ?? 0,
                                        'razon' => $analisis['validacion']['razon'] ?? '',
                                        'accion' => $analisis['validacion']['accion'] ?? ''
                                    ];
                                }
                            } catch (\Exception $e) {
                                Log::warning('Error procesando análisis del archivo', [
                                    'archivo_id' => $informacion->archivoActual->id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

                        $archivos = [[
                            'id' => $informacion->archivoActual->id,
                            'file_hash_sha256' => $informacion->archivoActual->file_hash_sha256,
                            'nombre' => basename($informacion->archivoActual->archivo_pdf),
                            'tamaño' => $informacion->archivoActual->file_size_bytes,
                            'fechaSubida' => $informacion->archivoActual->subido_en?->format('Y-m-d'),
                            'estado' => 'completado',
                            'progreso' => 100,
                            // Información del análisis IA
                            'fechaExpedicion' => $fechaExpedicion,
                            'vigenciaDocumento' => $vigenciaDocumento,
                            'diasRestantesVigencia' => $diasRestantesVigencia,
                            'validacionIA' => $validacionIA
                        ]];
                    }
                }

                $documentosFormateados[] = [
                    'id' => (string)$doc->id,
                    'concepto' => $doc->nombre,
                    'descripcion' => $doc->descripcion,
                    'fechaLimite' => $fechaLimite,
                    'estado' => $estado,
                    'obligatorio' => !$doc->aplica_area_salud, // Si no aplica solo a área de salud, es obligatorio para todos
                    'categoria' => 'fiscal', // Por defecto
                    'requiere_vigencia' => $doc->requiere_vigencia,
                    'vigencia_meses' => $doc->vigencia_meses,
                    'entidad_emisora' => $doc->entidad_emisora,
                    'archivos' => $archivos
                ];
            }

            return $documentosFormateados;

        } catch (\Exception $e) {
            Log::error('Error obteniendo documentos requeridos', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);

            // Fallback a documentos básicos en caso de error
            return [
                [
                    'id' => '1',
                    'concepto' => 'Documento Ejemplo',
                    'descripcion' => 'Documento de ejemplo',
                    'fechaLimite' => '2024-12-15',
                    'estado' => 'pendiente',
                    'obligatorio' => true,
                    'categoria' => 'fiscal',
                    'archivos' => []
                ]
            ];
        }
    }

    public function cambiarCampus(Request $request)
    {
        try {
            $campusId = $request->input('campus_id');
            $user = Auth::user();

            $usuario = usuario_model::with('empleado')->find($user->ID_Usuario);

            // Verificar que el campus pertenece al director
            $campusValido = CampusContador::where('ID_Empleado', $usuario->empleado->ID_Empleado)
                ->where('ID_Campus', $campusId)
                ->first();

            if (!$campusValido) {
                return response()->json(['error' => 'Campus no autorizado'], 403);
            }

            // Obtener datos del campus
            $campus = campus_model::where('ID_Campus', $campusId)->first();

            $campusLimpio = null;
            if ($campus) {
                $campusLimpio = [
                    'ID_Campus' => $campus->ID_Campus,
                    'Campus' => mb_convert_encoding($campus->Campus ?? 'Campus ' . $campus->ID_Campus, 'UTF-8', 'UTF-8'),
                    'Activo' => $campus->Activo ?? true
                ];
            }

            $documentosCampus = $this->getDocumentosPorCampus($campusId);

            return response()->json([
                'campus' => $campusLimpio,
                'documentos' => $documentosCampus
            ]);

        } catch (\Exception $e) {
            Log::error('Error cambiando campus', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    private function getDocumentosPorCampus($campusId)
    {
        return $this->getDocumentosRequeridos($campusId);
    }

    /**
     * Obtener documentos específicos por campus y carrera
     */
    public function getDocumentosPorCarrera(Request $request)
    {
        try {
            $campusId = $request->input('campus_id');
            $carreraId = $request->input('carrera_id');

            Log::info('=== CONSULTA DOCUMENTOS POR CARRERA ===', [
                'campus_id' => $campusId,
                'carrera_id' => $carreraId,
                'request_all' => $request->all()
            ]);

            if (!$campusId) {
                return response()->json(['error' => 'Campus ID requerido'], 400);
            }

            // Si no se especifica carrera, obtener todos los documentos del campus
            $documentos = $this->getDocumentosRequeridos($campusId, $carreraId);

            Log::info('Documentos obtenidos:', [
                'total' => count($documentos),
                'campus_id' => $campusId,
                'carrera_id' => $carreraId
            ]);

            return response()->json([
                'success' => true,
                'documentos' => $documentos,
                'campus_id' => $campusId,
                'carrera_id' => $carreraId
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo documentos por carrera', [
                'error' => $e->getMessage(),
                'campus_id' => $request->input('campus_id'),
                'carrera_id' => $request->input('carrera_id'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtener estados de documentos médicos para todas las carreras de una vez (OPTIMIZADO)
     */
    public function getDocumentosMedicosOptimizado(Request $request)
    {
        try {
            $campusId = $request->input('campus_id');

            Log::info('=== CONSULTA OPTIMIZADA DOCUMENTOS MÉDICOS ===', [
                'campus_id' => $campusId
            ]);

            if (!$campusId) {
                return response()->json(['error' => 'Campus ID requerido'], 400);
            }

            // 1. Obtener todas las carreras médicas del campus
            $carrerasMedicas = $this->obtenerCarrerasMedicasCampus($campusId);

            if (empty($carrerasMedicas)) {
                return response()->json([
                    'success' => true,
                    'carreras' => [],
                    'documentos_por_carrera' => []
                ]);
            }

            // 2. Obtener IDs de carreras
            $idsCarreras = collect($carrerasMedicas)->pluck('ID_Especialidad')->toArray();

            Log::info('Carreras médicas encontradas:', [
                'total' => count($carrerasMedicas),
                'ids' => $idsCarreras
            ]);

            // 3. Consulta optimizada: obtener todos los documentos médicos con sus estados en una sola consulta
            $documentosMedicos = [];

            foreach ($idsCarreras as $carreraId) {
                $documentosCarrera = $this->getDocumentosRequeridos($campusId, $carreraId);
                $documentosMedicos[$carreraId] = $documentosCarrera;
            }

            Log::info('Documentos médicos obtenidos:', [
                'carreras_procesadas' => count($documentosMedicos),
                'total_documentos' => array_sum(array_map('count', $documentosMedicos))
            ]);

            return response()->json([
                'success' => true,
                'campus_id' => $campusId,
                'carreras' => $carrerasMedicas,
                'documentos_por_carrera' => $documentosMedicos
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo documentos médicos optimizado', [
                'error' => $e->getMessage(),
                'campus_id' => $request->input('campus_id'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function subirArchivo(Request $request)
    {
        // CONFIGURAR LÍMITES PHP PARA ARCHIVOS GRANDES
        ini_set('upload_max_filesize', '50M');
        ini_set('post_max_size', '55M');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '600');
        ini_set('max_input_time', '600');
        ini_set('max_file_uploads', '20');

        // Log súper básico para verificar que llega aquí
        error_log('LLEGÓ AL CONTROLADOR SUBIR ARCHIVO');
        file_put_contents(storage_path('logs/debug.txt'), date('Y-m-d H:i:s') . " - LLEGÓ AL CONTROLADOR\n", FILE_APPEND);

        // Log RAW de la petición para ver qué llega exactamente
        Log::info('=== REQUEST RAW DATA ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'all_input' => $request->all(),
            'files' => $request->allFiles(),
            'has_archivo' => $request->hasFile('archivo'),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'php_limits_current' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
            ]
        ]);

        Log::info('=== INICIO SUBIR ARCHIVO ===', [
            'request_data' => $request->all(),
            'documento_id' => $request->input('documento_id'),
            'campus_id' => $request->input('campus_id'),
            'carrera_id' => $request->input('carrera_id'),
            'timestamp' => now()
        ]);

        try {
        Log::info('Validando request...', [
            'has_file' => $request->hasFile('archivo'),
            'documento_id' => $request->input('documento_id'),
            'campus_id' => $request->input('campus_id'),
            'archivo_info' => $request->hasFile('archivo') ? [
                'name' => $request->file('archivo')->getClientOriginalName(),
                'size' => $request->file('archivo')->getSize(),
                'mime' => $request->file('archivo')->getMimeType(),
                'error' => $request->file('archivo')->getError(),
            ] : 'No hay archivo',
            'php_config' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'request_size' => $_SERVER['CONTENT_LENGTH'] ?? 'unknown'
        ]);

            // Validación con límites generosos para archivos grandes
            $validator = Validator::make($request->all(), [
                'documento_id' => 'required|exists:sug_documentos,id',
                'campus_id' => 'required|string|max:3',
                'carrera_id' => 'nullable|string|max:10', // ID_Especialidad para documentos médicos (nvarchar)
                'archivo' => 'required|file|mimes:pdf|max:51200', // 50MB máximo
                'folio_documento' => 'nullable|string|max:50',
                'fecha_expedicion' => 'nullable|date',
                'lugar_expedicion' => 'nullable|string|max:100',
                'vigencia_documento' => 'nullable|date',
                'observaciones' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                $validationErrors = $validator->errors()->toArray();

                Log::error('=== VALIDACIÓN FALLÓ ===', [
                    'errors' => $validationErrors,
                    'input' => $request->all(),
                    'archivo_presente' => $request->hasFile('archivo'),
                    'archivo_info' => $request->hasFile('archivo') ? [
                        'name' => $request->file('archivo')->getClientOriginalName(),
                        'size' => $request->file('archivo')->getSize(),
                        'mime' => $request->file('archivo')->getMimeType(),
                    ] : 'No hay archivo',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Errores de validación',
                    'validation_errors' => $validationErrors,
                    'debug_info' => [
                        'has_file' => $request->hasFile('archivo'),
                        'documento_id' => $request->input('documento_id'),
                        'campus_id' => $request->input('campus_id')
                    ]
                ], 422);
            }

            Log::info('✅ VALIDACIÓN OMITIDA - Continuando sin validaciones...');

            Log::info('Obteniendo usuario autenticado...');
            $user = Auth::user();
            $usuario = usuario_model::with('empleado')->find($user->ID_Usuario);

            if (!$usuario || !$usuario->empleado) {
                Log::warning('Usuario no autorizado', ['user_id' => $user->ID_Usuario ?? null]);
                return response()->json(['error' => 'Usuario no autorizado'], 401);
            }
            Log::info('Usuario obtenido', ['empleado_id' => $usuario->empleado->ID_Empleado]);

            Log::info('Verificando acceso al campus...');
            // Verificar que el usuario tenga acceso a este campus
            $tieneAcceso = CampusContador::where('ID_Empleado', $usuario->empleado->ID_Empleado)
                ->where('ID_Campus', $request->campus_id)
                ->exists();

            if (!$tieneAcceso) {
                Log::warning('Sin acceso al campus', [
                    'empleado_id' => $usuario->empleado->ID_Empleado,
                    'campus_id' => $request->campus_id
                ]);
                return response()->json(['error' => 'No tienes acceso a este campus'], 403);
            }
            Log::info('Acceso al campus verificado');

            Log::info('Procesando archivo...');
            $archivo = $request->file('archivo');
            $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
            $rutaArchivo = $archivo->storeAs('documentos/' . $request->campus_id, $nombreArchivo, 'public');
            Log::info('Archivo guardado', ['ruta' => $rutaArchivo]);

            // Calcular hash del archivo
            Log::info('Calculando hash del archivo...');
            $hashArchivo = hash_file('sha256', $archivo->getPathname());
            Log::info('Hash calculado', ['hash' => substr($hashArchivo, 0, 10) . '...']);

            Log::info('Buscando información existente del documento...');
            // Buscar si ya existe información para este documento, campus Y carrera (para documentos médicos)
            $query = SugDocumentoInformacion::where('documento_id', $request->documento_id)
                ->where('campus_id', $request->campus_id);

            // Si hay carrera_id (documentos médicos), incluirla en la búsqueda
            if ($request->carrera_id) {
                $query->where('carrera_id', $request->carrera_id);
                Log::info('Búsqueda incluyendo carrera_id', ['carrera_id' => $request->carrera_id]);
            } else {
                // Para documentos fiscales, asegurar que carrera_id sea null
                $query->whereNull('carrera_id');
                Log::info('Búsqueda para documento fiscal (carrera_id null)');
            }

            $informacion = $query->first();

            if (!$informacion) {
                Log::info('Creando nueva información de documento...');
                // Crear nueva información de documento
                $informacion = SugDocumentoInformacion::create([
                    'documento_id' => $request->documento_id,
                    'campus_id' => $request->campus_id,
                    'carrera_id' => $request->carrera_id, // Agregar carrera_id (ID_Especialidad)
                    'nombre_documento' => $request->folio_documento ?? 'Sin folio',
                    'folio_documento' => $request->folio_documento,
                    'fecha_expedicion' => $request->fecha_expedicion,
                    'lugar_expedicion' => $request->lugar_expedicion,
                    'vigencia_documento' => $request->vigencia_documento,
                    'estado' => 'vigente',
                    'observaciones' => $request->observaciones,
                    'creado_en' => now(),
                    'empleado_captura_id' => $usuario->empleado->ID_Empleado
                ]);
                Log::info('Información creada', [
                    'informacion_id' => $informacion->id,
                    'carrera_id_guardado' => $informacion->carrera_id
                ]);
            } else {
                Log::info('Actualizando información existente...', ['informacion_id' => $informacion->id]);
                // Actualizar información existente
                $informacion->update([
                    'carrera_id' => $request->carrera_id ?? $informacion->carrera_id, // Actualizar carrera_id si viene
                    'nombre_documento' => $request->folio_documento ?? $informacion->nombre_documento,
                    'folio_documento' => $request->folio_documento ?? $informacion->folio_documento,
                    'fecha_expedicion' => $request->fecha_expedicion ?? $informacion->fecha_expedicion,
                    'lugar_expedicion' => $request->lugar_expedicion ?? $informacion->lugar_expedicion,
                    'vigencia_documento' => $request->vigencia_documento ?? $informacion->vigencia_documento,
                    'observaciones' => $request->observaciones ?? $informacion->observaciones,
                    'actualizado_en' => now(),
                    'empleado_actualiza_id' => $usuario->empleado->ID_Empleado
                ]);
                Log::info('Información actualizada');
            }

            Log::info('Desactivando archivos anteriores...');
            // Desactivar archivos anteriores
            SugDocumentoArchivo::where('documento_informacion_id', $informacion->id)
                ->update(['es_actual' => false]);

            Log::info('Creando nuevo archivo...');
            // Crear nuevo archivo
            $nuevoArchivo = SugDocumentoArchivo::create([
                'documento_informacion_id' => $informacion->id,
                'version' => SugDocumentoArchivo::where('documento_informacion_id', $informacion->id)->count() + 1,
                'es_actual' => true,
                'archivo_pdf' => $rutaArchivo,
                'mime_type' => $archivo->getMimeType(),
                'file_size_bytes' => $archivo->getSize(),
                'file_hash_sha256' => $hashArchivo,
                'observaciones' => $request->observaciones,
                'subido_por' => $usuario->empleado->ID_Empleado,
                'subido_en' => now()
            ]);
            Log::info('Archivo creado', ['archivo_id' => $nuevoArchivo->id]);

            Log::info('Actualizando referencia al archivo actual...');
            // Actualizar referencia al archivo actual
            $informacion->update(['archivo_actual_id' => $nuevoArchivo->id]);

            Log::info('Iniciando análisis automático...');
            // Iniciar análisis automático del documento
            $this->analizarDocumentoAutomaticamente($rutaArchivo, $request->campus_id, $usuario->empleado->ID_Empleado, $informacion->id, $nuevoArchivo->id, $request->documento_id);
            Log::info('Análisis automático iniciado');

            Log::info('=== PROCESO COMPLETADO EXITOSAMENTE ===');
            return response()->json([
                'success' => true,
                'mensaje' => 'Archivo subido exitosamente. Iniciando análisis automático...',
                'archivo' => [
                    'id' => $nuevoArchivo->id,
                    'file_hash_sha256' => $nuevoArchivo->file_hash_sha256,
                    'nombre' => basename($rutaArchivo),
                    'tamaño' => $archivo->getSize(),
                    'fechaSubida' => $nuevoArchivo->subido_en->format('Y-m-d'),
                    'estado' => 'analizando',
                    'progreso' => 100
                ]
            ]);

        } catch (\Exception $e) {
            // Logs directos que siempre funcionan
            error_log('ERROR SUBIR ARCHIVO: ' . $e->getMessage());
            error_log('ARCHIVO: ' . $e->getFile());
            error_log('LINEA: ' . $e->getLine());

            // Log a archivo específico
            $errorDetails = "=== ERROR SUBIR ARCHIVO ===\n";
            $errorDetails .= "Mensaje: " . $e->getMessage() . "\n";
            $errorDetails .= "Archivo: " . $e->getFile() . "\n";
            $errorDetails .= "Línea: " . $e->getLine() . "\n";
            $errorDetails .= "Trace: " . $e->getTraceAsString() . "\n";
            $errorDetails .= "Request: " . json_encode($request->all()) . "\n";
            $errorDetails .= "========================\n\n";

            file_put_contents(storage_path('logs/upload_errors.log'), $errorDetails, FILE_APPEND);

            Log::error('=== ERROR EN SUBIR ARCHIVO ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verLogs()
    {
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return response()->json(['error' => 'Archivo de log no encontrado']);
        }

        // Leer las últimas 100 líneas del archivo de log
        $lines = [];
        $file = new \SplFileObject($logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - 200);
        $file->seek($startLine);

        while (!$file->eof()) {
            $line = $file->fgets();
            if (strpos($line, 'Debug') !== false || strpos($line, 'getCampusDelDirector') !== false) {
                $lines[] = trim($line);
            }
        }

        return response()->json([
            'logs' => array_slice($lines, -50), // Últimas 50 líneas de debug
            'total_lines' => count($lines)
        ]);
    }

    /**
     * Inicia el análisis automático de un documento
     */
    private function analizarDocumentoAutomaticamente($rutaArchivo, $campusId, $empleadoId, $informacionId, $archivoId, $documentoRequeridoId = null)
    {
        Log::info('=== INICIO ANÁLISIS AUTOMÁTICO ===', [
            'ruta_archivo' => $rutaArchivo,
            'campus_id' => $campusId,
            'empleado_id' => $empleadoId,
            'informacion_id' => $informacionId,
            'archivo_id' => $archivoId,
            'documento_requerido_id' => $documentoRequeridoId
        ]);

        try {
            // Ejecutar análisis en segundo plano usando jobs o de forma síncrona
            Log::info('Creando instancia de DocumentAnalyzerService...');
            $analyzer = new DocumentAnalyzerService();

            Log::info('Ejecutando análisis del documento...');
            $resultado = $analyzer->analizarDocumento($rutaArchivo, $campusId, $empleadoId, $documentoRequeridoId);

            Log::info('Resultado del análisis obtenido', [
                'success' => $resultado['success'],
                'tiene_analisis' => isset($resultado['analisis'])
            ]);

            if ($resultado['success']) {
                Log::info('Procesando resultados del análisis...');
                // Procesar los resultados del análisis
                $procesado = $analyzer->procesarAnalisis($resultado['analisis'], $informacionId, $archivoId);

                Log::info('Análisis automático completado', [
                    'archivo_id' => $archivoId,
                    'informacion_id' => $informacionId,
                    'campus_id' => $campusId,
                    'documento_requerido_id' => $documentoRequeridoId,
                    'validacion_exitosa' => $resultado['analisis']['validacion']['coincide'] ?? 'N/A',
                    'procesado_exitosamente' => $procesado
                ]);
            } else {
                Log::warning('Error en análisis automático', [
                    'archivo_id' => $archivoId,
                    'error' => $resultado['error']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('=== EXCEPCIÓN EN ANÁLISIS AUTOMÁTICO ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'archivo_id' => $archivoId,
                'ruta_archivo' => $rutaArchivo
            ]);
        }
    }

    /**
     * Obtiene el estado del análisis de un documento
     */
    public function obtenerEstadoAnalisis(Request $request)
    {
        try {
            $archivoId = $request->query('archivo_id');

            Log::info('🔍 CONSULTANDO ESTADO DE ANÁLISIS', [
                'archivo_id' => $archivoId
            ]);

            $archivo = SugDocumentoArchivo::with('documentoInformacion')->find($archivoId);

            if (!$archivo) {
                Log::error('❌ ARCHIVO NO ENCONTRADO', ['archivo_id' => $archivoId]);
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            $metadataJson = $archivo->documentoInformacion->metadata_json ?? null;
            $analisis = $metadataJson ? json_decode($metadataJson, true) : null;

            $response = [
                'estado' => $archivo->documentoInformacion->estado ?? 'pendiente',
                'tiene_analisis' => !is_null($analisis),
                'analisis' => $analisis,
                'observaciones' => $archivo->observaciones,
                'metadata' => [
                    'folio_documento' => $archivo->documentoInformacion->folio_documento,
                    'fecha_expedicion' => $archivo->documentoInformacion->fecha_expedicion,
                    'vigencia_documento' => $archivo->documentoInformacion->vigencia_documento,
                    'lugar_expedicion' => $archivo->documentoInformacion->lugar_expedicion
                ]
            ];

            Log::info('📋 ESTADO DE ANÁLISIS CONSULTADO', [
                'archivo_id' => $archivoId,
                'tiene_analisis' => $response['tiene_analisis'],
                'estado' => $response['estado'],
                'metadata_json_length' => $metadataJson ? strlen($metadataJson) : 0,
                'analisis_keys' => $analisis ? array_keys($analisis) : []
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estado de análisis', [
                'error' => $e->getMessage(),
                'archivo_id' => $request->query('archivo_id')
            ]);

            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Permite reanalizar un documento manualmente
     */
    public function reanalizar(Request $request)
    {
        try {
            $request->validate([
                'archivo_id' => 'required|exists:sug_documentos_archivos,id'
            ]);

            $archivo = SugDocumentoArchivo::with('documentoInformacion')->find($request->archivo_id);

            if (!$archivo) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            $user = Auth::user();
            $usuario = usuario_model::with('empleado')->find($user->ID_Usuario);

            // Verificar permisos
            $tieneAcceso = CampusContador::where('ID_Empleado', $usuario->empleado->ID_Empleado)
                ->where('ID_Campus', $archivo->documentoInformacion->campus_id)
                ->exists();

            if (!$tieneAcceso) {
                return response()->json(['error' => 'No tienes acceso a este campus'], 403);
            }

            // Iniciar reanálisis
            $this->analizarDocumentoAutomaticamente(
                $archivo->archivo_pdf,
                $archivo->documentoInformacion->campus_id,
                $usuario->empleado->ID_Empleado,
                $archivo->documentoInformacion->id,
                $archivo->id,
                $archivo->documentoInformacion->documento_id
            );

            return response()->json([
                'success' => true,
                'mensaje' => 'Reanálisis iniciado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en reanálisis', [
                'error' => $e->getMessage(),
                'archivo_id' => $request->archivo_id ?? null
            ]);

            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Ver archivo en el navegador
     */
    public function verArchivo($id)
    {
        try {
            // Buscar el archivo en la base de datos
            $archivo = SugDocumentoArchivo::find($id);

            if (!$archivo) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            // Construir la ruta del archivo
            $rutaCompleta = storage_path('app/public/' . $archivo->archivo_pdf);

            Log::info('Intentando servir archivo', [
                'archivo_id' => $id,
                'ruta_bd' => $archivo->archivo_pdf,
                'ruta_completa' => $rutaCompleta,
                'archivo_existe' => file_exists($rutaCompleta)
            ]);

            if (!file_exists($rutaCompleta)) {
                return response()->json(['error' => 'Archivo no encontrado en el sistema de archivos'], 404);
            }

            // Servir el archivo como PDF
            return response()->file($rutaCompleta, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . basename($archivo->archivo_pdf) . '"'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al servir archivo', [
                'archivo_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Ver archivo por hash SHA256 (sin mostrar ID en URL)
     */
    public function verArchivoPorHash($hash)
    {
        try {
            // Buscar el archivo por hash SHA256
            $archivo = SugDocumentoArchivo::where('file_hash_sha256', $hash)->first();

            if (!$archivo) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            // Construir la ruta del archivo
            $rutaCompleta = storage_path('app/public/' . $archivo->archivo_pdf);

            Log::info('Intentando servir archivo por hash', [
                'hash' => $hash,
                'ruta_bd' => $archivo->archivo_pdf,
                'ruta_completa' => $rutaCompleta,
                'archivo_existe' => file_exists($rutaCompleta)
            ]);

            if (!file_exists($rutaCompleta)) {
                return response()->json(['error' => 'Archivo no encontrado en el sistema de archivos'], 404);
            }

            // Servir el archivo como PDF
            return response()->file($rutaCompleta, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . basename($archivo->archivo_pdf) . '"'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al servir archivo por hash', [
                'hash' => $hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Descargar archivo
     */
    public function descargarArchivo($id)
    {
        try {
            // Buscar el archivo en la base de datos
            $archivo = SugDocumentoArchivo::find($id);

            if (!$archivo) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            // Construir la ruta del archivo
            $rutaCompleta = storage_path('app/public/' . $archivo->archivo_pdf);

            if (!file_exists($rutaCompleta)) {
                return response()->json(['error' => 'Archivo no encontrado en el sistema de archivos'], 404);
            }

            // Descargar el archivo
            return response()->download($rutaCompleta, basename($archivo->archivo_pdf));

        } catch (\Exception $e) {
            Log::error('Error al descargar archivo', [
                'archivo_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Verificar si un campus tiene carreras médicas y obtener la lista
     */
    public function verificarCampusMedico($campusId)
    {
        try {
            $tieneCarrerasMedicas = $this->campusTieneCarrerasMedicas($campusId);
            $carrerasMedicas = $tieneCarrerasMedicas ? $this->obtenerCarrerasMedicasCampus($campusId) : collect([]);

            return response()->json([
                'campus_id' => $campusId,
                'tiene_carreras_medicas' => $tieneCarrerasMedicas,
                'carreras_medicas' => $carrerasMedicas
            ]);

        } catch (\Exception $e) {
            Log::error('Error verificando campus médico', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId
            ]);

            return response()->json(['error' => 'Error verificando campus médico'], 500);
        }
    }

    /**
     * Obtener estadísticas para el dashboard
     */
    public function getEstadisticasDashboard()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                Log::error('Usuario no autenticado en dashboard');
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            Log::info('Iniciando obtención de estadísticas dashboard', [
                'user_id' => $user->ID_Usuario,
                'user_class' => get_class($user)
            ]);

            $userRoles = $this->getUserRoles($user);

            Log::info('Roles obtenidos para usuario', [
                'user_id' => $user->ID_Usuario,
                'user_roles' => $userRoles
            ]);

            // Obtener campus del usuario
            $campusDelDirector = $this->getCampusDelDirector($user->ID_Usuario);
            $campusIds = $campusDelDirector->pluck('ID_Campus')->toArray();

            Log::info('Campus del usuario', [
                'user_id' => $user->ID_Usuario,
                'campus_ids' => $campusIds
            ]);

            if ($userRoles['isRole16']) {
                Log::info('Usuario es supervisor, obteniendo estadísticas globales');
                return $this->getEstadisticasSupervisor();
            } else if ($userRoles['isRole13or14']) {
                Log::info('Usuario es director, obteniendo estadísticas de campus');
                return $this->getEstadisticasCampus($campusIds);
            }

            Log::warning('Usuario sin permisos para estadísticas dashboard', [
                'user_id' => $user->ID_Usuario,
                'roles' => $userRoles['roles']
            ]);

            return response()->json(['error' => 'Sin permisos para ver estadísticas'], 403);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'user_id' => isset($user) ? $user->ID_Usuario : null
            ]);

            return response()->json([
                'error' => 'Error obteniendo estadísticas',
                'details' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Obtener roles del usuario
     */
    private function getUserRoles($user)
    {
        try {
            // Acceder a la relación roles de Eloquent
            $roles = $user->roles;

            if (!$roles || $roles->isEmpty()) {
                Log::warning('Usuario sin roles asignados', [
                    'user_id' => $user->ID_Usuario
                ]);
                return [
                    'isRole13or14' => false,
                    'isRole16' => false,
                    'roles' => []
                ];
            }

            // Extraer los IDs de rol
            $roleIds = $roles->pluck('ID_Rol')->toArray();

            Log::info('Roles del usuario', [
                'user_id' => $user->ID_Usuario,
                'role_ids' => $roleIds
            ]);

            return [
                'isRole13or14' => in_array(13, $roleIds) || in_array(14, $roleIds),
                'isRole16' => in_array(16, $roleIds),
                'roles' => $roleIds
            ];

        } catch (\Exception $e) {
            Log::error('Error obteniendo roles de usuario', [
                'error' => $e->getMessage(),
                'user_id' => $user->ID_Usuario ?? null
            ]);

            return [
                'isRole13or14' => false,
                'isRole16' => false,
                'roles' => []
            ];
        }
    }

    /**
     * Estadísticas para supervisores (rol 16)
     */
    private function getEstadisticasSupervisor()
    {
        try {
            Log::info('Iniciando obtención de estadísticas de supervisor con SP');

            // Obtener campus activos excluyendo los especificados
            $campusExcluidos = [19, 20]; // Los mismos que en SupervisionController
            $campusActivos = DB::table('campus')
                ->where('Activo', 1)
                ->whereNotIn('ID_Campus', $campusExcluidos)
                ->pluck('ID_Campus')
                ->toArray();

            if (empty($campusActivos)) {
                Log::warning('No se encontraron campus activos para supervisión');
                return response()->json(['error' => 'No hay campus activos'], 404);
            }

            Log::info('Campus activos encontrados', [
                'total_campus' => count($campusActivos),
                'campus_ids' => $campusActivos
            ]);

            // Usar cualquier ID de empleado válido y activar @Todos=1
            $idEmpleadoGenerico = 1;

            Log::info('Usando SP con @Todos=1 para obtener datos de TODOS los campus');

            // Ejecutar el stored procedure CON el parámetro @Todos=1
            $resultados = DB::select('EXEC sug_reporte_estatus_documentos ?, ?', [$idEmpleadoGenerico, 1]);

            Log::info('Resultados del SP con @Todos=1', [
                'resultados_count' => count($resultados),
                'primera_fila' => count($resultados) > 0 ? get_object_vars($resultados[0]) : 'Sin resultados'
            ]);

            // Obtener nombres de campus
            $campusNombres = DB::table('campus')
                ->whereIn('ID_Campus', $campusActivos)
                ->pluck('Campus', 'ID_Campus')
                ->toArray();

            $estadisticasPorCampus = [];
            $totalesGenerales = [
                'total_documentos' => 0,
                'aprobados' => 0,
                'pendientes' => 0,
                'caducados' => 0,
                'rechazados' => 0,
                'en_revision' => 0
            ];

            // Agrupar resultados por campus
            foreach ($resultados as $fila) {
                $campusId = $fila->campus_id;
                $tipoDoc = $fila->tipo_documento;

                // Solo procesar campus activos
                if (!in_array($campusId, $campusActivos)) {
                    continue;
                }

                // Inicializar campus si no existe
                if (!isset($estadisticasPorCampus[$campusId])) {
                    $campusNombre = isset($campusNombres[$campusId])
                        ? mb_convert_encoding($campusNombres[$campusId], 'UTF-8', 'UTF-8')
                        : "Campus $campusId";

                    $estadisticasPorCampus[$campusId] = [
                        'campus_id' => (int)$campusId,
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
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['total_documentos'] = (int)$fila->Total;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['pendientes'] = (int)$fila->Pendientes;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['aprobados'] = (int)$fila->Vigentes;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['caducados'] = (int)($fila->Caducados ?? 0);
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['rechazados'] = (int)$fila->Rechazados;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['subidos'] = 0;

                // Debug: Log de caducados para verificar
                if (isset($fila->Caducados) && (int)$fila->Caducados > 0) {
                    Log::info("Caducados encontrados en SP (DocumentoController)", [
                        'campus_id' => $campusId,
                        'tipo_documento' => $tipoDoc,
                        'caducados_raw' => $fila->Caducados,
                        'caducados_int' => (int)$fila->Caducados
                    ]);
                }
            }

            // Calcular totales por campus y agregar propiedades adicionales
            foreach ($estadisticasPorCampus as $campusId => &$campus) {
                // Calcular totales generales del campus
                $campus['total_documentos'] = $campus['fiscales']['total_documentos'] + $campus['medicos']['total_documentos'];
                $campus['total_aprobados'] = $campus['fiscales']['aprobados'] + $campus['medicos']['aprobados'];
                $campus['total_caducados'] = $campus['fiscales']['caducados'] + $campus['medicos']['caducados'];
                $campus['total_pendientes'] = $campus['fiscales']['pendientes'] + $campus['medicos']['pendientes'];
                $campus['total_rechazados'] = $campus['fiscales']['rechazados'] + $campus['medicos']['rechazados'];

                // Calcular porcentaje de cumplimiento
                $campus['porcentaje_cumplimiento'] = $campus['total_documentos'] > 0
                    ? round(($campus['total_aprobados'] / $campus['total_documentos']) * 100)
                    : 0;

                // Determinar qué tipos de documentos tiene este campus
                $campus['tiene_fiscales'] = $campus['fiscales']['total_documentos'] > 0;
                $campus['tiene_medicos'] = $campus['medicos']['total_documentos'] > 0;

                // Sumar a totales generales
                $totalesGenerales['total_documentos'] += $campus['total_documentos'];
                $totalesGenerales['aprobados'] += $campus['total_aprobados'];
                $totalesGenerales['caducados'] += $campus['total_caducados'];
                $totalesGenerales['pendientes'] += $campus['total_pendientes'];
                $totalesGenerales['rechazados'] += $campus['total_rechazados'];
            }

            // Convertir a array indexado
            $estadisticasPorCampusArray = array_values($estadisticasPorCampus);

            // Debug: Log de estructura de caducados final
            foreach ($estadisticasPorCampusArray as $campus) {
                if ($campus['total_caducados'] > 0) {
                    Log::info("Campus con caducados en estructura final (DocumentoController)", [
                        'campus_nombre' => $campus['campus_nombre'],
                        'total_caducados' => $campus['total_caducados'],
                        'fiscales_caducados' => $campus['fiscales']['caducados'],
                        'medicos_caducados' => $campus['medicos']['caducados']
                    ]);
                }
            }

            // Estadísticas separadas por tipo
            $estadisticasFiscales = [
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'en_revision' => 0,
                'caducados' => 0,
                'rechazados' => 0,
                'subidos' => 0
            ];

            $estadisticasMedicos = [
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'en_revision' => 0,
                'caducados' => 0,
                'rechazados' => 0,
                'subidos' => 0
            ];

            // Sumar estadísticas por tipo
            foreach ($estadisticasPorCampusArray as $campus) {
                $estadisticasFiscales['total_documentos'] += $campus['fiscales']['total_documentos'];
                $estadisticasFiscales['pendientes'] += $campus['fiscales']['pendientes'];
                $estadisticasFiscales['aprobados'] += $campus['fiscales']['aprobados'];
                $estadisticasFiscales['caducados'] += $campus['fiscales']['caducados'];
                $estadisticasFiscales['rechazados'] += $campus['fiscales']['rechazados'];

                $estadisticasMedicos['total_documentos'] += $campus['medicos']['total_documentos'];
                $estadisticasMedicos['pendientes'] += $campus['medicos']['pendientes'];
                $estadisticasMedicos['aprobados'] += $campus['medicos']['aprobados'];
                $estadisticasMedicos['caducados'] += $campus['medicos']['caducados'];
                $estadisticasMedicos['rechazados'] += $campus['medicos']['rechazados'];
            }

            $totalUsuarios = DB::table('usuarios')->whereIn('ID_Campus', $campusActivos)->count();
            $totalCampus = count($campusActivos);

            Log::info('Estadísticas de supervisión calculadas con SP:', [
                'total_documentos' => $totalesGenerales['total_documentos'],
                'aprobados' => $totalesGenerales['aprobados'],
                'caducados' => $totalesGenerales['caducados'],
                'pendientes' => $totalesGenerales['pendientes'],
                'rechazados' => $totalesGenerales['rechazados'],
                'usuarios' => $totalUsuarios,
                'campus' => $totalCampus
            ]);

            return response()->json([
                'tipo_usuario' => 'supervisor',
                'estadisticas' => [
                    'total_campus' => $totalCampus,
                    'documentos_por_estado' => [
                        'total_documentos' => $totalesGenerales['total_documentos'],
                        'pendientes' => $totalesGenerales['pendientes'],
                        'aprobados' => $totalesGenerales['aprobados'],
                        'en_revision' => 0,
                        'caducados' => $totalesGenerales['caducados'],
                        'rechazados' => $totalesGenerales['rechazados'],
                        'subidos' => 0
                    ],
                    'cumplimiento_promedio' => $totalesGenerales['total_documentos'] > 0
                        ? round(($totalesGenerales['aprobados'] / $totalesGenerales['total_documentos']) * 100, 1)
                        : 0,
                    'campus_criticos' => 0,
                    'usuarios_activos' => $totalUsuarios
                ],
                'estadisticas_fiscales' => $estadisticasFiscales,
                'estadisticas_medicos' => $estadisticasMedicos,
                'estadisticas_por_campus' => $estadisticasPorCampusArray,
                'totalDocumentos' => $totalesGenerales['total_documentos'],
                'documentosAprobados' => $totalesGenerales['aprobados'],
                'documentosVigentes' => $totalesGenerales['aprobados'],
                'documentosCaducados' => $totalesGenerales['caducados'],
                'documentosPendientes' => $totalesGenerales['pendientes'],
                'documentosRechazados' => $totalesGenerales['rechazados'],
                'usuariosActivos' => $totalUsuarios,
                'campusConectados' => $totalCampus
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de supervisor con SP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error obteniendo estadísticas',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estadísticas para directores de campus (roles 13 y 14)
     */
    private function getEstadisticasCampus($campusIds)
    {
        try {
            Log::info('Iniciando obtención de estadísticas de campus', [
                'campus_ids' => $campusIds
            ]);

            if (empty($campusIds)) {
                Log::info('Campus IDs vacío, retornando estadísticas vacías');
                return response()->json([
                    'tipo_usuario' => 'campus',
                    'estadisticas' => [
                        'total_documentos' => 0,
                        'pendientes' => 0,
                        'aprobados' => 0,
                        'en_revision' => 0,
                        'caducados' => 0,
                        'rechazados' => 0
                    ],
                    'documentos_por_tipo' => [],
                    'actividad_reciente' => [],
                    'documentos_vencidos' => []
                ]);
            }

            // Obtener ID de empleado del usuario actual
            $user = Auth::user();
            $idEmpleado = $user->ID_Empleado ?? $user->ID_Usuario;

            // Usar stored procedure que trae datos separados por campus
            try {
                $estadisticasOptimizadas = $this->calcularEstadisticasConSP($idEmpleado);

                Log::info('Estadísticas obtenidas con stored procedure', [
                    'user_id' => $idEmpleado,
                    'estadisticas' => $estadisticasOptimizadas
                ]);

                // Generar estadísticas por campus para gráficas
                $estadisticasPorCampus = $this->generarEstadisticasPorCampus($idEmpleado, $campusIds);

                // Obtener actividad reciente
                $actividadReciente = $this->obtenerActividadReciente($campusIds);

                // Obtener documentos próximos a vencer o vencidos
                $documentosVencidos = $this->obtenerDocumentosVencidos($campusIds);

                return response()->json([
                    'tipo_usuario' => 'campus',
                    'estadisticas' => $estadisticasOptimizadas['total'],
                    'estadisticas_fiscales' => $estadisticasOptimizadas['fiscales'],
                    'estadisticas_medicos' => $estadisticasOptimizadas['medicos'],
                    'estadisticas_por_campus' => $estadisticasPorCampus,
                    'documentos_por_tipo' => [
                        [
                            'tipo_documento' => 'fiscales',
                            'cantidad' => $estadisticasOptimizadas['fiscales']->total_documentos,
                            'aprobados' => $estadisticasOptimizadas['fiscales']->aprobados
                        ],
                        [
                            'tipo_documento' => 'medicos',
                            'cantidad' => $estadisticasOptimizadas['medicos']->total_documentos,
                            'aprobados' => $estadisticasOptimizadas['medicos']->aprobados
                        ]
                    ],
                    'actividad_reciente' => $actividadReciente,
                    'documentos_vencidos' => $documentosVencidos,
                    'metodo_usado' => 'stored_procedure'
                ]);

            } catch (\Exception $spError) {
                Log::warning('Error con stored procedure, usando método fallback', [
                    'error' => $spError->getMessage(),
                    'user_id' => $idEmpleado
                ]);

                // Fallback al método anterior
                $estadisticasOptimizadas = $this->calcularEstadisticasInteligentes($campusIds);

                return response()->json([
                    'tipo_usuario' => 'campus',
                    'estadisticas' => $estadisticasOptimizadas['total'],
                    'estadisticas_fiscales' => $estadisticasOptimizadas['fiscales'],
                    'estadisticas_medicos' => $estadisticasOptimizadas['medicos'],
                    'documentos_por_tipo' => [
                        [
                            'tipo_documento' => 'fiscales',
                            'cantidad' => $estadisticasOptimizadas['fiscales']->total_documentos,
                            'aprobados' => $estadisticasOptimizadas['fiscales']->aprobados
                        ],
                        [
                            'tipo_documento' => 'medicos',
                            'cantidad' => $estadisticasOptimizadas['medicos']->total_documentos,
                            'aprobados' => $estadisticasOptimizadas['medicos']->aprobados
                        ]
                    ],
                    'actividad_reciente' => [],
                    'documentos_vencidos' => [],
                    'metodo_usado' => 'fallback'
                ]);
            }            // Primero, verificar qué estados existen en la tabla
            $estadosExistentes = DB::table('sug_documentos_informacion as sdi')
                ->whereIn('sdi.campus_id', $campusIds)
                ->select('sdi.estado', DB::raw('COUNT(*) as cantidad'))
                ->groupBy('sdi.estado')
                ->get();

            Log::info('Estados existentes en sug_documentos_informacion', [
                'estados' => $estadosExistentes->toArray()
            ]);

            // Estadísticas separadas por tipo de documento
            $estadisticasFiscales = (object)[
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'rechazados' => 0,
                'subidos' => 0
            ];

            $estadisticasMedicos = (object)[
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'rechazados' => 0,
                'subidos' => 0
            ];

            // Obtener estadísticas separadas por tipo y estado
            $estadisticasPorTipoYEstado = DB::table('sug_documentos_informacion as sdi')
                ->whereIn('sdi.campus_id', $campusIds)
                ->select(
                    'sdi.estado',
                    DB::raw('CASE
                        WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                        THEN \'medicos\'
                        ELSE \'fiscales\'
                    END as tipo_documento'),
                    DB::raw('COUNT(*) as cantidad')
                )
                ->groupBy('sdi.estado', DB::raw('CASE
                    WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                    THEN \'medicos\'
                    ELSE \'fiscales\'
                END'))
                ->get();

            Log::info('Estadísticas por tipo y estado obtenidas', [
                'estadisticas_detalladas' => $estadisticasPorTipoYEstado->toArray(),
                'total_registros' => $estadisticasPorTipoYEstado->count()
            ]);

            // Procesar estadísticas por tipo
            foreach ($estadisticasPorTipoYEstado as $stat) {
                $estadoNombre = strtolower(trim($stat->estado));
                $tipoDoc = $stat->tipo_documento;
                $cantidad = $stat->cantidad;

                Log::info('Procesando estadística', [
                    'estado_original' => $stat->estado,
                    'estado_normalizado' => $estadoNombre,
                    'tipo' => $tipoDoc,
                    'cantidad' => $cantidad,
                    'accion_tomada' => 'mapeando_estado'
                ]);

                // Determinar a qué objeto de estadísticas pertenece
                $estadisticasObj = ($tipoDoc === 'medicos') ? $estadisticasMedicos : $estadisticasFiscales;
                $estadisticasObj->total_documentos += $cantidad;

                // Mapear estado a categoría
                switch ($estadoNombre) {
                    case 'pendiente':
                    case 'pending':
                    case 'por_revisar':
                    case 'subido':
                    case 'nuevo':
                        $estadisticasObj->pendientes += $cantidad;
                        break;
                    case 'aprobado':
                    case 'approved':
                    case 'aceptado':
                    case 'vigente':
                    case 'activo':
                    case 'valido':
                        $estadisticasObj->aprobados += $cantidad;
                        Log::info('Estado mapeado como APROBADO', [
                            'estado_original' => $stat->estado,
                            'estado_normalizado' => $estadoNombre,
                            'tipo' => $tipoDoc,
                            'cantidad' => $cantidad,
                            'nuevo_total_aprobados' => $estadisticasObj->aprobados
                        ]);
                        break;
                    case 'rechazado':
                    case 'rejected':
                    case 'denegado':
                    case 'no_valido':
                    case 'invalido':
                        $estadisticasObj->rechazados += $cantidad;
                        break;
                    case 'en_revision':
                    case 'revisando':
                    case 'en_proceso':
                    case 'revision':
                        $estadisticasObj->en_revision += $cantidad;
                        break;
                    default:
                        Log::warning('Estado no mapeado', [
                            'estado' => $estadoNombre,
                            'tipo' => $tipoDoc,
                            'estado_original' => $stat->estado
                        ]);
                        // Por defecto, los estados desconocidos los ponemos como pendientes
                        $estadisticasObj->pendientes += $cantidad;
                        break;
                }
            }

            // LÓGICA INTELIGENTE: Comparar con documentos requeridos reales
            $estadisticasInteligentes = $this->calcularEstadisticasInteligentes($campusIds);

            // Validar que las estadísticas se calcularon correctamente
            if (!$estadisticasInteligentes || !is_array($estadisticasInteligentes)) {
                Log::error('Error: calcularEstadisticasInteligentes devolvió null o no es array', [
                    'resultado' => $estadisticasInteligentes,
                    'campus_ids' => $campusIds
                ]);

                // Fallback a estadísticas vacías
                $estadisticasInteligentes = [
                    'total' => (object) ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0],
                    'fiscales' => (object) ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0],
                    'medicos' => (object) ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0]
                ];
            }

            // Usar las estadísticas inteligentes en lugar de las básicas
            $estadisticasDocumentos = $estadisticasInteligentes['total'] ?? (object) ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];
            $estadisticasFiscales = $estadisticasInteligentes['fiscales'] ?? (object) ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];
            $estadisticasMedicos = $estadisticasInteligentes['medicos'] ?? (object) ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];

            Log::info('Estadísticas inteligentes calculadas', [
                'total' => $estadisticasDocumentos,
                'fiscales' => $estadisticasFiscales,
                'medicos' => $estadisticasMedicos
            ]);

            // Estadísticas detalladas POR CAMPUS - VERSIÓN INTELIGENTE
            $estadisticasPorCampus = $this->calcularEstadisticasPorCampusInteligente($campusIds);

            Log::info('Estadísticas por campus calculadas (inteligente)', [
                'campus_count' => count($estadisticasPorCampus),
                'preview' => array_slice($estadisticasPorCampus, 0, 2) // Solo los primeros 2 para el log
            ]);            // Documentos por tipo (fiscales vs médicos) - simplificado
            $documentosPorTipo = DB::table('sug_documentos_informacion as sdi')
                ->join('sug_documentos as sd', 'sdi.documento_id', '=', 'sd.id')
                ->whereIn('sdi.campus_id', $campusIds)
                ->select(
                    DB::raw('CASE
                        WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                        THEN \'medicos\'
                        ELSE \'fiscales\'
                    END as tipo_documento'),
                    DB::raw('COUNT(*) as cantidad')
                )
                ->groupBy(DB::raw('CASE
                    WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                    THEN \'medicos\'
                    ELSE \'fiscales\'
                END'))
                ->get();

            Log::info('Documentos por tipo obtenidos', [
                'documentos_por_tipo' => $documentosPorTipo->toArray()
            ]);

            // Actividad reciente del campus - simplificado
            $actividadReciente = DB::table('sug_documentos_informacion as sdi')
                ->join('sug_documentos as sd', 'sdi.documento_id', '=', 'sd.id')
                ->whereIn('sdi.campus_id', $campusIds)
                ->select(
                    'sd.nombre as documento_nombre',
                    'sdi.estado',
                    DB::raw('CASE
                        WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                        THEN \'medico\'
                        ELSE \'fiscal\'
                    END as tipo_documento')
                )
                ->limit(10)
                ->get();

            Log::info('Actividad reciente obtenida', [
                'cantidad_actividades' => $actividadReciente->count()
            ]);

            return response()->json([
                'tipo_usuario' => 'campus',
                'estadisticas' => $estadisticasDocumentos,
                'estadisticas_fiscales' => $estadisticasFiscales,
                'estadisticas_medicos' => $estadisticasMedicos,
                'estadisticas_por_campus' => $estadisticasPorCampus,
                'documentos_por_tipo' => $documentosPorTipo,
                'actividad_reciente' => $actividadReciente,
                'documentos_vencidos' => [], // Simplificado por ahora
                'debug_estados' => $estadosExistentes // Para debugging
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas campus', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'campus_ids' => $campusIds
            ]);
            throw $e;
        }
    }

    /**
     * Calcular estadísticas por campus individual de forma inteligente
     */
    private function calcularEstadisticasPorCampusInteligente($campusIds)
    {
        $estadisticasPorCampus = [];

        foreach ($campusIds as $campusId) {
            // Obtener información del campus
            $campusInfo = DB::table('campus')
                ->where('ID_Campus', $campusId)
                ->select('ID_Campus', 'Campus', 'Activo')
                ->first();

            if (!$campusInfo) continue;

            // Calcular documentos requeridos vs subidos para este campus
            $estadisticasCampus = $this->calcularComparativoPorCampus($campusId);

            $estadisticasPorCampus[] = [
                'campus_id' => $campusInfo->ID_Campus,
                'campus_nombre' => $campusInfo->Campus,
                'fiscales' => $estadisticasCampus['fiscales'],
                'medicos' => $estadisticasCampus['medicos'],
                'total_requeridos' => $estadisticasCampus['total_requeridos'],
                'total_subidos' => $estadisticasCampus['total_subidos'],
                'total_aprobados' => $estadisticasCampus['total_aprobados'],
                'porcentaje_cumplimiento' => $estadisticasCampus['total_requeridos'] > 0
                    ? round(($estadisticasCampus['total_subidos'] / $estadisticasCampus['total_requeridos']) * 100, 1)
                    : 0,
                'porcentaje_aprobacion' => $estadisticasCampus['total_subidos'] > 0
                    ? round(($estadisticasCampus['total_aprobados'] / $estadisticasCampus['total_subidos']) * 100, 1)
                    : 0,
                'tiene_fiscales' => $estadisticasCampus['fiscales']['requeridos'] > 0,
                'tiene_medicos' => $estadisticasCampus['medicos']['requeridos'] > 0
            ];
        }

        return $estadisticasPorCampus;
    }

    /**
     * Calcular comparativo de documentos requeridos vs subidos para un campus específico
     */
    private function calcularComparativoPorCampus($campusId)
    {
        $estadisticasFiscales = [
            'requeridos' => 0,
            'subidos' => 0,
            'pendientes' => 0,
            'aprobados' => 0,
            'rechazados' => 0,
            'en_revision' => 0
        ];

        $estadisticasMedicos = [
            'requeridos' => 0,
            'subidos' => 0,
            'pendientes' => 0,
            'aprobados' => 0,
            'rechazados' => 0,
            'en_revision' => 0
        ];

        // 1. DOCUMENTOS FISCALES - usar método original para obtener archivos
        $documentosFiscales = $this->getDocumentosRequeridos($campusId, null);

        if (is_array($documentosFiscales)) {
            foreach ($documentosFiscales as $doc) {
                $estadisticasFiscales['requeridos']++;

                // Si tiene archivo subido, contarlo
                if (!empty($doc['archivo']) || !empty($doc['archivos'])) {
                    $estadisticasFiscales['subidos']++;

                    $estado = strtolower(trim($doc['estado'] ?? 'pendiente'));

                    if (in_array($estado, ['vigente', 'aprobado', 'approved', 'aceptado', 'activo', 'valido'])) {
                        $estadisticasFiscales['aprobados']++;
                    } elseif (in_array($estado, ['rechazado', 'rejected', 'denegado', 'no_valido', 'invalido'])) {
                        $estadisticasFiscales['rechazados']++;
                    } elseif (in_array($estado, ['en_revision', 'revisando', 'en_proceso', 'revision'])) {
                        $estadisticasFiscales['en_revision']++;
                    } else {
                        $estadisticasFiscales['pendientes']++;
                    }
                }
            }
        }

        // 2. DOCUMENTOS MÉDICOS - usar método original para obtener archivos
        $carrerasMedicas = $this->obtenerCarrerasMedicasCampus($campusId);

        foreach ($carrerasMedicas as $carrera) {
            $documentosMedicos = $this->getDocumentosRequeridos($campusId, $carrera['ID_Especialidad']);

            if (is_array($documentosMedicos)) {
                foreach ($documentosMedicos as $doc) {
                    $estadisticasMedicos['requeridos']++;

                    // Si tiene archivo subido, contarlo
                    if (!empty($doc['archivo']) || !empty($doc['archivos'])) {
                        $estadisticasMedicos['subidos']++;

                        $estado = strtolower(trim($doc['estado'] ?? 'pendiente'));

                        if (in_array($estado, ['vigente', 'aprobado', 'approved', 'aceptado', 'activo', 'valido'])) {
                            $estadisticasMedicos['aprobados']++;
                        } elseif (in_array($estado, ['rechazado', 'rejected', 'denegado', 'no_valido', 'invalido'])) {
                            $estadisticasMedicos['rechazados']++;
                        } elseif (in_array($estado, ['en_revision', 'revisando', 'en_proceso', 'revision'])) {
                            $estadisticasMedicos['en_revision']++;
                        } else {
                            $estadisticasMedicos['pendientes']++;
                        }
                    }
                }
            }
        }

        return [
            'fiscales' => $estadisticasFiscales,
            'medicos' => $estadisticasMedicos,
            'total_requeridos' => $estadisticasFiscales['requeridos'] + $estadisticasMedicos['requeridos'],
            'total_subidos' => $estadisticasFiscales['subidos'] + $estadisticasMedicos['subidos'],
            'total_aprobados' => $estadisticasFiscales['aprobados'] + $estadisticasMedicos['aprobados']
        ];
    }

    /**
     * Calcular estadísticas inteligentes comparando con documentos requeridos
     * Basado en cómo realmente funciona el sistema de subida de documentos
     */
    private function calcularEstadisticasInteligentes($campusIds)
    {
        try {
            Log::info('Iniciando cálculo de estadísticas inteligentes', [
                'campus_ids' => $campusIds
            ]);

            // LÓGICA CORREGIDA: Contar documentos por campus (no total del sistema)
            // Para estadísticas generales, usar lógica por campus como en getDocumentosRequeridos
            $estadisticasFiscales = [
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'en_revision' => 0,
                'caducados' => 0,
                'rechazados' => 0
            ];

            // Contar documentos fiscales usando un método específico para estadísticas
            foreach ($campusIds as $campusId) {
                try {
                    $documentosFiscales = $this->getDocumentosRequeridosParaEstadisticas($campusId, null);

                    Log::info("Procesando campus $campusId", [
                        'documentos_fiscales_count' => is_array($documentosFiscales) ? count($documentosFiscales) : 'null/error',
                        'documentos_fiscales_type' => gettype($documentosFiscales)
                    ]);

                    if (is_array($documentosFiscales)) {
                        foreach ($documentosFiscales as $doc) {
                            $estadisticasFiscales['total_documentos']++;

                            $estado = strtolower(trim($doc['estado'] ?? ''));

                            // Lógica de estados mejorada
                            if ($estado === 'vigente' || $estado === 'aprobado') {
                                $estadisticasFiscales['aprobados']++;
                            } elseif ($estado === 'rechazado') {
                                $estadisticasFiscales['rechazados']++;
                            } elseif ($estado === 'caducado' || $estado === 'vencido') {
                                $estadisticasFiscales['caducados']++;
                            } elseif ($estado === 'en_revision' || $estado === 'revision') {
                                $estadisticasFiscales['en_revision']++;
                            } else {
                                $estadisticasFiscales['pendientes']++;
                            }
                        }
                    } else {
                        Log::warning("getDocumentosRequeridosParaEstadisticas devolvió null o no es array para campus $campusId");
                    }
                } catch (\Exception $e) {
                    Log::error("Error procesando documentos fiscales para campus $campusId", [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                }
            }            // Documentos médicos - usar misma lógica por campus
            $estadisticasMedicos = [
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'en_revision' => 0,
                'caducados' => 0,
                'rechazados' => 0
            ];

            // Contar documentos médicos por campus y carrera
            foreach ($campusIds as $campusId) {
                try {
                    $carrerasMedicas = $this->obtenerCarrerasMedicasCampus($campusId);

                    Log::info("Procesando carreras médicas campus $campusId", [
                        'carreras_count' => is_array($carrerasMedicas) || is_object($carrerasMedicas) ? count($carrerasMedicas) : 'null/error'
                    ]);

                    foreach ($carrerasMedicas as $carrera) {
                        try {
                            $documentosMedicos = $this->getDocumentosRequeridosParaEstadisticas($campusId, $carrera['ID_Especialidad'] ?? null);

                            if (is_array($documentosMedicos)) {
                                foreach ($documentosMedicos as $doc) {
                                    $estadisticasMedicos['total_documentos']++;

                                    $estado = strtolower(trim($doc['estado'] ?? ''));

                                    // Lógica de estados mejorada
                                    if ($estado === 'vigente' || $estado === 'aprobado') {
                                        $estadisticasMedicos['aprobados']++;
                                    } elseif ($estado === 'rechazado') {
                                        $estadisticasMedicos['rechazados']++;
                                    } elseif ($estado === 'caducado' || $estado === 'vencido') {
                                        $estadisticasMedicos['caducados']++;
                                    } elseif ($estado === 'en_revision' || $estado === 'revision') {
                                        $estadisticasMedicos['en_revision']++;
                                    } else {
                                        $estadisticasMedicos['pendientes']++;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error("Error procesando documentos médicos para carrera", [
                                'campus_id' => $campusId,
                                'carrera' => $carrera,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error obteniendo carreras médicas para campus $campusId", [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                }
            }

            // Totales
            $estadisticasTotal = [
                'total_documentos' => $estadisticasFiscales['total_documentos'] + $estadisticasMedicos['total_documentos'],
                'pendientes' => $estadisticasFiscales['pendientes'] + $estadisticasMedicos['pendientes'],
                'aprobados' => $estadisticasFiscales['aprobados'] + $estadisticasMedicos['aprobados'],
                'en_revision' => $estadisticasFiscales['en_revision'] + $estadisticasMedicos['en_revision'],
                'caducados' => $estadisticasFiscales['caducados'] + $estadisticasMedicos['caducados'],
                'rechazados' => $estadisticasFiscales['rechazados'] + $estadisticasMedicos['rechazados']
            ];

            Log::info('Estadísticas inteligentes calculadas correctamente (lógica por campus)', [
                'campus_ids' => $campusIds,
                'total_campus' => count($campusIds),
                'fiscales' => $estadisticasFiscales,
                'medicos' => $estadisticasMedicos,
                'total' => $estadisticasTotal,
                'debug_detalle' => [
                    'logica_usada' => 'conteo por campus usando getDocumentosRequeridosParaEstadisticas',
                    'esperado_fiscales' => count($campusIds) . ' campus × 6 docs = ' . (count($campusIds) * 6),
                    'obtenido_fiscales' => $estadisticasFiscales['total_documentos'],
                    'filtro_aplicado' => 'aplica_area_salud: 0=fiscal, 1=medico'
                ]
            ]);

            return [
                'total' => (object) $estadisticasTotal,
                'fiscales' => (object) $estadisticasFiscales,
                'medicos' => (object) $estadisticasMedicos
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando estadísticas inteligentes', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback a estadísticas básicas usando la lógica original
            return $this->calcularEstadisticasBasicas($campusIds);
        }
    }

    /**
     * Calcular estadísticas básicas como fallback
     */
    private function calcularEstadisticasBasicas($campusIds)
    {
            $estadisticasPorTipoYEstado = DB::table('sug_documentos_informacion as sdi')
                ->whereIn('sdi.campus_id', $campusIds)
                ->select(
                    'sdi.estado',
                    DB::raw('CASE
                        WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                        THEN \'medicos\'
                        ELSE \'fiscales\'
                    END as tipo_documento'),
                    DB::raw('COUNT(*) as cantidad')
                )
                ->groupBy('sdi.estado', DB::raw('CASE
                    WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                    THEN \'medicos\'
                    ELSE \'fiscales\'
                END'))
                ->get();

            $estatFiscalesBasico = ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];
            $estatMedicosBasico = ['total_documentos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];

            foreach ($estadisticasPorTipoYEstado as $stat) {
                $estadoNombre = strtolower(trim($stat->estado ?? ''));
                $tipoDoc = $stat->tipo_documento;
                $cantidad = $stat->cantidad;

                // Lógica simplificada: vigente=aprobado, rechazado=rechazado, todo lo demás=pendiente
                if ($tipoDoc === 'medicos') {
                    $estatMedicosBasico['total_documentos'] += $cantidad;
                    if ($estadoNombre === 'vigente') {
                        $estatMedicosBasico['aprobados'] += $cantidad;
                    } elseif ($estadoNombre === 'rechazado') {
                        $estatMedicosBasico['rechazados'] += $cantidad;
                    } else {
                        $estatMedicosBasico['pendientes'] += $cantidad;
                    }
                } else {
                    $estatFiscalesBasico['total_documentos'] += $cantidad;
                    if ($estadoNombre === 'vigente') {
                        $estatFiscalesBasico['aprobados'] += $cantidad;
                    } elseif ($estadoNombre === 'rechazado') {
                        $estatFiscalesBasico['rechazados'] += $cantidad;
                    } else {
                        $estatFiscalesBasico['pendientes'] += $cantidad;
                    }
                }
            }

            $estatTotalBasico = [
                'total_documentos' => $estatFiscalesBasico['total_documentos'] + $estatMedicosBasico['total_documentos'],
                'pendientes' => $estatFiscalesBasico['pendientes'] + $estatMedicosBasico['pendientes'],
                'aprobados' => $estatFiscalesBasico['aprobados'] + $estatMedicosBasico['aprobados'],
                'rechazados' => $estatFiscalesBasico['rechazados'] + $estatMedicosBasico['rechazados']
            ];

            return [
                'total' => (object) $estatTotalBasico,
                'fiscales' => (object) $estatFiscalesBasico,
                'medicos' => (object) $estatMedicosBasico
            ];
        }

    /**
     * Método específico para obtener documentos requeridos SOLO para estadísticas
     * Filtra por aplica_area_salud sin afectar la funcionalidad del frontend
     */
    private function getDocumentosRequeridosParaEstadisticas($campusId = null, $carreraId = null)
    {
        try {
            // Filtrar documentos según el tipo requerido SOLO para estadísticas
            if ($carreraId !== null) {
                // Para documentos médicos: aplica_area_salud = 1
                $documentos = SugDocumento::where('activo', true)
                    ->where('aplica_area_salud', 1)
                    ->get();

                Log::info('Documentos médicos encontrados para estadísticas', [
                    'campus_id' => $campusId,
                    'carrera_id' => $carreraId,
                    'count' => $documentos->count(),
                    'documentos' => $documentos->pluck('nombre', 'id')->toArray()
                ]);
            } else {
                // Para documentos fiscales: aplica_area_salud = 0
                $documentos = SugDocumento::where('activo', true)
                    ->where('aplica_area_salud', 0)
                    ->get();

                Log::info('Documentos fiscales encontrados para estadísticas', [
                    'campus_id' => $campusId,
                    'count' => $documentos->count(),
                    'documentos' => $documentos->map(function($doc) {
                        return [
                            'id' => $doc->id,
                            'nombre' => $doc->nombre,
                            'aplica_area_salud' => $doc->aplica_area_salud,
                            'activo' => $doc->activo
                        ];
                    })->toArray(),
                    'debug_query' => 'SELECT * FROM sug_documentos WHERE activo = 1 AND aplica_area_salud = 0'
                ]);
            }            $documentosFormateados = [];

            foreach ($documentos as $doc) {
                // Si se especifica un campus, buscar información específica del documento para ese campus
                $informacion = null;
                if ($campusId) {
                    $query = SugDocumentoInformacion::where('documento_id', $doc->id)
                        ->where('campus_id', $campusId);

                    // Si se especifica carrera_id, incluirlo en la consulta
                    if ($carreraId !== null) {
                        $query->where('carrera_id', $carreraId);
                    }

                    $informacion = $query->with(['archivoActual', 'archivos'])->first();
                }

                // Determinar estado del documento
                $estado = 'pendiente';
                if ($informacion) {
                    $estado = $informacion->estado ?? 'pendiente';
                }

                $documentosFormateados[] = [
                    'id' => (string)$doc->id,
                    'nombre' => $doc->nombre,
                    'estado' => $estado
                ];
            }

            return $documentosFormateados;

        } catch (\Exception $e) {
            Log::error('Error obteniendo documentos para estadísticas', [
                'error' => $e->getMessage(),
                'campus_id' => $campusId,
                'carrera_id' => $carreraId
            ]);

            return [];
        }
    }

    /**
     * Generar estadísticas separadas por campus usando el stored procedure
     */
    private function generarEstadisticasPorCampus($idEmpleado, $campusIds)
    {
        try {
            // Ejecutar el stored procedure CON @Todos=0 para datos específicos del empleado
            // pero también probar @Todos=1 para obtener todos los datos
            $resultados = DB::select('EXEC sug_reporte_estatus_documentos ?, ?', [$idEmpleado, 1]);

            Log::info('Generando estadísticas por campus con @Todos=1', [
                'id_empleado' => $idEmpleado,
                'campus_ids' => $campusIds,
                'resultados_count' => count($resultados),
                'primera_fila_estructura' => count($resultados) > 0 ? get_object_vars($resultados[0]) : 'Sin resultados'
            ]);

            // Obtener nombres reales de campus
            $campusIdsDelSP = collect($resultados)->pluck('campus_id')->unique()->toArray();
            $campusNombres = campus_model::whereIn('ID_Campus', $campusIdsDelSP)
                ->pluck('Campus', 'ID_Campus')
                ->toArray();

            Log::info('Nombres de campus obtenidos', [
                'campus_ids_del_sp' => $campusIdsDelSP,
                'campus_nombres' => $campusNombres
            ]);

            $estadisticasPorCampus = [];

            // Agrupar resultados por campus
            foreach ($resultados as $fila) {
                $campusId = $fila->campus_id;
                $tipoDoc = $fila->tipo_documento;

                // Filtrar solo los campus del usuario (si se especificaron)
                if (!empty($campusIds) && !in_array($campusId, $campusIds)) {
                    continue;
                }

                // Inicializar campus si no existe
                if (!isset($estadisticasPorCampus[$campusId])) {
                    $campusNombre = isset($campusNombres[$campusId])
                        ? mb_convert_encoding($campusNombres[$campusId], 'UTF-8', 'UTF-8')
                        : "Campus $campusId";

                    $estadisticasPorCampus[$campusId] = [
                        'campus_id' => (int)$campusId,
                        'campus_nombre' => $campusNombre,
                        'fiscales' => [
                            'total_documentos' => 0,
                            'pendientes' => 0,
                            'aprobados' => 0,
                            'caducados' => 0,
                            'rechazados' => 0,
                            'en_revision' => 0,
                            'subidos' => 0
                        ],
                        'medicos' => [
                            'total_documentos' => 0,
                            'pendientes' => 0,
                            'aprobados' => 0,
                            'caducados' => 0,
                            'rechazados' => 0,
                            'en_revision' => 0,
                            'subidos' => 0
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
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['en_revision'] = 0; // El SP no tiene esta columna
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['subidos'] = 0; // Campo requerido por frontend

                // Debug: Log de caducados para verificar
                if (isset($fila->Caducados) && (int)$fila->Caducados > 0) {
                    Log::info("Caducados encontrados en generarEstadisticasPorCampus", [
                        'campus_id' => $campusId,
                        'campus_nombre' => $estadisticasPorCampus[$campusId]['campus_nombre'],
                        'tipo_documento' => $tipoDoc,
                        'caducados_raw' => $fila->Caducados,
                        'caducados_int' => (int)$fila->Caducados
                    ]);
                }
            }

            // Calcular totales por campus y agregar propiedades adicionales requeridas por el frontend
            foreach ($estadisticasPorCampus as $campusId => &$campus) {
                // Calcular totales generales del campus
                $campus['total_documentos'] = $campus['fiscales']['total_documentos'] + $campus['medicos']['total_documentos'];
                $campus['total_aprobados'] = $campus['fiscales']['aprobados'] + $campus['medicos']['aprobados'];
                $campus['total_caducados'] = $campus['fiscales']['caducados'] + $campus['medicos']['caducados'];
                $campus['total_pendientes'] = $campus['fiscales']['pendientes'] + $campus['medicos']['pendientes'];
                $campus['total_rechazados'] = $campus['fiscales']['rechazados'] + $campus['medicos']['rechazados'];

                // Calcular porcentaje de cumplimiento
                $campus['porcentaje_cumplimiento'] = $campus['total_documentos'] > 0
                    ? round(($campus['total_aprobados'] / $campus['total_documentos']) * 100)
                    : 0;

                // Determinar qué tipos de documentos tiene este campus
                $campus['tiene_fiscales'] = $campus['fiscales']['total_documentos'] > 0;
                $campus['tiene_medicos'] = $campus['medicos']['total_documentos'] > 0;

                // Log detallado de campus con caducados
                if ($campus['total_caducados'] > 0) {
                    Log::info("Campus con caducados en estructura generarEstadisticasPorCampus", [
                        'campus_nombre' => $campus['campus_nombre'],
                        'total_caducados' => $campus['total_caducados'],
                        'fiscales_caducados' => $campus['fiscales']['caducados'],
                        'medicos_caducados' => $campus['medicos']['caducados'],
                        'estructura_fiscales' => $campus['fiscales'],
                        'estructura_medicos' => $campus['medicos']
                    ]);
                }
            }

            // Convertir a array indexado
            $resultado = array_values($estadisticasPorCampus);

            Log::info('Estadísticas por campus generadas', [
                'total_campus_procesados' => count($resultado),
                'campus_con_documentos' => count(array_filter($resultado, function($campus) {
                    return $campus['total_documentos'] > 0;
                })),
                'campus_con_caducados' => count(array_filter($resultado, function($campus) {
                    return $campus['total_caducados'] > 0;
                }))
            ]);

            return $resultado;

        } catch (\Exception $e) {
            Log::error('Error generando estadísticas por campus', [
                'id_empleado' => $idEmpleado,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Calcular estadísticas usando stored procedure optimizado
     */
    private function calcularEstadisticasConSP($idEmpleado)
    {
        try {
            Log::info('Ejecutando stored procedure sug_reporte_estatus_documentos con parámetro @Todos', [
                'id_empleado' => $idEmpleado
            ]);

            // Ejecutar el stored procedure con parámetro @Todos = 0 (solo campus del director)
            $resultados = DB::select('EXEC sug_reporte_estatus_documentos ?, ?', [$idEmpleado, 0]);

            Log::info('Resultados raw del stored procedure', [
                'id_empleado' => $idEmpleado,
                'resultados_count' => count($resultados),
                'resultados_raw' => $resultados
            ]);

            // Inicializar contadores
            $estadisticasFiscales = [
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'en_revision' => 0,
                'caducados' => 0,
                'rechazados' => 0
            ];

            $estadisticasMedicos = [
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'en_revision' => 0,
                'caducados' => 0,
                'rechazados' => 0
            ];

            // Procesar resultados del SP
            foreach ($resultados as $fila) {
                if ($fila->tipo_documento === 'FISCAL') {
                    $estadisticasFiscales['total_documentos'] += $fila->Total;
                    $estadisticasFiscales['pendientes'] += $fila->Pendientes;
                    $estadisticasFiscales['aprobados'] += $fila->Vigentes; // Vigente = Aprobado
                    $estadisticasFiscales['caducados'] += $fila->Caducados ?? 0; // Campo de caducados
                    $estadisticasFiscales['rechazados'] += $fila->Rechazados;
                } else {
                    $estadisticasMedicos['total_documentos'] += $fila->Total;
                    $estadisticasMedicos['pendientes'] += $fila->Pendientes;
                    $estadisticasMedicos['aprobados'] += $fila->Vigentes; // Vigente = Aprobado
                    $estadisticasMedicos['caducados'] += $fila->Caducados ?? 0; // Campo de caducados
                    $estadisticasMedicos['rechazados'] += $fila->Rechazados;
                }
            }

            // Calcular totales
            $estadisticasTotal = [
                'total_documentos' => $estadisticasFiscales['total_documentos'] + $estadisticasMedicos['total_documentos'],
                'pendientes' => $estadisticasFiscales['pendientes'] + $estadisticasMedicos['pendientes'],
                'aprobados' => $estadisticasFiscales['aprobados'] + $estadisticasMedicos['aprobados'],
                'en_revision' => $estadisticasFiscales['en_revision'] + $estadisticasMedicos['en_revision'],
                'caducados' => $estadisticasFiscales['caducados'] + $estadisticasMedicos['caducados'],
                'rechazados' => $estadisticasFiscales['rechazados'] + $estadisticasMedicos['rechazados']
            ];

            Log::info('Estadísticas calculadas con stored procedure', [
                'id_empleado' => $idEmpleado,
                'total' => $estadisticasTotal,
                'fiscales' => $estadisticasFiscales,
                'medicos' => $estadisticasMedicos,
                'resultados_sp' => $resultados
            ]);

            return [
                'total' => (object) $estadisticasTotal,
                'fiscales' => (object) $estadisticasFiscales,
                'medicos' => (object) $estadisticasMedicos
            ];

        } catch (\Exception $e) {
            Log::error('Error ejecutando stored procedure sug_reporte_estatus_documentos', [
                'id_empleado' => $idEmpleado,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            // Fallback al método anterior
            throw $e;
        }
    }

    /**
     * Obtener actividad reciente de documentos del campus
     */
    private function obtenerActividadReciente($campusIds, $limite = 10)
    {
        try {
            $actividad = DB::table('sug_documentos_informacion as sdi')
                ->join('sug_documentos as sd', 'sdi.documento_id', '=', 'sd.id')
                ->join('campus as c', 'sdi.campus_id', '=', 'c.ID_Campus')
                ->leftJoin('sug_documentos_archivos as sda', 'sdi.archivo_actual_id', '=', 'sda.id')
                ->whereIn('sdi.campus_id', $campusIds)
                ->where('c.Activo', 1)
                ->select(
                    'sd.nombre as documento_nombre',
                    'c.Campus as campus_nombre',
                    'sdi.estado',
                    'sdi.actualizado_en as updated_at',
                    'sdi.creado_en',
                    DB::raw('CASE
                        WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                        THEN \'MÉDICO\'
                        ELSE \'FISCAL\'
                    END as tipo_documento')
                )
                ->whereNotNull(DB::raw('COALESCE(sdi.actualizado_en, sdi.creado_en)'))
                ->orderByDesc(DB::raw('COALESCE(sdi.actualizado_en, sdi.creado_en)'))
                ->limit($limite)
                ->get();

            return $actividad->map(function($item) {
                return [
                    'documento_nombre' => $item->documento_nombre,
                    'campus_nombre' => mb_convert_encoding($item->campus_nombre ?? 'Campus desconocido', 'UTF-8', 'UTF-8'),
                    'estado' => $item->estado,
                    'updated_at' => $item->updated_at ?? $item->creado_en,
                    'tipo_documento' => $item->tipo_documento
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Error obteniendo actividad reciente', [
                'campus_ids' => $campusIds,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener documentos próximos a vencer o ya vencidos
     */
    private function obtenerDocumentosVencidos($campusIds, $diasAntelacion = 30)
    {
        try {
            $documentosVencidos = DB::table('sug_documentos_informacion as sdi')
                ->join('sug_documentos as sd', 'sdi.documento_id', '=', 'sd.id')
                ->join('campus as c', 'sdi.campus_id', '=', 'c.ID_Campus')
                ->whereIn('sdi.campus_id', $campusIds)
                ->where('c.Activo', 1)
                ->whereNotNull('sdi.vigencia_documento')
                ->where(function($query) use ($diasAntelacion) {
                    // Documentos que vencen en los próximos X días o ya vencieron
                    $query->where('sdi.vigencia_documento', '<=', now()->addDays($diasAntelacion)->format('Y-m-d'))
                          ->where('sdi.vigencia_documento', '>=', now()->subDays(365)->format('Y-m-d')); // No más de 1 año atrás
                })
                ->select(
                    'sd.nombre as documento_nombre',
                    'c.Campus as campus_nombre',
                    'sdi.vigencia_documento as fecha_vigencia_ia',
                    'sdi.estado',
                    DB::raw('DATEDIFF(DAY, GETDATE(), sdi.vigencia_documento) as dias_restantes'),
                    DB::raw('CASE
                        WHEN sdi.carrera_id IS NOT NULL AND sdi.carrera_id != \'\'
                        THEN \'MÉDICO\'
                        ELSE \'FISCAL\'
                    END as tipo_documento')
                )
                ->orderBy(DB::raw('DATEDIFF(DAY, GETDATE(), sdi.vigencia_documento)'))
                ->limit(15)
                ->get();

            return $documentosVencidos->map(function($item) {
                return [
                    'documento_nombre' => $item->documento_nombre,
                    'campus_nombre' => mb_convert_encoding($item->campus_nombre ?? 'Campus desconocido', 'UTF-8', 'UTF-8'),
                    'fecha_vigencia_ia' => $item->fecha_vigencia_ia,
                    'estado' => $item->estado,
                    'dias_restantes' => (int)$item->dias_restantes,
                    'tipo_documento' => $item->tipo_documento
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Error obteniendo documentos vencidos', [
                'campus_ids' => $campusIds,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
