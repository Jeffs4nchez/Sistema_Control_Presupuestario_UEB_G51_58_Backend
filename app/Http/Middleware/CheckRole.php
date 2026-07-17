<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    // Grupos de roles predefinidos — deben coincidir con el campo `cargo` en la tabla usuarios
    private const GRUPOS = [
        'directores'  => [
            'Director(a) financiero',
            'Administrador del sistema',
        ],
        'aprobadores' => [
            'Director(a) financiero',
            'Analista de presupuesto 3',
            'Administrador del sistema',
        ],
        'operativos'  => [
            'Director(a) financiero',
            'Analista de presupuesto 1',
            'Analista de presupuesto 3',
            'Administrador del sistema',
        ],
    ];

    /**
     * Verifica que el usuario autenticado pertenezca a alguno de los grupos/roles indicados.
     *
     * Uso en rutas:
     *   Route::middleware('check.role:directores')
     *   Route::middleware('check.role:directores,operativos')   // unión de ambos grupos
     */
    public function handle(Request $request, Closure $next, string ...$grupos): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        $permitidos = [];
        foreach ($grupos as $grupo) {
            $permitidos = array_merge($permitidos, self::GRUPOS[$grupo] ?? [$grupo]);
        }

        if (!in_array($user->cargo, array_unique($permitidos), true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para realizar esta acción',
            ], 403);
        }

        return $next($request);
    }
}
