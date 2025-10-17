<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class empleado_model extends Model
{
    protected $table = 'Empleados';

    protected $primaryKey = 'ID_Empleado';

    public $timestamps = false;

    protected $fillable = [
        'ID_Persona',
        'Area',
        'ID_Campus',
    ];

    protected $casts = [
        'ID_Empleado' => 'integer',
        'ID_Persona' => 'integer',
    ];

    public function persona()
    {
        return $this->belongsTo(persona_model::class, 'ID_Persona');
    }

    public function campus()
    {
        return $this->hasOne(campus_model::class, 'ID_Campus');
    }

    public function usuario()
    {
        return $this->hasOne(usuario_model::class, 'ID_Empleado', 'ID_Empleado');
    }
}
