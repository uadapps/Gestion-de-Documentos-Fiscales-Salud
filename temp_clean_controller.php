<?php

// Fragmento limpio para insertar en DocumentoController.php

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
