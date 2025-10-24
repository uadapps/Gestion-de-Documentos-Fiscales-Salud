<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::match(['get', 'post'], '/test-analizador', function (Request $request) {
    echo "<h1>Test rápido de análisis de documento</h1>";

    // Formulario simple para subir un PDF
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    echo '<label>Subir PDF: <input type="file" name="documento" accept=".pdf,.docx"></label> ';
    echo '<button type="submit">Analizar archivo subido</button>';
    echo '</form>';

    echo '<p>O usar el documento de ejemplo en <code>storage/app/public/documentos/01/a.pdf</code></p>';
    echo '<form method="post">';
    echo '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    echo '<input type="hidden" name="use_example" value="1">';
    echo '<button type="submit">Analizar documento de ejemplo</button>';
    echo '</form>';

    try {
        $analyzer = app()->make('App\\Services\\DocumentAnalyzerService');

        $rutaArchivo = null;

        if ($request->isMethod('post') && $request->hasFile('documento')) {
            $file = $request->file('documento');
            $stored = $file->store('temp_uploads', 'public');
            $rutaArchivo = $stored; // analyzeDocument manejará rutas relativas dentro de storage
            echo "<p>Archivo subido a: <code>{$stored}</code></p>";
        } elseif ($request->isMethod('post') && $request->input('use_example')) {
            $rutaArchivo = 'documentos/01/a.pdf';
            echo "<p>Usando documento de ejemplo: <code>{$rutaArchivo}</code></p>";
        } else {
            echo '<p>Sube un archivo o presiona "Analizar documento de ejemplo" para probar.</p>';
            return;
        }

        $resultado = $analyzer->analyzeDocument($rutaArchivo);

        if (!empty($resultado['success']) && $resultado['success']) {
            echo "<h2 style='color:green'>✅ Análisis completado correctamente</h2>";
            echo "<pre style='background:#f4f4f4;border:1px solid #ccc;padding:10px;white-space:pre-wrap'>";
            echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</pre>";
        } else {
            echo "<h2 style='color:red'>❌ Error en el análisis</h2>";
            echo "<pre>";
            print_r($resultado);
            echo "</pre>";
        }

    } catch (\Throwable $e) {
        echo "<h2 style='color:red'> EXCEPCIÓN</h2>";
        echo "<p><strong>Mensaje:</strong> {$e->getMessage()}</p>";
        echo "<p><strong>Archivo:</strong> {$e->getFile()}</p>";
        echo "<p><strong>Línea:</strong> {$e->getLine()}</p>";
        echo "<pre>{$e->getTraceAsString()}</pre>";
    }
});
