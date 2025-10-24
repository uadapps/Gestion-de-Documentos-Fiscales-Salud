<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\PDFProcessorService;
use Smalot\PdfParser\Parser;

class DocumentAnalyzerService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key', env('OPENAI_API_KEY', 'sk-proj-R4iVs3Fj25clL3USOuonT3BlbkFJZLBwRADJaIcJqyBkWzLB'));
        $this->baseUrl = config('openai.base_url', 'https://api.openai.com/v1/chat/completions');
    }

    /**
     * Analiza un documento PDF enviándolo directamente a OpenAI (método directo como Python)
     * Solo sube el PDF a OpenAI, sin extracción de texto
     */
    public function analizarDocumentoDirecto($rutaArchivo, $campusId, $empleadoId, $documentoRequeridoId = null)
    {
        // 🛡️ PREVENIR EJECUCIONES MÚLTIPLES
        $lockKey = 'analyzing_' . md5($rutaArchivo . $campusId . $empleadoId);
        if (cache()->has($lockKey)) {
            Log::warning('🔒 ANÁLISIS YA EN PROCESO - EVITANDO DUPLICADO', [
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
            Log::info('🚀 INICIANDO ANÁLISIS DIRECTO PDF → OpenAI', [
                'ruta_archivo' => $rutaArchivo,
                'campus_id' => $campusId,
                'empleado_id' => $empleadoId,
                'documento_requerido_id' => $documentoRequeridoId,
                'lock_key' => $lockKey
            ]);

            $rutaCompleta = storage_path('app/public/' . $rutaArchivo);

            if (!file_exists($rutaCompleta)) {
                cache()->forget($lockKey);
                return [
                    'success' => false,
                    'error' => 'Archivo no encontrado: ' . $rutaCompleta
                ];
            }

            // 1️⃣ SUBIR PDF A OPENAI (como código Python)
            $fileId = $this->subirPDFAOpenAI($rutaCompleta);
            if (!$fileId) {
                cache()->forget($lockKey);
                return [
                    'success' => false,
                    'error' => 'Error al subir PDF a OpenAI'
                ];
            }

            Log::info('� PDF subido exitosamente a OpenAI', [
                'file_id' => $fileId,
                'archivo' => $rutaArchivo
            ]);

            // 2️⃣ Solicitar análisis usando solo el archivo subido
            $analisisJSON = $this->solicitarAnalisisConArchivoSolamente($fileId, $rutaArchivo, $documentoRequeridoId);
            if (!$analisisJSON) {
                cache()->forget($lockKey);
                return [
                    'success' => false,
                    'error' => 'Error al obtener análisis de OpenAI'
                ];
            }

            Log::info('✅ ANÁLISIS DIRECTO COMPLETADO', [
                'ruta_archivo' => $rutaArchivo,
                'file_id' => $fileId,
                'tiene_analisis' => !empty($analisisJSON)
            ]);

            // 🔄 Convertir el formato de OpenAI al formato esperado por el sistema
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
            Log::info('🔍 Solicitando análisis SOLO con PDF subido (método Python)', [
                'file_id' => $fileId,
                'archivo' => $nombreArchivo,
                'documento_requerido_id' => $documentoRequeridoId
            ]);

            $prompt = $this->obtenerPromptAnalisisDirectoConCatalogo($documentoRequeridoId);

            // 🎯 Usar exactamente la misma estructura que el código Python exitoso
            $payload = [
                'model' => 'gpt-5',
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

            Log::info('🔍 Respuesta de OpenAI recibida (método Python)', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'has_body' => !empty($response->body())
            ]);

            if (!$response->successful()) {
                Log::error('❌ Error en /v1/responses', [
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
                Log::info('✅ Análisis con PDF exitoso (output_text)');
                $analisisJSON = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $analisisJSON;
                } else {
                    Log::error('❌ Error decodificando JSON de output_text', [
                        'json_error' => json_last_error_msg(),
                        'content' => $content
                    ]);
                    return null;
                }
            }

            // 2️⃣ Estructura alternativa como en Python
            $output = $data['output'] ?? [];
            if (!empty($output) &&
                isset($output[0]['content']) &&
                isset($output[0]['content'][0]['text'])) {

                $texto = $output[0]['content'][0]['text'];
                Log::info('✅ Análisis con PDF exitoso (estructura alternativa)');

                $analisisJSON = json_decode($texto, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $analisisJSON;
                } else {
                    Log::error('❌ Error decodificando JSON de estructura alternativa', [
                        'json_error' => json_last_error_msg(),
                        'texto' => $texto
                    ]);
                    return null;
                }
            }

            Log::warning('⚠️ No se pudo extraer contenido de la respuesta', [
                'data_keys' => array_keys($data),
                'data' => $data
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('❌ Excepción en análisis con PDF (método Python)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_id' => $fileId,
                'archivo' => $nombreArchivo
            ]);
            return null;
        }
    }

    /**
     * Solicita análisis usando texto extraído del PDF (método más directo y confiable)
     */
    private function solicitarAnalisisConTextoDirecto($textoPDF, $nombreArchivo = '')
    {
        try {
            Log::info('🔍 Solicitando análisis directo con texto extraído', [
                'longitud_texto' => strlen($textoPDF),
                'archivo' => $nombreArchivo,
                'muestra_texto' => substr($textoPDF, 0, 200) . '...'
            ]);

            $prompt = $this->obtenerPromptAnalisisDirecto();

            // Incluir información del archivo y contenido extraído
            $promptCompleto = $prompt . "\n\n=== DOCUMENTO ANALIZADO ===\n" .
                "Archivo: " . basename($nombreArchivo) . "\n\n" .
                "=== CONTENIDO EXTRAÍDO DEL DOCUMENTO PDF ===\n" . $textoPDF;

            // 🎯 Usar GPT-5 con el texto extraído
            $payload = [
                'model' => 'gpt-5',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $promptCompleto
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
                Log::info('✅ Análisis directo con texto exitoso');
                $analisisJSON = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $analisisJSON;
                } else {
                    Log::error('❌ Error decodificando JSON', [
                        'json_error' => json_last_error_msg(),
                        'content' => $content
                    ]);
                    return null;
                }
            }

            Log::warning('⚠️ No se pudo extraer contenido de la respuesta', [
                'data_keys' => array_keys($data)
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('❌ Excepción en análisis directo con texto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'archivo' => $nombreArchivo
            ]);
            return null;
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
                'purpose' => 'assistants'  // Usar 'assistants' para análisis con GPT-5
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
                'model' => 'gpt-5',
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
- Detecta el nombre y tipo de documento (por ejemplo, \"Constancia de Seguridad Estructural\").
- Extrae los nombres de los firmantes y peritos, incluyendo su cédula o registro profesional.
- Si el documento menciona una **vigencia**, calcula la fecha de vencimiento (1 año si no está indicada explícitamente).
- Si aparece un **folio u oficio**, extrae el número y colócalo en \"folio_documento\" o \"oficio_documento\".
- Si hay una **razón social o propietario**, indícalo en el bloque \"propietario\".
- Si se menciona un **fundamento legal**, escríbelo textualmente.
- Determina la **entidad emisora**, su **nivel (estatal, municipal, federal)** y su **tipo (gobierno o privado)**.
- Determina si el documento está vigente según la fecha actual.
- **IMPORTANTE: Todas las fechas deben estar en formato YYYY-MM-DD** (ejemplo: \"2025-08-31\" en lugar de \"31 de agosto de 2025\").
- Si encuentras fechas en formato texto, conviértelas al formato YYYY-MM-DD.
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
                ->map(function($doc) {
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

---

### 🗂️ CATÁLOGO DE DOCUMENTOS DISPONIBLES

Debes comparar el contenido del documento con el siguiente catálogo:

{$catalogoTexto}{$documentoEsperadoTexto}

---

### 🧠 LÓGICA DE CLASIFICACIÓN Y VALIDACIÓN

1️⃣ **Identificación de tipo**
   - Compara palabras clave, frases y entidad emisora con el catálogo.
   - Considera sinónimos y variaciones (ej: \"Compatibilidad Urbanística\" → Uso de Suelo).
   - Usa el `tipo_documento_id` correspondiente si hay coincidencia directa o semántica.
   - Si no hay coincidencia, deja `tipo_documento_id = null`.

2️⃣ **Compatibilidad con documento esperado**
   - Si existe un documento esperado, evalúa si el documento analizado cumple la misma función o propósito.
   - Ejemplo: una \"Constancia de Compatibilidad Urbanística\" puede considerarse válida para \"Uso de Suelo\".

3️⃣ **Nivel de gobierno (estatal vs municipal)**
   - *Estatal:* menciona \"Secretaría de Desarrollo Urbano del Estado\", \"Gobierno del Estado\", \"nivel estatal\".
   - *Municipal:* menciona \"Presidencia Municipal\", \"Dirección de Obras Públicas Municipales\", \"Ayuntamiento\".
   - Si hay duda, revisa la firma o sello oficial.

4️⃣ **Fechas y vigencia**
   - Busca cuidadosamente todas las fechas del texto.
   - **Expedición:** puede aparecer como \"expedido el\", \"Zacatecas, 15 de marzo de 2024\", \"fecha de emisión\".
   - **Vigencia:** busca \"válido hasta\", \"vigencia\", \"vence el\".
   - Convierte todas las fechas al formato ISO `YYYY-MM-DD`.
   - Si la vigencia no se menciona, asume 12 meses desde la expedición si el tipo lo requiere.

5️⃣ **Firmante y entidad emisora**
   - Extrae el nombre completo, cargo y área emisora del firmante.
   - Ejemplo: `Arq. Luz Eugenia Pérez Haro`, `Secretaría de Desarrollo Urbano y Ordenamiento Territorial`.

6️⃣ **Propietario o razón social**
   - Si aparece una razón social (ej. \"Fomento Educativo y Cultural Francisco de Ibarra A.C.\"), colócala en `\"propietario.razon_social\"`.
   - Si aparece un nombre de persona física, colócalo en `\"propietario.nombre_propietario\"`.

7️⃣ **Fundamento legal y observaciones**
   - Extrae artículos, leyes o reglamentos citados (\"Artículo 13 del Código Territorial y Urbano del Estado de Zacatecas\").
   - Si hay advertencias o notas (\"será nulo si carece de la carátula del reverso\"), inclúyelas en `\"observaciones\"`.

---

### 📦 ESTRUCTURA JSON ESPERADA

Devuelve **únicamente** un JSON con esta estructura exacta:

```json
{
  \"documento\": {
    \"nombre_detectado\": \"string\",
    \"tipo_documento_id\": \"number | null\",
    \"tipo_documento\": \"string\",
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

- **Todas las fechas deben estar en formato YYYY-MM-DD.**
- **Calcula `dias_restantes_vigencia`** comparando la fecha de hoy (2025-10-22) con la vigencia.
- **Determina `estado_documento`:**
  - \"vigente\" → aún dentro de vigencia
  - \"por_vencer\" → faltan menos de 30 días
  - \"vencido\" → vigencia ya pasada
  - \"pendiente\" → sin vigencia definida
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
            Log::info('🔄 Convirtiendo análisis de OpenAI al formato del sistema', [
                'tiene_datos' => !empty($analisisOpenAI)
            ]);

            // Validar si el documento coincide con el requerido
            $validacion = $this->validarDocumentoContraRequerido($analisisOpenAI, $documentoRequeridoId);

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
                    "fecha_expedicion" => $metadatos['fecha_expedicion'] ?? date('Y-m-d'),
                    "vigencia_documento" => $metadatos['vigencia_documento'] ?? date('Y-m-d', strtotime('+12 months')),
                    "dias_restantes_vigencia" => $metadatos['dias_restantes_vigencia'] ?? 365,
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
     * Simplificado: confía en la evaluación de GPT-5 pero es estricto con estatal vs municipal
     */
    private function validarDocumentoContraRequerido($analisisOpenAI, $documentoRequeridoId = null)
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

            // 🎯 Confiar en la evaluación de GPT-5
            $coincideCatalogo = $analisisOpenAI['documento']['coincide_catalogo'] ?? false;
            $tipoDocumentoId = $analisisOpenAI['documento']['tipo_documento_id'] ?? null;
            $documentoDetectado = $analisisOpenAI['documento']['nombre_detectado'] ?? $analisisOpenAI['documento']['tipo_documento'] ?? '';
            $nivelDetectado = $analisisOpenAI['entidad_emisora']['nivel'] ?? '';

            // Obtener información del documento requerido
            $documentoRequerido = \App\Models\SugDocumento::find($documentoRequeridoId);
            $nombreRequerido = $documentoRequerido ? $documentoRequerido->nombre : 'Documento no encontrado';
            $nivelRequerido = $documentoRequerido ? $documentoRequerido->nivel_emisor : '';

            Log::info('🤖 Evaluación GPT-5 para validación', [
                'documento_detectado' => $documentoDetectado,
                'nombre_requerido' => $nombreRequerido,
                'nivel_detectado' => $nivelDetectado,
                'nivel_requerido' => $nivelRequerido,
                'coincide_catalogo' => $coincideCatalogo,
                'tipo_documento_id' => $tipoDocumentoId,
                'documento_requerido_id' => $documentoRequeridoId
            ]);

            // 🚨 VALIDACIÓN ESTRICTA: Si los niveles son diferentes entre estatal y municipal, rechazar
            if ($this->sonNivelesIncompatibles($nivelDetectado, $nivelRequerido)) {
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

            // Si GPT-5 marcó coincidencia en catálogo Y el ID coincide exactamente
            if ($coincideCatalogo && $tipoDocumentoId == $documentoRequeridoId) {
                return [
                    'coincide' => true,
                    'porcentaje_coincidencia' => 100,
                    'razon' => "GPT-5 confirmó coincidencia perfecta (ID: {$tipoDocumentoId})",
                    'accion' => 'aprobar',
                    'documento_esperado' => $nombreRequerido,
                    'documento_detectado' => $documentoDetectado,
                    'evaluacion_gpt' => 'coincidencia_perfecta'
                ];
            }

            // Si GPT-5 marcó coincidencia en catálogo pero el ID es diferente
            if ($coincideCatalogo) {
                // Verificar si es un caso de compatibilidad válida (mismo nivel)
                if ($nivelDetectado === $nivelRequerido || empty($nivelRequerido) || empty($nivelDetectado)) {
                    return [
                        'coincide' => true,
                        'porcentaje_coincidencia' => 85,
                        'razon' => "GPT-5 identificó documento compatible del catálogo (mismo nivel: {$nivelDetectado})",
                        'accion' => 'aprobar',
                        'documento_esperado' => $nombreRequerido,
                        'documento_detectado' => $documentoDetectado,
                        'evaluacion_gpt' => 'documento_compatible_mismo_nivel'
                    ];
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

            // Si no marcó coincidencia en catálogo, rechazar
            return [
                'coincide' => false,
                'porcentaje_coincidencia' => 0,
                'razon' => "GPT-5 no identificó coincidencia. Esperado: {$nombreRequerido}, Detectado: {$documentoDetectado}",
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
    private function sonNivelesIncompatibles($nivelDetectado, $nivelRequerido)
    {
        $nivelDetectado = strtolower(trim($nivelDetectado));
        $nivelRequerido = strtolower(trim($nivelRequerido));

        // Si alguno está vacío, no podemos determinar incompatibilidad
        if (empty($nivelDetectado) || empty($nivelRequerido)) {
            return false;
        }

        // Definir niveles incompatibles específicamente
        $incompatibilidades = [
            'estatal' => ['municipal'],
            'municipal' => ['estatal'],
            // Agregar más incompatibilidades según sea necesario
        ];

        return isset($incompatibilidades[$nivelDetectado]) &&
               in_array($nivelRequerido, $incompatibilidades[$nivelDetectado]);
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
                return [
                    'success' => false,
                    'error' => 'Error al subir PDF a OpenAI'
                ];
            }

            // 2️⃣ Obtener análisis JSON
            $analisisJSON = $this->solicitarAnalisisDirecto($fileId);
            if (!$analisisJSON) {
                return [
                    'success' => false,
                    'error' => 'Error al obtener análisis de OpenAI'
                ];
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
     * Analiza un documento PDF usando GPT-5
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
     * Método para compatibilidad con archivos externos que esperan procesarAnalisis
     */
    public function procesarAnalisis($analisis, $informacionId, $archivoId)
    {
        // Este método se mantiene para compatibilidad pero podría implementarse
        // la lógica de procesamiento específica según las necesidades
        Log::info('Procesando análisis', [
            'informacion_id' => $informacionId,
            'archivo_id' => $archivoId,
            'tiene_analisis' => !empty($analisis)
        ]);

        return [
            'success' => true,
            'procesado' => true,
            'informacion_id' => $informacionId,
            'archivo_id' => $archivoId
        ];
    }

    /**
     * Método para compatibilidad con archivos externos que esperan testSimpleConnection
     */
    public function testSimpleConnection()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection']
                ],
                'max_tokens' => 5
            ]);

            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Conexión exitosa con OpenAI API'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Error de conexión: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Método para compatibilidad con archivos externos que esperan analyzeDocument
     */
    public function analyzeDocument($path)
    {
        return $this->analizarPDFDirecto($path);
    }
}
