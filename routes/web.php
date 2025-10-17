<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

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
