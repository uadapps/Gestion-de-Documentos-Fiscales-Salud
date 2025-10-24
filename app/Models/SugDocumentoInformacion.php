<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SugDocumentoInformacion extends Model
{
    protected $table = 'sug_documentos_informacion';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'documento_id',
        'campus_id',
        'carrera_id',
        'nombre_documento',
        'folio_documento',
        'fecha_expedicion',
        'lugar_expedicion',
        'vigencia_documento',
        'estado',
        'observaciones',
        'metadata_json',
        'archivo_actual_id',
        'creado_en',
        'actualizado_en',
        'empleado_captura_id',
        'empleado_actualiza_id'
    ];

    protected $casts = [
        'fecha_expedicion' => 'date',
        'vigencia_documento' => 'date',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    /**
     * Relación: una información pertenece a un documento
     */
    public function documento()
    {
        return $this->belongsTo(SugDocumento::class, 'documento_id');
    }

    /**
     * Relación: una información tiene muchos archivos
     */
    public function archivos()
    {
        return $this->hasMany(SugDocumentoArchivo::class, 'documento_informacion_id');
    }

    /**
     * Relación: una información tiene un archivo actual
     */
    public function archivoActual()
    {
        return $this->belongsTo(SugDocumentoArchivo::class, 'archivo_actual_id');
    }

    /**
     * Relación: una información pertenece a un campus
     */
    public function campus()
    {
        return $this->belongsTo(campus_model::class, 'campus_id', 'ID_Campus');
    }
}
