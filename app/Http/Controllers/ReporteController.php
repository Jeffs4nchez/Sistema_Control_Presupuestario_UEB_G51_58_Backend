<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    /* ── Helpers ────────────────────────────────────────────────────── */

    private function queryCertificaciones(Request $request)
    {
        $q = DB::table('certificacion as c')
            ->leftJoin('unidad_requiriente as er',    'c.id_unidad_requiriente',    '=', 'er.id_unidad_requiriente')
            ->leftJoin('cedula_presupuestaria as cp',  'c.id_cedula_presupuestaria', '=', 'cp.id_cedula_presupuestaria')
            ->leftJoin('usuarios as u',                'c.id_usuario',               '=', 'u.id_usuario')
            ->select(
                'c.numero_certificado', 'c.fecha_elaboracion', 'c.estado', 'c.seccion_memorando',
                'er.nombre_entidad', 'er.responsable_entidad', 'cp.anio',
                DB::raw("CONCAT(u.nombres, ' ', u.apellidos) as elaborado_por"),
                DB::raw('(SELECT COALESCE(SUM(ci.monto),0) FROM certificacion_items ci WHERE ci.id_certificacion = c.id_certificacion) as monto_total')
            )
            ->orderBy('c.fecha_elaboracion', 'desc');

        if ($request->filled('id_cedula_presupuestaria')) $q->where('c.id_cedula_presupuestaria', $request->id_cedula_presupuestaria);
        if ($request->filled('desde'))               $q->whereDate('c.fecha_elaboracion', '>=', $request->desde);
        if ($request->filled('hasta'))               $q->whereDate('c.fecha_elaboracion', '<=', $request->hasta);
        if ($request->filled('estado'))              $q->where('c.estado', $request->estado);
        if ($request->filled('numero_certificado'))  $q->where('c.numero_certificado', 'ilike', '%' . $request->numero_certificado . '%');

        return $q->get();
    }

    private function queryLiquidaciones(Request $request)
    {
        $q = DB::table('liquidaciones as l')
            ->leftJoin('certificacion_items as ci', 'l.id_certificacion_item', '=', 'ci.id_certificacion_item')
            ->leftJoin('certificacion as c',         'ci.id_certificacion',     '=', 'c.id_certificacion')
            ->leftJoin('items as i',                 'l.id_item',               '=', 'i.id_item')
            ->select(
                'l.id_liquidacion', 'l.fecha_creacion', 'l.memorando',
                'l.cantidad_liquidacion', 'l.estado', 'l.motivo_anulacion',
                'i.cod_item', 'i.nombre_item', 'c.numero_certificado'
            )
            ->orderBy('l.fecha_creacion', 'desc');

        if ($request->filled('id_cedula_presupuestaria')) $q->where('c.id_cedula_presupuestaria', $request->id_cedula_presupuestaria);
        if ($request->filled('desde'))              $q->whereDate('l.fecha_creacion', '>=', $request->desde);
        if ($request->filled('hasta'))              $q->whereDate('l.fecha_creacion', '<=', $request->hasta);
        if ($request->filled('estado'))             $q->where('l.estado', $request->estado);
        if ($request->filled('numero_certificado')) $q->where('c.numero_certificado', 'ilike', '%' . $request->numero_certificado . '%');

        return $q->get();
    }

    private function queryPresupuesto(Request $request)
    {
        $q = DB::table('fuente_items as fi')
            ->join('items as i',                 'fi.id_item',        '=', 'i.id_item')
            ->join('fuente_financiamiento as f',  'fi.id_fuente',      '=', 'f.id_fuente')
            ->leftJoin('actividad as a',           'i.id_actividad',    '=', 'a.id_actividad')
            ->leftJoin('proyecto as pr',           'a.id_proyecto',     '=', 'pr.id_proyecto')
            ->leftJoin('subprograma as sp',        'pr.id_subprograma', '=', 'sp.id_subprograma')
            ->leftJoin('programa as p',            'sp.id_programa',    '=', 'p.id_programa')
            ->select(
                'i.id_item', 'fi.id_fuente', 'i.cod_item', 'i.nombre_item',
                DB::raw('COALESCE(fi.asignado, 0) as asignado'),
                DB::raw('COALESCE(fi.modificado, 0) as modificado'),
                'f.cod_fuente', 'f.nombre_fuente', 'a.cod_actividad',
                'p.cod_programa', 'p.nombre_programa'
            )
            ->orderBy('i.cod_item');

        if ($request->filled('id_cedula_presupuestaria')) $q->where('fi.id_cedula_presupuestaria', $request->id_cedula_presupuestaria);
        if ($request->filled('cod_item'))      $q->where(fn($w) => $w->where('i.cod_item', 'like', '%'.$request->cod_item.'%')->orWhere('i.nombre_item', 'like', '%'.$request->cod_item.'%'));
        if ($request->filled('cod_programa'))  $q->where(fn($w) => $w->where('p.cod_programa', 'like', '%'.$request->cod_programa.'%')->orWhere('p.nombre_programa', 'like', '%'.$request->cod_programa.'%'));
        if ($request->filled('cod_actividad')) $q->where(fn($w) => $w->where('a.cod_actividad', 'like', '%'.$request->cod_actividad.'%')->orWhere('a.nombre_actividad', 'like', '%'.$request->cod_actividad.'%'));
        if ($request->filled('cod_fuente'))    $q->where(fn($w) => $w->where('f.cod_fuente', 'like', '%'.$request->cod_fuente.'%')->orWhere('f.nombre_fuente', 'like', '%'.$request->cod_fuente.'%'));

        $rows = $q->get();

        $certMap = DB::table('certificacion_items as ci')
            ->join('certificacion as c', 'ci.id_certificacion', '=', 'c.id_certificacion')
            ->select('ci.id_item', 'ci.id_fuente', DB::raw('SUM(ci.monto) as total'))
            ->whereIn('c.estado', ['APROBADO', 'LIQUIDADO'])
            ->when($request->filled('desde'), fn($q) => $q->whereDate('c.fecha_elaboracion', '>=', $request->desde))
            ->when($request->filled('hasta'), fn($q) => $q->whereDate('c.fecha_elaboracion', '<=', $request->hasta))
            ->groupBy('ci.id_item', 'ci.id_fuente')
            ->get()
            ->mapWithKeys(fn($r) => ["{$r->id_item}_{$r->id_fuente}" => (float) $r->total]);

        $liquidadoMap = DB::table('liquidaciones as l')
            ->join('certificacion_items as ci', 'l.id_certificacion_item', '=', 'ci.id_certificacion_item')
            ->select('ci.id_item', 'ci.id_fuente', DB::raw('SUM(l.cantidad_liquidacion) as total'))
            ->where('l.estado', '!=', 'ANULADA')
            ->groupBy('ci.id_item', 'ci.id_fuente')
            ->get()
            ->mapWithKeys(fn($r) => ["{$r->id_item}_{$r->id_fuente}" => (float) $r->total]);

        $data = $rows->map(function ($r) use ($certMap, $liquidadoMap) {
            $key              = "{$r->id_item}_{$r->id_fuente}";
            $codificado       = (float) $r->asignado + (float) $r->modificado;
            $totalCertificado = $certMap[$key]      ?? 0.0;
            $liquidado        = $liquidadoMap[$key] ?? 0.0;
            $certificado      = max(0, $totalCertificado - $liquidado);
            $saldo            = max(0, $codificado - $certificado);
            return (object) [
                'cod_item'          => $r->cod_item,
                'nombre_item'       => $r->nombre_item,
                'cod_programa'      => $r->cod_programa    ?? '',
                'nombre_programa'   => $r->nombre_programa ?? '',
                'cod_actividad'     => $r->cod_actividad   ?? '',
                'cod_fuente'        => $r->cod_fuente,
                'codificado'        => round($codificado,       2),
                'certificado'       => round($certificado,      2),
                'certificado_total' => round($totalCertificado, 2),
                'liquidado'         => round($liquidado,        2),
                'saldo'             => round($saldo,            2),
            ];
        });

        if ($request->disponible === 'solo_disponibles') $data = $data->filter(fn($r) => $r->saldo > 0);
        if ($request->disponible === 'solo_agotados')    $data = $data->filter(fn($r) => $r->saldo <= 0);

        return $data->values();
    }

    private function queryAuditoria(Request $request)
    {
        $q = \App\Models\Auditoria::orderBy('fecha_hora', 'desc');

        if ($request->filled('id_cedula_presupuestaria')) {
            $idCedula = $request->id_cedula_presupuestaria;
            $q->whereExists(fn($sub) =>
                $sub->selectRaw('1')->from('certificacion as c')
                    ->whereColumn('c.id_certificacion', 'auditoria.id_certificacion')
                    ->where('c.id_cedula_presupuestaria', $idCedula)
            );
        }
        if ($request->filled('desde'))              $q->whereDate('fecha_hora', '>=', $request->desde);
        if ($request->filled('hasta'))              $q->whereDate('fecha_hora', '<=', $request->hasta);
        if ($request->filled('accion'))             $q->where('accion', $request->accion);
        if ($request->filled('numero_certificado')) $q->where('numero_certificado', 'ilike', '%' . $request->numero_certificado . '%');

        return $q->get();
    }

    /**
     * GET /api/reportes/certificaciones/csv
     */
    public function certificacionesCsv(Request $request)
    {
        $rows = $this->queryCertificaciones($request);

        $headers = [
            'N° Certificado', 'Fecha', 'Estado', 'Monto Total',
            'Memorando', 'Entidad Requiriente', 'Año Cédula', 'Elaborado Por',
        ];

        $csv = $this->buildCsv($headers, $rows->map(fn($r) => [
            $r->numero_certificado,
            $r->fecha_elaboracion,
            $r->estado,
            number_format((float) $r->monto_total, 2, '.', ''),
            $r->seccion_memorando ?? '',
            $r->nombre_entidad    ?? '',
            $r->anio              ?? '',
            $r->elaborado_por     ?? '',
        ])->toArray());

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="certificaciones_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    /**
     * GET /api/reportes/liquidaciones/csv
     */
    public function liquidacionesCsv(Request $request)
    {
        $rows = $this->queryLiquidaciones($request);

        $headers = [
            'ID', 'Fecha', 'Memorando', 'Cantidad Liquidada', 'Estado',
            'Motivo Anulación', 'Código Ítem', 'Nombre Ítem', 'N° Certificado',
        ];

        $csv = $this->buildCsv($headers, $rows->map(fn($r) => [
            $r->id_liquidacion,
            $r->fecha_creacion,
            $r->memorando,
            $r->cantidad_liquidacion,
            $r->estado,
            $r->motivo_anulacion   ?? '',
            $r->cod_item           ?? '',
            $r->nombre_item        ?? '',
            $r->numero_certificado ?? '',
        ])->toArray());

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="liquidaciones_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    /**
     * GET /api/reportes/presupuesto/csv
     */
    public function presupuestoCsv(Request $request)
    {
        $data = $this->queryPresupuesto($request);

        $headers = [
            'Código Ítem', 'Nombre Ítem', 'Programa', 'Actividad', 'Fuente',
            'Codificado', 'Certificado', 'Saldo',
        ];

        $csv = $this->buildCsv($headers, $data->map(fn($r) => [
            $r->cod_item,
            $r->nombre_item,
            $r->cod_programa,
            $r->cod_actividad ? substr($r->cod_actividad, -3) : '',
            $r->cod_fuente,
            number_format($r->codificado,  2, '.', ''),
            number_format($r->certificado, 2, '.', ''),
            number_format($r->saldo,       2, '.', ''),
        ])->toArray());

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="presupuesto_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    /**
     * GET /api/reportes/certificaciones/json — para impresión PDF
     */
    public function certificacionesJson(Request $request)
    {
        try {
            return response()->json(['success' => true, 'data' => $this->queryCertificaciones($request)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/reportes/liquidaciones/json — para impresión PDF
     */
    public function liquidacionesJson(Request $request)
    {
        try {
            return response()->json(['success' => true, 'data' => $this->queryLiquidaciones($request)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/reportes/presupuesto/json — para impresión PDF
     */
    public function presupuestoJson(Request $request)
    {
        try {
            return response()->json(['success' => true, 'data' => $this->queryPresupuesto($request)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/reportes/auditoria/csv
     */
    public function auditoriaCsv(Request $request)
    {
        $rows = $this->queryAuditoria($request);

        $headers = [
            'ID', 'N° Certificado', 'Acción', 'Campo Modificado',
            'Estado Anterior', 'Estado Nuevo', 'Monto Anterior', 'Monto Nuevo',
            'Motivo', 'Usuario', 'Fecha y Hora',
        ];

        $csv = $this->buildCsv($headers, $rows->map(fn($r) => [
            $r->id_auditoria,
            $r->numero_certificado ?? '',
            $r->accion             ?? '',
            $r->campo_modificado   ?? '',
            $r->estado_anterior    ?? '',
            $r->estado_nuevo       ?? '',
            $r->monto_anterior !== null ? number_format((float) $r->monto_anterior, 2, '.', '') : '',
            $r->monto_nuevo     !== null ? number_format((float) $r->monto_nuevo,    2, '.', '') : '',
            $r->motivo             ?? '',
            $r->nombre_usuario     ?? 'Sistema',
            $r->fecha_hora?->format('d/m/Y H:i:s') ?? '',
        ])->toArray());

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="auditoria_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    /**
     * GET /api/reportes/auditoria/json — para impresión PDF
     */
    public function auditoriaJson(Request $request)
    {
        try {
            $rows = $this->queryAuditoria($request)->map(fn($r) => [
                'id_auditoria'       => $r->id_auditoria,
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
            ]);

            return response()->json(['success' => true, 'data' => $rows->values()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function buildCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM UTF-8 para Excel
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
