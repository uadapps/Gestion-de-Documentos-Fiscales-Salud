<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class empleado_model extends Model
{
    protected $table = 'Empleados';

    protected $primaryKey = 'ID_Empleado';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ID_Persona',
        'ID_Area',
        'ID_Campus',
        'ID_Fotografia',
    ];

    protected $casts = [
        'ID_Empleado' => 'string',
        'ID_Persona' => 'string',
        'ID_Area' => 'string',
        'ID_Campus' => 'string',
    ];

    public function persona()
    {
        return $this->belongsTo(persona_model::class, 'ID_Persona', 'Id_Persona');
    }

    public function area()
    {
        return $this->belongsTo(area_model::class, 'ID_Area');
    }

    public function campus()
    {
        return $this->belongsTo(campus_model::class, 'ID_Campus');
    }

    public function usuario()
    {
        return $this->hasOne(usuario_model::class, 'ID_Empleado', 'ID_Empleado');
    }
}
