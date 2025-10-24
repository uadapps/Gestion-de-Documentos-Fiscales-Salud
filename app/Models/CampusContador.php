<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampusContador extends Model
{
    protected $table = 'Campus_Contadores';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'ID_Empleado',
        'ID_Campus',
    ];

    /**
     * Relación: un Campus_Contador pertenece a un empleado
     */
    public function empleado()
    {
        return $this->belongsTo(empleado_model::class, 'ID_Empleado', 'ID_Empleado');
    }

    /**
     * Relación: un Campus_Contador pertenece a un campus
     */
    public function campus()
    {
        return $this->belongsTo(campus_model::class, 'ID_Campus', 'ID_Campus');
    }
}
