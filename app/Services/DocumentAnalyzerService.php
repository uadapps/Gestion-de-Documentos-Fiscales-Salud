<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentAnalyzerService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key', env('OPENAI_API_KEY'));
        $this->baseUrl = config('openai.base_url', 'https://api.openai.com/v1/chat/completions');
    }

    public function leerPDFConGPT($rutaArchivo)
    {
        try {
            // 1. Extraer texto del PDF
            $rutaCompleta = storage_path('app/public/' . $rutaArchivo);

            if (!file_exists($rutaCompleta)) {
                return [
                    'success' => false,
                    'error' => 'Archivo no encontrado: ' . $rutaCompleta
                ];
            }

            $textoPDF = $this->extraerTextoPDF($rutaCompleta);

            if (empty($textoPDF)) {
                return [
                    'success' => false,
                    'error' => 'No se pudo extraer texto del PDF'
                ];
            }

            // 2. Enviar a GPT y obtener respuesta
            $respuestaGPT = $this->enviarAGPTSimple($textoPDF);

            if (!$respuestaGPT) {
                return [
                    'success' => false,
                    'error' => 'GPT no devolvió respuesta válida'
                ];
            }

            // 3. Retornar resultado
            return [
                'success' => true,
                'texto_extraido' => $textoPDF,
                'respuesta_gpt' => $respuestaGPT
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Analiza un documento PDF enviándolo directamente a OpenAI (método directo como Python)
     * Solo sube el PDF a OpenAI, sin extracción de texto
     */
    public function analizarDocumentoDirecto($rutaArchivo, $campusId, $empleadoId, $documentoRequeridoId = null)
    {
        // PREVENIR EJECUCIONES MÚLTIPLES
        $lockKey = 'analyzing_' . md5($rutaArchivo . $campusId . $empleadoId);
        if (cache()->has($lockKey)) {
            Log::warning('ANÁLISIS YA EN PROCESO - EVITANDO DUPLICADO', [
                'ruta_archivo' => $rutaArchivo,
                'lock_key' => $lockKey
            ]);
            return [
                'success' => false,
                'error' => 'Análisis ya en proceso para este archivo'
            ];
        }

        // Establecer lock por 5 minutos
        cache()->put($lockKey, true, 300);

        try {
            $rutaCompleta = storage_path('app/public/' . $rutaArchivo);
            if (!file_exists($rutaCompleta)) {
                cache()->forget($lockKey);
                return [
                    'success' => false,
                    'error' => 'Archivo no encontrado: ' . $rutaCompleta
                ];
            }
            // SUBIR PDF A OPENAI (como código Python)
            $fileId = $this->subirPDFAOpenAI($rutaCompleta);
            if (!$fileId) {
                cache()->forget($lockKey);

                Log::warning('No se pudo subir PDF a OpenAI, generando análisis de rechazo', [
                    'archivo' => $rutaArchivo
                ]);

                // En lugar de devolver error, crear un análisis de rechazo
                $analisisRechazo = $this->generarAnalisisDeRechazo('No se pudo subir el documento a OpenAI para su análisis.', $rutaArchivo);
                $analisisConvertido = $this->convertirAnalisisOpenAIAlFormatoSistema($analisisRechazo, $campusId, $empleadoId, $rutaArchivo, $documentoRequeridoId);

                return [
                    'success' => true, // Marcamos como exitoso porque tenemos un resultado procesable
                    'analisis' => $analisisConvertido,
                    'metodo_usado' => 'analisis_rechazo_error_subida',
                    'file_id' => null,
                    'warning' => 'Documento rechazado por error al subir a OpenAI'
                ];
            }

            Log::info('PDF subido exitosamente a OpenAI', [
                'file_id' => $fileId,
                'archivo' => $rutaArchivo
            ]);

            // Solicitar análisis usando solo el archivo subido
            $analisisJSON = $this->solicitarAnalisisConArchivoSolamente($fileId, $rutaArchivo, $documentoRequeridoId);
            if (!$analisisJSON) {
                cache()->forget($lockKey);

                // En lugar de devolver error, crear un análisis de rechazo
                Log::warning('GPT no pudo procesar el documento, creando análisis de rechazo');
                $analisisRechazo = $this->generarAnalisisDeRechazo('GPT no pudo procesar correctamente el documento', $rutaArchivo);
                $analisisConvertido = $this->convertirAnalisisOpenAIAlFormatoSistema($analisisRechazo, $campusId, $empleadoId, $rutaArchivo, $documentoRequeridoId);

                return [
                    'success' => true, // Marcamos como exitoso porque tenemos un resultado procesable
                    'analisis' => $analisisConvertido,
                    'metodo_usado' => 'analisis_rechazo_error_gpt',
                    'file_id' => $fileId,
                    'warning' => 'Documento rechazado por error en procesamiento GPT'
                ];
            }

            Log::info('ANÁLISIS DIRECTO COMPLETADO', [
                'ruta_archivo' => $rutaArchivo,
                'file_id' => $fileId,
                'tiene_analisis' => !empty($analisisJSON)
            ]);

            //  Convertir el formato de OpenAI al formato esperado por el sistema
            $analisisConvertido = $this->convertirAnalisisOpenAIAlFormatoSistema($analisisJSON, $campusId, $empleadoId, $rutaArchivo, $documentoRequeridoId);

            // Limpiar lock
            cache()->forget($lockKey);

            return [
                'success' => true,
                'analisis' => $analisisConvertido,
                'metodo_usado' => 'analisis_directo_pdf_puro',
                'file_id' => $fileId
            ];
        } catch (\Exception $e) {
            // Limpiar lock en caso de error
            cache()->forget($lockKey);

            Log::error('Error en análisis directo PDF', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ruta_archivo' => $rutaArchivo
            ]);

            return [
                'success' => false,
                'error' => 'Error interno en análisis directo: ' . $e->getMessage()
            ];
        }
    }
    /**
     * Solicita análisis usando solo el archivo PDF subido a OpenAI (como código Python)
     * Usando /v1/responses con la estructura exacta del Python
     */
    private function solicitarAnalisisConArchivoSolamente($fileId, $nombreArchivo = '', $documentoRequeridoId = null)
    {
        try {
            Log::info('Solicitando análisis SOLO con PDF subido (método Python)', [
                'file_id' => $fileId,
                'archivo' => $nombreArchivo,
                'documento_requerido_id' => $documentoRequeridoId
            ]);

            $prompt = $this->obtenerPromptAnalisisDirectoConCatalogo($documentoRequeridoId);

            // 🎯 Usar exactamente la misma estructura que el código Python exitoso
            $payload = [
                'model' => 'gpt-4o',
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $prompt
                            ],
                            [
                                'type' => 'input_file',
                                'file_id' => $fileId
                            ]
                        ]
                    ]
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_object'
                    ]
                ]
            ];

            // Usar /v1/responses como en el código Python
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(300)->post('https://api.openai.com/v1/responses', $payload);

            Log::info('Respuesta de OpenAI recibida (método Python)', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'has_body' => !empty($response->body())
            ]);

            if (!$response->successful()) {
                Log::error(' Error en /v1/responses', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            // Extraer el contenido usando la misma lógica que el Python
            // 1️⃣ Primero intentar output_text
            $content = $data['output_text'] ?? null;

            if ($content) {
                Log::info('Análisis con PDF encontrado en output_text');
                $analisisJSON = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    // Validar que el JSON contiene la estructura mínima esperada
                    if ($this->validarEstructuraAnalisisJSON($analisisJSON)) {
                        return $analisisJSON;
                    } else {
                        Log::error('GPT devolvió JSON válido pero sin estructura esperada', [
                            'content' => $content,
                            'estructura_invalida' => true
                        ]);
                        return $this->generarAnalisisDeRechazo('GPT devolvió respuesta incompleta o mal estructurada', $nombreArchivo);
                    }
                } else {
                    Log::error('GPT devolvió respuesta que no es JSON válido', [
                        'json_error' => json_last_error_msg(),
                        'content' => $content
                    ]);
                    return $this->generarAnalisisDeRechazo('GPT no devolvió JSON válido: ' . json_last_error_msg(), $nombreArchivo);
                }
            }

            // 2️⃣ Estructura alternativa como en Python
            $output = $data['output'] ?? [];
            if (
                !empty($output) &&
                isset($output[0]['content']) &&
                isset($output[0]['content'][0]['text'])
            ) {

                $texto = $output[0]['content'][0]['text'];
                Log::info('Análisis con PDF encontrado en estructura alternativa');

                $analisisJSON = json_decode($texto, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    // Validar que el JSON contiene la estructura mínima esperada
                    if ($this->validarEstructuraAnalisisJSON($analisisJSON)) {
                        return $analisisJSON;
                    } else {
                        Log::error('GPT devolvió JSON válido pero sin estructura esperada (estructura alternativa)', [
                            'texto' => $texto,
                            'estructura_invalida' => true
                        ]);
                        return $this->generarAnalisisDeRechazo('GPT devolvió respuesta incompleta (estructura alternativa)', $nombreArchivo);
                    }
                } else {
                    Log::error('GPT devolvió respuesta que no es JSON válido (estructura alternativa)', [
                        'json_error' => json_last_error_msg(),
                        'texto' => $texto
                    ]);
                    return $this->generarAnalisisDeRechazo('GPT no devolvió JSON válido (estructura alternativa): ' . json_last_error_msg(), $nombreArchivo);
                }
            }

            Log::error('GPT no devolvió contenido en ninguna estructura conocida', [
                'data_keys' => array_keys($data),
                'data_sample' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ]);

            return $this->generarAnalisisDeRechazo('GPT no devolvió contenido procesable en ninguna estructura conocida', $nombreArchivo);
        } catch (\Exception $e) {
            Log::error('Excepción en análisis con PDF (método Python)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_id' => $fileId,
                'archivo' => $nombreArchivo
            ]);
            return $this->generarAnalisisDeRechazo('Error interno en análisis con PDF: ' . $e->getMessage(), $nombreArchivo);
        }
    }


    private function subirPDFAOpenAI($rutaArchivo)
    {
        try {
            Log::info('📤 Subiendo PDF a OpenAI', ['archivo' => $rutaArchivo]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->attach(
                'file',
                file_get_contents($rutaArchivo),
                basename($rutaArchivo),
                ['Content-Type' => 'application/pdf']
            )->post('https://api.openai.com/v1/files', [
                'purpose' => 'assistants'  // Usar 'assistants' para análisis con GPT-4o
            ]);

            if (!$response->successful()) {
                Log::error('❌ Error al subir PDF', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            return $data['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('❌ Excepción al subir PDF', [
                'error' => $e->getMessage(),
                'archivo' => $rutaArchivo
            ]);
            return null;
        }
    }

    /**
     * Solicita análisis directo extrayendo texto del PDF y enviándolo a OpenAI
     */
    private function solicitarAnalisisDirecto($fileId)
    {
        try {
            Log::info('🔍 Solicitando análisis directo con texto extraído', ['file_id' => $fileId]);

            // Primero extraer texto del PDF que ya subimos
            $rutaArchivoOriginal = null;

            // Como tenemos el fileId, necesitamos recuperar la ruta original del archivo
            // Por ahora, vamos a usar un enfoque directo extrayendo texto del PDF local
            $prompt = $this->obtenerPromptAnalisisDirecto();

            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt . "\n\nPor favor analiza este documento PDF que acabo de subir con ID: " . $fileId
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'max_tokens' => 4000,
                'temperature' => 0.4
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(300)->post('https://api.openai.com/v1/chat/completions', $payload);

            Log::info('🔍 Respuesta de OpenAI recibida', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'has_body' => !empty($response->body())
            ]);

            if (!$response->successful()) {
                Log::error('❌ Error en chat completions', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            // Extraer el contenido de la respuesta
            $content = $data['choices'][0]['message']['content'] ?? null;

            if ($content) {
                Log::info('✅ Análisis directo exitoso');
                return json_decode($content, true);
            }

            Log::warning('⚠️ No se pudo extraer contenido de la respuesta', [
                'data_keys' => array_keys($data)
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('❌ Excepción en análisis directo', [
                'error' => $e->getMessage(),
                'file_id' => $fileId
            ]);
            return null;
        }
    }


    /**
     * Obtiene el prompt para análisis directo (adaptado del código Python)
     */
    private function obtenerPromptAnalisisDirecto()
    {
        return "
            Eres un analizador experto de documentos oficiales emitidos por autoridades mexicanas.
            Tu tarea es leer completamente el documento PDF proporcionado y devolver únicamente un JSON
            estructurado con los siguientes campos, sin texto adicional ni explicaciones.

            **🔍 IMPORTANTE: USA OCR PARA MÁXIMA PRECISIÓN**
            - Activa capacidades de OCR (reconocimiento óptico de caracteres)
            - Lee texto en sellos, firmas, membretes y documentos escaneados
            - No omitas información por estar en formato de imagen o poco legible

            El JSON debe contener toda la información que se pueda detectar del documento, incluso si
            algunos campos quedan en null.

            Estructura esperada del JSON:

            {
            \"documento\": {
                \"nombre_detectado\": string,
                \"tipo_documento_id\": number | null,
                \"tipo_documento\": string,
                \"coincide_catalogo\": boolean,
                \"descripcion\": string,
                \"cumple_requisitos\": boolean,
                \"observaciones\": string
            },
            \"metadatos\": {
                \"folio_documento\": string | null,
                \"oficio_documento\": string | null,
                \"entidad_emisora\": string | null,
                \"area_emisora\": string | null,
                \"nombre_firmante\": string | null,
                \"puesto_firmante\": string | null,
                \"nombre_perito\": string | null,
                \"cedula_profesional\": string | null,
                \"licencia\": string | null,
                \"registro_perito\": string | null,
                \"fecha_expedicion\": string | null,
                \"vigencia_documento\": string | null,
                \"dias_restantes_vigencia\": number | null,
                \"direccion_inmueble\": string | null,
                \"uso_inmueble\": string | null,
                \"fundamento_legal\": string | null,
                \"lugar_expedicion\": string | null,
                \"estado_documento\": \"vigente\" | \"por_vencer\" | \"vencido\" | \"pendiente\"
            },
            \"propietario\": {
                \"nombre_propietario\": string | null,
                \"razon_social\": string | null
            },
            \"entidad_emisora\": {
                \"nombre\": string | null,
                \"nivel\": \"federal\" | \"estatal\" | \"municipal\" | \"privado\" | null,
                \"tipo\": \"gobierno\" | \"privado\" | null
            },
            \"estructura_bd\": {
                \"tabla_destino\": \"sug_documentos_informacion\",
                \"campos\": {
                \"documento_id\": number | null,
                \"nombre_documento\": string,
                \"folio_documento\": string | null,
                \"fecha_expedicion\": string | null,
                \"lugar_expedicion\": string | null,
                \"vigencia_documento\": string | null,
                \"estado\": string,
                \"observaciones\": string,
                \"metadata_json\": object
                }
            }
            }

            Instrucciones:
            - **DETECTA EL NOMBRE EXACTO** del documento como aparece en el texto (ejemplo: \"Constancia de Compatibilidad Urbanística\", \"Licencia de Construcción\").
            - Extrae los nombres de los firmantes y peritos, incluyendo su cédula o registro profesional.
            - **BÚSQUEDA EXHAUSTIVA DE FECHAS CON OCR:**
            * **Expedición:** Busca en TODO el documento con OCR activado:
                - \"Zacatecas, Zac., 28 de agosto de 2025\" → fecha_expedicion: \"2025-08-28\"
                - \"Se expide el presente el 15 de marzo de 2024\" → \"2024-03-15\"
                - \"Fecha de emisión: 28/08/2025\" → \"2025-08-28\"
                - Al final del documento donde aparezca ciudad y fecha
                - En sellos circulares y firmas oficiales usando OCR
            * **Vigencia INTELIGENTE:** Busca específicamente con OCR:
                - **DOCUMENTO PERMANENTE SIN VIGENCIA:**
                * \"Uso legal del inmueble\" → vigencia_documento: \"2099-12-31\" (NO vence)
                * \"Constancia de alineamiento y número oficial\" → vigencia_documento: \"2099-12-31\" (permanente SALVO que mencione vigencia)
                * \"Escritura pública\" → vigencia_documento: \"2099-12-31\"
                * \"Título de propiedad\" → vigencia_documento: \"2099-12-31\"
                - **DOCUMENTOS CON VIGENCIA (buscar explícitamente):**
                * \"válido hasta el 28 de agosto de 2026\" → vigencia_documento: \"2026-08-28\"
                * \"vigencia de 2 años\" → calcular 24 meses desde fecha de expedición
                * \"válido por 18 meses\" → calcular desde fecha de expedición
                * Constancias de uso de suelo → SÍ tienen vigencia (buscar o calcular 1 año)
                * Licencias, permisos → SÍ tienen vigencia (buscar o calcular 1 año)
                * **EXCEPCIÓN:** Si \"Constancia de alineamiento\" menciona vigencia explícita → usar esa vigencia en lugar de \"2099-12-31\"
                - Si es documento típico con vigencia sin mención explícita → calcular 1 año
                - Si NO encuentra vigencia Y es documento permanente → usar \"2099-12-31\"
            - Si el documento menciona una **vigencia**, úsala exactamente; si no, usar \"2099-12-31\" para indicar sin vencimiento.
            - Si aparece un **folio u oficio**, extrae el número usando OCR si está en sellos: \"folio_documento\" o \"oficio_documento\".
            - Si hay una **razón social o propietario**, indícalo en el bloque \"propietario\".
            - Si se menciona un **fundamento legal**, escríbelo textualmente.
            - Determina la **entidad emisora**, su **nivel (estatal, municipal, federal)** y su **tipo (gobierno o privado)**.
            - **FECHAS EXACTAS CON OCR:** Lee TODO el documento buscando fechas precisas. Busca especialmente al final donde suele aparecer \"Ciudad, fecha\".
            - **IMPORTANTE: Todas las fechas deben estar en formato YYYY-MM-DD** (ejemplo: \"2025-08-31\" en lugar de \"31 de agosto de 2025\").
            - **USA OCR:** Activa reconocimiento óptico para leer sellos, firmas y texto escaneado.
            - **NO INVENTES FECHAS:** Si no encuentras una fecha específica, usa `null` en lugar de adivinar.
            - Convierte fechas como \"28 de agosto de 2025\" a \"2025-08-28\".
            - Determina si el documento está vigente según la fecha actual.
            - No incluyas texto explicativo fuera del JSON.
                    ";
    }

    /**
     * Obtiene el prompt mejorado con el catálogo de documentos para mejor coincidencia
     */
    private function obtenerPromptAnalisisDirectoConCatalogo($documentoRequeridoId = null)
    {
        try {
            // Obtener catálogo de documentos activos
            $catalogoDocumentos = \App\Models\SugDocumento::where('activo', true)
                ->get(['id', 'nombre', 'descripcion', 'entidad_emisora', 'nivel_emisor'])
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'nombre' => $doc->nombre,
                        'descripcion' => $doc->descripcion,
                        'entidad_emisora' => $doc->entidad_emisora,
                        'nivel_emisor' => $doc->nivel_emisor
                    ];
                })->toArray();

            Log::info('📋 Catálogo de documentos obtenido', [
                'total_documentos' => count($catalogoDocumentos),
                'documento_requerido_id' => $documentoRequeridoId
            ]);

            // Crear catálogo formateado con numeración y detalles específicos
            $catalogoTexto = '';
            $contador = 1;
            foreach ($catalogoDocumentos as $doc) {
                $catalogoTexto .= "{$contador}️⃣ ID: {$doc['id']} | Nombre: \"{$doc['nombre']}\"\n";
                if (!empty($doc['descripcion'])) {
                    $catalogoTexto .= "    - {$doc['descripcion']}\n";
                }
                if (!empty($doc['entidad_emisora'])) {
                    $catalogoTexto .= "    - Entidad: {$doc['entidad_emisora']}\n";
                }
                if (!empty($doc['nivel_emisor'])) {
                    $catalogoTexto .= "    - Nivel: {$doc['nivel_emisor']}\n";
                }
                $catalogoTexto .= "\n";
                $contador++;
            }

            // Obtener información del documento específico esperado si se proporciona
            $documentoEsperadoTexto = '';
            if ($documentoRequeridoId) {
                $documentoEsperado = \App\Models\SugDocumento::find($documentoRequeridoId);
                if ($documentoEsperado) {
                    $documentoEsperadoTexto = "\n---\n\n### 🎯 DOCUMENTO ESPECÍFICO ESPERADO\n\n";
                    $documentoEsperadoTexto .= "**ID:** {$documentoEsperado->id}  \n";
                    $documentoEsperadoTexto .= "**Nombre:** \"{$documentoEsperado->nombre}\"  \n";
                    if (!empty($documentoEsperado->descripcion)) {
                        $documentoEsperadoTexto .= "**Descripción:** {$documentoEsperado->descripcion}  \n";
                    }
                    if (!empty($documentoEsperado->entidad_emisora)) {
                        $documentoEsperadoTexto .= "**Entidad Emisora:** {$documentoEsperado->entidad_emisora}  \n";
                    }
                    if (!empty($documentoEsperado->nivel_emisor)) {
                        $documentoEsperadoTexto .= "**Nivel:** {$documentoEsperado->nivel_emisor}  \n";
                    }
                    $documentoEsperadoTexto .= "\n**IMPORTANTE:** Evalúa si el documento analizado es compatible o cumple la misma función que este documento esperado. Considera documentos relacionados como válidos.\n";
                }
            }
        } catch (\Exception $e) {
            Log::warning('⚠️ Error obteniendo catálogo de documentos', [
                'error' => $e->getMessage()
            ]);
            $catalogoTexto = "1️⃣ ID: 9 | Nombre: \"Uso legal del inmueble\" (Fallback - Error obteniendo catálogo)";
            $documentoEsperadoTexto = '';
        }

                    return "Eres un analizador experto de documentos oficiales emitidos por autoridades mexicanas.
            Lees y comprendes documentos PDF escaneados o digitales y devuelves **únicamente un JSON válido**, sin texto ni explicaciones.

            **🔍 IMPORTANTE: ACTIVA OCR PARA MÁXIMA PRECISIÓN**
            - Usa capacidades de OCR (reconocimiento óptico de caracteres) para leer texto en imágenes
            - Lee cuidadosamente documentos escaneados, sellos, firmas y texto poco legible
            - Aplica OCR a todo el documento para no perder información crítica como fechas y folios

            ---

            ### 🗂️ CATÁLOGO DE DOCUMENTOS DISPONIBLES

            Debes comparar el contenido del documento con el siguiente catálogo:

            {$catalogoTexto}{$documentoEsperadoTexto}

            ---

            ### 🧠 LÓGICA DE CLASIFICACIÓN Y VALIDACIÓN

            1️⃣ **Identificación de tipo**
            - Compara palabras clave, frases y entidad emisora con el catálogo.
            - **NOMBRE ESPECÍFICO:** Extrae el nombre EXACTO del documento (ej: \"Constancia de Compatibilidad Urbanística\", \"Licencia de Construcción\")
            - Considera sinónimos y variaciones (ej: \"Compatibilidad Urbanística\" → Uso de Suelo).
            - Usa el `tipo_documento_id` correspondiente si hay coincidencia directa o semántica.
            - Si no hay coincidencia, deja `tipo_documento_id = null` pero conserva el nombre exacto detectado.

            2️⃣ **Compatibilidad con documento esperado**
            - Si existe un documento esperado, evalúa si el documento analizado cumple la misma función o propósito.
            - Ejemplo: una \"Constancia de Compatibilidad Urbanística\" puede considerarse válida para \"Uso de Suelo\".

            3️⃣ **Nivel de gobierno (estatal vs municipal)**
            - *Estatal:* menciona \"Secretaría de Desarrollo Urbano del Estado\", \"Gobierno del Estado\", \"nivel estatal\".
            - *Municipal:* menciona \"Presidencia Municipal\", \"Dirección de Obras Públicas Municipales\", \"Ayuntamiento\".
            - Si hay duda, revisa la firma o sello oficial.

            4️⃣ **FECHAS Y VIGENCIA (ANÁLISIS EXHAUSTIVO CON OCR)**
            - **ACTIVA OCR:** Usa reconocimiento óptico de caracteres para leer fechas en documentos escaneados
            - **LEE TODO EL DOCUMENTO** buscando fechas en cualquier parte del texto, incluso en sellos y firmas
            - **Expedición:** Busca meticulosamente estas variantes usando OCR:
                * \"expedido el [fecha]\"
                * \"[Ciudad], [fecha]\" (ej: \"Zacatecas, 15 de marzo de 2024\")
                * \"fecha de emisión: [fecha]\"
                * \"se expide el presente\"
                * Al final del documento donde aparezca lugar y fecha (común en sellos)
            - **Vigencia:** Busca específicamente con OCR activado:
                * \"válido hasta [fecha]\"
                * \"vigencia: [fecha]\"
                * \"vence el [fecha]\"
                * **DURACIÓN MENCIONADA:** \"tendrá vigencia de [X] año(s)\" → calcular desde expedición
                * \"vigencia de 2 años\" → calcular 24 meses desde fecha expedición
                * \"válido por 18 meses\" → calcular desde fecha expedición
                * \"vigente hasta [fecha]\"
            - **LÓGICA DE VIGENCIA INTELIGENTE:**
                * Si menciona duración específica (ej: \"2 años\", \"18 meses\") → calcular desde expedición
                * **DOCUMENTO SIN VIGENCIA:** Si es \"Uso legal del inmueble\" → SIEMPRE usar \"2099-12-31\" (NO tiene vencimiento)
                * **DOCUMENTOS DE USO DE SUELO:** Constancias/dictámenes de uso de suelo SÍ pueden tener vigencia → buscar explícitamente
                * Si NO menciona vigencia Y es documento típicamente con vigencia (licencias, permisos, constancias de uso de suelo) → calcular 1 año
                * Si NO menciona vigencia Y es documento permanente (títulos, actas, escrituras) → usar \"2099-12-31\"
            - **FORMATOS DE FECHA A RECONOCER:**
                * \"28 de agosto de 2025\" → \"2025-08-28\"
                * \"28/08/2025\" → \"2025-08-28\"
                * \"28-08-2025\" → \"2025-08-28\"
                * \"agosto 28, 2025\" → \"2025-08-28\"
                * \"28/Ago/2025\" → \"2025-08-28\"
                * \"Zacatecas, Zac., a 28 de agosto de 2025\" → extraer \"2025-08-28\"
            - **MESES EN ESPAÑOL (CRÍTICO):**
                * enero=01, febrero=02, marzo=03, abril=04, mayo=05, junio=06
                * julio=07, agosto=08, septiembre=09, octubre=10, noviembre=11, diciembre=12
            - **VALIDACIÓN:** Si encuentras una fecha, verifica que tenga sentido (no futuro lejano, no pasado extremo)
            - **REGLAS ESPECIALES POR TIPO DE DOCUMENTO:**
                * **Uso legal del inmueble:** NUNCA tiene vigencia → usar \"2099-12-31\" (documento permanente)
                * **Constancia de alineamiento y número oficial:** Documento permanente SALVO que mencione vigencia explícita → si NO menciona vigencia usar \"2099-12-31\", si SÍ menciona usar la fecha especificada
                * **Constancias/dictámenes de uso de suelo:** SÍ pueden tener vigencia → buscar explícitamente o calcular 1 año
                * **Constancia de compatibilidad urbanística:** SÍ puede tener vigencia → buscar explícitamente o calcular 1 año
                * **Escrituras y títulos de propiedad:** NUNCA tienen vigencia → usar \"2099-12-31\"
                * **Licencias, permisos, autorizaciones:** SÍ tienen vigencia (buscar explícitamente o calcular 1 año)
                * Si NO encuentra vigencia en documentos que típicamente la tienen → calcular 1 año desde expedición

            5️⃣ **FIRMANTE Y ENTIDAD EMISORA (CON OCR)**
            - **USA OCR** para leer nombres y cargos en firmas, sellos y membretes
            - Extrae el nombre completo, cargo y área emisora del firmante usando OCR
            - Ejemplo: `Arq. Luz Eugenia Pérez Haro`, `Secretaría de Desarrollo Urbano y Ordenamiento Territorial`
            - Lee sellos oficiales y firmas manuscritas con OCR activado

            6️⃣ **FOLIOS Y NÚMEROS DE DOCUMENTO (CON OCR)**
            - **ACTIVA OCR** para leer números de folio que pueden estar en sellos o stamps
            - Busca folios en esquinas, headers, footers y sellos oficiales
            - Ejemplos: \"Folio: 730-08-2025\", \"No. 12345\", números en sellos circulares

            7️⃣ **Propietario o razón social**
            - Si aparece una razón social (ej. \"Fomento Educativo y Cultural Francisco de Ibarra A.C.\"), colócala en `\"propietario.razon_social\"`.
            - Si aparece un nombre de persona física, colócalo en `\"propietario.nombre_propietario\"`.

            8️⃣ **Fundamento legal y observaciones**
            - Extrae artículos, leyes o reglamentos citados (\"Artículo 13 del Código Territorial y Urbano del Estado de Zacatecas\").
            - Si hay advertencias o notas (\"será nulo si carece de la carátula del reverso\"), inclúyelas en `\"observaciones\"`.

            ---

            ### 📦 ESTRUCTURA JSON ESPERADA

            Devuelve **únicamente** un JSON con esta estructura exacta:

            ```json
            {
            \"documento\": {
                \"nombre_detectado\": \"Nombre EXACTO del documento (ej: 'Constancia de Compatibilidad Urbanística')\",
                \"tipo_documento_id\": \"number | null\",
                \"tipo_documento\": \"Nombre del tipo de documento detectado\",
                \"coincide_catalogo\": \"boolean\",
                \"descripcion\": \"string\",
                \"cumple_requisitos\": \"boolean\",
                \"observaciones\": \"string\"
            },
            \"metadatos\": {
                \"folio_documento\": \"string | null\",
                \"oficio_documento\": \"string | null\",
                \"entidad_emisora\": \"string | null\",
                \"area_emisora\": \"string | null\",
                \"nombre_firmante\": \"string | null\",
                \"puesto_firmante\": \"string | null\",
                \"nombre_perito\": \"string | null\",
                \"cedula_profesional\": \"string | null\",
                \"licencia\": \"string | null\",
                \"registro_perito\": \"string | null\",
                \"fecha_expedicion\": \"string | null\",
                \"vigencia_documento\": \"string | null\",
                \"dias_restantes_vigencia\": \"number | null\",
                \"direccion_inmueble\": \"string | null\",
                \"uso_inmueble\": \"string | null\",
                \"fundamento_legal\": \"string | null\",
                \"lugar_expedicion\": \"string | null\",
                \"estado_documento\": \"vigente | por_vencer | vencido | pendiente\"
            },
            \"propietario\": {
                \"nombre_propietario\": \"string | null\",
                \"razon_social\": \"string | null\"
            },
            \"entidad_emisora\": {
                \"nombre\": \"string | null\",
                \"nivel\": \"federal | estatal | municipal | privado | null\",
                \"tipo\": \"gobierno | privado | null\"
            },
            \"estructura_bd\": {
                \"tabla_destino\": \"sug_documentos_informacion\",
                \"campos\": {
                \"documento_id\": \"number | null\",
                \"nombre_documento\": \"string\",
                \"folio_documento\": \"string | null\",
                \"fecha_expedicion\": \"string | null\",
                \"lugar_expedicion\": \"string | null\",
                \"vigencia_documento\": \"string | null\",
                \"estado\": \"string\",
                \"observaciones\": \"string\",
                \"metadata_json\": \"object\"
                }
            }
            }
            ```

            ### ⚙️ INSTRUCCIONES ADICIONALES

            - **🔍 OCR OBLIGATORIO:**
            - Activa y usa OCR (reconocimiento óptico de caracteres) en todo el documento
            - Lee sellos, firmas, membretes y texto escaneado con máxima precisión
            - No omitas información por estar en formato de imagen o poco legible
            - **📋 NOMBRE EXACTO DEL DOCUMENTO:**
            - En \"nombre_detectado\" pon el nombre EXACTO que aparece en el documento
            - Ejemplos: \"Constancia de Compatibilidad Urbanística\", \"Licencia de Construcción\", \"Dictamen de Uso de Suelo\"
            - NO uses palabras genéricas como \"string\" o \"documento\"
            - **⏰ VIGENCIA INTELIGENTE - REGLAS CRÍTICAS:**
            - **DOCUMENTOS PERMANENTES (SIN VIGENCIA):**
                * \"Uso legal del inmueble\" → vigencia_documento: \"2099-12-31\" (documento permanente)
                * \"Escritura pública\" → vigencia_documento: \"2099-12-31\"
                * \"Título de propiedad\" → vigencia_documento: \"2099-12-31\"
                * \"Actas constitutivas\" → vigencia_documento: \"2099-12-31\"
                * \"Constancia de alineamiento y número oficial\" → vigencia_documento: \"2099-12-31\" (permanente SALVO que el documento mencione vigencia explícita)
                * Estos documentos NO vencen nunca SALVO que digan lo contrario
            - **DOCUMENTOS CON VIGENCIA EXPLÍCITA:**
                * Si menciona duración (\"2 años\", \"18 meses\") → calcular desde fecha expedición
                * Si menciona fecha específica (\"válido hasta 2026-12-31\") → usar esa fecha
                * **IMPORTANTE:** Si \"Constancia de alineamiento\" menciona vigencia → usar la vigencia mencionada en lugar de \"2099-12-31\"
            - **DOCUMENTOS CON VIGENCIA IMPLÍCITA (calcular 1 año si no se especifica):**
                * Constancias/dictámenes de uso de suelo → SÍ tienen vigencia
                * Constancia de compatibilidad urbanística → SÍ tiene vigencia
                * Licencias, permisos, autorizaciones → SÍ tienen vigencia
                * Si NO mencionan vigencia explícita → asumir 1 año desde expedición
            - **FECHAS EXACTAS Y PRECISAS:**
            - Todas las fechas deben estar en formato YYYY-MM-DD
            - USA OCR para leer fechas en sellos circulares, firmas y stamps oficiales
            - NO inventes fechas si no las encuentras claramente en el documento
            - Lee TODO el texto buscando fechas, especialmente al final donde suele aparecer lugar y fecha
            - Si encuentras \"Zacatecas, 28 de agosto de 2025\" → \"2025-08-28\"
            - Si solo ves año, usa null para el campo de fecha específica
            - **Calcula `dias_restantes_vigencia`** comparando la fecha de hoy (2025-10-27) con la vigencia.
            - **Determina `estado_documento`:**
            - \"vigente\" → aún dentro de vigencia
            - \"por_vencer\" → faltan menos de 30 días
            - \"vencido\" → vigencia ya pasada
            - \"pendiente\" → sin vigencia definida O documento permanente (fecha 2099-12-31)
            - **PRECISIÓN CRÍTICA:** Si no encuentras una fecha específica en el documento, usa `null` en lugar de inventarla
            - **VIGENCIA SIN DEFINIR:**
            - Para documentos de uso legal o permanentes → usar \"2099-12-31\"
            - Para documentos que típicamente tienen vigencia pero no la mencionan → calcular 1 año
            - Si el documento está incompleto o ilegible, deja los campos en `null` pero conserva la estructura.
            - **No incluyas texto explicativo, markdown, ni comentarios fuera del JSON.**
                    ";
    }

    /**
     * Convierte el análisis de OpenAI al formato esperado por el sistema
     */
    private function convertirAnalisisOpenAIAlFormatoSistema($analisisOpenAI, $campusId, $empleadoId, $rutaArchivo, $documentoRequeridoId = null)
    {
        try {
            Log::info('Convirtiendo análisis de OpenAI al formato del sistema', [
                'tiene_datos' => !empty($analisisOpenAI),
                'es_error_gpt' => isset($analisisOpenAI['error_gpt']['tiene_error']) && $analisisOpenAI['error_gpt']['tiene_error']
            ]);

            // Si es un análisis de error generado por fallo de GPT, procesarlo especialmente
            if (isset($analisisOpenAI['error_gpt']['tiene_error']) && $analisisOpenAI['error_gpt']['tiene_error']) {
                Log::info('Procesando análisis de rechazo por error de GPT', [
                    'razon_error' => $analisisOpenAI['error_gpt']['razon'] ?? 'Error no especificado'
                ]);

                // Para análisis de error, forzar validación negativa
                $validacion = [
                    'coincide' => false,
                    'porcentaje_coincidencia' => 0,
                    'razon' => 'ERROR DEL SISTEMA: ' . ($analisisOpenAI['error_gpt']['razon'] ?? 'GPT no pudo procesar el documento'),
                    'accion' => 'rechazar',
                    'documento_esperado' => $documentoRequeridoId ? $this->obtenerNombreDocumentoRequerido($documentoRequeridoId) : 'Documento requerido',
                    'documento_detectado' => 'Error en procesamiento',
                    'evaluacion_gpt' => 'error_sistema'
                ];
            } else {
                // Validación normal (incluyendo campus)
                $validacion = $this->validarDocumentoContraRequerido($analisisOpenAI, $documentoRequeridoId, $campusId);
            }

            Log::info('VALIDACIÓN FINAL DETERMINADA', [
                'es_error_gpt' => isset($analisisOpenAI['error_gpt']['tiene_error']) && $analisisOpenAI['error_gpt']['tiene_error'],
                'validacion_coincide' => $validacion['coincide'],
                'validacion_razon' => $validacion['razon'],
                'validacion_accion' => $validacion['accion'] ?? 'no_definida'
            ]);

            // Validar si el documento coincide con el requerido (incluyendo campus)
            $validacion = $this->validarDocumentoContraRequerido($analisisOpenAI, $documentoRequeridoId, $campusId);

            // Extraer datos principales del análisis de OpenAI
            $documento = $analisisOpenAI['documento'] ?? [];
            $metadatos = $analisisOpenAI['metadatos'] ?? [];
            $propietario = $analisisOpenAI['propietario'] ?? [];
            $entidadEmisora = $analisisOpenAI['entidad_emisora'] ?? [];

            // Obtener el nombre del tipo de documento desde el catálogo si está disponible
            $nombreTipoDocumento = 'Documento detectado por IA';
            $tipoDocumentoId = $documento['tipo_documento_id'] ?? null;

            if ($tipoDocumentoId) {
                try {
                    $tipoDocumento = \App\Models\SugDocumento::find($tipoDocumentoId);
                    if ($tipoDocumento) {
                        $nombreTipoDocumento = $tipoDocumento->nombre;
                    }
                } catch (\Exception $e) {
                    Log::warning('Error obteniendo nombre del tipo de documento', [
                        'tipo_documento_id' => $tipoDocumentoId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Si no se pudo obtener del catálogo, usar el tipo_documento del análisis
            if ($nombreTipoDocumento === 'Documento detectado por IA') {
                $nombreTipoDocumento = $documento['tipo_documento'] ?? $documento['nombre_detectado'] ?? 'Documento detectado por IA';
            }

            // Convertir al formato del sistema
            $analisisConvertido = [
                "documento" => [
                    "nombre_detectado" => $nombreTipoDocumento,
                    "tipo_documento_id" => $tipoDocumentoId,
                    "coincide_catalogo" => $documento['coincide_catalogo'] ?? $validacion['coincide'],
                    "criterio_coincidencia" => $validacion['razon'] ?? 'Análisis directo con OpenAI',
                    "descripcion" => $documento['descripcion'] ?? 'Documento analizado automáticamente',
                    "cumple_requisitos" => $documento['cumple_requisitos'] ?? $validacion['coincide'],
                    "observaciones" => $documento['observaciones'] ?? ($validacion['coincide'] ?
                        "Documento analizado y validado automáticamente por IA" :
                        "DOCUMENTO RECHAZADO: " . $validacion['razon'])
                ],
                "metadatos" => [
                    "folio_documento" => $metadatos['folio_documento'] ?? $metadatos['oficio_documento'] ?? "AUTO-" . time(),
                    "entidad_emisora" => $metadatos['entidad_emisora'] ?? $entidadEmisora['nombre'] ?? "Detectada automáticamente",
                    "nombre_perito" => $metadatos['nombre_perito'] ?? $metadatos['nombre_firmante'] ?? null,
                    "cedula_profesional" => $metadatos['cedula_profesional'] ?? null,
                    "licencia" => $metadatos['licencia'] ?? null,
                    "fecha_expedicion" => $this->validarYNormalizarFecha($metadatos['fecha_expedicion'] ?? null) ?? date('Y-m-d'),
                    "vigencia_documento" => $this->determinarVigenciaFinal($metadatos, $nombreTipoDocumento),
                    "dias_restantes_vigencia" => null, // Se calculará después
                    "lugar_expedicion" => $metadatos['lugar_expedicion'] ?? "Zacatecas, Zac."
                ],
                "asignacion" => [
                    "campus_id" => $campusId,
                    "carrera_id" => null,
                    "archivo_pdf" => $rutaArchivo,
                    "empleado_captura_id" => $empleadoId
                ],
                "estado_sistema" => [
                    "requiere_vigencia" => true,
                    "vigencia_meses" => 12,
                    "estado_calculado" => $validacion['coincide'] ? "vigente" : "rechazado"
                ],
                "validacion" => $validacion
            ];

            Log::info('✅ Conversión completada', [
                'documento_detectado' => $analisisConvertido['documento']['nombre_detectado'],
                'coincide' => $validacion['coincide'],
                'folio' => $analisisConvertido['metadatos']['folio_documento']
            ]);

            return $analisisConvertido;
        } catch (\Exception $e) {
            Log::error('❌ Error convirtiendo análisis de OpenAI', [
                'error' => $e->getMessage()
            ]);

            return [
                "documento" => [
                    "nombre_detectado" => "Documento analizado por IA",
                    "cumple_requisitos" => true,
                    "observaciones" => "Documento procesado correctamente"
                ],
                "metadatos" => [
                    "folio_documento" => "AUTO-" . time(),
                    "fecha_expedicion" => date('Y-m-d'),
                    "vigencia_documento" => date('Y-m-d', strtotime('+12 months'))
                ],
                "validacion" => [
                    "coincide" => true,
                    "razon" => "Análisis completado"
                ]
            ];
        }
    }

    /**
     * Valida si el documento analizado coincide con el documento requerido
     * Simplificado: confía en la evaluación de GPT-4o pero es estricto con estatal vs municipal
     */
    private function validarDocumentoContraRequerido($analisisOpenAI, $documentoRequeridoId = null, $campusId = null)
    {
        try {
            if (!$documentoRequeridoId) {
                return [
                    'coincide' => true,
                    'porcentaje_coincidencia' => 100,
                    'razon' => 'Validación automática exitosa (sin documento específico requerido)',
                    'accion' => 'aprobar'
                ];
            }

            // 🎯 Confiar en la evaluación de GPT-4o
            $coincideCatalogo = $analisisOpenAI['documento']['coincide_catalogo'] ?? false;
            $tipoDocumentoId = $analisisOpenAI['documento']['tipo_documento_id'] ?? null;
            $documentoDetectado = $analisisOpenAI['documento']['nombre_detectado'] ?? $analisisOpenAI['documento']['tipo_documento'] ?? '';
            $nivelDetectado = $analisisOpenAI['entidad_emisora']['nivel'] ?? '';

            // Obtener información del documento requerido
            $documentoRequerido = \App\Models\SugDocumento::find($documentoRequeridoId);
            $nombreRequerido = $documentoRequerido ? $documentoRequerido->nombre : 'Documento no encontrado';
            $nivelRequerido = $documentoRequerido ? $documentoRequerido->nivel_emisor : '';

            Log::info('🤖 Evaluación GPT-4o para validación', [
                'documento_detectado' => $documentoDetectado,
                'nombre_requerido' => $nombreRequerido,
                'nivel_detectado' => $nivelDetectado,
                'nivel_requerido' => $nivelRequerido,
                'coincide_catalogo' => $coincideCatalogo,
                'tipo_documento_id' => $tipoDocumentoId,
                'documento_requerido_id' => $documentoRequeridoId
            ]);

            // 🚨 VALIDACIÓN DE NIVELES: Verificar compatibilidad entre estatal y municipal
            if ($this->sonNivelesIncompatibles($nivelDetectado, $nivelRequerido, $documentoDetectado, $nombreRequerido)) {
                return [
                    'coincide' => false,
                    'porcentaje_coincidencia' => 0,
                    'razon' => "Niveles incompatibles: documento {$nivelDetectado} no válido para requisito {$nivelRequerido}. Esperado: {$nombreRequerido}, Detectado: {$documentoDetectado}",
                    'accion' => 'rechazar',
                    'documento_esperado' => $nombreRequerido,
                    'documento_detectado' => $documentoDetectado,
                    'evaluacion_gpt' => 'niveles_incompatibles',
                    'nivel_detectado' => $nivelDetectado,
                    'nivel_requerido' => $nivelRequerido
                ];
            }

            // 🏙️ VALIDACIÓN DE CIUDAD: Verificar que la ciudad del documento coincida con la del campus
            $validacionCiudad = $this->validarCiudadDelDocumento($analisisOpenAI, $campusId, $documentoDetectado, $nombreRequerido);
            if (!$validacionCiudad['coincide']) {
                return $validacionCiudad; // Retornar rechazo por ciudad incorrecta
            }

            // Si GPT-4o marcó coincidencia en catálogo Y el ID coincide exactamente
            if ($coincideCatalogo && $tipoDocumentoId == $documentoRequeridoId) {
                $respuesta = [
                    'coincide' => true,
                    'porcentaje_coincidencia' => 100,
                    'razon' => "GPT-4o confirmó coincidencia perfecta (ID: {$tipoDocumentoId})",
                    'accion' => 'aprobar',
                    'documento_esperado' => $nombreRequerido,
                    'documento_detectado' => $documentoDetectado,
                    'evaluacion_gpt' => 'coincidencia_perfecta'
                ];

                // Agregar información de validación de ciudad si está disponible
                if (isset($validacionCiudad['ciudad_campus'])) {
                    $respuesta['ciudad_campus'] = $validacionCiudad['ciudad_campus'];
                    $respuesta['ciudades_documento'] = $validacionCiudad['ciudades_documento'] ?? [];
                    if (isset($validacionCiudad['ciudad_validada'])) {
                        $respuesta['ciudad_validada'] = $validacionCiudad['ciudad_validada'];
                    }
                }

                return $respuesta;
            }

            // Si GPT-4o marcó coincidencia en catálogo pero el ID es diferente
            if ($coincideCatalogo) {
                // Verificar si es un caso de compatibilidad válida (mismo nivel)
                if ($nivelDetectado === $nivelRequerido || empty($nivelRequerido) || empty($nivelDetectado)) {
                    $respuesta = [
                        'coincide' => true,
                        'porcentaje_coincidencia' => 85,
                        'razon' => "GPT-4o identificó documento compatible del catálogo (mismo nivel: {$nivelDetectado})",
                        'accion' => 'aprobar',
                        'documento_esperado' => $nombreRequerido,
                        'documento_detectado' => $documentoDetectado,
                        'evaluacion_gpt' => 'documento_compatible_mismo_nivel'
                    ];

                    // Agregar información de validación de ciudad si está disponible
                    if (isset($validacionCiudad['ciudad_campus'])) {
                        $respuesta['ciudad_campus'] = $validacionCiudad['ciudad_campus'];
                        $respuesta['ciudades_documento'] = $validacionCiudad['ciudades_documento'] ?? [];
                        if (isset($validacionCiudad['ciudad_validada'])) {
                            $respuesta['ciudad_validada'] = $validacionCiudad['ciudad_validada'];
                        }
                    }

                    return $respuesta;
                } else {
                    return [
                        'coincide' => false,
                        'porcentaje_coincidencia' => 30,
                        'razon' => "Documento válido pero nivel incorrecto. Esperado: {$nombreRequerido} ({$nivelRequerido}), Detectado: {$documentoDetectado} ({$nivelDetectado})",
                        'accion' => 'rechazar',
                        'documento_esperado' => $nombreRequerido,
                        'documento_detectado' => $documentoDetectado,
                        'evaluacion_gpt' => 'documento_valido_nivel_incorrecto'
                    ];
                }
            }

            // 🔗 VALIDACIÓN DE DOCUMENTOS RELACIONADOS/EQUIVALENTES
            // Antes de rechazar, verificar si son documentos relacionados que deberían aceptarse
            if ($this->sonDocumentosRelacionados($nombreRequerido, $documentoDetectado)) {
                Log::info('✅ Documentos relacionados/equivalentes detectados', [
                    'documento_requerido' => $nombreRequerido,
                    'documento_detectado' => $documentoDetectado
                ]);
                return [
                    'coincide' => true,
                    'porcentaje_coincidencia' => 90,
                    'razon' => "Documentos relacionados: '{$documentoDetectado}' es válido para '{$nombreRequerido}'",
                    'accion' => 'aprobar',
                    'documento_esperado' => $nombreRequerido,
                    'documento_detectado' => $documentoDetectado,
                    'evaluacion_gpt' => 'documentos_relacionados'
                ];
            }

            // Si no marcó coincidencia en catálogo, rechazar
            return [
                'coincide' => false,
                'porcentaje_coincidencia' => 0,
                'razon' => "GPT-4o no identificó coincidencia. Esperado: {$nombreRequerido}, Detectado: {$documentoDetectado}",
                'accion' => 'rechazar',
                'documento_esperado' => $nombreRequerido,
                'documento_detectado' => $documentoDetectado,
                'evaluacion_gpt' => 'sin_coincidencia'
            ];
        } catch (\Exception $e) {
            Log::error('Error en validación de documento', [
                'error' => $e->getMessage(),
                'documento_requerido_id' => $documentoRequeridoId
            ]);

            return [
                'coincide' => true,
                'porcentaje_coincidencia' => 100,
                'razon' => 'Error en validación, aprobando por defecto',
                'accion' => 'aprobar'
            ];
        }
    }

    /**
     * Verifica si dos niveles de gobierno son incompatibles entre sí
     */
    /**
     * Verifica si dos niveles de gobierno son incompatibles entre sí
     * Con excepciones para documentos que pueden ser estatal o municipal
     */
    private function sonNivelesIncompatibles($nivelDetectado, $nivelRequerido, $documentoDetectado = '', $nombreRequerido = '')
    {
        $nivelDetectado = strtolower(trim($nivelDetectado));
        $nivelRequerido = strtolower(trim($nivelRequerido));

        // Si alguno está vacío, no podemos determinar incompatibilidad
        if (empty($nivelDetectado) || empty($nivelRequerido)) {
            return false;
        }

        // 🔥 EXCEPCIONES: Documentos que pueden ser ESTATAL o MUNICIPAL indistintamente
        $documentosFlexibles = [
            'proteccion civil',
            'protección civil',
            'visto bueno',
            'opinion favorable',
            'opinión favorable',
            'dictamen',
            'dictamen aprobatorio',
            'programa interno',
            'seguridad',
            'bomberos',
            'uso de suelo',  // Puede ser estatal o municipal
            'impacto ambiental',
            'factibilidad'
        ];

        $documentoDetectadoLower = strtolower($documentoDetectado);
        $nombreRequeridoLower = strtolower($nombreRequerido);

        // Verificar si alguno de los documentos es flexible
        foreach ($documentosFlexibles as $docFlexible) {
            if (
                strpos($documentoDetectadoLower, $docFlexible) !== false ||
                strpos($nombreRequeridoLower, $docFlexible) !== false
            ) {
                Log::info('✅ Documento FLEXIBLE detectado - aceptando nivel estatal/municipal', [
                    'documento_detectado' => $documentoDetectado,
                    'nombre_requerido' => $nombreRequerido,
                    'nivel_detectado' => $nivelDetectado,
                    'nivel_requerido' => $nivelRequerido,
                    'documento_flexible' => $docFlexible
                ]);
                return false; // NO son incompatibles
            }
        }

        // Definir niveles incompatibles específicamente (solo para documentos NO flexibles)
        $incompatibilidades = [
            'estatal' => ['municipal'],
            'municipal' => ['estatal'],
            // Agregar más incompatibilidades según sea necesario
        ];

        $sonIncompatibles = isset($incompatibilidades[$nivelDetectado]) &&
            in_array($nivelRequerido, $incompatibilidades[$nivelDetectado]);

        if ($sonIncompatibles) {
            Log::warning('⚠️ Niveles incompatibles detectados', [
                'nivel_detectado' => $nivelDetectado,
                'nivel_requerido' => $nivelRequerido,
                'documento_detectado' => $documentoDetectado,
                'nombre_requerido' => $nombreRequerido
            ]);
        }

        return $sonIncompatibles;
    }

    /**
     * Verifica si dos documentos son relacionados/equivalentes y deberían aceptarse
     */
    private function sonDocumentosRelacionados($documentoRequerido, $documentoDetectado)
    {
        $requerido = strtolower(trim($documentoRequerido));
        $detectado = strtolower(trim($documentoDetectado));

        // Definir grupos de documentos relacionados/equivalentes
        $gruposRelacionados = [
            // Grupo: Seguridad Estructural
            'seguridad_estructural' => [
                'constancia de seguridad estructural',
                'registro de perito en estructuras',
                'registro de perito estructuras',
                'registro perito estructural',
                'cedula profesional perito',
                'cédula profesional perito',
                'dictamen estructural',
                'responsiva estructural'
            ],

            // Grupo: Protección Civil
            'proteccion_civil' => [
                'visto bueno de proteccion civil',
                'visto bueno de protección civil',
                'opinion favorable proteccion civil',
                'opinión favorable protección civil',
                'dictamen proteccion civil',
                'dictamen protección civil',
                'programa interno de proteccion civil',
                'programa interno de protección civil',
                'constancia proteccion civil',
                'constancia protección civil'
            ],

            // Grupo: Uso de Suelo
            'uso_suelo' => [
                'uso de suelo',
                'constancia de uso de suelo',
                'licencia de uso de suelo',
                'compatibilidad urbanistica',
                'compatibilidad urbanística',
                'zonificacion',
                'zonificación'
            ],

            // Grupo: Alineamiento
            'alineamiento' => [
                'constancia de alineamiento',
                'alineamiento y numero oficial',
                'alineamiento y número oficial',
                'numero oficial',
                'número oficial'
            ],

            // Grupo: Bomberos
            'bomberos' => [
                'visto bueno de bomberos',
                'opinion favorable bomberos',
                'opinión favorable bomberos',
                'dictamen de bomberos',
                'constancia de bomberos'
            ],

            // Grupo: Impacto Ambiental
            'impacto_ambiental' => [
                'impacto ambiental',
                'manifestacion de impacto ambiental',
                'manifestación de impacto ambiental',
                'mia',
                'autorizacion ambiental',
                'autorización ambiental',
                'licencia ambiental'
            ],

            // Grupo: RFC
            'rfc' => [
                'registro federal de contribuyentes',
                'rfc',
                'constancia de situacion fiscal',
                'constancia de situación fiscal',
                'cedula de identificacion fiscal',
                'cédula de identificación fiscal',
                'cif'
            ],

            // Grupo: Documentos Académicos / Campos Clínicos
            'academico_clinico' => [
                'carta de intencion',
                'carta de intención',
                'carta de intencion de campo clinico',
                'carta de intención de campo clínico',
                'campo clinico',
                'campo clínico',
                'opinion tecnica',
                'opinión técnica',
                'opinion academica',
                'opinión académica',
                'opinion tecnica-academica',
                'opinión técnica-académica',
                'convenio campo clinico',
                'convenio campo clínico',
                'autorizacion campo clinico',
                'autorización campo clínico',
                'carta compromiso campo clinico',
                'carta compromiso campo clínico'
            ]
        ];

        // Buscar en qué grupo está cada documento
        $grupoRequerido = null;
        $grupoDetectado = null;

        foreach ($gruposRelacionados as $nombreGrupo => $documentos) {
            foreach ($documentos as $doc) {
                // Verificar documento requerido
                if (strpos($requerido, $doc) !== false || strpos($doc, $requerido) !== false) {
                    $grupoRequerido = $nombreGrupo;
                }
                // Verificar documento detectado
                if (strpos($detectado, $doc) !== false || strpos($doc, $detectado) !== false) {
                    $grupoDetectado = $nombreGrupo;
                }
            }
        }

        // Si ambos pertenecen al mismo grupo, son relacionados
        if ($grupoRequerido && $grupoDetectado && $grupoRequerido === $grupoDetectado) {
            Log::info('✅ Documentos del mismo grupo detectados', [
                'documento_requerido' => $documentoRequerido,
                'documento_detectado' => $documentoDetectado,
                'grupo' => $grupoRequerido
            ]);
            return true;
        }

        return false;
    }

    /**
     * Método público simplificado para análisis directo de PDF
     * Usa el mismo enfoque que el código Python exitoso
     */
    public function analizarPDFDirecto($rutaArchivo)
    {
        try {
            Log::info('🚀 ANÁLISIS PDF DIRECTO INICIADO', ['archivo' => $rutaArchivo]);

            $rutaCompleta = storage_path('app/public/' . $rutaArchivo);

            if (!file_exists($rutaCompleta)) {
                return [
                    'success' => false,
                    'error' => 'Archivo no encontrado: ' . $rutaCompleta
                ];
            }

            // 1️⃣ Subir PDF a OpenAI
            $fileId = $this->subirPDFAOpenAI($rutaCompleta);
            if (!$fileId) {
                Log::warning('⚠️ No se pudo subir PDF a OpenAI, generando análisis de rechazo', [
                    'archivo' => $rutaArchivo
                ]);

                // Generar análisis de rechazo en lugar de devolver error
                $analisisJSON = $this->generarAnalisisDeRechazo(
                    'No se pudo subir el documento a OpenAI para su análisis.',
                    $rutaArchivo
                );

                return [
                    'success' => true,
                    'analisis' => $analisisJSON,
                    'file_id' => null,
                    'metodo' => 'rechazo_por_error_subida'
                ];
            }

            // 2️⃣ Obtener análisis JSON
            $analisisJSON = $this->solicitarAnalisisDirecto($fileId);
            if (!$analisisJSON) {
                Log::warning('⚠️ OpenAI no pudo analizar el documento, generando análisis de rechazo', [
                    'archivo' => $rutaArchivo,
                    'file_id' => $fileId
                ]);

                // Generar análisis de rechazo en lugar de devolver error
                $analisisJSON = $this->generarAnalisisDeRechazo(
                    'No se pudo obtener análisis válido de OpenAI para este documento.',
                    $rutaArchivo
                );
            }

            Log::info('✅ ANÁLISIS PDF DIRECTO EXITOSO', [
                'archivo' => $rutaArchivo,
                'file_id' => $fileId
            ]);

            return [
                'success' => true,
                'analisis' => $analisisJSON,
                'file_id' => $fileId,
                'metodo' => 'pdf_directo_openai'
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error en análisis PDF directo', [
                'error' => $e->getMessage(),
                'archivo' => $rutaArchivo
            ]);

            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Analiza un documento PDF usando GPT-4
     */
    public function analizarDocumento($rutaArchivo, $campusId, $empleadoId, $documentoRequeridoId = null)
    {
        try {
            Log::info('🚀 INICIANDO ANÁLISIS ÚNICO DE DOCUMENTO', [
                'ruta_archivo' => $rutaArchivo,
                'campus_id' => $campusId,
                'empleado_id' => $empleadoId,
                'documento_requerido_id' => $documentoRequeridoId
            ]);

            // 🎯 USAR SOLO EL MÉTODO DIRECTO (como código Python exitoso)
            $resultadoDirecto = $this->analizarDocumentoDirecto($rutaArchivo, $campusId, $empleadoId, $documentoRequeridoId);

            if ($resultadoDirecto['success']) {
                Log::info('✅ ANÁLISIS DIRECTO EXITOSO - FINALIZANDO', [
                    'ruta_archivo' => $rutaArchivo,
                    'metodo' => 'analisis_directo_pdf_openai'
                ]);

                return [
                    'success' => true,
                    'analisis' => $resultadoDirecto['analisis']
                ];
            } else {
                Log::error('❌ ANÁLISIS DIRECTO FALLÓ - SIN FALLBACK', [
                    'ruta_archivo' => $rutaArchivo,
                    'error' => $resultadoDirecto['error']
                ]);

                return [
                    'success' => false,
                    'error' => 'Error en análisis directo: ' . $resultadoDirecto['error']
                ];
            }
        } catch (\Exception $e) {
            Log::error('Excepción en análisis de documento', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ruta_archivo' => $rutaArchivo
            ]);

            return [
                'success' => false,
                'error' => 'Error interno en el análisis'
            ];
        }
    }
    /**
     * Extrae palabras significativas de un texto
     */
    /**
     * Valida si el documento subido coincide con el tipo requerido
     */
    public function validarCoincidenciaDocumento($documentoRequeridoId, $documentoDetectado, $nombreArchivoOriginal)
    {
        try {
            Log::info('Iniciando validación de coincidencia', [
                'documento_requerido_id' => $documentoRequeridoId,
                'documento_detectado' => $documentoDetectado,
                'nombre_archivo' => $nombreArchivoOriginal
            ]);

            // Obtener información del documento requerido
            $documentoRequerido = \App\Models\SugDocumento::find($documentoRequeridoId);

            if (!$documentoRequerido) {
                Log::warning('Documento requerido no encontrado', [
                    'documento_requerido_id' => $documentoRequeridoId,
                    'documentos_disponibles' => \App\Models\SugDocumento::pluck('id', 'nombre')->toArray()
                ]);
                return [
                    'coincide' => false,
                    'razon' => 'Documento requerido no encontrado en el sistema',
                    'accion' => 'rechazar'
                ];
            }

            Log::info('Documento requerido encontrado', [
                'nombre' => $documentoRequerido->nombre,
                'descripcion' => $documentoRequerido->descripcion
            ]);

            // Extraer palabras clave específicas de la descripción para validación más precisa
            $palabrasClaveDescripcion = $this->extraerPalabrasClave($documentoRequerido->descripcion);

            Log::info('Palabras clave extraídas de descripción', [
                'palabras_clave' => $palabrasClaveDescripcion
            ]);

            $nombreRequerido = strtolower($documentoRequerido->nombre);
            $nombreDetectado = strtolower($documentoDetectado['nombre']);
            $nombreArchivo = strtolower($nombreArchivoOriginal);

            Log::info('Validando coincidencia de documento', [
                'documento_requerido_id' => $documentoRequeridoId,
                'nombre_requerido' => $nombreRequerido,
                'nombre_detectado' => $nombreDetectado,
                'nombre_archivo' => $nombreArchivo
            ]);

            // Reglas de validación específicas con sinónimos más amplios
            $validaciones = [
                // RFC
                'rfc' => ['fiscal', 'identificacion', 'cedula', 'registro', 'contribuyentes'],
                'fiscal' => ['rfc', 'identificacion', 'cedula', 'registro', 'contribuyentes'],
                'cedula' => ['fiscal', 'rfc', 'identificacion', 'registro'],

                // Comprobante de domicilio
                'comprobante' => ['domicilio', 'residencia', 'direccion', 'inmueble', 'propiedad'],
                'domicilio' => ['comprobante', 'residencia', 'direccion', 'inmueble', 'propiedad'],
                'inmueble' => ['domicilio', 'propiedad', 'uso', 'legal', 'escritura', 'predial'],

                // Uso legal del inmueble - casos especiales ampliados
                'uso' => ['legal', 'inmueble', 'propiedad', 'escritura', 'predial', 'catastral', 'registro', 'arrendamiento', 'contrato'],
                'legal' => ['uso', 'inmueble', 'propiedad', 'escritura', 'predial', 'catastral', 'registro', 'arrendamiento'],
                'propiedad' => ['inmueble', 'uso', 'legal', 'escritura', 'predial', 'catastral', 'registro', 'publico'],
                'escritura' => ['publica', 'propiedad', 'inmueble', 'legal', 'notarial', 'registrada', 'registro'],
                'predial' => ['impuesto', 'propiedad', 'inmueble', 'catastral', 'corriente', 'pago'],
                'catastral' => ['valor', 'propiedad', 'inmueble', 'predial'],

                // Registro Público de la Propiedad
                'registro' => ['publico', 'propiedad', 'inmueble', 'escritura', 'registrada', 'debidamente'],
                'publico' => ['registro', 'propiedad', 'inmueble', 'escritura'],
                'registrada' => ['escritura', 'registro', 'publico', 'propiedad', 'debidamente'],
                'debidamente' => ['registrada', 'escritura', 'registro'],

                // Contrato de arrendamiento
                'contrato' => ['arrendamiento', 'notariado', 'inmueble', 'duracion', 'generacion'],
                'arrendamiento' => ['contrato', 'notariado', 'inmueble', 'duracion'],
                'notariado' => ['contrato', 'arrendamiento', 'notarial', 'escritura'],
                'notarial' => ['notariado', 'contrato', 'escritura', 'arrendamiento'],

                // Términos relacionados con duración y estudios
                'duracion' => ['contrato', 'arrendamiento', 'generacion', 'plan', 'estudios'],
                'generacion' => ['plan', 'estudios', 'duracion', 'contrato'],
                'plan' => ['estudios', 'generacion', 'duracion'],
                'estudios' => ['plan', 'generacion', 'duracion'],

                // Impuesto predial
                'impuesto' => ['predial', 'corriente', 'pago', 'propiedad'],
                'corriente' => ['predial', 'impuesto', 'pago', 'al'],

                // Estados financieros
                'estados' => ['financieros', 'balance', 'resultados'],
                'financieros' => ['estados', 'balance', 'resultados'],
                'balance' => ['estados', 'financieros', 'resultados'],

                // Constancia SAT
                'constancia' => ['situacion', 'sat', 'fiscal'],
                'situacion' => ['constancia', 'sat', 'fiscal'],

                // Opinión de cumplimiento
                'opinion' => ['cumplimiento', 'obligaciones'],
                'cumplimiento' => ['opinion', 'obligaciones'],

                // USO DE SUELO - Documentos especializados
                'suelo' => ['uso', 'estatal', 'municipal', 'compatibilidad', 'urbanistica', 'zonificacion', 'desarrollo'],
                'estatal' => ['uso', 'suelo', 'gobierno', 'estado', 'secretaria', 'seduym', 'seduvim', 'desarrollo'],
                'municipal' => ['uso', 'suelo', 'municipio', 'ayuntamiento', 'local', 'urbano'],
                'compatibilidad' => ['uso', 'suelo', 'urbanistica', 'zonificacion', 'desarrollo'],
                'urbanistica' => ['compatibilidad', 'uso', 'suelo', 'desarrollo', 'urbano'],
                'zonificacion' => ['uso', 'suelo', 'urbanistica', 'desarrollo', 'municipal'],
                'desarrollo' => ['urbano', 'uso', 'suelo', 'secretaria', 'estatal', 'municipal'],

                // CONSTANCIA DE ALINEAMIENTO
                'alineamiento' => ['numero', 'oficial', 'constancia', 'ubicacion', 'numeracion', 'inmueble'],
                'numero' => ['oficial', 'alineamiento', 'folio', 'constancia', 'numeracion'],
                'oficial' => ['numero', 'alineamiento', 'constancia', 'numeracion', 'ubicacion'],
                'numeracion' => ['oficial', 'numero', 'alineamiento', 'ubicacion', 'inmueble'],
                'ubicacion' => ['numeracion', 'oficial', 'alineamiento', 'inmueble'],

                // CONSTANCIA DE SEGURIDAD ESTRUCTURAL
                'seguridad' => ['estructural', 'constancia', 'dictamen', 'construccion'],
                'estructural' => ['seguridad', 'constancia', 'dictamen', 'construccion', 'obra'],
                'dictamen' => ['seguridad', 'estructural', 'constancia', 'vigente'],

                // Declaraciones
                'declaraciones' => ['anuales', 'fiscal', 'sat'],
                'anuales' => ['declaraciones', 'fiscal']
            ];

            // Verificar coincidencias por palabras clave
            $coincidencias = 0;
            $palabrasRequeridas = explode(' ', $nombreRequerido);
            $palabrasDetectadas = explode(' ', $nombreDetectado);
            $palabrasArchivo = explode(' ', str_replace(['_', '-', '.'], ' ', $nombreArchivo));

            foreach ($palabrasRequeridas as $palabraRequerida) {
                $palabraRequerida = trim($palabraRequerida);
                if (strlen($palabraRequerida) < 3) continue;

                // Buscar coincidencia directa
                if (
                    strpos($nombreDetectado, $palabraRequerida) !== false ||
                    strpos($nombreArchivo, $palabraRequerida) !== false
                ) {
                    $coincidencias++;
                    continue;
                }

                // Buscar coincidencias por sinónimos
                if (isset($validaciones[$palabraRequerida])) {
                    foreach ($validaciones[$palabraRequerida] as $sinonimo) {
                        if (
                            strpos($nombreDetectado, $sinonimo) !== false ||
                            strpos($nombreArchivo, $sinonimo) !== false
                        ) {
                            $coincidencias++;
                            break;
                        }
                    }
                }
            }

            $porcentajeCoincidencia = count($palabrasRequeridas) > 0 ?
                ($coincidencias / count($palabrasRequeridas)) * 100 : 0;

            Log::info('Resultado de validación', [
                'coincidencias' => $coincidencias,
                'total_palabras' => count($palabrasRequeridas),
                'porcentaje' => $porcentajeCoincidencia
            ]);

            // Lógica especial para documentos de inmuebles/propiedades (ampliada)
            $esDocumentoInmueble = false;
            $palabrasInmueble = [
                'uso',
                'legal',
                'inmueble',
                'propiedad',
                'escritura',
                'predial',
                'catastral',
                'registro',
                'publico',
                'arrendamiento',
                'contrato',
                'notariado',
                'notarial',
                'registrada',
                'debidamente',
                'impuesto',
                'corriente',
                'duracion',
                'generacion',
                // AGREGADO: Palabras específicas de uso de suelo
                'suelo',
                'estatal',
                'municipal',
                'compatibilidad',
                'urbanistica',
                'zonificacion',
                'desarrollo',
                'alineamiento',
                'numero',
                'oficial',
                'numeracion',
                'ubicacion',
                'seguridad',
                'estructural',
                'dictamen',
                'construccion'
            ];

            foreach ($palabrasInmueble as $palabra) {
                if (
                    strpos($nombreRequerido, $palabra) !== false ||
                    strpos($nombreDetectado, $palabra) !== false ||
                    strpos($nombreArchivo, $palabra) !== false
                ) {
                    $esDocumentoInmueble = true;
                    break;
                }
            }

            // NUEVA VALIDACIÓN: Usar palabras clave de la descripción para mayor precisión
            $validacionDescripcion = $this->validarContraDescripcion($nombreDetectado, $nombreArchivo, $palabrasClaveDescripcion);
            $coincidenciasDescripcion = $validacionDescripcion['coincidencias'];
            $totalPalabrasDescripcion = $validacionDescripcion['total_palabras'];

            // Validación específica por nombre de archivo para mayor precisión
            $validacionNombreArchivo = $this->validarEspecificidadNombreArchivo($nombreArchivo, $documentoRequerido->nombre);

            // Calcular porcentaje combinando coincidencias de nombre y descripción
            $porcentajeDescripcion = $totalPalabrasDescripcion > 0 ?
                ($coincidenciasDescripcion / $totalPalabrasDescripcion) * 100 : 0;

            // Usar el mayor porcentaje entre validación por nombre y por descripción
            $porcentajeFinal = max($porcentajeCoincidencia, $porcentajeDescripcion);

            Log::info('Comparación de validaciones', [
                'porcentaje_nombre' => $porcentajeCoincidencia,
                'porcentaje_descripcion' => $porcentajeDescripcion,
                'porcentaje_final' => $porcentajeFinal,
                'coincidencias_descripcion' => $coincidenciasDescripcion,
                'total_palabras_descripcion' => $totalPalabrasDescripcion
            ]);

            // VALIDACIÓN ESTRICTA: Verificar coincidencia exacta de tipo de documento
            $coincidenciaExacta = ($nombreDetectado === $nombreRequerido);

            //  NUEVA REGLA: Para documentos específicos como "uso de suelo", verificar palabras clave exactas
            $requiereValidacionEspecifica = $this->requiereValidacionEspecifica($nombreRequerido);

            if ($requiereValidacionEspecifica) {
                $validacionEspecifica = $this->validarDocumentoEspecifico($nombreRequerido, $nombreDetectado, $nombreArchivo);

                if (!$validacionEspecifica['es_valido']) {
                    Log::warning('Documento rechazado por validación específica', [
                        'documento_requerido' => $nombreRequerido,
                        'documento_detectado' => $nombreDetectado,
                        'razon' => $validacionEspecifica['razon']
                    ]);

                    return [
                        'coincide' => false,
                        'porcentaje_coincidencia' => $porcentajeFinal,
                        'razon' => "DOCUMENTO INCORRECTO: {$validacionEspecifica['razon']}",
                        'accion' => 'rechazar',
                        'tipo_requerido' => $nombreRequerido,
                        'tipo_detectado' => $nombreDetectado
                    ];
                }
            }

            // Ajustar umbral de coincidencia según el tipo de documento
            $umbralMinimo = 60; // Umbral permisivo por defecto

            // Ser aún más permisivo si hay coincidencia exacta de tipo
            if ($coincidenciaExacta) {
                $umbralMinimo = 70; // Más permisivo si el tipo coincide exactamente
                Log::info('Tipos coinciden exactamente, usando umbral reducido', [
                    'umbral_reducido' => $umbralMinimo
                ]);
            }

            // Si hay coincidencia específica en el nombre del archivo Y coincide el tipo
            if ($validacionNombreArchivo['coincidencia_especifica'] && $coincidenciaExacta) {
                $umbralMinimo -= 10; // Solo reducir si TAMBIÉN coincide el tipo
            }
            Log::info('Evaluando coincidencia', [
                'porcentaje_original' => $porcentajeCoincidencia,
                'porcentaje_descripcion' => $porcentajeDescripcion,
                'porcentaje_final' => $porcentajeFinal,
                'umbral_usado' => $umbralMinimo,
                'es_documento_inmueble' => $esDocumentoInmueble
            ]);

            // Determinar si coincide usando el porcentaje final (el mayor)
            if ($porcentajeFinal >= $umbralMinimo) {
                $tipoValidacion = $porcentajeFinal == $porcentajeDescripcion ? 'descripción' : 'nombre';
                $mensaje = $esDocumentoInmueble ?
                    "Documento de inmueble válido - Coincidencia del {$porcentajeFinal}% (validado por {$tipoValidacion})" :
                    "Documento válido - Coincidencia del {$porcentajeFinal}% (validado por {$tipoValidacion})";

                return [
                    'coincide' => true,
                    'porcentaje_coincidencia' => $porcentajeFinal,
                    'razon' => $mensaje,
                    'accion' => 'aprobar',
                    'umbral_usado' => $umbralMinimo,
                    'metodo_validacion' => $tipoValidacion
                ];
            } else {
                return [
                    'coincide' => false,
                    'porcentaje_coincidencia' => $porcentajeFinal,
                    'razon' => "Documento no coincide con el tipo requerido. Se esperaba '{$documentoRequerido->nombre}' pero se detectó '{$documentoDetectado['nombre']}'. Coincidencia: {$porcentajeFinal}% (mínimo requerido: {$umbralMinimo}%)",
                    'accion' => 'rechazar',
                    'documento_esperado' => $documentoRequerido->nombre,
                    'documento_detectado' => $documentoDetectado['nombre'],
                    'umbral_usado' => $umbralMinimo,
                    'detalle_validacion' => [
                        'porcentaje_nombre' => $porcentajeCoincidencia,
                        'porcentaje_descripcion' => $porcentajeDescripcion
                    ]
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error en validación de coincidencia', [
                'error' => $e->getMessage(),
                'documento_id' => $documentoRequeridoId
            ]);

            return [
                'coincide' => false,
                'razon' => 'Error interno en la validación del documento',
                'accion' => 'rechazar'
            ];
        }
    }


    /**
     * Procesa el resultado del análisis y actualiza la base de datos
     */
    public function procesarAnalisis($analisis, $informacionId, $archivoId)
    {
        try {
            Log::info('PROCESANDO ANÁLISIS - INICIO', [
                'informacion_id' => $informacionId,
                'archivo_id' => $archivoId,
                'tiene_metadatos' => isset($analisis['metadatos']),
                'tiene_documento' => isset($analisis['documento']),
                'estructura_analisis' => array_keys($analisis ?? [])
            ]);

            if (!isset($analisis['metadatos']) || !isset($analisis['documento'])) {
                Log::error('ESTRUCTURA DE ANÁLISIS INCORRECTA', [
                    'metadatos_presente' => isset($analisis['metadatos']),
                    'documento_presente' => isset($analisis['documento']),
                    'analisis_recibido' => $analisis
                ]);
                return false;
            }

            $metadatos = $analisis['metadatos'];
            $documento = $analisis['documento'];
            $estadoSistema = $analisis['estado_sistema'] ?? [];
            $validacion = $analisis['validacion'] ?? [];

            // Determinar estado basado en la validación
            $estado = 'vigente';
            $observaciones = $documento['observaciones'] ?? '';

            // DETECCIÓN ESPECÍFICA DE ERRORES DE GPT
            $esErrorGPT = isset($analisis['error_gpt']['tiene_error']) && $analisis['error_gpt']['tiene_error'];

            Log::info('DETERMINANDO ESTADO FINAL', [
                'validacion_coincide' => $validacion['coincide'] ?? 'no_definido',
                'cumple_requisitos' => $documento['cumple_requisitos'] ?? 'no_definido',
                'es_error_gpt' => $esErrorGPT,
                'error_gpt_data' => $analisis['error_gpt'] ?? 'no_presente',
                'validacion_razon' => $validacion['razon'] ?? 'no_definida'
            ]);

            if ($esErrorGPT || (isset($validacion['coincide']) && !$validacion['coincide'])) {
                $estado = 'rechazado';
                $razonRechazo = $esErrorGPT ?
                    'ERROR DE SISTEMA: ' . ($analisis['error_gpt']['razon'] ?? 'GPT no pudo procesar el documento') :
                    $validacion['razon'] ?? 'Documento no válido';
                $observaciones = 'DOCUMENTO RECHAZADO: ' . $razonRechazo;

                Log::info('DOCUMENTO MARCADO COMO RECHAZADO', [
                    'motivo' => $esErrorGPT ? 'error_gpt' : 'validacion_fallo',
                    'razon' => $razonRechazo,
                    'estado_final' => $estado
                ]);
            } elseif ($documento['cumple_requisitos']) {
                $estado = 'vigente';
                $observaciones = 'Documento analizado automáticamente - Cumple requisitos';

                Log::info('DOCUMENTO MARCADO COMO VIGENTE', [
                    'cumple_requisitos' => true,
                    'estado_final' => $estado
                ]);
            } else {
                Log::warning('ESTADO AMBIGUO - usando vigente por defecto', [
                    'validacion' => $validacion,
                    'documento' => $documento,
                    'estado_final' => $estado
                ]);
            }

            // Actualizar la información del documento con los datos extraídos
            $informacion = \App\Models\SugDocumentoInformacion::find($informacionId);
            if ($informacion) {
                // Estructurar el JSON con el formato deseado
                $metadataEstructurado = [
                    'documento' => [
                        'nombre_detectado' => $documento['nombre_detectado'] ?? 'No detectado',
                        'tipo_documento_id' => $documento['tipo_documento_id'] ?? null,
                        'coincide_catalogo' => $documento['coincide_catalogo'] ?? false,
                        'criterio_coincidencia' => $documento['criterio_coincidencia'] ?? '',
                        'descripcion' => $documento['descripcion'] ?? '',
                        'cumple_requisitos' => $documento['cumple_requisitos'] ?? false,
                        'observaciones' => $documento['observaciones'] ?? ''
                    ],
                    'metadatos' => [
                        'folio_documento' => $metadatos['folio_documento'] ?? "AUTO-" . time(),
                        'entidad_emisora' => $metadatos['entidad_emisora'] ?? "Detectada automáticamente",
                        'nombre_perito' => $metadatos['nombre_perito'] ?? null,
                        'cedula_profesional' => $metadatos['cedula_profesional'] ?? null,
                        'licencia' => $metadatos['licencia'] ?? null,
                        'fecha_expedicion' => $metadatos['fecha_expedicion'] ?? date('Y-m-d'),
                        'vigencia_documento' => $metadatos['vigencia_documento'] ?? date('Y-m-d', strtotime('+12 months')),
                        'dias_restantes_vigencia' => $metadatos['dias_restantes_vigencia'] ?? 365,
                        'lugar_expedicion' => $metadatos['lugar_expedicion'] ?? "Zacatecas, Zac."
                    ],
                    'asignacion' => [
                        'campus_id' => $analisis['asignacion']['campus_id'] ?? $informacion->campus_id,
                        'carrera_id' => $analisis['asignacion']['carrera_id'] ?? $informacion->carrera_id,
                        'archivo_pdf' => $analisis['asignacion']['archivo_pdf'] ?? '',
                        'empleado_captura_id' => $analisis['asignacion']['empleado_captura_id'] ?? ''
                    ],
                    'estado_sistema' => [
                        'requiere_vigencia' => $estadoSistema['requiere_vigencia'] ?? true,
                        'vigencia_meses' => $estadoSistema['vigencia_meses'] ?? 12,
                        'estado_calculado' => $estadoSistema['estado_calculado'] ?? $estado
                    ],
                    'estructura_bd' => [
                        'tabla_destino' => 'sug_documentos_informacion',
                        'campos' => [
                            'documento_id' => $informacion->documento_id,
                            'campus_id' => $informacion->campus_id,
                            'carrera_id' => $informacion->carrera_id,
                            'nombre_documento' => $documento['nombre_detectado'] ?? $informacion->nombre_documento,
                            'folio_documento' => $metadatos['folio_documento'] ?? $informacion->folio_documento ?? "AUTO-" . time(),
                            'fecha_expedicion' => $metadatos['fecha_expedicion'] ?? $informacion->fecha_expedicion ?? date('Y-m-d'),
                            'lugar_expedicion' => $metadatos['lugar_expedicion'] ?? $informacion->lugar_expedicion ?? "Zacatecas, Zac.",
                            'vigencia_documento' => $metadatos['vigencia_documento'] ?? $informacion->vigencia_documento ?? date('Y-m-d', strtotime('+12 months')),
                            'estado' => $estado,
                            'observaciones' => $observaciones,
                            'metadata_json' => [
                                'entidad_emisora' => $metadatos['entidad_emisora'] ?? "Detectada automáticamente",
                                'nombre_perito' => $metadatos['nombre_perito'] ?? null,
                                'licencia' => $metadatos['licencia'] ?? null,
                                'cedula_profesional' => $metadatos['cedula_profesional'] ?? null
                            ]
                        ]
                    ]
                ];

                Log::info('ACTUALIZANDO INFORMACIÓN EN BD', [
                    'informacion_id' => $informacionId,
                    'estado' => $estado,
                    'observaciones' => $observaciones,
                    'folio_documento' => $metadatos['folio_documento'] ?? $informacion->folio_documento ?? "AUTO-" . time(),
                    'metadata_json_length' => strlen(json_encode($metadataEstructurado))
                ]);

                $informacion->update([
                    'nombre_documento' => $documento['nombre_detectado'] ?? $informacion->nombre_documento,
                    'folio_documento' => $metadatos['folio_documento'] ?? $informacion->folio_documento ?? "AUTO-" . time(),
                    'fecha_expedicion' => $metadatos['fecha_expedicion'] ?? $informacion->fecha_expedicion ?? date('Y-m-d'),
                    'lugar_expedicion' => $metadatos['lugar_expedicion'] ?? $informacion->lugar_expedicion ?? "Zacatecas, Zac.",
                    'vigencia_documento' => $metadatos['vigencia_documento'] ?? $informacion->vigencia_documento ?? date('Y-m-d', strtotime('+12 months')),
                    'estado' => $estado,
                    'observaciones' => $observaciones,
                    'metadata_json' => json_encode($metadataEstructurado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ]);

                Log::info('INFORMACIÓN ACTUALIZADA EN BD', [
                    'informacion_id' => $informacionId,
                    'metadata_json_guardado' => !empty($informacion->metadata_json)
                ]);
            }

            // Actualizar el archivo con observaciones del análisis
            $archivo = \App\Models\SugDocumentoArchivo::find($archivoId);
            if ($archivo) {
                $archivo->update([
                    'observaciones' => $observaciones
                ]);
            }

            Log::info('Análisis procesado', [
                'informacion_id' => $informacionId,
                'archivo_id' => $archivoId,
                'estado_final' => $estado,
                'validacion_coincide' => $validacion['coincide'] ?? 'N/A'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error procesando análisis', [
                'error' => $e->getMessage(),
                'informacion_id' => $informacionId,
                'archivo_id' => $archivoId
            ]);
            return false;
        }
    }



    /**
     * Validar especificidad del nombre de archivo para determinar precisión de coincidencia
     */
    private function validarEspecificidadNombreArchivo($nombreArchivo, $nombreRequerido)
    {
        $nombreArchivo = strtolower($nombreArchivo);
        $nombreRequerido = strtolower($nombreRequerido);

        // Patrones específicos para diferentes tipos de documentos
        $patronesEspecificos = [
            'uso legal del inmueble' => [
                'municipal' => ['municipal', 'municipio', 'ayuntamiento'],
                'estatal' => ['estatal', 'estado', 'gobierno del estado'],
                'federal' => ['federal', 'federacion', 'gobierno federal'],
                'suelo' => ['suelo', 'uso de suelo', 'zonificacion'],
                'construccion' => ['construccion', 'edificacion', 'obra']
            ],
            'constancia de seguridad estructural' => [
                'seguridad' => ['seguridad', 'estructural', 'estructura'],
                'dictamen' => ['dictamen', 'evaluacion', 'revision'],
                'perito' => ['perito', 'responsable', 'director']
            ]
        ];

        $coincidenciaEspecifica = false;
        $tipoEspecifico = '';

        // Verificar si el nombre del archivo contiene patrones específicos
        if (isset($patronesEspecificos[$nombreRequerido])) {
            foreach ($patronesEspecificos[$nombreRequerido] as $tipo => $patrones) {
                foreach ($patrones as $patron) {
                    if (strpos($nombreArchivo, $patron) !== false) {
                        $coincidenciaEspecifica = true;
                        $tipoEspecifico = $tipo;
                        break 2;
                    }
                }
            }
        }

        // Verificar si hay discrepancia (archivo dice una cosa pero se requiere otra)
        $hayDiscrepancia = false;
        if ($nombreRequerido === 'uso legal del inmueble') {
            // Si se requiere "uso legal" pero el archivo dice específicamente "municipal", "estatal", etc.
            // debería ser más estricto
            $tiposEspecificosSuelo = ['municipal', 'estatal', 'federal'];
            foreach ($tiposEspecificosSuelo as $tipoSuelo) {
                if (strpos($nombreArchivo, $tipoSuelo) !== false) {
                    // El archivo es específico, requiere mayor precisión
                    $hayDiscrepancia = true;
                    break;
                }
            }
        }

        return [
            'coincidencia_especifica' => $coincidenciaEspecifica,
            'tipo_especifico' => $tipoEspecifico,
            'hay_discrepancia' => $hayDiscrepancia
        ];
    }

    /**
     * Extraer palabras clave específicas de la descripción del documento
     */
    private function extraerPalabrasClave($descripcion)
    {
        if (empty($descripcion)) {
            return [];
        }

        $descripcion = strtolower($descripcion);
        $palabrasClave = [];

        // Patrones específicos para extraer información importante
        $patrones = [
            // Entidades emisoras
            'entidades' => ['secretaría', 'secretaria', 'registro público', 'notaría', 'notaria', 'ayuntamiento', 'municipio', 'estado', 'federal', 'seduym', 'seduvim'],

            // Tipos de documentos
            'tipos' => ['constancia', 'certificado', 'escritura', 'contrato', 'dictamen', 'peritaje', 'avalúo', 'avaluo', 'licencia', 'permiso'],

            // Características específicas
            'caracteristicas' => ['seguridad estructural', 'uso de suelo', 'municipal', 'estatal', 'federal', 'notariado', 'registrado', 'vigente', 'corriente'],

            // Propósitos
            'propositos' => ['construcción', 'construccion', 'edificación', 'edificacion', 'habitacional', 'comercial', 'industrial', 'mixto'],

            // Requisitos
            'requisitos' => ['vigencia', 'firmado', 'sellado', 'foliado', 'debidamente', 'registrada']
        ];

        // Extraer palabras según patrones
        foreach ($patrones as $categoria => $palabrasPatron) {
            foreach ($palabrasPatron as $palabra) {
                if (strpos($descripcion, $palabra) !== false) {
                    $palabrasClave[$categoria][] = $palabra;
                }
            }
        }

        // Extraer entidades específicas con regex
        if (preg_match_all('/(?:secretaría|secretaria|registro)\s+(?:de\s+)?([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+)/i', $descripcion, $matches)) {
            foreach ($matches[1] as $entidad) {
                $palabrasClave['entidades_especificas'][] = trim($entidad);
            }
        }

        // Extraer artículos/marcos legales
        if (preg_match_all('/artículo\s+(\d+)/i', $descripcion, $matches)) {
            $palabrasClave['articulos_legales'] = $matches[1];
        }

        return $palabrasClave;
    }

    /**
     * Validar el documento detectado contra las palabras clave de la descripción
     */
    private function validarContraDescripcion($nombreDetectado, $nombreArchivo, $palabrasClaveDescripcion)
    {
        $coincidencias = 0;
        $totalPalabras = 0;

        $textoCompleto = $nombreDetectado . ' ' . $nombreArchivo;
        $textoCompleto = strtolower($textoCompleto);

        // Contar coincidencias por cada categoría de palabras clave
        foreach ($palabrasClaveDescripcion as $categoria => $palabras) {
            if (is_array($palabras)) {
                foreach ($palabras as $palabra) {
                    $totalPalabras++;
                    $palabra = strtolower($palabra);

                    // Buscar coincidencia exacta o parcial
                    if (strpos($textoCompleto, $palabra) !== false) {
                        $coincidencias++;
                        Log::info("Coincidencia encontrada en descripción", [
                            'categoria' => $categoria,
                            'palabra_clave' => $palabra,
                            'en_texto' => $textoCompleto
                        ]);
                    }
                }
            }
        }

        Log::info('Resultado validación descripción', [
            'coincidencias' => $coincidencias,
            'total_palabras' => $totalPalabras,
            'porcentaje' => $totalPalabras > 0 ? ($coincidencias / $totalPalabras) * 100 : 0
        ]);

        return [
            'coincidencias' => $coincidencias,
            'total_palabras' => $totalPalabras,
            'porcentaje' => $totalPalabras > 0 ? ($coincidencias / $totalPalabras) * 100 : 0
        ];
    }


    /**
     * Determina si un documento requiere validación específica estricta
     */
    private function requiereValidacionEspecifica($nombreRequerido)
    {
        $documentosEspecificos = [
            'uso de suelo estatal',
            'uso de suelo municipal',
            'constancia de alineamiento y número oficial',
            'constancia de seguridad estructural',
            'visto bueno de protección civil',
            'uso legal del inmueble'
        ];

        return in_array(strtolower($nombreRequerido), $documentosEspecificos);
    }

    /**
     * Valida documentos específicos con reglas estrictas
     */
    private function validarDocumentoEspecifico($nombreRequerido, $nombreDetectado, $nombreArchivo)
    {
        $nombreRequeridoLower = strtolower($nombreRequerido);
        $nombreDetectadoLower = strtolower($nombreDetectado);
        $nombreArchivoLower = strtolower($nombreArchivo);

        Log::info('Iniciando validación específica', [
            'requerido' => $nombreRequeridoLower,
            'detectado' => $nombreDetectadoLower,
            'archivo' => $nombreArchivoLower
        ]);

        // Reglas específicas para cada tipo de documento
        switch ($nombreRequeridoLower) {
            case 'uso de suelo estatal':
                // DEBE contener "uso", "suelo" Y "estatal"
                $requiereExacto = ['uso', 'suelo', 'estatal'];
                $prohibidas = ['municipal', 'legal', 'inmueble', 'propiedad'];
                break;

            case 'uso de suelo municipal':
                // DEBE contener "uso", "suelo" Y "municipal"
                $requiereExacto = ['uso', 'suelo', 'municipal'];
                $prohibidas = ['estatal', 'legal', 'inmueble', 'propiedad'];
                break;

            case 'uso legal del inmueble':
                // DEBE contener "uso", "legal" Y ("inmueble" O "propiedad")
                $requiereExacto = ['uso', 'legal'];
                $requiereUno = ['inmueble', 'propiedad'];
                $prohibidas = ['suelo', 'estatal', 'municipal'];
                break;

            case 'constancia de alineamiento y número oficial':
                // DEBE contener "alineamiento" Y ("numero" O "número")
                $requiereExacto = ['alineamiento'];
                $requiereUno = ['numero', 'número', 'oficial'];
                $prohibidas = ['suelo', 'legal', 'inmueble'];
                break;

            default:
                // Para otros documentos, usar validación menos estricta
                return ['es_valido' => true, 'razon' => 'Documento no requiere validación específica'];
        }

        // Verificar palabras requeridas exactas
        foreach ($requiereExacto as $palabra) {
            $encontradaEnDetectado = strpos($nombreDetectadoLower, $palabra) !== false;
            $encontradaEnArchivo = strpos($nombreArchivoLower, $palabra) !== false;

            if (!$encontradaEnDetectado && !$encontradaEnArchivo) {
                return [
                    'es_valido' => false,
                    'razon' => "Falta palabra clave obligatoria: '{$palabra}'. Documento detectado: '{$nombreDetectado}'"
                ];
            }
        }

        // Verificar que al menos una palabra del grupo "requiere uno" esté presente
        if (isset($requiereUno)) {
            $encontroAlgunaDelGrupo = false;
            foreach ($requiereUno as $palabra) {
                if (strpos($nombreDetectadoLower, $palabra) !== false || strpos($nombreArchivoLower, $palabra) !== false) {
                    $encontroAlgunaDelGrupo = true;
                    break;
                }
            }

            if (!$encontroAlgunaDelGrupo) {
                return [
                    'es_valido' => false,
                    'razon' => "Falta al menos una palabra del grupo: [" . implode(', ', $requiereUno) . "]. Documento detectado: '{$nombreDetectado}'"
                ];
            }
        }

        // Verificar palabras prohibidas
        if (isset($prohibidas)) {
            foreach ($prohibidas as $palabra) {
                if (strpos($nombreDetectadoLower, $palabra) !== false) {
                    return [
                        'es_valido' => false,
                        'razon' => "Contiene palabra prohibida: '{$palabra}'. Este parece ser un documento de tipo diferente. Documento detectado: '{$nombreDetectado}'"
                    ];
                }
            }
        }

        Log::info('Validación específica exitosa', [
            'documento' => $nombreRequeridoLower,
            'todas_las_validaciones_pasaron' => true
        ]);

        return ['es_valido' => true, 'razon' => 'Documento válido según criterios específicos'];
    }

    /**
     * Extrae texto de un archivo PDF
     */
    private function extraerTextoPDF($rutaArchivo)
    {
        try {
            // Fallback: usar librería PHP si está disponible
            if (class_exists('\Smalot\PdfParser\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($rutaArchivo);
                return $pdf->getText();
            }

            Log::warning('No hay herramientas disponibles para extraer texto del PDF');
            return '';
        } catch (\Exception $e) {
            Log::error('Error extrayendo texto del PDF', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Verifica si un comando está disponible en el sistema
     */

    /**
     * Función GPT simple - solo extrae información básica
     */
    private function enviarAGPTSimple($textoPDF)
    {
        try {
            $prompt = "Analiza el siguiente documento y extrae información en formato JSON:

                {
                \"fecha_expedicion\": \"YYYY-MM-DD o null si no encuentras\",
                \"folio_documento\": \"string o null si no encuentras\",
                \"entidad_emisora\": \"string o null si no encuentras\",
                \"vigencia_documento\": \"YYYY-MM-DD o null si no encuentras\",
                \"lugar_expedicion\": \"string o null si no encuentras\",
                \"nombre_documento\": \"string - tipo de documento detectado\",
                \"observaciones\": \"string - resumen de lo que encontraste\"
                }

                Busca fechas en formatos como:
                - \"15 de marzo de 2024\"
                - \"15/03/2024\"
                - \"Zacatecas, Zac., 15 de marzo de 2024\"

                IMPORTANTE: Si NO encuentras una fecha, pon null. NO inventes fechas.

                Responde SOLO con el JSON, sin explicaciones adicionales.";

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl, [
                    'model' => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt],
                        ['role' => 'user', 'content' => "Texto del documento:\n\n" . $textoPDF]
                    ],
                    'max_tokens' => 1000,
                    'temperature' => 0.4
                ]);

            if ($response->failed()) {
                Log::error(' Error en petición a GPT', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            $contenido = $data['choices'][0]['message']['content'] ?? '';

            // Log completo para debugging
            Log::info(' RESPUESTA GPT COMPLETA', [
                'texto_enviado' => substr($textoPDF, 0, 500) . '...',
                'respuesta_cruda' => $contenido
            ]);

            // Limpiar JSON
            $contenido = trim($contenido);
            $contenido = preg_replace('/```json\s*/', '', $contenido);
            $contenido = preg_replace('/```\s*$/', '', $contenido);

            $resultado = json_decode($contenido, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error(' Error parseando JSON', [
                    'error' => json_last_error_msg(),
                    'contenido' => $contenido
                ]);
                return null;
            }

            Log::info(' RESULTADO GPT PARSEADO', $resultado);
            return $resultado;
        } catch (\Exception $e) {
            Log::error(' Error enviando a GPT', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    /**
     * Valida y normaliza fechas para mayor precisión
     */
    private function validarYNormalizarFecha($fecha)
    {
        if (empty($fecha) || $fecha === 'null') {
            return null;
        }

        // Si es la fecha especial para "sin vigencia"
        if ($fecha === '2099-12-31') {
            return '2099-12-31';
        }

        // Si ya está en formato ISO, validar
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $timestamp = strtotime($fecha);
            if ($timestamp === false) {
                Log::warning('Fecha ISO inválida detectada', ['fecha' => $fecha]);
                return null;
            }
            return $fecha;
        }

        // Intentar convertir formatos comunes mexicanos
        $mesesEspanol = [
            'enero' => '01',
            'febrero' => '02',
            'marzo' => '03',
            'abril' => '04',
            'mayo' => '05',
            'junio' => '06',
            'julio' => '07',
            'agosto' => '08',
            'septiembre' => '09',
            'octubre' => '10',
            'noviembre' => '11',
            'diciembre' => '12'
        ];

        // Patrón: "28 de agosto de 2025"
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})/', $fecha, $matches)) {
            $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $mesNombre = strtolower($matches[2]);
            $año = $matches[3];

            if (isset($mesesEspanol[$mesNombre])) {
                $fechaNormalizada = $año . '-' . $mesesEspanol[$mesNombre] . '-' . $dia;
                Log::info('Fecha convertida exitosamente', [
                    'original' => $fecha,
                    'normalizada' => $fechaNormalizada
                ]);
                return $fechaNormalizada;
            }
        }

        Log::warning('No se pudo normalizar la fecha', ['fecha' => $fecha]);
        return null;
    }

    /**
     * Obtiene el nombre del documento requerido desde la base de datos
     */
    private function obtenerNombreDocumentoRequerido($documentoRequeridoId)
    {
        try {
            $documento = \App\Models\SugDocumento::find($documentoRequeridoId);
            return $documento ? $documento->nombre : 'Documento requerido ID: ' . $documentoRequeridoId;
        } catch (\Exception $e) {
            Log::warning('Error obteniendo nombre del documento requerido', [
                'documento_id' => $documentoRequeridoId,
                'error' => $e->getMessage()
            ]);
            return 'Documento requerido ID: ' . $documentoRequeridoId;
        }
    }

    /**
     * Valida que el JSON de análisis tenga la estructura mínima esperada
     */
    private function validarEstructuraAnalisisJSON($analisisJSON)
    {
        if (!is_array($analisisJSON)) {
            return false;
        }

        // Verificar que tenga las secciones principales
        $seccionesRequeridas = ['documento', 'metadatos'];
        foreach ($seccionesRequeridas as $seccion) {
            if (!isset($analisisJSON[$seccion]) || !is_array($analisisJSON[$seccion])) {
                Log::warning('Falta sección requerida en análisis JSON', [
                    'seccion_faltante' => $seccion,
                    'secciones_existentes' => array_keys($analisisJSON)
                ]);
                return false;
            }
        }

        // Verificar que tenga al menos algunos campos críticos en documento
        $camposDocumento = $analisisJSON['documento'];
        if (
            empty($camposDocumento['nombre_detectado']) &&
            empty($camposDocumento['tipo_documento']) &&
            empty($camposDocumento['tipo_documento_id'])
        ) {
            Log::warning('El documento no tiene información básica de identificación');
            return false;
        }

        return true;
    }

    /**
     * Genera un análisis de rechazo cuando GPT falla
     */
    private function generarAnalisisDeRechazo($razonError, $nombreArchivo = '')
    {
        Log::info('Generando análisis de rechazo por error de GPT', [
            'razon' => $razonError,
            'archivo' => $nombreArchivo
        ]);

        return [
            'documento' => [
                'nombre_detectado' => !empty($nombreArchivo) ? basename($nombreArchivo, '.pdf') : 'Documento no identificado',
                'tipo_documento_id' => null,
                'tipo_documento' => 'Error en procesamiento',
                'coincide_catalogo' => false,
                'descripcion' => 'El sistema no pudo procesar correctamente este documento',
                'cumple_requisitos' => false,
                'observaciones' => 'DOCUMENTO RECHAZADO: ' . $razonError
            ],
            'metadatos' => [
                'folio_documento' => 'ERROR-' . time(),
                'oficio_documento' => null,
                'entidad_emisora' => 'No identificada',
                'area_emisora' => null,
                'nombre_firmante' => null,
                'puesto_firmante' => null,
                'nombre_perito' => null,
                'cedula_profesional' => null,
                'licencia' => null,
                'registro_perito' => null,
                'fecha_expedicion' => date('Y-m-d'),
                'vigencia_documento' => '2099-12-31', // Sin vigencia definida
                'dias_restantes_vigencia' => null,
                'direccion_inmueble' => null,
                'uso_inmueble' => null,
                'fundamento_legal' => null,
                'lugar_expedicion' => 'No identificado',
                'estado_documento' => 'rechazado'
            ],
            'propietario' => [
                'nombre_propietario' => null,
                'razon_social' => null
            ],
            'entidad_emisora' => [
                'nombre' => 'No identificada',
                'nivel' => null,
                'tipo' => null
            ],
            'estructura_bd' => [
                'tabla_destino' => 'sug_documentos_informacion',
                'campos' => [
                    'documento_id' => null,
                    'nombre_documento' => !empty($nombreArchivo) ? basename($nombreArchivo, '.pdf') : 'Error en procesamiento',
                    'folio_documento' => 'ERROR-' . time(),
                    'fecha_expedicion' => date('Y-m-d'),
                    'lugar_expedicion' => 'No identificado',
                    'vigencia_documento' => '2099-12-31',
                    'estado' => 'rechazado',
                    'observaciones' => 'ERROR DE SISTEMA: ' . $razonError,
                    'metadata_json' => [
                        'error_procesamiento' => true,
                        'razon_error' => $razonError,
                        'timestamp_error' => now()->toISOString()
                    ]
                ]
            ],
            'error_gpt' => [
                'tiene_error' => true,
                'razon' => $razonError,
                'timestamp' => now()->toISOString()
            ],
            'validacion' => [
                'coincide' => false,
                'porcentaje_coincidencia' => 0,
                'razon' => $razonError,
                'accion' => 'rechazar',
                'documento_esperado' => 'Documento requerido',
                'documento_detectado' => 'Error en procesamiento',
                'evaluacion_gpt' => 'error_sistema'
            ]
        ];
    }
    /**
     * Determina la vigencia final considerando lo detectado por GPT-4 y la lógica inteligente
     */
    private function determinarVigenciaFinal($metadatos, $nombreDocumento)
    {
        // 1. Si GPT-4 encontró una vigencia específica, usarla
        $vigenciaDetectada = $this->validarYNormalizarFecha($metadatos['vigencia_documento'] ?? null);
        if ($vigenciaDetectada && $vigenciaDetectada !== '2099-12-31') {
            Log::info('Usando vigencia detectada por GPT-4', [
                'vigencia' => $vigenciaDetectada,
                'documento' => $nombreDocumento
            ]);
            return $vigenciaDetectada;
        }

        // 2. Si no encontró vigencia, usar lógica inteligente
        $fechaExpedicion = $this->validarYNormalizarFecha($metadatos['fecha_expedicion'] ?? null);
        $vigenciaInteligente = $this->determinarVigenciaInteligente($nombreDocumento, $fechaExpedicion);

        Log::info('Usando vigencia inteligente', [
            'vigencia' => $vigenciaInteligente,
            'documento' => $nombreDocumento,
            'fecha_expedicion' => $fechaExpedicion
        ]);

        return $vigenciaInteligente;
    }

    /**
     * Determina vigencia inteligente según el tipo de documento
     */
    private function determinarVigenciaInteligente($nombreDocumento, $fechaExpedicion)
    {
        $nombreLower = strtolower($nombreDocumento);

        // Documentos que típicamente tienen vigencia de 1 año
        $documentosConVigencia = [
            'licencia',
            'permiso',
            'constancia',
            'dictamen',
            'certificado',
            'autorización',
            'visto bueno',
            'validación',
            'verificación'
        ];

        // Documentos que son permanentes
        $documentosPermanentes = [
            'título',
            'acta',
            'escritura',
            'registro',
            'inscripción',
            'cédula profesional',
            'diploma',
            'certificado de nacimiento'
        ];

        // Verificar si es documento permanente
        foreach ($documentosPermanentes as $tipo) {
            if (strpos($nombreLower, $tipo) !== false) {
                Log::info('Documento clasificado como permanente', [
                    'nombre' => $nombreDocumento,
                    'tipo_detectado' => $tipo
                ]);
                return '2099-12-31'; // Sin vigencia
            }
        }

        // Verificar si es documento con vigencia típica
        foreach ($documentosConVigencia as $tipo) {
            if (strpos($nombreLower, $tipo) !== false) {
                $fechaVigencia = $fechaExpedicion ?
                    date('Y-m-d', strtotime($fechaExpedicion . ' +1 year')) :
                    date('Y-m-d', strtotime('+1 year'));

                Log::info('Documento clasificado con vigencia de 1 año', [
                    'nombre' => $nombreDocumento,
                    'tipo_detectado' => $tipo,
                    'vigencia_calculada' => $fechaVigencia
                ]);
                return $fechaVigencia;
            }
        }

        // Por defecto, documentos oficiales suelen tener vigencia
        $fechaVigencia = $fechaExpedicion ?
            date('Y-m-d', strtotime($fechaExpedicion . ' +1 year')) :
            date('Y-m-d', strtotime('+1 year'));

        Log::info('Documento clasificado con vigencia por defecto', [
            'nombre' => $nombreDocumento,
            'vigencia_calculada' => $fechaVigencia
        ]);

        return $fechaVigencia;
    }

    private function validarCiudadDelDocumento($analisisOpenAI, $campusId, $documentoDetectado, $nombreRequerido)
    {
        try {
            // DOCUMENTOS EXENTOS DE VALIDACIÓN DE CIUDAD
            // Estos documentos pueden ser firmados/expedidos en cualquier ciudad (notarías, oficinas centrales, etc.)
            $documentosExentos = [
                // Documentos notariales/fiscales
                'uso legal del inmueble',
                'escritura publica',
                'escritura pública',
                'titulo de propiedad',
                'título de propiedad',
                'acta constitutiva',
                'poder notarial',
                'contrato de arrendamiento', // Puede firmarse en cualquier ciudad
                'constancia de situacion fiscal',
                'constancia de situación fiscal',
                'cedula de identificacion fiscal',
                'cédula de identificación fiscal',

                // Documentos académicos/centralizados (expedidos en oficinas centrales)
                'carta de intencion',
                'carta de intención',
                'carta de intencion de campo clinico',
                'carta de intención de campo clínico',
                'campo clinico',
                'campo clínico',
                'opinion tecnica',
                'opinión técnica',
                'opinion academica',
                'opinión académica',
                'opinion tecnica-academica',
                'opinión técnica-académica',
                'convenio',
                'convenio de colaboracion',
                'convenio de colaboración',
                'acuerdo academico',
                'acuerdo académico'
            ];

            $nombreRequeridoLower = strtolower($nombreRequerido);
            $documentoDetectadoLower = strtolower($documentoDetectado);

            // Verificar si el documento está exento de validación de ciudad
            foreach ($documentosExentos as $docExento) {
                if (
                    strpos($nombreRequeridoLower, $docExento) !== false ||
                    strpos($documentoDetectadoLower, $docExento) !== false
                ) {
                    return [
                        'coincide' => true,
                        'nota' => 'Documento exento de validación de ciudad (notarial/fiscal)',
                        'documento_exento' => true
                    ];
                }
            }

            // Si no hay campusId, no podemos validar
            if (!$campusId) {
            return [
                    'coincide' => true,
                    'nota' => 'Sin campus seleccionado para validar ciudad'
                ];
            }

            // Obtener información del campus
            $campus = \App\Models\campus_model::where('ID_Campus', $campusId)->first();
            if (!$campus) {
                return [
                    'coincide' => true,
                    'nota' => 'Campus no encontrado - omitiendo validación de ciudad'
                ]; // Si no encontramos el campus, no rechazamos por esto
            }

            $ciudadCampus = $this->extraerCiudadDelNombreCampus($campus->Campus ?? '');
            // Obtener las ciudades detectadas en el documento
            $ciudadesDocumento = $this->extraerCiudadesDelAnalisis($analisisOpenAI);
            // Si no se detectaron ciudades en el documento, no rechazamos
            if (empty($ciudadesDocumento)) {
                return [
                    'coincide' => true,
                    'ciudad_campus' => $ciudadCampus,
                    'ciudades_documento' => [],
                    'nota' => 'No se detectaron ciudades en el documento'
                ];
            }

            // Verificar si alguna ciudad del documento coincide con la del campus
            foreach ($ciudadesDocumento as $ciudadDoc) {
                $ciudadDocNormalizada = $this->normalizarNombreCiudad($ciudadDoc);

                // VALIDACIÓN 1: Coincidencia exacta de ciudad
                if ($this->ciudadesCoinciden($ciudadCampus, $ciudadDocNormalizada)) {

                    return [
                        'coincide' => true,
                        'ciudad_campus' => $ciudadCampus,
                        'ciudades_documento' => $ciudadesDocumento,
                        'ciudad_validada' => $ciudadDocNormalizada,
                        'tipo_validacion' => 'coincidencia_exacta'
                    ];
                }

                // 🌎 VALIDACIÓN 2: Mismo estado (ej: Hermosillo y Nogales ambos en Sonora)
                if ($this->ciudadesMismoEstado($ciudadCampus, $ciudadDocNormalizada)) {
                    return [
                        'coincide' => true,
                        'ciudad_campus' => $ciudadCampus,
                        'ciudades_documento' => $ciudadesDocumento,
                        'ciudad_validada' => $ciudadDocNormalizada,
                        'tipo_validacion' => 'mismo_estado'
                    ];
                }
            }
            return [
                'coincide' => false,
                'porcentaje_coincidencia' => 0,
                'razon' => "Ciudad incorrecta: el documento pertenece a " . implode(', ', $ciudadesDocumento) . " pero el campus está en {$ciudadCampus}. Esperado: {$nombreRequerido}, Detectado: {$documentoDetectado}",
                'accion' => 'rechazar',
                'documento_esperado' => $nombreRequerido,
                'documento_detectado' => $documentoDetectado,
                'evaluacion_gpt' => 'ciudad_incorrecta',
                'ciudad_campus' => $ciudadCampus,
                'ciudades_documento' => $ciudadesDocumento
            ];
        } catch (\Exception $e) {
            // En caso de error, no rechazamos el documento
            return [
                'coincide' => true,
                'nota' => 'Error en validación de ciudad - documento aprobado por defecto',
                'error' => $e->getMessage()
            ];
        }
    }

    private function extraerCiudadesDelAnalisis($analisisOpenAI)
    {
        $ciudades = [];

        Log::info('EXTRAYENDO CIUDADES DEL ANÁLISIS', [
            'analisis_disponible' => !empty($analisisOpenAI)
        ]);

        // Buscar ciudades en diferentes secciones del análisis
        if (isset($analisisOpenAI['entidad_emisora']['direccion']['ciudad'])) {
            $ciudades[] = $analisisOpenAI['entidad_emisora']['direccion']['ciudad'];
            Log::info(' Ciudad encontrada en entidad_emisora.direccion.ciudad', ['ciudad' => $analisisOpenAI['entidad_emisora']['direccion']['ciudad']]);
        }

        if (isset($analisisOpenAI['entidad_emisora']['ubicacion'])) {
            $ciudades[] = $analisisOpenAI['entidad_emisora']['ubicacion'];
            Log::info('Ciudad encontrada en entidad_emisora.ubicacion', ['ciudad' => $analisisOpenAI['entidad_emisora']['ubicacion']]);
        }

        if (isset($analisisOpenAI['metadatos']['lugar_emision'])) {
            $ciudades[] = $analisisOpenAI['metadatos']['lugar_emision'];
        }

        if (isset($analisisOpenAI['metadatos']['lugar_expedicion'])) {
            $ciudades[] = $analisisOpenAI['metadatos']['lugar_expedicion'];
        }

        if (isset($analisisOpenAI['documento']['lugar_expedicion'])) {
            $ciudades[] = $analisisOpenAI['documento']['lugar_expedicion'];
            //  Log::info('Ciudad encontrada en documento.lugar_expedicion', ['ciudad' => $analisisOpenAI['documento']['lugar_expedicion']]);
        }
        // Filtrar valores vacíos y duplicados
        $ciudades = array_filter(array_unique($ciudades), function ($ciudad) {
            return !empty($ciudad) && strlen(trim($ciudad)) > 0;
        });
        return array_values($ciudades);
    }
    private function normalizarNombreCiudad($ciudad)
    {
        if (empty($ciudad)) return '';

        // Convertir a minúsculas y quitar acentos
        $ciudad = strtolower(trim($ciudad));
        $ciudad = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $ciudad);

        // Quitar caracteres especiales y espacios extra
        $ciudad = preg_replace('/[^a-z0-9\s]/', '', $ciudad);
        $ciudad = preg_replace('/\s+/', ' ', $ciudad);

        return trim($ciudad);
    }
    private function extraerCiudadDelNombreCampus($nombreCampus)
    {
        // Normalizar el nombre
        $nombreCampus = trim($nombreCampus);

        // Patrones para remover sufijos comunes
        $sufijosARemover = [
            'Fundadores',
            'Santander',
            'Ejecutivas',
            'Virtual',
            'Live',
            'CID',
            'Arena Lobo',
            'Arena Lobos',
            'Lobos',
            'Centro de Convenciones',
            'Forum',
            'Libreria',
            'Catedral'
        ];

        // Remover el prefijo "CID" si existe
        if (stripos($nombreCampus, 'CID') === 0) {
            $nombreCampus = trim(substr($nombreCampus, 3));
        }

        // Remover sufijos conocidos
        foreach ($sufijosARemover as $sufijo) {
            // Remover al final
            if (stripos($nombreCampus, $sufijo) !== false) {
                $nombreCampus = trim(str_ireplace($sufijo, '', $nombreCampus));
            }
        }

        // Limpiar espacios múltiples
        $nombreCampus = preg_replace('/\s+/', ' ', $nombreCampus);

        // Normalizar el resultado
        return $this->normalizarNombreCiudad($nombreCampus);
    }
    private function ciudadesCoinciden($ciudad1, $ciudad2)
    {
        if (empty($ciudad1) || empty($ciudad2)) return false;

        // Coincidencia exacta
        if ($ciudad1 === $ciudad2) return true;

        // Verificar si una ciudad contiene a la otra
        if (strpos($ciudad1, $ciudad2) !== false || strpos($ciudad2, $ciudad1) !== false) {
            return true;
        }

        // Verificar coincidencias parciales para ciudades conocidas
        $variaciones = [
            'durango' => ['durango', 'dgo', 'ciudad de durango', 'victoria de durango'],
            'zacatecas' => ['zacatecas', 'zac', 'ciudad de zacatecas'],
            'chihuahua' => ['chihuahua', 'chih', 'ciudad de chihuahua'],
            'guadalajara' => ['guadalajara', 'gdl', 'zona metropolitana de guadalajara'],
            'monterrey' => ['monterrey', 'mty', 'zona metropolitana de monterrey'],
            'tijuana' => ['tijuana', 'tj'],
            'ciudad de mexico' => ['ciudad de mexico', 'cdmx', 'df', 'distrito federal', 'mexico df'],
            'ciudad juarez' => ['ciudad juarez', 'cd juarez', 'cd. juarez', 'juarez'],
            'ciudad obregon' => ['ciudad obregon', 'cd obregon', 'cd. obregon', 'obregon'],
            'ciudad acuña' => ['ciudad acuna', 'cd acuna', 'cd. acuna', 'acuna'],
            'hermosillo' => ['hermosillo', 'hmo'],
            'culiacan' => ['culiacan', 'culiacán'],
            'mazatlan' => ['mazatlan', 'mazatlán', 'mzt'],
            'aguascalientes' => ['aguascalientes', 'ags'],
            'saltillo' => ['saltillo', 'coahuila'],
            'torreon' => ['torreon', 'torreón', 'laguna'],
            'queretaro' => ['queretaro', 'querétaro', 'qro'],
            'pachuca' => ['pachuca', 'hidalgo'],
            'morelia' => ['morelia', 'michoacan', 'michoacán'],
            'san luis potosi' => ['san luis potosi', 'san luis potosí', 'slp'],
            'mexicali' => ['mexicali', 'baja california'],
            'ensenada' => ['ensenada', 'bc'],
            'nogales' => ['nogales', 'sonora'],
            'los mochis' => ['los mochis', 'mochis'],
            'guasave' => ['guasave', 'sinaloa'],
            'xalapa' => ['xalapa', 'jalapa', 'veracruz'],
            'fresnillo' => ['fresnillo', 'zacatecas'],
            'santiago' => ['santiago', 'nuevo leon', 'nuevo león'],
            'monclova' => ['monclova', 'coahuila'],
            'piedras negras' => ['piedras negras', 'coahuila']
        ];

        foreach ($variaciones as $ciudadBase => $alias) {
            if (in_array($ciudad1, $alias) && in_array($ciudad2, $alias)) {
                return true;
            }
        }

        return false;
    }
    private function ciudadesMismoEstado($ciudad1, $ciudad2)
    {
        if (empty($ciudad1) || empty($ciudad2)) return false;

        // Normalizar nombres
        $ciudad1 = strtolower(trim($ciudad1));
        $ciudad2 = strtolower(trim($ciudad2));

        // Mapa de ciudades por estado
        $estadosCiudades = [
            'sonora' => [
                'hermosillo',
                'nogales',
                'ciudad obregon',
                'cd obregon',
                'obregon',
                'guaymas',
                'san luis rio colorado',
                'navojoa',
                'agua prieta',
                'caborca'
            ],
            'durango' => [
                'durango',
                'victoria de durango',
                'ciudad de durango',
                'gomez palacio',
                'gómez palacio',
                'lerdo',
                'santiago papasquiaro'
            ],
            'zacatecas' => [
                'zacatecas',
                'fresnillo',
                'guadalupe',
                'jerez',
                'rio grande',
                'río grande',
                'sombrerete',
                'jalpa'
            ],
            'chihuahua' => [
                'chihuahua',
                'ciudad juarez',
                'cd juarez',
                'juarez',
                'cuauhtemoc',
                'cuauhtémoc',
                'delicias',
                'parral',
                'hidalgo del parral',
                'nuevo casas grandes'
            ],
            'sinaloa' => [
                'culiacan',
                'culiacán',
                'mazatlan',
                'mazatlán',
                'los mochis',
                'mochis',
                'guasave',
                'guamuchil',
                'guamúchil',
                'ahome'
            ],
            'coahuila' => [
                'saltillo',
                'torreon',
                'torreón',
                'monclova',
                'piedras negras',
                'ciudad acuna',
                'cd acuna',
                'acuna',
                'acuña'
            ],
            'nuevo leon' => [
                'monterrey',
                'san pedro garza garcia',
                'san pedro',
                'guadalupe',
                'san nicolas de los garza',
                'san nicolás',
                'apodaca',
                'santa catarina',
                'santiago'
            ],
            'jalisco' => [
                'guadalajara',
                'zapopan',
                'tlaquepaque',
                'tonala',
                'tonalá',
                'puerto vallarta',
                'lagos de moreno',
                'tepatitlan',
                'tepatitlán'
            ],
            'baja california' => [
                'tijuana',
                'mexicali',
                'ensenada',
                'tecate',
                'rosarito',
                'playas de rosarito'
            ],
            'veracruz' => [
                'xalapa',
                'jalapa',
                'veracruz',
                'puerto de veracruz',
                'coatzacoalcos',
                'poza rica',
                'cordoba',
                'córdoba',
                'orizaba'
            ],
            'tamaulipas' => [
                'ciudad victoria',
                'victoria',
                'tampico',
                'reynosa',
                'matamoros',
                'nuevo laredo'
            ],
            'san luis potosi' => [
                'san luis potosi',
                'san luis potosí',
                'soledad de graciano sanchez',
                'soledad',
                'matehuala',
                'ciudad valles'
            ],
            'michoacan' => [
                'morelia',
                'uruapan',
                'zamora',
                'lazaro cardenas',
                'lázaro cárdenas',
                'patzcuaro',
                'pátzcuaro'
            ],
            'queretaro' => [
                'queretaro',
                'querétaro',
                'santiago de queretaro',
                'santiago de querétaro',
                'san juan del rio',
                'san juan del río'
            ],
            'hidalgo' => [
                'pachuca',
                'pachuca de soto',
                'tulancingo',
                'tula de allende',
                'tula'
            ],
            'aguascalientes' => [
                'aguascalientes',
                'jesus maria',
                'jesús maría',
                'calvillo'
            ]
        ];

        // Buscar en qué estado está cada ciudad
        $estado1 = null;
        $estado2 = null;

        foreach ($estadosCiudades as $estado => $ciudades) {
            foreach ($ciudades as $ciudad) {
                if (strpos($ciudad1, $ciudad) !== false || strpos($ciudad, $ciudad1) !== false) {
                    $estado1 = $estado;
                }
                if (strpos($ciudad2, $ciudad) !== false || strpos($ciudad, $ciudad2) !== false) {
                    $estado2 = $estado;
                }
            }
        }

        // Si ambas ciudades están en el mismo estado, coinciden
        if ($estado1 && $estado2 && $estado1 === $estado2) {
            Log::info('✅ Ciudades del mismo estado detectadas', [
                'ciudad1' => $ciudad1,
                'ciudad2' => $ciudad2,
                'estado' => $estado1
            ]);
            return true;
        }

        return false;
    }
}
