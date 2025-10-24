<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PDFProcessorService
{
    /**
     * Divide un PDF en páginas individuales
     */
    public function dividirPDFEnPaginas($rutaArchivoPDF, $carpetaDestino = null)
    {
        try {
            Log::info('Iniciando división de PDF', [
                'archivo' => $rutaArchivoPDF
            ]);

            if (!$carpetaDestino) {
                $carpetaDestino = 'temp_pdf_pages/' . time();
            }

            // Crear carpeta destino
            Storage::disk('public')->makeDirectory($carpetaDestino);
            $rutaCompleta = storage_path('app/public/' . $carpetaDestino);

            // Obtener información del PDF
            $infoPDF = $this->obtenerInformacionPDF($rutaArchivoPDF);
            $numPaginas = $infoPDF['pages'] ?? 1;

            Log::info('PDF info obtenida', [
                'paginas' => $numPaginas
            ]);

            $paginasDivididas = [];

            // Si tenemos Imagick disponible, usar eso
            if (extension_loaded('imagick')) {
                $paginasDivididas = $this->dividirConImagick($rutaArchivoPDF, $rutaCompleta, $numPaginas);
            }
            // Si tenemos Ghostscript, usar eso
            elseif ($this->gsDisponible()) {
                $paginasDivididas = $this->dividirConGhostscript($rutaArchivoPDF, $rutaCompleta, $numPaginas);
            }
            // Si no hay herramientas, intentar dividir manualmente
            else {
                Log::warning('No hay herramientas de PDF disponibles, procesando archivo completo');
                return [
                    'success' => true,
                    'paginas' => [
                        [
                            'numero' => 1,
                            'archivo' => $rutaArchivoPDF,
                            'tipo' => 'completo'
                        ]
                    ],
                    'total_paginas' => 1
                ];
            }

            return [
                'success' => true,
                'paginas' => $paginasDivididas,
                'total_paginas' => count($paginasDivididas),
                'carpeta_temp' => $carpetaDestino
            ];

        } catch (\Exception $e) {
            Log::error('Error dividiendo PDF', [
                'error' => $e->getMessage(),
                'archivo' => $rutaArchivoPDF
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Dividir PDF usando Imagick
     */
    private function dividirConImagick($rutaArchivoPDF, $rutaDestino, $numPaginas)
    {
        $paginas = [];

        try {
            $imagick = new \Imagick();
            $imagick->readImage($rutaArchivoPDF);

            for ($i = 0; $i < $imagick->getNumberImages(); $i++) {
                $imagick->setIteratorIndex($i);
                $nombrePagina = "pagina_" . ($i + 1) . ".jpg";
                $rutaPagina = $rutaDestino . '/' . $nombrePagina;

                // Convertir a imagen para mejor procesamiento por IA
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                $imagick->writeImage($rutaPagina);

                $paginas[] = [
                    'numero' => $i + 1,
                    'archivo' => $rutaPagina,
                    'tipo' => 'imagen',
                    'relativo' => 'temp_pdf_pages/' . basename(dirname($rutaPagina)) . '/' . $nombrePagina
                ];
            }

            $imagick->clear();
            $imagick->destroy();

        } catch (\Exception $e) {
            Log::error('Error con Imagick', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $paginas;
    }

    /**
     * Dividir PDF usando Ghostscript
     */
    private function dividirConGhostscript($rutaArchivoPDF, $rutaDestino, $numPaginas)
    {
        $paginas = [];

        for ($i = 1; $i <= $numPaginas; $i++) {
            $nombrePagina = "pagina_{$i}.pdf";
            $rutaPagina = $rutaDestino . '/' . $nombrePagina;

            // Comando Ghostscript para extraer una página específica
            $comando = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER " .
                      "-dFirstPage={$i} -dLastPage={$i} " .
                      "-sOutputFile=\"{$rutaPagina}\" \"{$rutaArchivoPDF}\"";

            exec($comando, $output, $returnCode);

            if ($returnCode === 0 && file_exists($rutaPagina)) {
                $paginas[] = [
                    'numero' => $i,
                    'archivo' => $rutaPagina,
                    'tipo' => 'pdf',
                    'relativo' => 'temp_pdf_pages/' . basename(dirname($rutaPagina)) . '/' . $nombrePagina
                ];
            }
        }

        return $paginas;
    }

    /**
     * Obtener información básica del PDF
     */
    private function obtenerInformacionPDF($rutaArchivoPDF)
    {
        $info = ['pages' => 1];

        try {
            // Intentar con Imagick primero
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                $imagick->readImage($rutaArchivoPDF);
                $info['pages'] = $imagick->getNumberImages();
                $imagick->clear();
                $imagick->destroy();
                return $info;
            }

            // Intentar estimar páginas leyendo el archivo
            $contenido = file_get_contents($rutaArchivoPDF);
            if ($contenido) {
                // Buscar patrones que indiquen número de páginas
                preg_match_all('/\/Page\W/', $contenido, $matches);
                if (count($matches[0]) > 0) {
                    $info['pages'] = count($matches[0]);
                }
            }

        } catch (\Exception $e) {
            Log::warning('No se pudo obtener info del PDF', ['error' => $e->getMessage()]);
        }

        return $info;
    }

    /**
     * Verificar si Ghostscript está disponible
     */
    private function gsDisponible()
    {
        $output = null;
        $returnCode = null;
        exec('gs --version', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Limpiar archivos temporales
     */
    public function limpiarArchivosTemporales($carpetaTemp)
    {
        try {
            Storage::disk('public')->deleteDirectory($carpetaTemp);
            Log::info('Archivos temporales limpiados', ['carpeta' => $carpetaTemp]);
        } catch (\Exception $e) {
            Log::warning('Error limpiando temporales', ['error' => $e->getMessage()]);
        }
    }
}
