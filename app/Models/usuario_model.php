<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class usuario_model extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID_Usuario';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'Usuario',
        'email',
        'passencrip',
        'ID_Empleado',
        'Password_mail',
        'Firma',
        'Activo',
        'ID_Campus',
        'ID_Rol',
        'FraseSecreta',
        'FechaCambioPass',
        'TextoPlano',
        'Generada',
        'id_rol_medicina',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'passencrip',
        'Password_mail',
        'FraseSecreta',
        'TextoPlano',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ID_Usuario' => 'string',
            'ID_Empleado' => 'string',
            'ID_Campus' => 'string',
            'ID_Rol' => 'string',
            'email_verified_at' => 'datetime',
            'FechaCambioPass' => 'datetime',
            'Activo' => 'boolean',
            'Generada' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            // No casteamos passencrip como 'hashed' porque manejamos el hash manualmente
        ];
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'Usuario';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        // Si el campo viene como binary de SQL Server, convertirlo a hex string
        $password = $this->passencrip;

        // Si ya es un string que empieza con 0x, devolverlo tal como está
        if (is_string($password) && (str_starts_with($password, '0x') || str_starts_with($password, '0X'))) {
            return $password;
        }

        // Si es binary, convertir a hex con prefijo 0x
        if (is_string($password) && !ctype_print($password)) {
            return '0x' . strtoupper(bin2hex($password));
        }

        // Si es hex sin prefijo, agregar el prefijo
        if (is_string($password) && ctype_xdigit($password) && strlen($password) > 32) {
            return '0x' . strtoupper($password);
        }

        return $password;
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('Activo', true);
    }

    /**
     * Get the user's name for display purposes.
     */
    public function getNameAttribute()
    {
        return $this->Usuario;
    }

    /**
     * Relación con el empleado
     */
    public function empleado()
    {
        return $this->belongsTo(empleado_model::class, 'ID_Empleado');
    }

    /**
     * Relación con el campus
     */
    public function campus()
    {
        return $this->belongsTo(campus_model::class, 'ID_Campus');
    }

    /**
     * Relación muchos a muchos con roles a través de UsuariosRol
     */
    public function roles()
    {
        return $this->belongsToMany(rol_model::class, 'UsuariosRol', 'ID_Usuario', 'ID_Rol');
    }

    /**
     * Relación para obtener el primer rol (rol principal)
     */
    public function rolPrincipal()
    {
        return $this->roles()->first();
    }

    /**
     * Obtener descripción del rol principal
     */
    public function getRolPrincipalDescripcionAttribute()
    {
        $rol = $this->rolPrincipal();
        return $rol ? $rol->Descripcion : null;
    }

    /**
     * Obtener el nombre completo del usuario desde la tabla personas
     */
    public function getNombreCompletoAttribute()
    {
        if ($this->empleado && $this->empleado->persona) {
            $persona = $this->empleado->persona;
            return trim($persona->Nombre . ' ' . $persona->Paterno . ' ' . $persona->Materno);
        }
        return $this->Usuario;
    }

    /**
     * Obtener la fotografía del empleado
     */
    public function getFotografiaAttribute()
    {
        if ($this->empleado && $this->empleado->ID_Fotografia) {
            // Convertir el VARBINARY a base64 para mostrar en el frontend
            return 'data:image/jpeg;base64,' . base64_encode($this->empleado->ID_Fotografia);
        }
        return null;
    }
}
