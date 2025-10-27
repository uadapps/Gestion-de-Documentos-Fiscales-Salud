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
                WHERE (e.descripcion LIKE '%MEDIC%' OR E.Descripcion LIKE '%ENFER%' OR E.Descripcion LIKE '%Méd%' OR E.Descripcion LIKE '%psi%' OR E.Descripcion LIKE '%NUTRI%' OR E.Descripcion LIKE '%FISIO%' OR E.Descripcion LIKE '%ODONTO%' OR E.Descripcion LIKE '%COSME%')
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
                WHERE (e.descripcion LIKE '%MEDIC%' OR E.Descripcion LIKE '%ENFER%' OR E.Descripcion LIKE '%Méd%' OR E.Descripcion LIKE '%psi%' OR E.Descripcion LIKE '%NUTRI%' OR E.Descripcion LIKE '%FISIO%' OR E.Descripcion LIKE '%ODONTO%' OR E.Descripcion LIKE '%COSME%')
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
            Log::info('Iniciando obtención de estadísticas de supervisor');

            // Aquí iría la lógica para estadísticas de supervisor
            // Por ahora, retornar datos básicos
            return response()->json([
                'tipo_usuario' => 'supervisor',
                'estadisticas' => [
                    'total_campus' => 0,
                    'documentos_por_estado' => [
                        'total_documentos' => 0,
                        'pendientes' => 0,
                        'aprobados' => 0,
                        'rechazados' => 0,
                        'en_revision' => 0
                    ],
                    'cumplimiento_promedio' => 0,
                    'campus_criticos' => 0,
                    'usuarios_activos' => 0
                ],
                'cumplimiento_por_campus' => [],
                'alertas_criticas' => [],
                'actividad_reciente' => []
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de supervisor', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Error obteniendo estadísticas de supervisor'], 500);
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
                        'rechazados' => 0,
                        'en_revision' => 0
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
                    'actividad_reciente' => [],
                    'documentos_vencidos' => [],
                    'metodo_usado' => 'stored_procedure'
                ]);

            } catch (\Exception $spError) {
                Log::warning('Error con stored procedure, retornando datos vacíos', [
                    'error' => $spError->getMessage(),
                    'user_id' => $idEmpleado
                ]);

                return response()->json([
                    'tipo_usuario' => 'campus',
                    'estadisticas' => [
                        'total_documentos' => 0,
                        'pendientes' => 0,
                        'aprobados' => 0,
                        'rechazados' => 0,
                        'en_revision' => 0
                    ],
                    'estadisticas_fiscales' => (object)[
                        'total_documentos' => 0,
                        'pendientes' => 0,
                        'aprobados' => 0,
                        'rechazados' => 0,
                        'en_revision' => 0
                    ],
                    'estadisticas_medicos' => (object)[
                        'total_documentos' => 0,
                        'pendientes' => 0,
                        'aprobados' => 0,
                        'rechazados' => 0,
                        'en_revision' => 0
                    ],
                    'estadisticas_por_campus' => [],
                    'documentos_por_tipo' => [],
                    'actividad_reciente' => [],
                    'documentos_vencidos' => [],
                    'metodo_usado' => 'fallback'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de campus', [
                'campus_ids' => $campusIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'tipo_usuario' => 'campus',
                'estadisticas' => [
                    'total_documentos' => 0,
                    'pendientes' => 0,
                    'aprobados' => 0,
                    'rechazados' => 0,
                    'en_revision' => 0
                ],
                'estadisticas_fiscales' => null,
                'estadisticas_medicos' => null,
                'estadisticas_por_campus' => [],
                'documentos_por_tipo' => [],
                'actividad_reciente' => [],
                'documentos_vencidos' => [],
                'metodo_usado' => 'error'
            ], 500);
        }
    }

    /**
     * Generar estadísticas separadas por campus usando el stored procedure
     */
    private function generarEstadisticasPorCampus($idEmpleado, $campusIds)
    {
        try {
            // Ejecutar el stored procedure
            $resultados = DB::select('EXEC sug_reporte_estatus_documentos ?', [$idEmpleado]);

            Log::info('Generando estadísticas por campus', [
                'id_empleado' => $idEmpleado,
                'campus_ids' => $campusIds,
                'resultados_count' => count($resultados),
                'resultados_raw' => $resultados,
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
                            'rechazados' => 0,
                            'en_revision' => 0
                        ],
                        'medicos' => [
                            'total_documentos' => 0,
                            'pendientes' => 0,
                            'aprobados' => 0,
                            'rechazados' => 0,
                            'en_revision' => 0
                        ]
                    ];
                }

                // Determinar qué tipo de estadística actualizar
                $tipoEstadistica = ($tipoDoc === 'FISCAL') ? 'fiscales' : 'medicos';

                // Actualizar estadísticas usando los nombres exactos del SP
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['total_documentos'] = (int)$fila->Total;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['pendientes'] = (int)$fila->Pendientes;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['aprobados'] = (int)$fila->Vigentes;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['rechazados'] = (int)$fila->Rechazados;
                $estadisticasPorCampus[$campusId][$tipoEstadistica]['en_revision'] = 0; // El SP no tiene esta columna
            }

            // Calcular totales por campus y agregar propiedades adicionales requeridas por el frontend
            foreach ($estadisticasPorCampus as $campusId => &$campus) {
                // Calcular totales generales del campus
                $campus['total_documentos'] = $campus['fiscales']['total_documentos'] + $campus['medicos']['total_documentos'];
                $campus['total_aprobados'] = $campus['fiscales']['aprobados'] + $campus['medicos']['aprobados'];

                // Calcular porcentaje de cumplimiento
                $campus['porcentaje_cumplimiento'] = $campus['total_documentos'] > 0
                    ? round(($campus['total_aprobados'] / $campus['total_documentos']) * 100)
                    : 0;

                // Determinar qué tipos de documentos tiene este campus
                $campus['tiene_fiscales'] = $campus['fiscales']['total_documentos'] > 0;
                $campus['tiene_medicos'] = $campus['medicos']['total_documentos'] > 0;
            }

            // Convertir a array indexado
            $resultado = array_values($estadisticasPorCampus);

            Log::info('Estadísticas por campus generadas', [
                'estadisticas_por_campus' => $resultado
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
            Log::info('Ejecutando stored procedure sug_reporte_estatus_documentos', [
                'id_empleado' => $idEmpleado
            ]);

            // Ejecutar el stored procedure
            $resultados = DB::select('EXEC sug_reporte_estatus_documentos ?', [$idEmpleado]);

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
                'rechazados' => 0,
                'en_revision' => 0
            ];

            $estadisticasMedicos = [
                'total_documentos' => 0,
                'pendientes' => 0,
                'aprobados' => 0,
                'rechazados' => 0,
                'en_revision' => 0
            ];

            // Procesar resultados
            foreach ($resultados as $fila) {
                $tipoDoc = $fila->tipo_documento;
                $total = (int)$fila->Total;
                $pendientes = (int)$fila->Pendientes;
                $aprobados = (int)$fila->Vigentes;
                $rechazados = (int)$fila->Rechazados;

                if ($tipoDoc === 'FISCAL') {
                    $estadisticasFiscales['total_documentos'] += $total;
                    $estadisticasFiscales['pendientes'] += $pendientes;
                    $estadisticasFiscales['aprobados'] += $aprobados;
                    $estadisticasFiscales['rechazados'] += $rechazados;
                } elseif ($tipoDoc === 'MEDICINA') {
                    $estadisticasMedicos['total_documentos'] += $total;
                    $estadisticasMedicos['pendientes'] += $pendientes;
                    $estadisticasMedicos['aprobados'] += $aprobados;
                    $estadisticasMedicos['rechazados'] += $rechazados;
                }
            }

            // Calcular totales
            $estadisticasTotales = [
                'total_documentos' => $estadisticasFiscales['total_documentos'] + $estadisticasMedicos['total_documentos'],
                'pendientes' => $estadisticasFiscales['pendientes'] + $estadisticasMedicos['pendientes'],
                'aprobados' => $estadisticasFiscales['aprobados'] + $estadisticasMedicos['aprobados'],
                'rechazados' => $estadisticasFiscales['rechazados'] + $estadisticasMedicos['rechazados'],
                'en_revision' => 0 // El SP no tiene esta columna
            ];

            return [
                'total' => (object)$estadisticasTotales,
                'fiscales' => (object)$estadisticasFiscales,
                'medicos' => (object)$estadisticasMedicos
            ];

        } catch (\Exception $e) {
            Log::error('Error ejecutando stored procedure', [
                'id_empleado' => $idEmpleado,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
