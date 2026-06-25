<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Certificacion;
use App\Models\CertificacionItem;
use App\Models\Item;
use App\Models\Actividad;
use App\Models\Ubicacion;
use App\Models\FuenteFinanciamiento;
use App\Models\Programa;
use App\Models\Subprograma;
use App\Models\Proyecto;
use App\Models\Organismo;
use App\Models\NaturalezaPrestacion;
use App\Models\EntidadRequiriente;
use App\Models\CedulaPresupuestaria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\Auditoria;
use App\Models\User;
use App\Traits\EnviaCorreoHtml;
use App\Models\RolPermiso;
use Carbon\Carbon;

class CertificacionController extends Controller
{
    use EnviaCorreoHtml;

    /**
     * Listar certificados con filtros y paginación
     */
    public function index(Request $request)
    {
        try {
            $page    = $request->input('page', 1);
            $limit   = $request->input('limit', 10);
            $search  = $request->input('search', '');
            $estado  = $request->input('estado', '');
            $desde   = $request->input('desde', '');
            $hasta   = $request->input('hasta', '');
            $idCedula = $request->input('id_cedula_presupuestaria', '');

            $offset = ($page - 1) * $limit;

            $query = Certificacion::with('usuario', 'entidadRequiriente');

            // RBAC: Analista solo ve sus propios certificados
            $u = Auth::user();
            if (!$u) {
                return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
            }
            $rolesDirector = ['Director(a) financiero', 'Analista de presupuesto 3', 'Administrador del sistema'];
            if (!in_array($u->cargo, $rolesDirector)) {
                $query->where('id_usuario', $u->id_usuario);
            }

            // Filtro por año fiscal (cédula)
            if ($idCedula) {
                $query->where('id_cedula_presupuestaria', $idCedula);
            }

            // Filtros
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('numero_certificado', 'LIKE', "%$search%")
                      ->orWhere('descripcion', 'LIKE', "%$search%");
                });
            }

            if ($estado) {
                $query->where('estado', $estado);
            } else {
                // Ocultar lógicamente eliminados salvo filtro explícito
                $query->where('estado', '!=', 'ERRADO');
            }

            if ($desde) {
                $query->whereDate('fecha_elaboracion', '>=', $desde);
            }

            if ($hasta) {
                $query->whereDate('fecha_elaboracion', '<=', $hasta);
            }

            $total = $query->count();
            $certificados = $query->orderBy('id_certificacion', 'DESC')
                                  ->offset($offset)
                                  ->limit($limit)
                                  ->get();

            $certIds = $certificados->pluck('id_certificacion');

            // Precarga montos totales por certificación (1 query)
            $montoTotales = DB::table('certificacion_items')
                ->whereIn('id_certificacion', $certIds)
                ->selectRaw('id_certificacion, SUM(monto) as total')
                ->groupBy('id_certificacion')
                ->pluck('total', 'id_certificacion');

            // Precarga liquidado total por certificación (1 query)
            $liquidadoTotales = DB::table('liquidaciones')
                ->join('certificacion_items', 'liquidaciones.id_certificacion_item', '=', 'certificacion_items.id_certificacion_item')
                ->whereIn('certificacion_items.id_certificacion', $certIds)
                ->where('liquidaciones.estado', '!=', 'ANULADA')
                ->selectRaw('certificacion_items.id_certificacion, SUM(liquidaciones.cantidad_liquidacion) as total')
                ->groupBy('certificacion_items.id_certificacion')
                ->pluck('total', 'id_certificacion');

            // Precarga pendiente por item para todas las certificaciones (2 queries)
            $allCertItems = DB::table('certificacion_items')
                ->whereIn('id_certificacion', $certIds)
                ->select('id_certificacion_item', 'id_certificacion', 'monto')
                ->get();

            $liqPorItem = DB::table('liquidaciones')
                ->whereIn('id_certificacion_item', $allCertItems->pluck('id_certificacion_item'))
                ->where('estado', '!=', 'ANULADA')
                ->selectRaw('id_certificacion_item, SUM(cantidad_liquidacion) as total')
                ->groupBy('id_certificacion_item')
                ->pluck('total', 'id_certificacion_item');

            $pendientePorCert = $allCertItems->groupBy('id_certificacion')->map(function ($items) use ($liqPorItem) {
                return $items->reduce(function ($carry, $item) use ($liqPorItem) {
                    $liq = (float) ($liqPorItem[$item->id_certificacion_item] ?? 0);
                    return $carry + max(0, (float) $item->monto - $liq);
                }, 0.0);
            });

            $data = $certificados->map(function ($cert) use ($montoTotales, $liquidadoTotales, $pendientePorCert) {
                $montoTotal = (float) ($montoTotales[$cert->id_certificacion] ?? 0);
                $liquidado  = (float) ($liquidadoTotales[$cert->id_certificacion] ?? 0);
                $pendiente  = (float) ($pendientePorCert[$cert->id_certificacion] ?? 0);

                $fecha = $cert->fecha_elaboracion;
                if (is_string($fecha)) {
                    $fecha = Carbon::createFromFormat('Y-m-d', $fecha);
                }

                return [
                    'id_certificacion'   => $cert->id_certificacion,
                    'numero_certificado' => $cert->numero_certificado,
                    'institucion'        => $cert->entidadRequiriente?->nombre_entidad ?? '-',
                    'id_usuario'         => $cert->id_usuario,
                    'usuario'            => $cert->usuario ? trim($cert->usuario->nombres . ' ' . $cert->usuario->apellidos) : '-',
                    'fecha_elaboracion'  => $fecha->format('d/m/Y'),
                    'monto_total'        => number_format($montoTotal, 2, ',', '.'),
                    'liquidado'          => number_format($liquidado,  2, ',', '.'),
                    'pendiente'          => number_format($pendiente,  2, ',', '.'),
                    'estado'             => $cert->estado,
                    'motivo_rechazo'     => $cert->motivo_rechazo,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'last_page' => ceil($total / $limit),
                    'per_page' => $limit
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo certificado con items
     */
    public function store(Request $request)
    {
        $cargo = Auth::user()?->cargo;
        if (!RolPermiso::tiene($cargo, 'certificaciones', 'crear')) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para crear certificaciones'], 403);
        }


        $request->validate([
            'descripcion' => 'required|string|max:1000',
            'clase_registro' => 'required|string|max:100',
            'clase_gasto' => 'required|string|max:100',
            'tipo_doc_respaldo' => 'required|string|max:100',
            'clase_doc_respaldo' => 'required|string|max:100',
            'seccion_memorando' => 'nullable|string|max:100',
            'id_unidad_requiriente' => 'required|exists:unidad_requiriente,id_unidad_requiriente',
            'id_cedula_presupuestaria' => 'required|exists:cedula_presupuestaria,id_cedula_presupuestaria',
            'items' => 'required|array|min:1',
            'items.*.id_programa' => 'required|exists:programa,id_programa',
            'items.*.id_subprograma' => 'required|exists:subprograma,id_subprograma',
            'items.*.id_proyecto' => 'required|exists:proyecto,id_proyecto',
            'items.*.id_actividad' => 'required|exists:actividad,id_actividad',
            'items.*.id_fuente' => 'required|exists:fuente_financiamiento,id_fuente',
            'items.*.id_ubicacion' => 'required|exists:ubicacion,id_ubicacion',
            'items.*.id_item' => 'required|exists:items,id_item',
            'items.*.id_organismo' => 'required|exists:organismos,id_organismo',
            'items.*.id_naturaleza' => 'required|exists:naturaleza_prestacion,id_naturaleza',
            'items.*.monto' => 'required|numeric|min:0.01',
        ]);

        if (!DB::table('fuente_items')
                ->where('id_cedula_presupuestaria', $request->id_cedula_presupuestaria)
                ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No existe estructura presupuestaria para el año seleccionado. Cargue los ítems del año antes de certificar.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generar número de certificado automático (lockForUpdate evita carrera de datos)
            $ultimoCertificado = Certificacion::lockForUpdate()->orderBy('id_certificacion', 'DESC')->first();
            $numero = ($ultimoCertificado?->id_certificacion ?? 0) + 1;
            $numeroCertificado = str_pad($numero, 3, '0', STR_PAD_LEFT);

            $certificado = Certificacion::create([
                'numero_certificado' => $numeroCertificado,
                'descripcion' => $request->descripcion,
                'fecha_elaboracion' => now()->toDateString(),
                'clase_registro' => $request->clase_registro,
                'clase_gasto' => $request->clase_gasto,
                'tipo_doc_respaldo' => $request->tipo_doc_respaldo,
                'clase_doc_respaldo' => $request->clase_doc_respaldo,
                'seccion_memorando' => $request->seccion_memorando,
                'estado' => 'REGISTRADO',
                'id_usuario' => auth()->id(),
                'id_unidad_requiriente' => $request->id_unidad_requiriente,
                'id_cedula_presupuestaria' => $request->id_cedula_presupuestaria
            ]);

            // Agregar items
            foreach ($request->items as $item) {
                CertificacionItem::create([
                    'id_certificacion' => $certificado->id_certificacion,
                    'id_item' => $item['id_item'],
                    'id_programa' => $item['id_programa'],
                    'id_subprograma' => $item['id_subprograma'],
                    'id_proyecto' => $item['id_proyecto'],
                    'id_actividad' => $item['id_actividad'],
                    'id_fuente' => $item['id_fuente'],
                    'id_ubicacion' => $item['id_ubicacion'],
                    'id_organismo' => $item['id_organismo'],
                    'id_naturaleza' => $item['id_naturaleza'],
                    'monto' => $item['monto']
                ]);
            }

            $certificado->actualizarMontoTotal();

            DB::commit();

            try {
                $u = Auth::user();
                Auditoria::create([
                    'id_certificacion'   => $certificado->id_certificacion,
                    'numero_certificado' => $certificado->numero_certificado,
                    'id_usuario'         => $u?->id_usuario ?? null,
                    'nombre_usuario'     => $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema',
                    'accion'             => 'CREACIÓN',
                    'estado_nuevo'       => $certificado->estado,
                    'monto_nuevo'        => (float) $certificado->items()->sum('monto'),
                    'fecha_hora'         => now(),
                ]);
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::store error: ' . $ae->getMessage());
            }

            // Solo Analista 1 notifica a Analista 3 al crear
            if ($cargo === 'Analista de presupuesto 1') {
                $this->notificarAnalista3($certificado, Auth::user(), 'creacion');
            }

            return response()->json([
                'success' => true,
                'message' => 'Certificado creado exitosamente con ' . count($request->items) . ' item(s)',
                'data' => $certificado->getDetalles()
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    private function notificarAnalista3($certificado, $analista, string $tipo = 'creacion'): void
    {
        try {
            $destinatarios = User::where('cargo', 'Analista de presupuesto 3')
                ->where(DB::raw('LOWER(estado)'), 'activo')
                ->get();

            $nombreAnalista = $analista
                ? trim($analista->nombres . ' ' . $analista->apellidos)
                : 'Analista';

            $fecha = now()->format('d/m/Y H:i');

            foreach ($destinatarios as $destinatario) {
                if ($tipo === 'creacion') {
                    $asunto = "Certificación N.° {$certificado->numero_certificado} — Pendiente de revisión";
                    $cuerpo = "Estimado/a {$destinatario->nombres},\n\n"
                        . "Por medio del presente, se le comunica que el/la Analista de Presupuesto {$nombreAnalista} "
                        . "ha registrado la Certificación Presupuestaria N.° {$certificado->numero_certificado}, "
                        . "la cual se encuentra pendiente de su revisión y aprobación.\n\n"
                        . "Detalle de la certificación:\n"
                        . "  - Número:      {$certificado->numero_certificado}\n"
                        . "  - Descripción: {$certificado->descripcion}\n"
                        . "  - Fecha:       {$certificado->fecha_elaboracion}\n\n"
                        . "Se le solicita cordialmente ingresar al Sistema de Control Presupuestario "
                        . "para proceder con la revisión correspondiente.\n\n"
                        . "Atentamente,\n"
                        . "Sistema de Control Presupuestario\n"
                        . "Universidad Estatal de Bolívar";
                } else {
                    $asunto = "Certificación N.° {$certificado->numero_certificado} — Reenviada para revisión";
                    $cuerpo = "Estimado/a {$destinatario->nombres},\n\n"
                        . "Se le informa que el/la Analista de Presupuesto {$nombreAnalista} ha realizado "
                        . "las correcciones solicitadas y ha reenviado la Certificación Presupuestaria "
                        . "N.° {$certificado->numero_certificado} para su nueva revisión y aprobación.\n\n"
                        . "Fecha de reenvío: {$fecha}\n\n"
                        . "Se le solicita cordialmente ingresar al Sistema de Control Presupuestario "
                        . "para proceder con la revisión correspondiente.\n\n"
                        . "Atentamente,\n"
                        . "Sistema de Control Presupuestario\n"
                        . "Universidad Estatal de Bolívar";
                }

                $this->enviarCorreo($destinatario->correo_institucional, $destinatario->nombres, $asunto, $cuerpo);
            }
        } catch (\Throwable $e) {
            \Log::warning('notificarAnalista3 error: ' . $e->getMessage());
        }
    }

    private function notificarAprobacion($cert, $aprobador): void
    {
        $nombreAprobador = trim($aprobador->nombres . ' ' . $aprobador->apellidos);
        $cargoAprobador  = $aprobador->cargo;
        $esAnalista3     = $cargoAprobador === 'Analista de presupuesto 3';
        $fecha           = now()->format('d/m/Y H:i');

        // 1. Notificar al creador que su certificación fue aprobada
        $creador = User::find($cert->id_usuario);
        if ($creador) {
            if (!$creador->correo_institucional) {
                \Log::warning("El usuario creador (id={$creador->id_usuario}) no tiene correo institucional registrado.");
            } else {
                $asunto = "Certificación N.° {$cert->numero_certificado} — Aprobada";
                $cuerpo = "Estimado/a {$creador->nombres},\n\n"
                    . "Me permito comunicarle que la Certificación Presupuestaria N.° {$cert->numero_certificado} "
                    . "ha sido APROBADA por {$nombreAprobador}, {$cargoAprobador}.\n\n"
                    . "Fecha de aprobación: {$fecha}\n\n"
                    . "Puede ingresar al Sistema de Control Presupuestario para consultar el detalle completo.\n\n"
                    . "Atentamente,\n"
                    . "Sistema de Control Presupuestario\n"
                    . "Universidad Estatal de Bolívar";
                $this->enviarCorreo($creador->correo_institucional, $creador->nombres, $asunto, $cuerpo);
            }
        } else {
            \Log::warning("No se encontró el creador de la certificación id={$cert->id_certificacion}");
        }

        // 2. Si aprobó el Analista 3, notificar también al Director Financiero
        if ($esAnalista3) {
            $directores = User::where('cargo', 'Director(a) financiero')
                ->where(DB::raw('LOWER(estado)'), 'activo')
                ->get();

            foreach ($directores as $director) {
                if (!$director->correo_institucional) continue;
                $asunto = "Certificación N.° {$cert->numero_certificado} — Aprobada por Analista de Presupuesto 3";
                $cuerpo = "Estimado/a {$director->nombres},\n\n"
                    . "Se le informa que la Certificación Presupuestaria N.° {$cert->numero_certificado} "
                    . "ha sido aprobada por el/la Analista de Presupuesto 3: {$nombreAprobador}.\n\n"
                    . "Fecha de aprobación: {$fecha}\n\n"
                    . "Puede ingresar al Sistema de Control Presupuestario para consultar el detalle.\n\n"
                    . "Atentamente,\n"
                    . "Sistema de Control Presupuestario\n"
                    . "Universidad Estatal de Bolívar";
                $this->enviarCorreo($director->correo_institucional, $director->nombres, $asunto, $cuerpo);
            }
        }
    }

    /**
     * Obtener detalles de un certificado
     */
    public function show($id)
    {
        try {
            $certificado = Certificacion::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $certificado->getDetalles()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar item al certificado
     */
    public function agregarItem(Request $request, $idCertificacion)
    {
        $request->validate([
            'id_programa' => 'required|exists:programa,id_programa',
            'id_subprograma' => 'required|exists:subprograma,id_subprograma',
            'id_proyecto' => 'required|exists:proyecto,id_proyecto',
            'id_actividad' => 'required|exists:actividad,id_actividad',
            'id_fuente' => 'required|exists:fuente_financiamiento,id_fuente',
            'id_ubicacion' => 'required|exists:ubicacion,id_ubicacion',
            'id_item' => 'required|exists:items,id_item',
            'id_organismo' => 'required|exists:organismos,id_organismo',
            'id_naturaleza' => 'required|exists:naturaleza_prestacion,id_naturaleza',
            'monto' => 'required|numeric|min:0.01',
        ]);

        try {
            $certificado = Certificacion::findOrFail($idCertificacion);

            $u = Auth::user();
            if (!RolPermiso::tiene($u?->cargo, 'certificaciones', 'aprobar') && (int) $certificado->id_usuario !== (int) $u?->id_usuario) {
                return response()->json(['success' => false, 'message' => 'No puede agregar ítems a una certificación de otro usuario'], 403);
            }
            if ($certificado->estado !== 'REGISTRADO') {
                return response()->json(['success' => false, 'message' => 'Solo se pueden agregar ítems a certificaciones en estado REGISTRADO'], 422);
            }

            // Verificar que el item no esté duplicado en este certificado
            $existente = CertificacionItem::where('id_certificacion', $idCertificacion)
                                          ->where('id_item', $request->id_item)
                                          ->where('id_fuente', $request->id_fuente)
                                          ->first();

            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este item ya está agregado al certificado'
                ], 422);
            }

            $montoAnterior = (float) $certificado->items()->sum('monto');

            // Crear registro en tabla intermedia
            $item = CertificacionItem::create([
                'id_certificacion' => $idCertificacion,
                'id_item' => $request->id_item,
                'id_programa' => $request->id_programa,
                'id_subprograma' => $request->id_subprograma,
                'id_proyecto' => $request->id_proyecto,
                'id_actividad' => $request->id_actividad,
                'id_fuente' => $request->id_fuente,
                'id_ubicacion' => $request->id_ubicacion,
                'id_organismo' => $request->id_organismo,
                'id_naturaleza' => $request->id_naturaleza,
                'monto' => $request->monto
            ]);

            // Actualizar monto total del certificado
            $certificado->actualizarMontoTotal();
            $montoNuevo = (float) $certificado->items()->sum('monto');

            try {
                $u = Auth::user();
                Auditoria::create([
                    'id_certificacion'   => $certificado->id_certificacion,
                    'numero_certificado' => $certificado->numero_certificado,
                    'id_usuario'         => $u?->id_usuario ?? null,
                    'nombre_usuario'     => $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema',
                    'accion'             => 'EDICIÓN',
                    'monto_anterior'     => $montoAnterior,
                    'monto_nuevo'        => $montoNuevo,
                    'campo_modificado'   => 'monto_total',
                    'fecha_hora'         => now(),
                ]);
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::agregarItem error: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Item agregado exitosamente',
                'data' => $certificado->getDetalles()
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar monto de un item del certificado
     */
    public function actualizarItem(Request $request, $idCertificacion, $idItem)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
        ]);

        try {
            $certificado = Certificacion::findOrFail($idCertificacion);

            $u = Auth::user();
            if (!RolPermiso::tiene($u?->cargo, 'certificaciones', 'aprobar') && (int) $certificado->id_usuario !== (int) $u?->id_usuario) {
                return response()->json(['success' => false, 'message' => 'No puede editar ítems de una certificación ajena'], 403);
            }

            $montoAnterior = (float) $certificado->items()->sum('monto');

            $item = CertificacionItem::where('id_certificacion', $idCertificacion)
                                      ->where('id_certificacion_item', $idItem)
                                      ->firstOrFail();

            $item->update(['monto' => $request->monto]);

            $certificado->actualizarMontoTotal();
            $montoNuevo = (float) $certificado->items()->sum('monto');

            try {
                $u = Auth::user();
                Auditoria::create([
                    'id_certificacion'   => $certificado->id_certificacion,
                    'numero_certificado' => $certificado->numero_certificado,
                    'id_usuario'         => $u?->id_usuario ?? null,
                    'nombre_usuario'     => $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema',
                    'accion'             => 'EDICIÓN',
                    'monto_anterior'     => $montoAnterior,
                    'monto_nuevo'        => $montoNuevo,
                    'campo_modificado'   => 'monto_total',
                    'fecha_hora'         => now(),
                ]);
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::actualizarItem error: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Monto actualizado exitosamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover item del certificado
     */
    public function removerItem($idCertificacion, $idItem)
    {
        try {
            $certificado = Certificacion::findOrFail($idCertificacion);

            $u = Auth::user();
            if (!RolPermiso::tiene($u?->cargo, 'certificaciones', 'aprobar') && (int) $certificado->id_usuario !== (int) $u?->id_usuario) {
                return response()->json(['success' => false, 'message' => 'No puede eliminar ítems de una certificación ajena'], 403);
            }

            $montoAnterior = (float) $certificado->items()->sum('monto');

            $item = CertificacionItem::where('id_certificacion', $idCertificacion)
                                      ->where('id_certificacion_item', $idItem)
                                      ->firstOrFail();

            $item->delete();

            // Actualizar monto total del certificado
            $certificado->actualizarMontoTotal();
            $montoNuevo = (float) $certificado->items()->sum('monto');

            try {
                $u = Auth::user();
                Auditoria::create([
                    'id_certificacion'   => $certificado->id_certificacion,
                    'numero_certificado' => $certificado->numero_certificado,
                    'id_usuario'         => $u?->id_usuario ?? null,
                    'nombre_usuario'     => $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema',
                    'accion'             => 'EDICIÓN',
                    'monto_anterior'     => $montoAnterior,
                    'monto_nuevo'        => $montoNuevo,
                    'campo_modificado'   => 'monto_total',
                    'fecha_hora'         => now(),
                ]);
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::removerItem error: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Item removido exitosamente',
                'data' => $certificado->getDetalles()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar certificado
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'descripcion' => 'nullable|string|max:1000',
            'clase_registro' => 'nullable|string|max:100',
            'clase_gasto' => 'nullable|string|max:100',
            'tipo_doc_respaldo' => 'nullable|string|max:100',
            'clase_doc_respaldo' => 'nullable|string|max:100',
            'estado' => 'nullable|in:REGISTRADO,APROBADO,RECHAZADO,LIQUIDADO,ERRADO'
        ]);

        $u          = Auth::user();
        $esDirector = RolPermiso::tiene($u?->cargo, 'certificaciones', 'aprobar');

        try {
            $certificado = Certificacion::findOrFail($id);

            if (!$esDirector) {
                if ((int) $certificado->id_usuario !== (int) $u?->id_usuario) {
                    return response()->json(['success' => false, 'message' => 'No puede editar una certificación de otro usuario'], 403);
                }
                if ($certificado->estado === 'ERRADO') {
                    return response()->json(['success' => false, 'message' => 'No se puede editar una certificación marcada como errada'], 403);
                }
                // No puede cambiar el estado vía edición (eso se hace por aprobar/rechazar/reenviar)
                if ($request->has('estado')) {
                    return response()->json(['success' => false, 'message' => 'No tiene permiso para cambiar el estado directamente'], 403);
                }
            }

            $estadoAnterior = $certificado->estado;
            $montoAnterior  = $certificado->monto_total;

            $fields = $request->only([
                'descripcion',
                'clase_registro',
                'clase_gasto',
                'tipo_doc_respaldo',
                'clase_doc_respaldo',
                'estado'
            ]);

            $certificado->update($fields);

            try {
                $u      = Auth::user();
                $uid    = $u?->id_usuario ?? null;
                $nombre = $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema';
                $changed = $certificado->getChanges();

                if (isset($changed['estado'])) {
                    Auditoria::create([
                        'id_certificacion'   => $certificado->id_certificacion,
                        'numero_certificado' => $certificado->numero_certificado,
                        'id_usuario'         => $uid,
                        'nombre_usuario'     => $nombre,
                        'accion'             => 'CAMBIO_ESTADO',
                        'estado_anterior'    => $estadoAnterior,
                        'estado_nuevo'       => $changed['estado'],
                        'campo_modificado'   => 'estado',
                        'fecha_hora'         => now(),
                    ]);
                } elseif (isset($changed['monto_total'])) {
                    Auditoria::create([
                        'id_certificacion'   => $certificado->id_certificacion,
                        'numero_certificado' => $certificado->numero_certificado,
                        'id_usuario'         => $uid,
                        'nombre_usuario'     => $nombre,
                        'accion'             => 'EDICIÓN',
                        'monto_anterior'     => $montoAnterior,
                        'monto_nuevo'        => $changed['monto_total'],
                        'campo_modificado'   => 'monto_total',
                        'fecha_hora'         => now(),
                    ]);
                } elseif (!empty($changed)) {
                    $campo = array_key_first(array_diff_key($changed, ['updated_at' => true]));
                    if ($campo) {
                        Auditoria::create([
                            'id_certificacion'   => $certificado->id_certificacion,
                            'numero_certificado' => $certificado->numero_certificado,
                            'id_usuario'         => $uid,
                            'nombre_usuario'     => $nombre,
                            'accion'             => 'EDICIÓN',
                            'campo_modificado'   => $campo,
                            'fecha_hora'         => now(),
                        ]);
                    }
                }
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::update error: ' . $ae->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Certificado actualizado exitosamente',
                'data' => $certificado->getDetalles()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar certificado
     */
    public function destroy($id)
    {
        $u          = Auth::user();
        $esDirector = RolPermiso::tiene($u?->cargo, 'certificaciones', 'aprobar');

        try {
            $certificado = Certificacion::findOrFail($id);

            if (!$esDirector) {
                if ((int) $certificado->id_usuario !== (int) $u?->id_usuario) {
                    return response()->json(['success' => false, 'message' => 'No puede eliminar una certificación de otro usuario'], 403);
                }
                if (!in_array($certificado->estado, ['REGISTRADO', 'RECHAZADO'])) {
                    return response()->json(['success' => false, 'message' => 'Solo puede eliminar certificaciones en estado REGISTRADO o RECHAZADO'], 403);
                }
            }

            try {
                $u = Auth::user();
                Auditoria::create([
                    'id_certificacion'   => $certificado->id_certificacion,
                    'numero_certificado' => $certificado->numero_certificado,
                    'id_usuario'         => $u?->id_usuario ?? null,
                    'nombre_usuario'     => $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema',
                    'accion'             => 'ELIMINACIÓN',
                    'estado_anterior'    => $certificado->estado,
                    'estado_nuevo'       => 'ERRADO',
                    'fecha_hora'         => now(),
                ]);
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::destroy error: ' . $ae->getMessage());
            }

            // Soft delete lógico: cambia estado a ERRADO sin borrar de la BD
            $certificado->estado = 'ERRADO';
            $certificado->save();

            return response()->json([
                'success' => true,
                'message' => 'Certificado marcado como ERRADO exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos para cascadas: programas
     * ?cedula=id  → solo programas que tienen ítems en esa cédula
     */
    public function getProgramas(Request $request)
    {
        try {
            $cedula = $request->query('cedula');

            $q = Programa::select('id_programa', 'cod_programa', 'nombre_programa');

            if ($cedula) {
                $q->whereExists(fn($sub) =>
                    $sub->selectRaw('1')->from('fuente_items as fi')
                        ->join('items as i',        'fi.id_item',        '=', 'i.id_item')
                        ->join('actividad as a',    'i.id_actividad',    '=', 'a.id_actividad')
                        ->join('proyecto as pr',    'a.id_proyecto',     '=', 'pr.id_proyecto')
                        ->join('subprograma as sp', 'pr.id_subprograma', '=', 'sp.id_subprograma')
                        ->whereColumn('sp.id_programa', 'programa.id_programa')
                        ->where('fi.id_cedula_presupuestaria', $cedula)
                );
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener subprogramas por programa
     */
    public function getSubprogramas(Request $request, $idPrograma)
    {
        try {
            $cedula = $request->query('cedula');

            $q = Subprograma::where('id_programa', $idPrograma)
                            ->select('id_subprograma', 'cod_subprograma', 'nombre_subprograma');

            if ($cedula) {
                $q->whereExists(fn($sub) =>
                    $sub->selectRaw('1')->from('fuente_items as fi')
                        ->join('items as i',     'fi.id_item',     '=', 'i.id_item')
                        ->join('actividad as a', 'i.id_actividad', '=', 'a.id_actividad')
                        ->join('proyecto as pr', 'a.id_proyecto',  '=', 'pr.id_proyecto')
                        ->whereColumn('pr.id_subprograma', 'subprograma.id_subprograma')
                        ->where('fi.id_cedula_presupuestaria', $cedula)
                );
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener proyectos por subprograma
     */
    public function getProyectos(Request $request, $idSubprograma)
    {
        try {
            $cedula = $request->query('cedula');

            $q = Proyecto::where('id_subprograma', $idSubprograma)
                         ->select('id_proyecto', 'cod_proyecto', 'nombre_proyecto');

            if ($cedula) {
                $q->whereExists(fn($sub) =>
                    $sub->selectRaw('1')->from('fuente_items as fi')
                        ->join('items as i',     'fi.id_item',     '=', 'i.id_item')
                        ->join('actividad as a', 'i.id_actividad', '=', 'a.id_actividad')
                        ->whereColumn('a.id_proyecto', 'proyecto.id_proyecto')
                        ->where('fi.id_cedula_presupuestaria', $cedula)
                );
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener actividades por proyecto
     */
    public function getActividades(Request $request, $idProyecto)
    {
        try {
            $cedula = $request->query('cedula');

            $q = Actividad::where('id_proyecto', $idProyecto)
                          ->select('id_actividad', 'cod_actividad', 'nombre_actividad');

            if ($cedula) {
                $q->whereExists(fn($sub) =>
                    $sub->selectRaw('1')->from('fuente_items as fi')
                        ->join('items as i', 'fi.id_item', '=', 'i.id_item')
                        ->whereColumn('i.id_actividad', 'actividad.id_actividad')
                        ->where('fi.id_cedula_presupuestaria', $cedula)
                );
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Ítems filtrados por actividad + fuente — jerarquía G7→G5
     * Sin duplicados (dentro de una fuente cada cod_item es único).
     * Incluye G6/G8/G9 para auto-completar el formulario.
     */
    public function getItemsByActividadFuente(Request $request, $idActividad)
    {
        try {
            $idFuente = $request->query('fuente');
            $cedula   = $request->query('cedula');

            $q = DB::table('items as i')
                ->join('fuente_items as fi',           'fi.id_item',       '=', 'i.id_item')
                ->join('ubicacion as ub',              'ub.id_ubicacion',  '=', 'i.id_ubicacion')
                ->join('organismos as org',            'org.id_organismo', '=', 'i.id_organismo')
                ->join('naturaleza_prestacion as np',  'np.id_naturaleza', '=', 'i.id_naturaleza')
                ->where('i.id_actividad', $idActividad)
                ->select(
                    'i.id_item', 'i.cod_item', 'i.nombre_item',
                    'i.id_ubicacion',  'ub.cod_ubicacion',  'ub.nombre_ubicacion',
                    'i.id_organismo',  'org.cod_organismo', 'org.nombre_organismo',
                    'i.id_naturaleza', 'np.cod_naturaleza', 'np.nombre_naturaleza'
                )
                ->distinct();

            if ($idFuente) {
                $q->where('fi.id_fuente', $idFuente);
            }
            if ($cedula) {
                $q->where('fi.id_cedula_presupuestaria', $cedula);
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener fuentes financiamiento (todas)
     */
    public function getFuentes()
    {
        try {
            $fuentes = FuenteFinanciamiento::select('id_fuente', 'cod_fuente', 'nombre_fuente')->get();
            return response()->json(['success' => true, 'data' => $fuentes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener fuentes por actividad
     */
    public function getFuentesByActividad(Request $request, $idActividad)
    {
        try {
            $cedula = $request->query('cedula');

            $q = DB::table('fuente_financiamiento as f')
                ->join('fuente_items as fi', 'f.id_fuente', '=', 'fi.id_fuente')
                ->join('items as i',         'fi.id_item',  '=', 'i.id_item')
                ->where('i.id_actividad', $idActividad)
                ->select('f.id_fuente', 'f.cod_fuente', 'f.nombre_fuente')
                ->distinct();

            if ($cedula) {
                $q->where('fi.id_cedula_presupuestaria', $cedula);
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener ubicaciones por actividad
     */
    public function getUbicaciones(Request $request, $idActividad)
    {
        try {
            $cedula = $request->query('cedula');

            $q = DB::table('ubicacion as u')
                ->join('items as i',       'i.id_ubicacion', '=', 'u.id_ubicacion')
                ->join('fuente_items as fi','fi.id_item',     '=', 'i.id_item')
                ->where('i.id_actividad', $idActividad)
                ->select('u.id_ubicacion', 'u.cod_ubicacion', 'u.nombre_ubicacion')
                ->distinct();

            if ($cedula) {
                $q->where('fi.id_cedula_presupuestaria', $cedula);
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener items por actividad y ubicación
     */
    public function getItems(Request $request, $idActividad, $idUbicacion)
    {
        try {
            $cedula = $request->query('cedula');

            $q = DB::table('items as i')
                ->join('fuente_items as fi', 'fi.id_item', '=', 'i.id_item')
                ->join('organismos as org', 'org.id_organismo', '=', 'i.id_organismo')
                ->join('naturaleza_prestacion as np', 'np.id_naturaleza', '=', 'i.id_naturaleza')
                ->where('i.id_actividad', $idActividad)
                ->where('i.id_ubicacion', $idUbicacion)
                ->select(
                    'i.id_item', 'i.cod_item', 'i.nombre_item',
                    'i.id_organismo', 'org.cod_organismo', 'org.nombre_organismo',
                    'i.id_naturaleza', 'np.cod_naturaleza', 'np.nombre_naturaleza'
                )
                ->distinct();

            if ($cedula) {
                $q->where('fi.id_cedula_presupuestaria', $cedula);
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener items por actividad, ubicación y fuente — incluye organismo y N Prestación
     */
    public function getItemsByFuente(Request $request, $idActividad, $idUbicacion, $idFuente)
    {
        try {
            $cedula = $request->query('cedula');

            $q = DB::table('items as i')
                ->join('fuente_items as fi', 'fi.id_item', '=', 'i.id_item')
                ->join('organismos as org', 'org.id_organismo', '=', 'i.id_organismo')
                ->join('naturaleza_prestacion as np', 'np.id_naturaleza', '=', 'i.id_naturaleza')
                ->where('i.id_actividad', $idActividad)
                ->where('i.id_ubicacion', $idUbicacion)
                ->where('fi.id_fuente', $idFuente)
                ->select(
                    'i.id_item', 'i.cod_item', 'i.nombre_item',
                    'i.id_organismo', 'org.cod_organismo', 'org.nombre_organismo',
                    'i.id_naturaleza', 'np.cod_naturaleza', 'np.nombre_naturaleza'
                )
                ->distinct();

            if ($cedula) {
                $q->where('fi.id_cedula_presupuestaria', $cedula);
            }

            return response()->json(['success' => true, 'data' => $q->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener organismos
     */
    public function getOrganismos()
    {
        try {
            $organismos = Organismo::select('id_organismo', 'cod_organismo', 'nombre_organismo')
                                    ->get();

            return response()->json([
                'success' => true,
                'data' => $organismos
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener unidades requirientes
     */
    public function getEntidadesRequirientes()
    {
        try {
            $entidades = EntidadRequiriente::select(
                'id_unidad_requiriente',
                'nombre_entidad',
                'responsable_entidad',
                'correo_institucional'
            )->get();

            return response()->json([
                'success' => true,
                'data' => $entidades
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva entidad requiriente
     */
    public function createEntidadRequiriente(Request $request)
    {
        try {
            $request->validate([
                'nombre_entidad' => 'required|string|max:100',
                'responsable_entidad' => 'required|string|max:100',
                'correo_institucional' => 'required|email|max:100',
            ]);

            $entidad = EntidadRequiriente::create([
                'nombre_entidad' => $request->nombre_entidad,
                'responsable_entidad' => $request->responsable_entidad,
                'correo_institucional' => $request->correo_institucional,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Unidad requiriente creada exitosamente',
                'data' => $entidad
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener cédulas presupuestarias
     */
    public function getCedulasPresupuestarias()
    {
        try {
            $cedulas = CedulaPresupuestaria::select('id_cedula_presupuestaria', 'anio')
                                           ->orderBy('anio', 'DESC')
                                           ->get();

            $conEstructura = DB::table('fuente_items')
                ->whereIn('id_cedula_presupuestaria', $cedulas->pluck('id_cedula_presupuestaria'))
                ->distinct()
                ->pluck('id_cedula_presupuestaria')
                ->flip();

            $cedulas = $cedulas->map(function ($cedula) use ($conEstructura) {
                return [
                    'id_cedula_presupuestaria' => $cedula->id_cedula_presupuestaria,
                    'anio'             => $cedula->anio,
                    'display'          => 'Año ' . $cedula->anio,
                    'tiene_estructura' => isset($conEstructura[$cedula->id_cedula_presupuestaria]),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $cedulas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener cédula del año actual
     */
    public function getCedulaActual()
    {
        try {
            $añoActual = now()->year;
            $cedula = CedulaPresupuestaria::where('anio', $añoActual)->first();

            if (!$cedula) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay cédula para el año ' . $añoActual
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id_cedula_presupuestaria' => $cedula->id_cedula_presupuestaria,
                    'anio' => $cedula->anio
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva cédula presupuestaria (Ya no es necesario - se crean en la migración)
     */
    public function createCedulaPresupuestaria(Request $request)
    {
        try {
            return response()->json([
                'success' => false,
                'message' => 'Las cédulas presupuestarias se crean automáticamente en la migración. Año actual + 3 años anteriores.'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener naturaleza prestación
     */
    public function getNaturalezas()
    {
        try {
            $naturalezas = NaturalezaPrestacion::select('id_naturaleza', 'cod_naturaleza', 'nombre_naturaleza')
                                               ->get();

            return response()->json([
                'success' => true,
                'data' => $naturalezas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar monto disponible para certificar de un item
     * Compara el codificado (asignado + modificado) con lo ya certificado
     * Los certificados se crean YA APROBADOS, no hay pendientes
     */
    public function verificarMontoDisponible(Request $request, $idItem, $idFuente)
    {
        try {
            $cedula = $request->query('cedula');

            // Obtener el registro de fuente_items
            $q = DB::table('fuente_items')
                ->where('id_item', $idItem)
                ->where('id_fuente', $idFuente)
                ->select('asignado', 'modificado');

            if ($cedula) {
                $q->where('id_cedula_presupuestaria', $cedula);
            }

            $fuenteItem = $q->first();

            if (!$fuenteItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item no encontrado en la fuente presupuestaria'
                ], 404);
            }

            $asignado   = round(floatval($fuenteItem->asignado   ?? 0), 2);
            $modificado = round(floatval($fuenteItem->modificado ?? 0), 2);

            // Certificado bruto: suma de todos los montos aprobados/liquidados
            $certificado_bruto = round((float) DB::table('certificacion_items')
                ->join('certificacion', 'certificacion_items.id_certificacion', '=', 'certificacion.id_certificacion')
                ->where('certificacion_items.id_item', $idItem)
                ->where('certificacion_items.id_fuente', $idFuente)
                ->whereIn('certificacion.estado', ['APROBADO', 'LIQUIDADO'])
                ->sum('certificacion_items.monto'), 2);

            // Lo ya liquidado (pagado) de esas certificaciones
            $ya_liquidado = round((float) DB::table('liquidaciones')
                ->join('certificacion_items', 'liquidaciones.id_certificacion_item', '=', 'certificacion_items.id_certificacion_item')
                ->join('certificacion', 'certificacion_items.id_certificacion', '=', 'certificacion.id_certificacion')
                ->where('certificacion_items.id_item', $idItem)
                ->where('certificacion_items.id_fuente', $idFuente)
                ->whereIn('certificacion.estado', ['APROBADO', 'LIQUIDADO'])
                ->sum('liquidaciones.cantidad_liquidacion'), 2);

            // Certificado neto = pendiente de pago (igual que cédula)
            $certificado_neto = round(max(0, $certificado_bruto - $ya_liquidado), 2);

            // Codificado = Asignado + Modificado
            $codificado = round($asignado + $modificado, 2);

            // Disponible = Codificado - Certificado neto (igual que Saldo Disponible en cédula)
            $disponible_final = round($codificado - $certificado_neto, 2);

            return response()->json([
                'success' => true,
                'data' => [
                    'asignado' => $asignado,
                    'modificado' => $modificado,
                    'codificado' => $codificado,
                    'certificado_actual' => $certificado_neto,
                    'certificado_pendiente' => 0,
                    'disponible' => $disponible_final,
                    'disponible_final' => max(0, $disponible_final),
                    'puede_certificar' => $disponible_final > 0
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ── Solo Director: aprobar certificación REGISTRADO → APROBADO ─────
    public function aprobar(int $id)
    {
        if (!RolPermiso::tiene(Auth::user()?->cargo, 'certificaciones', 'aprobar')) {
            return response()->json(['success' => false, 'message' => 'Solo el Director Financiero puede aprobar certificaciones'], 403);
        }

        try {
            $cert = DB::table('certificacion')->where('id_certificacion', $id)->first();
            if (!$cert) {
                return response()->json(['success' => false, 'message' => 'Certificación no encontrada'], 404);
            }
            if ($cert->estado !== 'REGISTRADO') {
                return response()->json(['success' => false, 'message' => 'Solo se pueden aprobar certificaciones en estado REGISTRADO'], 422);
            }

            DB::table('certificacion')
                ->where('id_certificacion', $id)
                ->update(['estado' => 'APROBADO', 'updated_at' => now()]);

            $this->notificarAprobacion($cert, Auth::user());

            return response()->json(['success' => true, 'message' => 'Certificación aprobada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Solo Director: rechazar certificación REGISTRADO → RECHAZADO ───
    public function rechazar(Request $request, int $id)
    {
        if (!RolPermiso::tiene(Auth::user()?->cargo, 'certificaciones', 'rechazar')) {
            return response()->json(['success' => false, 'message' => 'Solo el Director Financiero puede rechazar certificaciones'], 403);
        }

        try {
            $request->validate(['motivo' => 'required|string|max:500']);

            $cert = DB::table('certificacion')->where('id_certificacion', $id)->first();
            if (!$cert) {
                return response()->json(['success' => false, 'message' => 'Certificación no encontrada'], 404);
            }
            if ($cert->estado !== 'REGISTRADO') {
                return response()->json(['success' => false, 'message' => 'Solo se pueden rechazar certificaciones en estado REGISTRADO'], 422);
            }

            $motivo = $request->input('motivo');

            DB::table('certificacion')
                ->where('id_certificacion', $id)
                ->update([
                    'estado'         => 'RECHAZADO',
                    'motivo_rechazo' => $motivo,
                    'updated_at'     => now(),
                ]);

            // Notificar al analista que creó el certificado
            $analista = User::find($cert->id_usuario);
            if ($analista && $analista->correo_institucional) {
                $revisor = Auth::user();
                $nombreRevisor = $revisor ? trim($revisor->nombres . ' ' . $revisor->apellidos) : 'el responsable';
                $cargoRevisor  = $revisor?->cargo ?? 'Responsable';
                $cuerpo = "Estimado/a {$analista->nombres},\n\n"
                    . "Me permito comunicarle que la Certificación Presupuestaria N.° {$cert->numero_certificado} "
                    . "ha sido RECHAZADA por {$nombreRevisor}, {$cargoRevisor}.\n\n"
                    . "Motivo del rechazo:\n"
                    . "  {$motivo}\n\n"
                    . "Se le solicita ingresar al Sistema de Control Presupuestario, revisar la certificación, "
                    . "realizar las correcciones pertinentes y reenviarla para su nueva revisión.\n\n"
                    . "Atentamente,\n"
                    . "Sistema de Control Presupuestario\n"
                    . "Universidad Estatal de Bolívar";
                $this->enviarCorreo(
                    $analista->correo_institucional,
                    $analista->nombres,
                    "Certificación N.° {$cert->numero_certificado} — Rechazada",
                    $cuerpo
                );
            }

            return response()->json(['success' => true, 'message' => 'Certificación rechazada']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Analista: reenviar certificación RECHAZADO → REGISTRADO ──────────────
    public function reenviar(int $id)
    {
        try {
            $cert = DB::table('certificacion')->where('id_certificacion', $id)->first();
            if (!$cert) {
                return response()->json(['success' => false, 'message' => 'Certificación no encontrada'], 404);
            }
            if ($cert->estado !== 'RECHAZADO') {
                return response()->json(['success' => false, 'message' => 'Solo se pueden reenviar certificaciones en estado RECHAZADO'], 422);
            }

            $u = Auth::user();
            if (!RolPermiso::tiene($u?->cargo, 'certificaciones', 'aprobar') && (int) $cert->id_usuario !== (int) $u?->id_usuario) {
                return response()->json(['success' => false, 'message' => 'Solo puede reenviar sus propias certificaciones'], 403);
            }

            DB::table('certificacion')
                ->where('id_certificacion', $id)
                ->update([
                    'estado'         => 'REGISTRADO',
                    'motivo_rechazo' => null,
                    'updated_at'     => now(),
                ]);

            $certActualizado = Certificacion::find($id);
            $creadorCargo = User::find($cert->id_usuario)?->cargo;
            if ($creadorCargo === 'Analista de presupuesto 1') {
                $this->notificarAnalista3($certActualizado, Auth::user(), 'reenvio');
            }

            return response()->json(['success' => true, 'message' => 'Certificación reenviada para revisión']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Analista/Director: marcar certificación REGISTRADO/APROBADO → ERRADO ──
    public function errar(int $id)
    {
        if (!RolPermiso::tiene(Auth::user()?->cargo, 'certificaciones', 'errar')) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para marcar como errado'], 403);
        }

        try {
            $cert = DB::table('certificacion')->where('id_certificacion', $id)->first();
            if (!$cert) {
                return response()->json(['success' => false, 'message' => 'Certificación no encontrada'], 404);
            }

            $user       = Auth::user();
            $esDirector = RolPermiso::tiene($user?->cargo, 'certificaciones', 'aprobar');

            if (!in_array($cert->estado, ['REGISTRADO', 'APROBADO'])) {
                return response()->json(['success' => false, 'message' => 'Solo se pueden marcar como erradas las certificaciones en estado REGISTRADO o APROBADO'], 422);
            }

            // APROBADO → ERRADO solo lo puede hacer el Director
            if ($cert->estado === 'APROBADO' && !$esDirector) {
                return response()->json(['success' => false, 'message' => 'Solo el Director puede marcar como errada una certificación aprobada'], 403);
            }

            // Analista solo puede errar sus propias certificaciones REGISTRADAS
            if (!$esDirector && $cert->id_usuario != $user?->id_usuario) {
                return response()->json(['success' => false, 'message' => 'Solo puede marcar como errada sus propias certificaciones'], 403);
            }

            $estadoAnterior = $cert->estado;

            DB::table('certificacion')
                ->where('id_certificacion', $id)
                ->update(['estado' => 'ERRADO', 'updated_at' => now()]);

            try {
                Auditoria::create([
                    'id_certificacion'   => $id,
                    'numero_certificado' => $cert->numero_certificado,
                    'id_usuario'         => $user?->id_usuario,
                    'nombre_usuario'     => $user ? trim($user->nombres . ' ' . $user->apellidos) : 'Sistema',
                    'accion'             => 'CAMBIO_ESTADO',
                    'estado_anterior'    => $estadoAnterior,
                    'estado_nuevo'       => 'ERRADO',
                    'fecha_hora'         => now(),
                ]);
            } catch (\Throwable $ae) {
                \Log::error('Auditoria::errar error: ' . $ae->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Certificación marcada como errada']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
