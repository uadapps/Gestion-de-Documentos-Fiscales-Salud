<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        // Configurar idioma de la aplicación
        app()->setLocale('es');

        // Sobrescribir traducciones específicas
        app('translator')->addLines([
            'auth.failed' => 'Estas credenciales no coinciden con nuestros registros.',
            'auth.password' => 'La contraseña proporcionada es incorrecta.',
            'auth.throttle' => 'Demasiados intentos de acceso. Por favor intente nuevamente en :seconds segundos.',
        ], 'es');
    }
}
