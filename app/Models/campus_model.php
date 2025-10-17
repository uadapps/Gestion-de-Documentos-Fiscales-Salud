<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class campus_model extends Model
{

    protected $table = 'Campus';

    protected $primaryKey = 'ID_Campus';

    protected $keyType = 'string';
    
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'Campus',
        'Activo',
    ];

    protected $casts = [
        'Activo' => 'boolean',
    ];

    /**
     * Relación: un campus tiene muchos empleados
     */
    public function empleados()
    {
        return $this->hasMany(empleado_model::class, 'ID_Campus');
    }

    /**
     * Relación: un campus tiene muchos usuarios
     */
    public function usuarios()
    {
        return $this->hasMany(usuario_model::class, 'ID_Campus');
    }
}
