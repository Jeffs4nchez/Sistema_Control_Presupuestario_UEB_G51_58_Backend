<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresupuestoController extends Controller
{
    /**
     * GET /api/presupuesto-disponible
     *
     * Cálculo por estado según ciclo de vida de la certificación:
     *   REGISTRADO  → sin afectación presupuestaria (no cuenta)
     *   APROBADO   → reserva el saldo (certificado = monto - liquidaciones sobre esa cert)
     *   LIQUIDADO  → solo el gasto real cuenta (devengado = liquidaciones sobre esa cert)
     *   ANULADA / ERRADO / RECHAZADO → sin afectación
     *
     * Fórmula:
     *   codificado          = asignado + modificado
     *   certificado_reservado = SUM(cert_items APROBADO) − SUM(liq activas en APROBADO)
     *   devengado            = SUM(liq activas en LIQUIDADO)
     *   saldo                = codificado − certificado_reservado − devengado
     */
    public function index(Request $request)
    {
        try {
            $search    = $request->input('search', '');
            $nombre    = $request->input('nombre', '');
            $programa  = $request->input('programa', '');
            $actividad = $request->input('actividad', '');
            $fuente    = $request->input('fuente', '');
            $idCedula  = $request->input('id_cedula_presupuestaria', '');

            $query = DB::table('fuente_items as fi')
                ->join('items as i',                    'fi.id_item',        '=', 'i.id_item')
                ->join('fuente_financiamiento as f',    'fi.id_fuente',      '=', 'f.id_fuente')
                ->leftJoin('actividad as a',             'i.id_actividad',    '=', 'a.id_actividad')
                ->leftJoin('proyecto as pr',             'a.id_proyecto',     '=', 'pr.id_proyecto')
                ->leftJoin('subprograma as sp',          'pr.id_subprograma', '=', 'sp.id_subprograma')
                ->leftJoin('programa as p',              'sp.id_programa',    '=', 'p.id_programa')
                ->select(
                    'i.id_item',
                    'fi.id_fuente',
                    'i.cod_item',
                    'i.nombre_item',
                    DB::raw('COALESCE(fi.asignado, 0) as asignado'),
                    DB::raw('COALESCE(fi.modificado, 0) as modificado'),
                    'f.cod_fuente',
                    'f.nombre_fuente',
                    'a.cod_actividad',
                    'a.nombre_actividad',
                    'p.cod_programa',
                    'p.nombre_programa'
                );

            if ($search) {
                $query->where('i.cod_item', 'LIKE', "%$search%");
            }
            if ($nombre) {
                $query->where('i.nombre_item', 'ILIKE', "%$nombre%");
            }
            if ($idCedula)  $query->where('fi.id_cedula_presupuestaria', $idCedula);
            if ($programa)  $query->where('p.cod_programa',  'LIKE', "%$programa%");
            if ($actividad) $query->where('a.cod_actividad', 'LIKE', "%$actividad%");
            if ($fuente)    $query->where('f.cod_fuente',    'LIKE', "%$fuente%");

            $rows = $query->orderBy('i.cod_item')->get();

            // Certificado: total de certs en estado APROBADO o LIQUIDADO — filtrado por cédula
            $certQuery = DB::table('certificacion_items as ci')
                ->join('certificacion as c', 'ci.id_certificacion', '=', 'c.id_certificacion')
                ->select('ci.id_item', 'ci.id_fuente', DB::raw('SUM(ci.monto) as total'))
                ->whereIn('c.estado', ['APROBADO', 'LIQUIDADO']);
            if ($idCedula) $certQuery->where('c.id_cedula_presupuestaria', $idCedula);
            $certMap = $certQuery->groupBy('ci.id_item', 'ci.id_fuente')
                ->get()
                ->mapWithKeys(fn($r) => ["{$r->id_item}_{$r->id_fuente}" => (float) $r->total]);

            // Liquidado: total de liquidaciones activas — filtrado por cédula
            $liqQuery = DB::table('liquidaciones as l')
                ->join('certificacion_items as ci', 'l.id_certificacion_item', '=', 'ci.id_certificacion_item')
                ->join('certificacion as c', 'ci.id_certificacion', '=', 'c.id_certificacion')
                ->select('ci.id_item', 'ci.id_fuente', DB::raw('SUM(l.cantidad_liquidacion) as total'))
                ->where('l.estado', '!=', 'ANULADA');
            if ($idCedula) $liqQuery->where('c.id_cedula_presupuestaria', $idCedula);
            $liquidadoMap = $liqQuery->groupBy('ci.id_item', 'ci.id_fuente')
                ->get()
                ->mapWithKeys(fn($r) => ["{$r->id_item}_{$r->id_fuente}" => (float) $r->total]);

            $result = $rows->map(function ($row) use ($certMap, $liquidadoMap) {
                $key             = "{$row->id_item}_{$row->id_fuente}";
                $codificado       = (float) $row->asignado + (float) $row->modificado;
                $totalCertificado = $certMap[$key]      ?? 0.0;
                $liquidado        = $liquidadoMap[$key] ?? 0.0;

                // certificado = items certificados − liquidaciones (reserva neta pendiente)
                $certificado = max(0, $totalCertificado - $liquidado);

                // saldo = codificado − certificado
                $saldo = max(0, $codificado - $certificado);

                return [
                    'id_item'          => $row->id_item,
                    'id_fuente'        => $row->id_fuente,
                    'cod_item'         => $row->cod_item,
                    'nombre_item'      => $row->nombre_item,
                    'cod_fuente'       => $row->cod_fuente,
                    'nombre_fuente'    => $row->nombre_fuente,
                    'cod_actividad'    => $row->cod_actividad,
                    'nombre_actividad' => $row->nombre_actividad,
                    'cod_programa'     => $row->cod_programa,
                    'nombre_programa'  => $row->nombre_programa,
                    'codificado'       => round($codificado,  2),
                    'certificado'      => round($certificado, 2),
                    'liquidado'        => round($liquidado,   2),
                    'saldo'            => round($saldo,       2),
                    'sin_saldo'        => $saldo <= 0,
                ];
            });

            $totales = [
                'total_items'       => $result->count(),
                'total_codificado'  => round($result->sum('codificado'),  2),
                'total_certificado' => round($result->sum('certificado'), 2),
                'total_liquidado'   => round($result->sum('liquidado'),   2),
                'total_saldo'       => round($result->sum('saldo'),       2),
                'items_sin_saldo'   => $result->where('sin_saldo', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data'    => $result->values(),
                'totales' => $totales,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /presupuesto/certificaciones-por-item?id_item=X&id_fuente=Y&id_cedula=Z
     * Devuelve las certificaciones (APROBADO/LIQUIDADO) que contienen un ítem+fuente.
     */
    public function certificacionesPorItem(Request $request)
    {
        try {
            $idItem   = $request->input('id_item');
            $idFuente = $request->input('id_fuente');
            $idCedula = $request->input('id_cedula_presupuestaria');

            if (!$idItem || !$idFuente) {
                return response()->json(['success' => false, 'message' => 'id_item e id_fuente son requeridos'], 422);
            }

            $query = DB::table('certificacion_items as ci')
                ->join('certificacion as c',                  'ci.id_certificacion',      '=', 'c.id_certificacion')
                ->leftJoin('unidad_requiriente as ur',        'c.id_unidad_requiriente',  '=', 'ur.id_unidad_requiriente')
                ->leftJoin('fuente_financiamiento as f',      'ci.id_fuente',             '=', 'f.id_fuente')
                ->where('ci.id_item',   $idItem)
                ->where('ci.id_fuente', $idFuente)
                ->whereIn('c.estado', ['APROBADO', 'LIQUIDADO'])
                ->select(
                    'c.id_certificacion',
                    'c.numero_certificado',
                    'c.fecha_elaboracion',
                    'c.estado',
                    'ci.monto',
                    'ur.nombre as unidad_requiriente',
                    'f.cod_fuente',
                    'f.nombre_fuente'
                );

            if ($idCedula) {
                $query->where('c.id_cedula_presupuestaria', $idCedula);
            }

            $data = $query->orderBy('c.fecha_elaboracion', 'desc')->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
