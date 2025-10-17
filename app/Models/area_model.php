<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class area_model extends Model
{
    protected $table = 'areas';

    protected $primaryKey = 'ID_Area';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'Descripcion',
        'ID_TipoGasto',
    ];

    protected $casts = [
        'ID_Area' => 'string',
        'ID_TipoGasto' => 'string',
    ];

    /**
     * Relación: un área tiene muchos empleados
     */
    public function empleados()
    {
        return $this->hasMany(empleado_model::class, 'ID_Area');
    }
}
