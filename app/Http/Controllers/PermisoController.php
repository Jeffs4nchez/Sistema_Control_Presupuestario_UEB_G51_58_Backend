<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RolPermiso;

class PermisoController extends Controller
{
    private const MODULOS = [
        'certificaciones' => ['ver', 'crear', 'aprobar', 'rechazar', 'reenviar', 'errar'],
        'liquidaciones'   => ['ver', 'crear', 'anular', 'eliminar'],
    ];

    /**
     * Devuelve la matriz completa de permisos agrupada por cargo.
     * GET /permisos
     */
    public function index()
    {
        try {
            $permisos = RolPermiso::all()
                ->groupBy('cargo')
                ->map(fn($items) =>
                    $items->groupBy('modulo')
                          ->map(fn($acciones) => $acciones->pluck('accion')->values())
                );

            return response()->json([
                'success' => true,
                'data'    => $permisos,
                'modulos' => self::MODULOS,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza las acciones de un cargo en un módulo.
     * PUT /permisos
     * Body: { cargo, modulo, acciones: ['ver','crear',...] }
     */
    public function update(Request $request)
    {
        $request->validate([
            'cargo'      => 'required|string',
            'modulo'     => 'required|string|in:' . implode(',', array_keys(self::MODULOS)),
            'acciones'   => 'required|array',
            'acciones.*' => 'string|in:' . implode(',', array_merge(...array_values(self::MODULOS))),
        ]);

        try {
            $cargo  = $request->cargo;
            $modulo = $request->modulo;

            $accionesValidas = self::MODULOS[$modulo] ?? [];
            $accionesNuevas  = array_intersect($request->acciones, $accionesValidas);

            RolPermiso::where('cargo', $cargo)->where('modulo', $modulo)->delete();

            foreach ($accionesNuevas as $accion) {
                RolPermiso::create(['cargo' => $cargo, 'modulo' => $modulo, 'accion' => $accion]);
            }

            return response()->json([
                'success' => true,
                'message' => "Permisos de '{$cargo}' en '{$modulo}' actualizados",
                'data'    => RolPermiso::where('cargo', $cargo)->where('modulo', $modulo)->pluck('accion'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve los permisos de un cargo específico.
     * GET /permisos/{cargo}
     */
    public function show(string $cargo)
    {
        try {
            $permisos = RolPermiso::where('cargo', $cargo)
                ->get()
                ->groupBy('modulo')
                ->map(fn($items) => $items->pluck('accion')->values());

            return response()->json(['success' => true, 'data' => $permisos]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
