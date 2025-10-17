<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class persona_model extends Model
{
    protected $table = 'Personas';

    protected $primaryKey = 'Id_Persona';

    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'Paterno',
        'Materno',
    ];

    protected $casts = [
        'Id_Persona' => 'integer',
    ];

    /**
     * Relación: una persona puede ser un empleado (hasOne)
     */
    public function empleado()
    {
        return $this->hasOne(empleado_model::class, 'ID_Persona', 'Id_Persona');
    }
}
