<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AccessController extends Controller
{
    /**
     * Mostrar página de acceso denegado
     */
    public function denied()
    {
        return Inertia::render('access-denied', [
            'message' => 'No tienes permisos para acceder a este sistema.',
            'subtitle' => 'Solo los usuarios con roles autorizados pueden acceder.',
        ]);
    }
}
