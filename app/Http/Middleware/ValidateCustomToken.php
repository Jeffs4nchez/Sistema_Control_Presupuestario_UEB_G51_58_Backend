<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ValidateCustomToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el token del header Authorization
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token no proporcionado en header Authorization'
            ], 401);
        }

        if (strlen($token) !== 64) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido'
            ], 401);
        }

        if (!ctype_xdigit($token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token debe ser hexadecimal válido'
            ], 401);
        }

        // Buscar el usuario con este token
        $user = User::where('api_token', $token)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido o usuario no encontrado'
            ], 401);
        }

        if (strtolower($user->estado) !== 'activo') {
            $msg = strtolower($user->estado) === 'bloqueado'
                ? 'Tu cuenta está bloqueada. Contacta al administrador.'
                : 'Tu cuenta está inactiva. Contacta al administrador.';
            return response()->json(['status' => 'error', 'message' => $msg], 403);
        }

        // Autenticar al usuario
        Auth::setUser($user);

        return $next($request);
    }
}
