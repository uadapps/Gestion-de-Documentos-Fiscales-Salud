<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SugDocumento extends Model
{
    protected $table = 'sug_documentos';
    
    protected $primaryKey = 'id';
    
    public $timestamps = false;
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo_documento_id',
        'activo',
        'requiere_vigencia',
        'vigencia_meses',
        'aplica_area_salud',
        'entidad_emisora',
        'nivel_emisor'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'requiere_vigencia' => 'boolean',
        'aplica_area_salud' => 'boolean',
        'vigencia_meses' => 'integer',
    ];

    /**
     * Relación: un documento tiene muchas informaciones
     */
    public function informaciones()
    {
        return $this->hasMany(SugDocumentoInformacion::class, 'documento_id');
    }

    /**
     * Relación: un documento pertenece a un tipo de documento
     */
    public function tipoDocumento()
    {
        return $this->belongsTo(SugTipoDocumento::class, 'tipo_documento_id');
    }
}