<?php
// Ruta temporal para verificar configuración PHP
Route::get('debug-php-config', function() {
    return response()->json([
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_time' => ini_get('max_input_time'),
        'memory_limit' => ini_get('memory_limit'),
        'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
        'max_file_uploads' => ini_get('max_file_uploads'),
    ]);
})->name('debug-php-config');
