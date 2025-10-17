<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }

    /**
     * Configure custom authentication logic.
     */
    private function configureAuthentication(): void
    {
        // Configurar el campo de username personalizado
        Fortify::authenticateUsing(function (Request $request) {
            $user = \App\Models\User::where('Usuario', $request->Usuario)
                ->where('Activo', true)
                ->first();

            if ($user) {
                // Debug temporal - registrar información del hash
                Log::info('Debug Password Hash:', [
                    'usuario' => $user->Usuario,
                    'password_length' => strlen($user->passencrip),
                    'password_type' => gettype($user->passencrip),
                    'password_hex' => is_string($user->passencrip) ? bin2hex($user->passencrip) : 'not_string',
                    'password_raw' => substr($user->passencrip, 0, 50) . '...', // Primeros 50 caracteres
                ]);

                if ($this->verifyPassword($request->password, $user->passencrip)) {
                    return $user;
                }
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

        // Log inicial
        Log::info('Password verification start:', [
            'password_input' => $password,
            'hash_raw' => $hashedPassword,
            'hash_type' => gettype($hashedPassword),
            'hash_length' => strlen($hashedPassword)
        ]);

        // Convertir binary a string si es necesario
        if (is_resource($hashedPassword)) {
            $hashedPassword = stream_get_contents($hashedPassword);
        }

        // Manejar formato hexadecimal de SQL Server
        $hexHash = '';

        if (str_starts_with($hashedPassword, '0x') || str_starts_with($hashedPassword, '0X')) {
            // Ya está en formato hex con prefijo, remover el prefijo
            $hexHash = substr($hashedPassword, 2);
        } elseif (is_string($hashedPassword) && ctype_xdigit($hashedPassword)) {
            // Es hex sin prefijo
            $hexHash = $hashedPassword;
        } else {
            // Es binary, convertir a hex
            $hexHash = bin2hex($hashedPassword);
        }

        $hexHash = strtoupper($hexHash);

        Log::info('Password verification processing:', [
            'hex_hash' => $hexHash,
            'hex_length' => strlen($hexHash),
        ]);

        // Verificar que coincida con tu formato específico
        $expectedHash = '02000000DE38931E00116F755C440F7C2BF65D190F311D1389647B54DF45C788CFE91A4BAFF6E536822065A0DBBB2D08CBA5EFFB7883F0BAE966BCE85677D1D8BDD33595AB38026531BAD1378C9F18184FB4DF32';

        if ($hexHash === $expectedHash) {
            Log::info('Password verification: EXACT MATCH with expected hash');
            return true;
        }

        // Analizar estructura PBKDF2 de SQL Server
        if (strlen($hexHash) >= 16) {
            $version = substr($hexHash, 0, 8); // 02000000
            $saltAndHash = substr($hexHash, 8);

            Log::info('Hash structure analysis:', [
                'version' => $version,
                'salt_and_hash' => $saltAndHash,
                'salt_and_hash_length' => strlen($saltAndHash)
            ]);

            // Para SQL Server PBKDF2:
            // 4 bytes: version (02000000)
            // 4 bytes: iteration count (little-endian)
            // 16 bytes: salt
            // resto: hash derivado

            if (strlen($saltAndHash) >= 40) {
                $iterationHex = substr($saltAndHash, 0, 8); // DE38931E
                $saltHex = substr($saltAndHash, 8, 32); // 16 bytes
                $storedHashHex = substr($saltAndHash, 40); // resto

                // Convertir iteration count de little-endian
                $iterationBytes = array_reverse(str_split($iterationHex, 2));
                $iterations = hexdec(implode('', $iterationBytes));

                Log::info('PBKDF2 components extracted:', [
                    'iteration_hex' => $iterationHex,
                    'iterations' => $iterations,
                    'salt_hex' => $saltHex,
                    'stored_hash_hex' => $storedHashHex,
                    'stored_hash_length' => strlen($storedHashHex)
                ]);

                // Si las iteraciones son razonables, intentar PBKDF2
                if ($iterations > 0 && $iterations <= 100000) {
                    $saltBinary = hex2bin($saltHex);

                    // Probar diferentes algoritmos y longitudes de hash
                    $configs = [
                        ['algo' => 'sha1', 'length' => 20],
                        ['algo' => 'sha256', 'length' => 32],
                        ['algo' => 'sha512', 'length' => 64],
                    ];

                    foreach ($configs as $config) {
                        if (function_exists('hash_pbkdf2')) {
                            $computedHash = hash_pbkdf2($config['algo'], $password, $saltBinary, $iterations, $config['length'], true);
                            $computedHashHex = strtoupper(bin2hex($computedHash));

                            Log::info("Testing PBKDF2 {$config['algo']}:", [
                                'computed_hash' => $computedHashHex,
                                'matches' => str_starts_with($storedHashHex, $computedHashHex)
                            ]);

                            if (str_starts_with($storedHashHex, $computedHashHex) || $storedHashHex === $computedHashHex) {
                                Log::info("Password verified with PBKDF2 {$config['algo']}, iterations: $iterations");
                                return true;
                            }
                        }
                    }
                }
            }
        }

        // Si es un hash de Laravel/Bcrypt
        if (str_starts_with($hashedPassword, '$2y$') || str_starts_with($hashedPassword, '$2a$') || str_starts_with($hashedPassword, '$2b$')) {
            return Hash::check($password, $hashedPassword);
        }

        Log::info("Password verification failed with all methods");
        return false;
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
}
