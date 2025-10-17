<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class usuarios_rol_model extends Model
{
    protected $table = 'UsuariosRol';

    protected $primaryKey = 'ID_Movimiento';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ID_Usuario',
        'ID_Rol',
    ];

    protected $casts = [
        'ID_Movimiento' => 'string',
        'ID_Usuario' => 'string',
        'ID_Rol' => 'string',
    ];

    /**
     * Relación con el usuario
     */
    public function usuario()
    {
        return $this->belongsTo(usuario_model::class, 'ID_Usuario');
    }

    /**
     * Relación con el rol
     */
    public function rol()
    {
        return $this->belongsTo(rol_model::class, 'ID_Rol');
    }
}
