<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SugObservacion extends Model
{
    protected $table = 'sug_observaciones';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'campus_id',
        'documento_informacion_id',
        'tipo_observacion',
        'observacion',
        'estatus',
        'creado_por',
        'creado_en',
        'actualizado_por',
        'actualizado_en',
        'activo'
    ];

    protected $casts = [
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'activo' => 'boolean'
    ];

    /**
     * Relación: una observación pertenece a un documento
     */
    public function documentoInformacion()
    {
        return $this->belongsTo(SugDocumentoInformacion::class, 'documento_informacion_id');
    }

    /**
     * Relación: una observación pertenece a un campus
     */
    public function campus()
    {
        return $this->belongsTo(campus_model::class, 'campus_id', 'ID_Campus');
    }

    /**
     * Scope: solo observaciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: observaciones de documentos específicos
     */
    public function scopeDeDocumento($query, $documentoInformacionId)
    {
        return $query->where('documento_informacion_id', $documentoInformacionId);
    }

    /**
     * Scope: observaciones generales del campus (sin documento)
     */
    public function scopeGeneralesCampus($query, $campusId)
    {
        return $query->where('campus_id', $campusId)
                    ->whereNull('documento_informacion_id');
    }

    /**
     * Scope: por estatus
     */
    public function scopePorEstatus($query, $estatus)
    {
        return $query->where('estatus', $estatus);
    }
}
