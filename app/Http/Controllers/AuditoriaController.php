<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Auditoria;

class AuditoriaController extends Controller
{
    private static array $rolesPermitidos = ['Director(a) financiero', 'Administrador del sistema'];

    private function formatRow(Auditoria $r): array
    {
        return [
            'id_auditoria'       => $r->id_auditoria,
            'id_certificacion'   => $r->id_certificacion,
            'numero_certificado' => $r->numero_certificado,
            'accion'             => $r->accion,
            'campo_modificado'   => $r->campo_modificado,
            'estado_anterior'    => $r->estado_anterior,
            'estado_nuevo'       => $r->estado_nuevo,
            'monto_anterior'     => $r->monto_anterior,
            'monto_nuevo'        => $r->monto_nuevo,
            'motivo'             => $r->motivo,
            'nombre_usuario'     => $r->nombre_usuario ?? 'Sistema',
            'fecha_hora'         => $r->fecha_hora?->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * GET /auditoria/certificacion/{id}
     * Historial de un certificado específico.
     */
    public function porCertificacion($id)
    {
        if (!in_array(Auth::user()?->cargo, self::$rolesPermitidos)) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para ver la auditoría'], 403);
        }
        try {
            $registros = Auditoria::where('id_certificacion', $id)
                ->orderBy('fecha_hora', 'desc')
                ->get()
                ->map(fn($r) => $this->formatRow($r));

            return response()->json(['success' => true, 'data' => $registros]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /auditoria
     * Listado completo con filtros y paginación.
     */
    public function index(Request $request)
    {
        if (!in_array(Auth::user()?->cargo, self::$rolesPermitidos)) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para ver la auditoría'], 403);
        }
        try {
            $search  = $request->input('search', '');
            $accion  = $request->input('accion', '');
            $desde   = $request->input('desde', '');
            $hasta   = $request->input('hasta', '');
            $page    = max(1, (int) $request->input('page', 1));
            $limit   = (int) $request->input('limit', 20);

            $query = Auditoria::orderBy('fecha_hora', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_certificado', 'LIKE', "%$search%")
                      ->orWhere('nombre_usuario',   'LIKE', "%$search%");
                });
            }
            if ($accion) $query->where('accion', $accion);
            if ($desde)  $query->whereDate('fecha_hora', '>=', $desde);
            if ($hasta)  $query->whereDate('fecha_hora', '<=', $hasta);

            $total   = $query->count();
            $registros = $query->offset(($page - 1) * $limit)->limit($limit)->get()
                ->map(fn($r) => $this->formatRow($r));

            return response()->json([
                'success'    => true,
                'data'       => $registros,
                'pagination' => [
                    'total' => $total,
                    'page'  => $page,
                    'limit' => $limit,
                    'pages' => (int) ceil($total / max(1, $limit)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
