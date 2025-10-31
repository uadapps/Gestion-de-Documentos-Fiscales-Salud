<?php

namespace App\Providers;

use Exception;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
        $this->configureResponses();
    }

    /**
     * Configure custom authentication logic.
     */
    private function configureAuthentication(): void
    {


        // Configurar el campo de username personalizado
        Fortify::authenticateUsing(function (Request $request) {

            // Buscar usuario base
            $user = \App\Models\usuario_model::where('Usuario', $request->Usuario)
                ->where('Activo', true)
                ->first();

            if (!$user) {

                return null;
            }



            // Verificar que tenga frase secreta
            if (empty($user->FraseSecreta)) {

                return null;
            }

            // Obtener contraseña desencriptada usando Eloquent
            $userWithDecryptedPassword = \App\Models\usuario_model::select([
                '*',
                DB::raw("CAST(DECRYPTBYPASSPHRASE(FraseSecreta, passencrip, 1, Usuario) AS NVARCHAR) as password_decrypted")
            ])
            ->where('Usuario', $request->Usuario)
            ->where('Activo', true)
            ->first();

            if (!$userWithDecryptedPassword) {

                return null;
            }



            // Comparar contraseñas
            $decryptedPassword = trim($userWithDecryptedPassword->password_decrypted ?? '');
            $inputPassword = trim($request->password);

            if (!empty($decryptedPassword) && $decryptedPassword === $inputPassword) {

                // VALIDAR ROLES ANTES DE AUTENTICAR
                $user->load('roles');
                $rolesAutorizados = config('roles.authorized_roles');
                $rolesUsuario = $user->roles->pluck('ID_Rol')->toArray();
                $tieneRolAutorizado = $user->roles->pluck('ID_Rol')->intersect($rolesAutorizados)->isNotEmpty();



                if (!$tieneRolAutorizado) {

                    // Retornar null para que falle como credenciales incorrectas
                    return null;
                }

                //  VALIDACIONES ESPECIALES DE USUARIOS Y ROLES
                $usuarioNormalizado = strtolower(trim($user->Usuario));

                // VALIDACIÓN ESPECIAL: Si tiene rol 16 (Supervisor), debe ser usuario "rector"
                $tieneRol16 = in_array('16', $rolesUsuario);
                if ($tieneRol16) {
                    if ($usuarioNormalizado !== 'rector') {
                        // Retornar null para que falle como credenciales incorrectas
                        return null;
                    }
                }

                // VALIDACIÓN ESPECIAL: Si tiene rol 20, debe ser usuario "planeacion_desarrollo"
                $tieneRol20 = in_array('20', $rolesUsuario);
                if ($tieneRol20) {
                    if ($usuarioNormalizado !== 'planeacion_desarrollo') {
                        // Retornar null para que falle como credenciales incorrectas
                        return null;
                    }
                }


                // Retornar el usuario original (sin el campo password_decrypted)
                return $user;
            } else {

            }

            return null;
        });
    }

    /**
     * Verify password against different hash types
     */
    private function verifyPassword($password, $hashedPassword)
    {
        // Si el hash está vacío o es null
        if (empty($hashedPassword)) {
            return false;
        }


        // Convertir binary a hex si es necesario
        if (!mb_check_encoding($hashedPassword, 'UTF-8')) {
            $hexHash = strtoupper(bin2hex($hashedPassword));
        } else {
            $hexHash = strtoupper($hashedPassword);
        }



        // Verificar si es hash de SQL Server (empieza con 02000000)
        if (str_starts_with($hexHash, '02000000') && strlen($hexHash) >= 48) {
            return $this->verifySqlServerPassword($password, $hexHash);
        }

        // Si es un hash de Laravel/Bcrypt
        if (str_starts_with($hashedPassword, '$2y$') || str_starts_with($hashedPassword, '$2a$') || str_starts_with($hashedPassword, '$2b$')) {
            return Hash::check($password, $hashedPassword);
        }

        // Verificación MD5 simple (legacy)
        if (strlen($hexHash) === 32 && ctype_xdigit($hexHash)) {
            $md5Hash = strtoupper(md5($password));

            return $hexHash === $md5Hash;
        }

        Log::info('Password verification failed: unknown hash format');
        return false;
    }

    /**
     * Verificar contraseña contra hash PBKDF2 de SQL Server
     */
    private function verifySqlServerPassword($password, $hexHash)
    {


        try {
            // Estructura del hash SQL Server:
            // 4 bytes: version (02000000)
            // 4 bytes: iteration count (little-endian)
            // 16 bytes: salt
            // resto: hash derivado

            $version = substr($hexHash, 0, 8); // 02000000
            $iterationHex = substr($hexHash, 8, 8);
            $saltHex = substr($hexHash, 16, 32); // 16 bytes = 32 hex chars
            $storedHashHex = substr($hexHash, 48);

            // Convertir iteration count de little-endian
            $iterationBytes = array_reverse(str_split($iterationHex, 2));
            $iterations = hexdec(implode('', $iterationBytes));

            Log::info('PBKDF2 components:', [
                'version' => $version,
                'iterations' => $iterations,
                'salt_length' => strlen($saltHex),
                'hash_length' => strlen($storedHashHex)
            ]);

            // Validar que las iteraciones sean razonables
            if ($iterations <= 0 || $iterations > 100000) {
                Log::info('Invalid iteration count: ' . $iterations);
                return false;
            }

            $saltBinary = hex2bin($saltHex);
            $storedHashBinary = hex2bin($storedHashHex);

            // Probar diferentes algoritmos de hash
            $algorithms = ['sha1', 'sha256', 'sha512'];

            foreach ($algorithms as $algo) {
                // Calcular hash PBKDF2 con el algoritmo actual
                $computedHash = hash_pbkdf2($algo, $password, $saltBinary, $iterations, strlen($storedHashBinary), true);

                Log::info("Testing PBKDF2 $algo:", [
                    'computed_length' => strlen($computedHash),
                    'stored_length' => strlen($storedHashBinary),
                    'matches' => hash_equals($storedHashBinary, $computedHash)
                ]);

                if (hash_equals($storedHashBinary, $computedHash)) {
                    Log::info("Password verified successfully with PBKDF2 $algo");
                    return true;
                }
            }

            Log::info('Password verification failed with all PBKDF2 algorithms');
            return false;

        } catch (Exception $e) {
            Log::error('Error in SQL Server password verification:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

        /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Configure custom responses.
     */
    private function configureResponses(): void
    {
        // Configurar el idioma para las respuestas de Fortify
        app()->setLocale('es');
    }
}
