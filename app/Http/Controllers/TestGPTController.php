<?php

namespace App\Http\Controllers;

use App\Services\DocumentAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestGPTController extends Controller
{
    public function testConnection()
    {
        try {
            $analyzer = new DocumentAnalyzerService();

            // Hacer una petición simple de prueba
            $testResult = $analyzer->testSimpleConnection();

            return response()->json([
                'success' => $testResult['status'] === 'success',
                'message' => $testResult['message'],
                'test_result' => $testResult,
                'api_key_configured' => !empty(config('openai.api_key')),
                'api_key_partial' => substr(config('openai.api_key'), 0, 10) . '...',
                'config' => [
                    'base_url' => config('openai.base_url'),
                    'model' => config('openai.model'),
                    'timeout' => config('openai.timeout')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en prueba de conexión GPT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'api_key_configured' => !empty(config('openai.api_key')),
                'config_check' => [
                    'api_key' => !empty(config('openai.api_key')) ? 'Configurada' : 'No configurada',
                    'base_url' => config('openai.base_url'),
                    'model' => config('openai.model'),
                    'timeout' => config('openai.timeout')
                ]
            ], 500);
        }
    }
}
