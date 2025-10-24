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
            } else {
                // Para documentos fiscales: aplica_area_salud = 0
                $documentos = SugDocumento::where('activo', true)
                    ->where('aplica_area_salud', 0)
                    ->get();
            }

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
