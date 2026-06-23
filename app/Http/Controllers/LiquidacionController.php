<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Liquidacion;
use App\Models\CertificacionItem;
use App\Models\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LiquidacionController extends Controller
{
    /**
     * Listar liquidaciones de un certificacion_item específico
     * GET /liquidaciones?id_certificacion_item=X
     */
    public function index(Request $request)
    {
        try {
            $idCertItem = $request->input('id_certificacion_item');

            $query = Liquidacion::with(['item', 'certificacionItem.certificacion'])
                                ->orderBy('fecha_creacion', 'desc');

            if ($idCertItem) $query->where('id_certificacion_item', $idCertItem);

            // Analista solo ve liquidaciones de sus propias certificaciones
            $u = Auth::user();
            $rolesDirector = ['Director(a) financiero', 'Analista de presupuesto 3', 'Administrador del sistema'];
            if ($u && !in_array($u->cargo, $rolesDirector)) {
                $query->whereHas('certificacionItem.certificacion', function ($q) use ($u) {
                    $q->where('id_usuario', $u->id_usuario);
                });
            }

            $liquidaciones = $query->get();

            $totalLiquidado = $liquidaciones->where('estado', '!=', 'ANULADA')->sum('cantidad_liquidacion');

            // Traer el monto certificado de certificacion_items
            $certItem = null;
            if ($idCertItem) {
                $certItem = CertificacionItem::find($idCertItem);
            }

            $montoCertificado = $certItem ? (float) $certItem->monto : 0;
            $pendiente        = max(0, $montoCertificado - $totalLiquidado);

            return response()->json([
                'success' => true,
                'data'    => $liquidaciones,
                'resumen' => [
                    'certificado' => $montoCertificado,
                    'liquidado'   => (float) $totalLiquidado,
                    'pendiente'   => (float) $pendiente,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear una liquidación
     * POST /liquidaciones
     */
    public function store(Request $request)
    {
        $rolesOperativos = ['Director(a) financiero', 'Analista de presupuesto 1', 'Analista de presupuesto 3', 'Administrador del sistema'];
        if (!in_array(Auth::user()?->cargo, $rolesOperativos)) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para registrar liquidaciones'], 403);
        }

        $request->validate([
            'id_certificacion_item' => 'required|integer|exists:certificacion_items,id_certificacion_item',
            'cantidad_liquidacion'  => 'required|numeric|min:0.01|max:999999999999.99',
            'fecha_creacion'        => 'required|date',
            'memorando'             => 'required|string|max:100',
        ]);

        try {
            $certItem = CertificacionItem::with('certificacion')->findOrFail($request->id_certificacion_item);

            $cert = $certItem->certificacion;
            if (!$cert || !in_array($cert->estado, ['APROBADO', 'LIQUIDADO'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede liquidar una certificación en estado APROBADO o LIQUIDADO.',
                ], 422);
            }

            $montoCertificado = (float) $certItem->monto;

            $yaLiquidado = (float) Liquidacion::where('id_certificacion_item', $request->id_certificacion_item)
                                              ->where('estado', '!=', 'ANULADA')
                                              ->sum('cantidad_liquidacion');

            $disponible = max(0, $montoCertificado - $yaLiquidado);

            if ((float) $request->cantidad_liquidacion > $disponible) {
                return response()->json([
                    'success' => false,
                    'message' => "El monto supera el disponible. Certificado: \$$montoCertificado, "
                               . "Ya liquidado: \$$yaLiquidado, Disponible: \$$disponible",
                ], 422);
            }

            $liquidacion = Liquidacion::create([
                'id_item'               => $certItem->id_item,
                'id_certificacion_item' => $request->id_certificacion_item,
                'cantidad_liquidacion'  => $request->cantidad_liquidacion,
                'fecha_creacion'        => $request->fecha_creacion,
                'memorando'             => $request->memorando,
                'estado'                => 'LIQUIDADO',
            ]);

            $liquidacion->load(['item', 'certificacionItem.certificacion']);

            try {
                $u    = Auth::user();
                $cert = $liquidacion->certificacionItem?->certificacion;
                $uid    = $u?->id_usuario ?? null;
                $nombre = $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema';

                Auditoria::create([
                    'id_certificacion'   => $cert?->id_certificacion ?? 0,
                    'numero_certificado' => $cert?->numero_certificado ?? 'N/A',
                    'id_usuario'         => $uid,
                    'nombre_usuario'     => $nombre,
                    'accion'             => 'CREACION_LIQUIDACION',
                    'campo_modificado'   => $request->memorando,
                    'monto_nuevo'        => $request->cantidad_liquidacion,
                    'fecha_hora'         => now(),
                ]);

                // Cambio automático a LIQUIDADO si el 100% está cubierto
                if ($cert && $cert->estado !== 'LIQUIDADO') {
                    $totalC = $this->totalCertificado($cert->id_certificacion);
                    $totalL = $this->totalLiquidado($cert->id_certificacion);
                    if ($totalC > 0 && $totalL >= $totalC) {
                        $estadoAnterior = $cert->estado;
                        DB::table('certificacion')->where('id_certificacion', $cert->id_certificacion)->update(['estado' => 'LIQUIDADO']);
                        Auditoria::create([
                            'id_certificacion'   => $cert->id_certificacion,
                            'numero_certificado' => $cert->numero_certificado,
                            'id_usuario'         => $uid,
                            'nombre_usuario'     => $nombre,
                            'accion'             => 'CAMBIO_ESTADO',
                            'estado_anterior'    => $estadoAnterior,
                            'estado_nuevo'       => 'LIQUIDADO',
                            'campo_modificado'   => 'estado',
                            'fecha_hora'         => now(),
                        ]);
                    }
                }
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::liquidacion store error: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'data'    => $liquidacion,
                'message' => 'Liquidación registrada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar una liquidación
     * DELETE /liquidaciones/{id}
     */
    public function destroy($id)
    {
        $rolesPermitidos = ['Director(a) financiero', 'Administrador del sistema'];
        if (!in_array(Auth::user()?->cargo, $rolesPermitidos)) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para eliminar liquidaciones'], 403);
        }

        try {
            $liquidacion = Liquidacion::findOrFail($id);
            $liquidacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Liquidación eliminada',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Anular una liquidación (soft delete)
     * PATCH /liquidaciones/{id}/anular
     */
    public function anular(Request $request, $id)
    {
        $rolesPermitidos = ['Director(a) financiero', 'Administrador del sistema'];
        if (!in_array(\Auth::user()?->cargo, $rolesPermitidos)) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para anular liquidaciones'], 403);
        }

        $request->validate([
            'motivo_anulacion' => 'required|string|max:255',
        ]);

        try {
            $liquidacion = Liquidacion::findOrFail($id);

            if ($liquidacion->estado === 'ANULADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta liquidación ya está anulada',
                ], 422);
            }

            $usuario = Auth::user();

            $liquidacion->estado               = 'ANULADA';
            $liquidacion->motivo_anulacion     = $request->motivo_anulacion;
            $liquidacion->id_usuario_anulacion = $usuario?->id_usuario ?? null;
            $liquidacion->save();

            try {
                $certItem = CertificacionItem::with('certificacion')->find($liquidacion->id_certificacion_item);
                $cert     = $certItem?->certificacion;
                $uid    = $usuario?->id_usuario ?? null;
                $nombre = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'Sistema';

                Auditoria::create([
                    'id_certificacion'   => $cert?->id_certificacion ?? 0,
                    'numero_certificado' => $cert?->numero_certificado ?? 'N/A',
                    'id_usuario'         => $uid,
                    'nombre_usuario'     => $nombre,
                    'accion'             => 'ANULACION_LIQUIDACION',
                    'campo_modificado'   => $liquidacion->memorando,
                    'motivo'             => $request->motivo_anulacion,
                    'monto_anterior'     => $liquidacion->cantidad_liquidacion,
                    'fecha_hora'         => now(),
                ]);

                // Si el cert estaba LIQUIDADO y ya no tiene cobertura completa → revertir a APROBADO
                if ($cert && $cert->estado === 'LIQUIDADO') {
                    $totalC = $this->totalCertificado($cert->id_certificacion);
                    $totalL = $this->totalLiquidado($cert->id_certificacion);
                    if ($totalL < $totalC) {
                        DB::table('certificacion')->where('id_certificacion', $cert->id_certificacion)->update(['estado' => 'APROBADO']);
                        Auditoria::create([
                            'id_certificacion'   => $cert->id_certificacion,
                            'numero_certificado' => $cert->numero_certificado,
                            'id_usuario'         => $uid,
                            'nombre_usuario'     => $nombre,
                            'accion'             => 'CAMBIO_ESTADO',
                            'estado_anterior'    => 'LIQUIDADO',
                            'estado_nuevo'       => 'APROBADO',
                            'campo_modificado'   => 'estado',
                            'fecha_hora'         => now(),
                        ]);
                    }
                }
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::anular error: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'data'    => $liquidacion,
                'message' => 'Liquidación anulada correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function totalCertificado(int $idCert): float
    {
        return (float) DB::table('certificacion_items')
            ->where('id_certificacion', $idCert)
            ->sum('monto');
    }

    private function totalLiquidado(int $idCert): float
    {
        return (float) DB::table('liquidaciones')
            ->join('certificacion_items', 'liquidaciones.id_certificacion_item', '=', 'certificacion_items.id_certificacion_item')
            ->where('certificacion_items.id_certificacion', $idCert)
            ->where('liquidaciones.estado', '!=', 'ANULADA')
            ->sum('liquidaciones.cantidad_liquidacion');
    }

    /**
     * Certificaciones agrupadas con sus ítems y estado de liquidación
     * GET /liquidaciones/certificaciones?search=X
     */
    public function certificaciones(Request $request)
    {
        try {
            $u             = Auth::user();
            $rolesDirector = ['Director(a) financiero', 'Analista de presupuesto 3', 'Administrador del sistema'];

            $search   = $request->input('search', '');
            $idCedula = $request->input('id_cedula_presupuestaria', '');

            $query = DB::table('certificacion_items as ci')
                ->join('certificacion as c',         'ci.id_certificacion', '=', 'c.id_certificacion')
                ->join('items as i',                 'ci.id_item',          '=', 'i.id_item')
                ->leftJoin('fuente_financiamiento as f', 'ci.id_fuente',    '=', 'f.id_fuente')
                ->select(
                    'ci.id_certificacion_item',
                    'ci.id_certificacion',
                    'ci.id_item',
                    'ci.id_fuente',
                    'ci.monto',
                    'i.cod_item',
                    'i.nombre_item',
                    'c.numero_certificado',
                    'c.fecha_elaboracion',
                    'f.cod_fuente',
                    'f.nombre_fuente'
                )
                ->where('ci.monto', '>', 0)
                ->whereIn('c.estado', ['APROBADO', 'LIQUIDADO']);

            // Analista y otros roles no-director solo ven sus propias certificaciones
            if ($u && !in_array($u->cargo, $rolesDirector)) {
                $query->where('c.id_usuario', $u->id_usuario);
            }

            if ($idCedula) {
                $query->where('c.id_cedula_presupuestaria', $idCedula);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('c.numero_certificado', 'LIKE', "%$search%")
                      ->orWhere('i.cod_item',          'LIKE', "%$search%")
                      ->orWhere('i.nombre_item',        'LIKE', "%$search%");
                });
            }

            $allItems = $query->orderBy('c.fecha_elaboracion', 'desc')
                              ->orderBy('i.cod_item')
                              ->get();

            // Precarga todos los sumas de liquidaciones en una sola query
            $liqSums = DB::table('liquidaciones')
                ->whereIn('id_certificacion_item', $allItems->pluck('id_certificacion_item'))
                ->where('estado', '!=', 'ANULADA')
                ->selectRaw('id_certificacion_item, SUM(cantidad_liquidacion) as total')
                ->groupBy('id_certificacion_item')
                ->pluck('total', 'id_certificacion_item');

            $allItems = $allItems->map(function ($row) use ($liqSums) {
                $monto          = (float) $row->monto;
                $liquidado      = (float) ($liqSums[$row->id_certificacion_item] ?? 0);
                $row->monto     = $monto;
                $row->liquidado = $liquidado;
                $row->pendiente = max(0, $monto - $liquidado);
                return $row;
            });

            // Agrupar por certificación
            $grouped = $allItems->groupBy('id_certificacion')->map(function ($certItems) {
                $first = $certItems->first();
                return [
                    'id_certificacion'   => $first->id_certificacion,
                    'numero_certificado' => $first->numero_certificado,
                    'fecha_elaboracion'  => $first->fecha_elaboracion,
                    'total_monto'        => (float) $certItems->sum('monto'),
                    'total_liquidado'    => (float) $certItems->sum('liquidado'),
                    'total_pendiente'    => (float) $certItems->sum('pendiente'),
                    'items_count'        => $certItems->count(),
                    'items'              => $certItems->values(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data'    => $grouped,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener todos los certificacion_items con monto > 0 y sus totales liquidados
     * GET /liquidaciones/certificacion-items?search=X
     */
    public function certificacionItems(Request $request)
    {
        try {
            $u             = Auth::user();
            $rolesDirector = ['Director(a) financiero', 'Analista de presupuesto 3', 'Administrador del sistema'];

            $search = $request->input('search', '');
            $page   = (int) $request->input('page', 1);
            $limit  = (int) $request->input('limit', 20);
            $offset = ($page - 1) * $limit;

            $query = DB::table('certificacion_items as ci')
                ->join('items as i',               'ci.id_item',           '=', 'i.id_item')
                ->join('certificacion as c',        'ci.id_certificacion',  '=', 'c.id_certificacion')
                ->leftJoin('fuente_financiamiento as f', 'ci.id_fuente',    '=', 'f.id_fuente')
                ->select(
                    'ci.id_certificacion_item',
                    'ci.id_certificacion',
                    'ci.id_item',
                    'ci.id_fuente',
                    'ci.monto',
                    'i.cod_item',
                    'i.nombre_item',
                    'c.numero_certificado',
                    'c.fecha_elaboracion',
                    'f.cod_fuente',
                    'f.nombre_fuente'
                )
                ->where('ci.monto', '>', 0)
                ->whereIn('c.estado', ['APROBADO', 'LIQUIDADO']);

            // Analista y otros roles no-director solo ven sus propias certificaciones
            if ($u && !in_array($u->cargo, $rolesDirector)) {
                $query->where('c.id_usuario', $u->id_usuario);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('i.cod_item',            'LIKE', "%$search%")
                      ->orWhere('i.nombre_item',        'LIKE', "%$search%")
                      ->orWhere('c.numero_certificado', 'LIKE', "%$search%");
                });
            }

            $total   = $query->count();
            $records = $query->orderBy('c.fecha_elaboracion', 'desc')
                             ->orderBy('i.cod_item')
                             ->offset($offset)
                             ->limit($limit)
                             ->get();

            // Precarga sumas de liquidaciones en una sola query
            $liqSumsPage = DB::table('liquidaciones')
                ->whereIn('id_certificacion_item', $records->pluck('id_certificacion_item'))
                ->where('estado', '!=', 'ANULADA')
                ->selectRaw('id_certificacion_item, SUM(cantidad_liquidacion) as total')
                ->groupBy('id_certificacion_item')
                ->pluck('total', 'id_certificacion_item');

            $result = $records->map(function ($row) use ($liqSumsPage) {
                $monto          = (float) $row->monto;
                $liquidado      = (float) ($liqSumsPage[$row->id_certificacion_item] ?? 0);
                $row->monto     = $monto;
                $row->liquidado = $liquidado;
                $row->pendiente = max(0, $monto - $liquidado);
                return $row;
            });

            return response()->json([
                'success'    => true,
                'data'       => $result,
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
