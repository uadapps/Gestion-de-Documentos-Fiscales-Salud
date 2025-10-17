<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class usuario_model extends Authenticatable
{
	use HasFactory, Notifiable;

	protected $table = 'Usuarios';

	protected $primaryKey = 'ID_Usuario';

	public $timestamps = false;

	protected $fillable = [
		'Usuario',
		'Password',
		'ID_Empleado',
		'email',
		'Activo',
		'ID_Campus',
		'FraseSecreta',
	];

	/**
	 * Ocultar campos en arrays/JSON
	 */
	protected $hidden = [
		'FraseSecreta',
		'Password',
	];


	protected $casts = [
		'ID_Usuario' => 'integer',
		'Activo' => 'boolean',
		'Password' => 'hashed',
	];

	/**
	 * Relación: usuario pertenece a un empleado
	 */
	public function empleado()
	{
		return $this->belongsTo(empleado_model::class, 'ID_Empleado');
	}

	/**
	 * Relación: usuario pertenece a un rol
	 */
	public function rol()
	{
		return $this->belongsTo(rol_model::class, 'ID_Rol');
	}

}
