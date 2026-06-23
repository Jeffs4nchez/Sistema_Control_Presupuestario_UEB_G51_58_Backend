<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CertificacionHistoricaSeeder extends Seeder
{
    // ─────────────────────────────────────────────────────────────────────────
    // CONFIGURACIÓN — ajusta estos valores antes de ejecutar
    // ─────────────────────────────────────────────────────────────────────────
    private string $usuarioCorreo = 'jefferson.sanchez@ueb.edu.ec'; // correo del usuario responsable por defecto
    private int    $anio          = 2026;

    // ─────────────────────────────────────────────────────────────────────────
    // CERTIFICACIONES — agrega aquí los 60 registros
    // Formato de cada item: [pg, sp, py, act, item, ubg, fte, org, nprest, monto]
    // ─────────────────────────────────────────────────────────────────────────
    private function certificaciones(): array
    {
        return [
            [
                'numero'      => '001',
                'fecha'       => '2026-01-15',
                'memorando'   => 'UEB-DTH 2026-0052-M',
                'descripcion' => 'PARA CERTIFICAR DISPONIBILIDAD PRESUPUESTARIA PARA VIÁTICOS Y SUBSISTENCIAS EN EL EXTERIOR A FAVOR DEL DR. HERNÁN ARTURO ROJAS, RECTOR DE LA UEB, QUIEN PARTICIPARÁ EN EL ACTO DE LA FIRMA DE CONVENIO DE COOPERACIÓN ACADÉMICA ENTRE NUESTRA UNIVERSIDAD Y LA UNIVERSIDAD CENTRO PANAMERICANO DE ESTUDIOS SUPERIORES (UNICEPES) Y LA RED IBEROAMERICANA DE MEDIO AMBIENTE (REIMA, A.C.) A REALIZARSE DEL 19 AL 23 DE ENERO DE 2026 EN LA CIUDAD DE ZITÁCUARO, MICHOACÁN, MÉXICO CONFORME OFICIO N° REIMA-2025-0423-DG, INFORME DE TALENTO HUMANO N°01-DTH-2026, SEGÚN CERTIFICACION POA N° 0001-DPAC-UEB-2026 Y MEMORANDO N° UEB-DTH-2026-0052-M Y DISPOSICIÓN MEDIANTE QUIPUX.',
                'estado'      => 'APROBADO',
                'entidad'     => 'Dirección de Talento Humano',
                'items'       => [
                    ['pg'=>'82','sp'=>'00','py'=>'000','act'=>'002','item'=>'530304','ubg'=>'0201','fte'=>'003','org'=>'0000','nprest'=>'0000','monto'=>1959.10],
                ],
            ],

            // ── CERTIFICACIÓN 002 ──────────────────────────────────────────
            // [
            //     'numero'      => '002',
            //     'fecha'       => '2026-01-DD',
            //     'memorando'   => 'UEB-XXX 2026-XXXX-M',
            //     'descripcion' => 'DESCRIPCIÓN...',
            //     'estado'      => 'APROBADO',
            //     'entidad'     => 'Nombre Unidad Requiriente',
            //     'items'       => [
            //         ['pg'=>'XX','sp'=>'00','py'=>'000','act'=>'XXX','item'=>'XXXXXX','ubg'=>'XXXX','fte'=>'XXX','org'=>'0000','nprest'=>'0000','monto'=>0.00],
            //     ],
            // ],

            // Repite el bloque anterior para cada una de las 60 certificaciones...
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // No modificar lo que está debajo
    // ─────────────────────────────────────────────────────────────────────────

    private array $cache = [];

    public function run(): void
    {
        $cedula = DB::table('cedula_presupuestaria')->where('anio', $this->anio)->first();
        if (!$cedula) {
            $this->command->error("No existe cédula presupuestaria para {$this->anio}. Verifica la BD.");
            return;
        }
        $cedulaId = $cedula->id_cedula_presupuestaria;

        $usuario = DB::table('usuarios')->where('correo_institucional', $this->usuarioCorreo)->first();
        if (!$usuario) {
            $this->command->error("Usuario '{$this->usuarioCorreo}' no encontrado en la BD.");
            return;
        }
        $usuarioId = $usuario->id_usuario;

        $ok = 0;
        $err = 0;

        foreach ($this->certificaciones() as $c) {
            try {
                // Permite usuario diferente por certificación
                $uid = $usuarioId;
                if (!empty($c['usuario'])) {
                    $uid = (int) DB::table('usuarios')->where('correo_institucional', $c['usuario'])->value('id_usuario');
                    if (!$uid) throw new \RuntimeException("Usuario '{$c['usuario']}' no encontrado.");
                }

                DB::transaction(function () use ($c, $cedulaId, $uid, &$ok) {
                    $usuarioId = $uid;

                    // Entidad requiriente — crea si no existe
                    $entidad = DB::table('unidad_requiriente')
                        ->whereRaw('LOWER(nombre_entidad) LIKE ?', ['%' . strtolower($c['entidad']) . '%'])
                        ->first();
                    if (!$entidad) {
                        DB::table('unidad_requiriente')->insert([
                            'nombre_entidad'       => $c['entidad'],
                            'responsable_entidad'  => $c['responsable'] ?? 'Por definir',
                            'correo_institucional' => $c['correo_entidad'] ?? 'info@ueb.edu.ec',
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);
                        $entidad = DB::table('unidad_requiriente')
                            ->whereRaw('LOWER(nombre_entidad) LIKE ?', ['%' . strtolower($c['entidad']) . '%'])
                            ->first();
                        $this->command->line("  + Entidad creada: {$c['entidad']}");
                    }

                    // Verificar duplicado
                    if (DB::table('certificacion')->where('numero_certificado', $c['numero'])->exists()) {
                        $this->command->warn("  Saltando {$c['numero']} — ya existe.");
                        return;
                    }

                    $certId = DB::table('certificacion')->insertGetId([
                        'numero_certificado'       => $c['numero'],
                        'descripcion'              => $c['descripcion'] ?? null,
                        'fecha_elaboracion'        => $c['fecha'],
                        'clase_registro'           => $c['clase_registro']    ?? null,
                        'clase_gasto'              => $c['clase_gasto']       ?? null,
                        'tipo_doc_respaldo'        => $c['tipo_doc_respaldo'] ?? null,
                        'clase_doc_respaldo'       => $c['clase_doc_respaldo'] ?? null,
                        'seccion_memorando'        => $c['memorando']         ?? null,
                        'estado'                   => $c['estado']            ?? 'REGISTRADO',
                        'id_usuario'               => $uid,
                        'id_unidad_requiriente'    => $entidad->id_unidad_requiriente,
                        'id_cedula_presupuestaria' => $cedulaId,
                        'created_at'               => now(),
                        'updated_at'               => now(),
                    ], 'id_certificacion');

                    foreach ($c['items'] as $it) {
                        $pgId    = $this->pg($it['pg']);
                        $spId    = $this->sp($it['sp'], $pgId);
                        $pyId    = $this->py($it['py'], $spId);
                        $actId   = $this->act($it['act'], $pyId);
                        $itemId  = $this->item($it['item']);
                        $fteId   = $this->fte($it['fte']);
                        $ubgId   = $this->ubg($it['ubg']);
                        $orgId   = $this->org($it['org']);
                        $natId   = $this->nat($it['nprest']);

                        DB::table('certificacion_items')->insert([
                            'id_certificacion' => $certId,
                            'id_programa'      => $pgId,
                            'id_subprograma'   => $spId,
                            'id_proyecto'      => $pyId,
                            'id_actividad'     => $actId,
                            'id_item'          => $itemId,
                            'id_fuente'        => $fteId,
                            'id_ubicacion'     => $ubgId,
                            'id_organismo'     => $orgId,
                            'id_naturaleza'    => $natId,
                            'monto'            => $it['monto'],
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }

                    $ok++;
                    $this->command->info("  ✓ Certificación {$c['numero']} — {$c['fecha']}");
                });
            } catch (\Throwable $e) {
                $err++;
                $this->command->error("  ✗ Certificación {$c['numero']}: " . $e->getMessage());
            }
        }

        $this->command->line('');
        $this->command->info("Completado: $ok insertadas, $err con error.");
    }

    // ── Helpers con caché en memoria ──────────────────────────────────────────

    private function pg(string $cod): int
    {
        return $this->cache["pg_$cod"] ??= (int) DB::table('programa')
            ->where('cod_programa', $cod)->value('id_programa')
            ?: throw new \RuntimeException("Programa '$cod' no encontrado.");
    }

    private function sp(string $cod, int $pgId): int
    {
        $k = "sp_{$cod}_{$pgId}";
        return $this->cache[$k] ??= (int) DB::table('subprograma')
            ->where('id_programa', $pgId)
            ->where(fn($q) => $q->where('cod_subprograma', $cod)->orWhere('cod_subprograma', 'LIKE', '% '.$cod))
            ->value('id_subprograma')
            ?: throw new \RuntimeException("Subprograma '$cod' para programa $pgId no encontrado.");
    }

    private function py(string $cod, int $spId): int
    {
        $k = "py_{$cod}_{$spId}";
        return $this->cache[$k] ??= (int) DB::table('proyecto')
            ->where('id_subprograma', $spId)
            ->where(fn($q) => $q->where('cod_proyecto', $cod)->orWhere('cod_proyecto', 'LIKE', '% '.$cod))
            ->value('id_proyecto')
            ?: throw new \RuntimeException("Proyecto '$cod' para subprograma $spId no encontrado.");
    }

    private function act(string $cod, int $pyId): int
    {
        $k = "act_{$cod}_{$pyId}";
        return $this->cache[$k] ??= (int) DB::table('actividad')
            ->where('id_proyecto', $pyId)
            ->where(fn($q) => $q->where('cod_actividad', $cod)->orWhere('cod_actividad', 'LIKE', '% '.$cod))
            ->value('id_actividad')
            ?: throw new \RuntimeException("Actividad '$cod' para proyecto $pyId no encontrada.");
    }

    private function item(string $cod): int
    {
        return $this->cache["item_$cod"] ??= (int) DB::table('items')
            ->where('cod_item', $cod)->value('id_item')
            ?: throw new \RuntimeException("Ítem '$cod' no encontrado.");
    }

    private function fte(string $cod): int
    {
        return $this->cache["fte_$cod"] ??= (int) DB::table('fuente_financiamiento')
            ->where('cod_fuente', $cod)->value('id_fuente')
            ?: throw new \RuntimeException("Fuente '$cod' no encontrada.");
    }

    private function ubg(string $cod): int
    {
        return $this->cache["ubg_$cod"] ??= (int) DB::table('ubicacion')
            ->where('cod_ubicacion', $cod)->value('id_ubicacion')
            ?: throw new \RuntimeException("Ubicación '$cod' no encontrada.");
    }

    private function org(string $cod): int
    {
        return $this->cache["org_$cod"] ??= (int) DB::table('organismos')
            ->where('cod_organismo', $cod)->value('id_organismo')
            ?: throw new \RuntimeException("Organismo '$cod' no encontrado.");
    }

    private function nat(string $cod): int
    {
        return $this->cache["nat_$cod"] ??= (int) DB::table('naturaleza_prestacion')
            ->where('cod_naturaleza', $cod)->value('id_naturaleza')
            ?: throw new \RuntimeException("N. Prestación '$cod' no encontrada.");
    }
}
