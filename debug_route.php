<?php
// Ruta temporal para debug
Route::get('debug-documentos', function() {
    $documentos = \App\Models\SugDocumento::all(['id', 'nombre']);
    return response()->json([
        'documentos_en_bd' => $documentos,
        'total' => $documentos->count()
    ]);
})->name('debug-documentos');
