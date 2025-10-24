<?php

namespace App\Http\Controllers;

use App\Models\CampusContador;
use App\Models\usuario_model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function debugCampus()
    {
        try {
            $user = Auth::user();
            $usuario = usuario_model::with('empleado')->find($user->ID_Usuario);

            $empleadoId = $usuario && $usuario->empleado ? $usuario->empleado->ID_Empleado : null;

            $totalRegistros = CampusContador::count();
            $primerosRegistros = CampusContador::limit(10)->get();

            $registrosEmpleado001040039 = CampusContador::where('ID_Empleado', '001040039')->get();

            $registrosEmpleadoActual = [];
            if ($empleadoId) {
                $registrosEmpleadoActual = CampusContador::where('ID_Empleado', $empleadoId)->get();
            }

            Log::info('Debug Campus_Contadores', [
                'total_registros_tabla' => $totalRegistros,
                'empleado_actual_id' => $empleadoId,
                'registros_empleado_001040039' => $registrosEmpleado001040039->count(),
                'registros_empleado_actual' => $registrosEmpleadoActual ? count($registrosEmpleadoActual) : 0
            ]);

            return response()->json([
                'success' => true,
                'total_registros' => $totalRegistros,
                'empleado_actual_id' => $empleadoId,
                'registros_001040039' => [
                    'count' => $registrosEmpleado001040039->count(),
                    'data' => $registrosEmpleado001040039->toArray()
                ],
                'registros_empleado_actual' => [
                    'count' => $registrosEmpleadoActual ? count($registrosEmpleadoActual) : 0,
                    'data' => $registrosEmpleadoActual ? $registrosEmpleadoActual->toArray() : []
                ],
                'muestra_primeros_10' => $primerosRegistros->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('Error en debugCampus', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
