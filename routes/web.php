<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        // Si está autenticado (ya pasó la validación de roles), ir al dashboard
        return redirect()->route('dashboard');
    }

    // Si no está autenticado, mostrar login
    return Inertia::render('auth/login');
})->name('home');

Route::get('access-denied', [AccessController::class, 'denied'])->name('access.denied');

Route::middleware(['auth', 'verified', 'authorized.role'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Rutas de usuario
    Route::prefix('api/user')->name('api.user.')->group(function () {
        Route::get('profile', [UserController::class, 'getProfile'])->name('profile');
        Route::get('photo', [UserController::class, 'getProfilePhoto'])->name('photo');
        Route::get('debug', [UserController::class, 'debugUserData'])->name('debug');
        Route::get('debug-simple', [UserController::class, 'debugSimple'])->name('debug-simple');
    });

    // Rutas de documentos
    Route::prefix('documentos')->name('documentos.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('documentos/index');
        })->name('index');
        Route::get('upload', function () {
            return Inertia::render('documentos/upload');
        })->name('upload');
    });

    // Otras rutas del campus
    Route::get('calendario', function () {
        return Inertia::render('calendario/index');
    })->name('calendario');

    Route::get('reportes', function () {
        return Inertia::render('reportes/index');
    })->name('reportes');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
