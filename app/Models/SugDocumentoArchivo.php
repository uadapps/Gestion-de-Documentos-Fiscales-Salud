<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SugDocumentoArchivo extends Model
{
    protected $table = 'sug_documentos_archivos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'documento_informacion_id',
        'version',
        'es_actual',
        'archivo_pdf',
        'mime_type',
        'file_size_bytes',
        'file_hash_sha256',
        'observaciones',
        'subido_por',
        'subido_en'
    ];

    protected $casts = [
        'es_actual' => 'boolean',
        'subido_en' => 'datetime',
    ];

    /**
     * Relación: un archivo pertenece a una información de documento
     */
    public function documentoInformacion()
    {
        return $this->belongsTo(SugDocumentoInformacion::class, 'documento_informacion_id');
    }
}
