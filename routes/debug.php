<?php

use Illuminate\Support\Facades\Route;

Route::get('/debug/php-config', function () {
    return response()->json([
        'php_version' => PHP_VERSION,
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
        'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: 'System default',
        'max_input_time' => ini_get('max_input_time'),
        'max_input_vars' => ini_get('max_input_vars'),
        'request_size_limit' => [
            'upload_max_filesize_bytes' => (int) ini_get('upload_max_filesize') * 1024 * 1024,
            'post_max_size_bytes' => (int) ini_get('post_max_size') * 1024 * 1024,
        ]
    ]);
});

Route::post('/debug/test-upload', function (Illuminate\Http\Request $request) {
    return response()->json([
        'has_file' => $request->hasFile('archivo'),
        'all_files' => $request->allFiles(),
        'request_size' => $_SERVER['CONTENT_LENGTH'] ?? 'unknown',
        'input_data' => $request->all(),
        'php_errors' => error_get_last(),
        'upload_errors' => $request->hasFile('archivo') ? [
            'error_code' => $request->file('archivo')->getError(),
            'error_message' => $request->file('archivo')->getErrorMessage(),
        ] : 'No file',
    ]);
});
