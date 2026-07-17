<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\RolPermiso;
use App\Models\PermisoHistorial;

class PermisoController extends Controller
{
    private const MODULOS = [
        'certificaciones' => ['ver', 'crear', 'editar', 'aprobar', 'rechazar', 'reenviar', 'errar'],
        'liquidaciones'   => ['ver', 'crear', 'anular'],
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
     * Body: { cargo, modulo, acciones, tipo_documento, numero_documento, fecha_documento, observacion }
     */
    public function update(Request $request)
    {
        $request->validate([
            'cargo'            => 'required|string',
            'modulo'           => 'required|string|in:' . implode(',', array_keys(self::MODULOS)),
            'acciones'         => 'sometimes|nullable|array',
            'acciones.*'       => 'string|in:' . implode(',', array_merge(...array_values(self::MODULOS))),
            'tipo_documento'   => 'required|string|in:quipux,oficio,memorando',
            'numero_documento' => 'required|string|max:100',
            'observacion'      => 'nullable|string|max:500',
            'archivo'          => 'required|file|mimes:pdf|max:5120',
        ]);

        try {
            $cargo  = $request->cargo;
            $modulo = $request->modulo;

            $accionesValidas    = self::MODULOS[$modulo] ?? [];
            $accionesNuevas     = array_values(array_intersect($request->input('acciones', []), $accionesValidas));
            $accionesAnteriores = RolPermiso::where('cargo', $cargo)
                ->where('modulo', $modulo)
                ->pluck('accion')
                ->sort()
                ->values()
                ->toArray();

            RolPermiso::where('cargo', $cargo)->where('modulo', $modulo)->delete();

            foreach ($accionesNuevas as $accion) {
                RolPermiso::create(['cargo' => $cargo, 'modulo' => $modulo, 'accion' => $accion]);
            }

            $archivoPath  = null;
            $nombreOriginal = null;
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $nombreOriginal = $file->getClientOriginalName();
                $archivoPath = $file->store('permisos_documentos', 'public');
            }

            $usuario = Auth::user();
            PermisoHistorial::create([
                'id_usuario'              => $usuario->id_usuario,
                'nombre_usuario'          => trim(($usuario->nombres ?? '') . ' ' . ($usuario->apellidos ?? '')) ?: 'Sistema',
                'cargo_modificado'        => $cargo,
                'modulo'                  => $modulo,
                'acciones_anteriores'     => $accionesAnteriores,
                'acciones_nuevas'         => $accionesNuevas,
                'tipo_documento'          => $request->tipo_documento,
                'numero_documento'        => $request->numero_documento,
                'fecha_documento'         => now()->toDateString(),
                'observacion'             => $request->observacion,
                'archivo_documento'       => $archivoPath,
                'nombre_archivo_original' => $nombreOriginal,
            ]);

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

    /**
     * Devuelve el historial de cambios de permisos.
     * GET /permisos/historial
     */
    public function historial(Request $request)
    {
        try {
            $query = PermisoHistorial::orderBy('created_at', 'desc');

            if ($request->filled('cargo')) {
                $query->where('cargo_modificado', $request->cargo);
            }

            $data = $query->limit(100)->get()->map(fn($h) => [
                'id'                  => $h->id,
                'nombre_usuario'      => $h->nombre_usuario,
                'cargo_modificado'    => $h->cargo_modificado,
                'modulo'              => $h->modulo,
                'acciones_anteriores' => $h->acciones_anteriores,
                'acciones_nuevas'     => $h->acciones_nuevas,
                'tipo_documento'      => $h->tipo_documento,
                'numero_documento'    => $h->numero_documento,
                'fecha_documento'     => $h->fecha_documento?->format('Y-m-d'),
                'observacion'             => $h->observacion,
                'archivo_url'             => $h->archivo_documento ? asset('storage/' . $h->archivo_documento) : null,
                'nombre_archivo_original' => $h->nombre_archivo_original,
                'created_at'              => $h->created_at?->format('d/m/Y H:i'),
            ]);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
