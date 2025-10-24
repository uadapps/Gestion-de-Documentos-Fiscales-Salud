<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SugTipoDocumento extends Model
{
    protected $table = 'sug_tipo_documento';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Relación: un tipo de documento tiene muchos documentos
     */
    public function documentos()
    {
        return $this->hasMany(SugDocumento::class, 'tipo_documento_id');
    }
}
