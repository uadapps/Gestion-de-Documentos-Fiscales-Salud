<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ActualizarDocumentosCaducados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documentos:actualizar-caducados {--dry-run : Solo mostrar qué documentos se actualizarían sin hacer cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el estatus de documentos vigentes que han caducado por fecha de vencimiento';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🕐 Iniciando proceso de actualización de documentos caducados...');

        $dryRun = $this->option('dry-run');
        $fechaActual = Carbon::now();

        if ($dryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales');
        }

        try {
            // Buscar documentos vigentes que ya han caducado
            $documentosCaducados = DB::table('sug_documentos_informacion')
                ->where('estado', 'vigente')
                ->where('fecha_vigencia', '<', $fechaActual)
                ->whereNotNull('fecha_vigencia')
                ->get();

            if ($documentosCaducados->isEmpty()) {
                $this->info('✅ No se encontraron documentos caducados');
                return 0;
            }

            $this->info("📄 Se encontraron {$documentosCaducados->count()} documentos caducados:");

            // Mostrar tabla de documentos que se van a actualizar
            $tableData = [];
            foreach ($documentosCaducados as $doc) {
                $tableData[] = [
                    'ID' => $doc->id,
                    'Campus' => $doc->campus_id,
                    'Tipo' => $doc->tipo_documento_id,
                    'Fecha Vigencia' => $doc->fecha_vigencia,
                    'Días Caducado' => $fechaActual->diffInDays($doc->fecha_vigencia)
                ];
            }

            $this->table([
                'ID Documento',
                'Campus ID',
                'Tipo Documento',
                'Fecha Vigencia',
                'Días Caducado'
            ], $tableData);

            if (!$dryRun) {
                if ($this->confirm('¿Continuar con la actualización?')) {
                    // Actualizar documentos a estado 'caducado'
                    $actualizados = DB::table('sug_documentos_informacion')
                        ->where('estado', 'vigente')
                        ->where('fecha_vigencia', '<', $fechaActual)
                        ->whereNotNull('fecha_vigencia')
                        ->update([
                            'estado' => 'caducado',
                            'fecha_actualizacion' => $fechaActual
                        ]);

                    $this->info("✅ Se actualizaron {$actualizados} documentos a estado 'caducado'");

                    // Log del proceso
                    Log::info('Proceso de actualización de documentos caducados completado', [
                        'documentos_actualizados' => $actualizados,
                        'fecha_proceso' => $fechaActual,
                        'ejecutado_por' => 'comando_artisan'
                    ]);
                } else {
                    $this->info('❌ Proceso cancelado por el usuario');
                }
            } else {
                $this->info('📝 En modo real se actualizarían estos documentos a estado "caducado"');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error en el proceso: ' . $e->getMessage());
            Log::error('Error en comando actualizar-caducados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
