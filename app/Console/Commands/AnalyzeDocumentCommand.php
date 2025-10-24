<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeDocumentCommand extends Command
{
    protected $signature = 'doc:analyze {path : Ruta al archivo (absoluta o relativa a storage/app/public)}';
    protected $description = 'Analiza un documento (PDF/DOCX) usando DocumentAnalyzerService y muestra el JSON resultante';

    public function handle()
    {
        $path = $this->argument('path');

        $this->info('Iniciando análisis de: ' . $path);

        try {
            $svc = app()->make('App\\Services\\DocumentAnalyzerService');
            $result = $svc->analyzeDocument($path);

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
