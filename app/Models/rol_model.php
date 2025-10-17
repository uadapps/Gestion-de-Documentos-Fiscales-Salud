<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class rol_model extends Model
{
    protected $table = 'Roles';

    protected $primaryKey = 'ID_Rol';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'Descripcion',
    ];

    protected $casts = [
        'ID_Rol' => 'string',
    ];

    /**
     * Relación muchos a muchos con usuarios a través de UsuariosRol
     */
    public function usuarios()
    {
        return $this->belongsToMany(usuario_model::class, 'UsuariosRol', 'ID_Rol', 'ID_Usuario');
    }
}
