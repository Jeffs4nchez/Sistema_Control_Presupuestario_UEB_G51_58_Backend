<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\RolPermiso;

class CheckPermission
{
    /**
     * Verifica que el usuario autenticado tenga el permiso indicado.
     *
     * Uso en rutas:
     *   Route::middleware('check.permission:certificaciones,crear')
     */
    public function handle(Request $request, Closure $next, string $modulo, string $accion): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        if (!RolPermiso::tiene($user->cargo, $modulo, $accion)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para realizar esta acción',
            ], 403);
        }

        return $next($request);
    }
}
