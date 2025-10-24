<?php

namespace App\Console\Commands;

use App\Services\DocumentAnalyzerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestDocumentAnalyzer extends Command
{
    protected $signature = 'document:test-analyzer {file_path} {campus_id} {empleado_id}';

    protected $description = 'Prueba el analizador de documentos con un archivo específico';

    public function handle()
    {
        $filePath = $this->argument('file_path');
        $campusId = $this->argument('campus_id');
        $empleadoId = $this->argument('empleado_id');

        $this->info("Analizando documento: {$filePath}");
        $this->info("Campus ID: {$campusId}");
        $this->info("Empleado ID: {$empleadoId}");

        if (!Storage::exists($filePath)) {
            $this->error("El archivo {$filePath} no existe en el storage.");
            return 1;
        }

        $analyzer = new DocumentAnalyzerService();

        $this->info("Iniciando análisis...");
        $resultado = $analyzer->analizarDocumento($filePath, $campusId, $empleadoId);

        if ($resultado['success']) {
            $this->info("✅ Análisis exitoso!");
            $this->line("📄 Resultado del análisis:");
            $this->line(json_encode($resultado['analisis'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error("❌ Error en el análisis:");
            $this->line($resultado['error']);
        }

        return 0;
    }
}
