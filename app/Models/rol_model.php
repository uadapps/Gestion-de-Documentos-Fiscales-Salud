<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class rol_model extends Model
{
    protected $table = 'Roles';

    protected $primaryKey = 'ID_Rol';

    public $timestamps = false;

    protected $fillable = [
        'descripcion',
    ];

    protected $casts = [
        'ID_Rol' => 'integer',
    ];

    /**
     * Relación: un rol tiene muchos usuarios
     */
    public function usuarios()
    {
        return $this->hasMany(usuario_model::class, 'ID_Rol');
    }
}
