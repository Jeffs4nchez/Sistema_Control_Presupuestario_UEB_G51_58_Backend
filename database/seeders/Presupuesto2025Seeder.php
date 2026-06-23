<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Presupuesto2025Seeder extends Seeder
{
    public function run(): void
    {
        // Seeder reemplazado por importación CSV desde la pantalla de Cédula Presupuestaria.
        return;

        $cedula = DB::table('cedula_presupuestaria')->where('anio', 2025)->first();
        if (!$cedula) {
            $this->command->error('No existe cédula para 2025. Ejecuta las migraciones primero.');
            return;
        }
        $cid = $cedula->id_cedula_presupuestaria;

        // ── Programas ─────────────────────────────────────────────────────────
        $programasData = [
            '01' => 'ADMINISTRACION CENTRAL',
            '82' => 'FORMACION Y GESTION ACADEMICA',
            '83' => 'GESTION DE LA INVESTIGACION',
        ];
        $progIds = [];
        foreach ($programasData as $cod => $nombre) {
            DB::table('programa')->updateOrInsert(
                ['cod_programa' => $cod],
                ['nombre_programa' => $nombre, 'created_at' => now(), 'updated_at' => now()]
            );
            $progIds[$cod] = DB::table('programa')->where('cod_programa', $cod)->value('id_programa');
        }

        // ── Subprogramas (código "00" por programa) ───────────────────────────
        $subprogIds = [];
        foreach ($progIds as $codProg => $progId) {
            DB::table('subprograma')->updateOrInsert(
                ['cod_subprograma' => '00', 'id_programa' => $progId],
                ['nombre_subprograma' => 'Subprograma 00', 'created_at' => now(), 'updated_at' => now()]
            );
            $subprogIds[$codProg] = DB::table('subprograma')
                ->where('cod_subprograma', '00')->where('id_programa', $progId)
                ->value('id_subprograma');
        }

        // ── Proyectos (código "000" por subprograma) ──────────────────────────
        $proyIds = [];
        foreach ($subprogIds as $codProg => $subprogId) {
            DB::table('proyecto')->updateOrInsert(
                ['cod_proyecto' => '000', 'id_subprograma' => $subprogId],
                ['nombre_proyecto' => 'Proyecto 000', 'created_at' => now(), 'updated_at' => now()]
            );
            $proyIds[$codProg] = DB::table('proyecto')
                ->where('cod_proyecto', '000')->where('id_subprograma', $subprogId)
                ->value('id_proyecto');
        }

        // ── Actividades ───────────────────────────────────────────────────────
        // cod_actividad es el 4.º segmento de CODIGOG2 ("01 00 000 001" → "001")
        $actividadesData = [
            ['prog' => '01', 'cod' => '001', 'nombre' => 'ADMINISTRACION DE LA GESTION INSTITUCIONAL'],
            ['prog' => '82', 'cod' => '002', 'nombre' => 'FORMACION DE TERCER NIVEL'],
            ['prog' => '83', 'cod' => '001', 'nombre' => 'DESARROLLO DE ACTIVIDADES INVESTIGATIVAS'],
        ];
        $actIds = []; // clave "prog_cod"
        foreach ($actividadesData as $a) {
            $proyId = $proyIds[$a['prog']];
            DB::table('actividad')->updateOrInsert(
                ['cod_actividad' => $a['cod'], 'id_proyecto' => $proyId],
                ['nombre_actividad' => $a['nombre'], 'created_at' => now(), 'updated_at' => now()]
            );
            $actIds[$a['prog'] . '_' . $a['cod']] = DB::table('actividad')
                ->where('cod_actividad', $a['cod'])->where('id_proyecto', $proyId)
                ->value('id_actividad');
        }

        // ── Fuentes de financiamiento ─────────────────────────────────────────
        $fuentesData = [
            '001' => 'Recursos Fiscales',
            '003' => 'Recursos Provenientes de Preasignaciones',
        ];
        $fuenteIds = [];
        foreach ($fuentesData as $cod => $nombre) {
            DB::table('fuente_financiamiento')->updateOrInsert(
                ['cod_fuente' => $cod],
                ['nombre_fuente' => $nombre, 'created_at' => now(), 'updated_at' => now()]
            );
            $fuenteIds[$cod] = DB::table('fuente_financiamiento')->where('cod_fuente', $cod)->value('id_fuente');
        }

        // ── Ubicaciones ───────────────────────────────────────────────────────
        $ubicacionesData = [
            '0200' => 'BOLIVAR',
            '0201' => 'GUARANDA',
        ];
        $ubicIds = [];
        foreach ($ubicacionesData as $cod => $nombre) {
            DB::table('ubicacion')->updateOrInsert(
                ['cod_ubicacion' => $cod],
                ['nombre_ubicacion' => $nombre, 'created_at' => now(), 'updated_at' => now()]
            );
            $ubicIds[$cod] = DB::table('ubicacion')->where('cod_ubicacion', $cod)->value('id_ubicacion');
        }

        // ── Filas del CSV R2025-11-20_14-20-13.csv ────────────────────────────
        // [cod_prog, cod_act, cod_fuente, cod_ubic, cod_item, nombre_item, asignado, modificado]
        $rows = [
            ['01','001','001','0200','510510','Servicios Personales por Contrato','92076','0'],
            ['01','001','001','0200','510204','Decimo Cuarto Sueldo','3739.98','0'],
            ['01','001','003','0200','510602','Fondo de Reserva','226917.33','0'],
            ['01','001','003','0200','510706','Beneficio por Jubilacion','0','0'],
            ['82','002','001','0200','510518','Servicios Personales por Contrato de Docentes del Magisterio y Docentes e Investigadores Universitarios','642642.8','0'],
            ['82','002','003','0200','510707','Compensacion por Vacaciones no Gozadas por Cesacion de Funciones','5000','0'],
            ['82','002','003','0200','510204','Decimo Cuarto Sueldo','134093.83','0'],
            ['83','001','001','0200','510707','Compensacion por Vacaciones no Gozadas por Cesacion de Funciones','5000','0'],
            ['01','001','003','0200','510105','Remuneraciones Unificadas','1838806.88','-79528.76'],
            ['01','001','003','0200','510106','Salarios Unificados','568793.31','20000'],
            ['01','001','003','0200','510204','Decimo Cuarto Sueldo','95760','0'],
            ['82','002','003','0200','510512','Subrogacion','2000','0'],
            ['82','002','003','0200','510203','Decimo Tercer Sueldo','516995.12','0'],
            ['82','002','003','0200','510108','Remuneracion Mensual Unificada de Docentes del Magisterio y Docentes e Investigadores Universitarios','4727202.39','937.53'],
            ['83','001','003','0200','510108','Remuneracion Mensual Unificada de Docentes del Magisterio y Docentes e Investigadores Universitarios','285000','85456.8'],
            ['01','001','003','0200','510510','Servicios Personales por Contrato','315484','-2000'],
            ['01','001','003','0200','510203','Decimo Tercer Sueldo','230889.02','0'],
            ['01','001','003','0200','510601','Aporte Patronal','277092.89','0'],
            ['01','001','003','0200','510704','Compensacion por Desahucio','50000','-20000'],
            ['01','001','003','0200','510408','Subsidio de Antiguedad','23000','0'],
            ['82','002','001','0200','510203','Decimo Tercer Sueldo','53791.59','0'],
            ['82','002','003','0200','510601','Aporte Patronal','610985.54','0'],
            ['83','001','003','0200','510518','Servicios Personales por Contrato de Docentes del Magisterio y Docentes e Investigadores Universitarios','0','237159.84'],
            ['01','001','001','0200','510203','Decimo Tercer Sueldo','8324.26','0'],
            ['01','001','001','0200','510706','Beneficio por Jubilacion','236112.26','0'],
            ['01','001','003','0200','510513','Encargos','0','2000'],
            ['83','001','003','0200','510204','Decimo Cuarto Sueldo','16333','-3173'],
            ['83','001','003','0200','510203','Decimo Tercer Sueldo','44728.73','5309.99'],
            ['01','001','001','0200','510602','Fondo de Reserva','7673','0'],
            ['01','001','003','0200','510304','Compensacion por Transporte','11000','0'],
            ['01','001','003','0200','510401','Por Cargas Familiares','5000','0'],
            ['83','001','003','0200','510601','Aporte Patronal','53009.05','5417.42'],
            ['01','001','003','0200','510705','Restitucion de Puesto','443.6','0'],
            ['01','001','003','0200','510306','Alimentacion','82000','0'],
            ['01','001','003','0201','510513','Encargos','0','0'],
            ['82','002','001','0200','510601','Aporte Patronal','58784.82','0'],
            ['82','002','003','0200','510518','Servicios Personales por Contrato de Docentes del Magisterio y Docentes e Investigadores Universitarios','1735006.34','-256513.72'],
            ['83','001','003','0200','510707','Compensacion por Vacaciones no Gozadas por Cesacion de Funciones','1000','0'],
            ['01','001','001','0200','510601','Aporte Patronal','8885.35','0'],
            ['01','001','003','0200','510512','Subrogacion','2211.84','0'],
            ['01','001','003','0200','510707','Compensacion por Vacaciones no Gozadas por Cesacion de Funciones','9000','0'],
            ['82','002','001','0200','510706','Beneficio por Jubilacion','132786.88','0'],
            ['82','002','001','0200','510602','Fondo de Reserva','52487.25','0'],
            ['83','001','003','0200','510602','Fondo de Reserva','48277.82','4933.9'],
            ['82','002','001','0200','510204','Decimo Cuarto Sueldo','14892.48','0'],
            ['82','002','003','0200','510602','Fondo de Reserva','530397.36','0'],
        ];

        DB::transaction(function () use ($rows, $actIds, $fuenteIds, $ubicIds, $cid) {
            foreach ($rows as [$codProg, $codAct, $codFuente, $codUbic, $codItem, $nombreItem, $asignado, $modificado]) {
                $actId    = $actIds[$codProg . '_' . $codAct];
                $fuenteId = $fuenteIds[$codFuente];
                $ubicId   = $ubicIds[$codUbic];

                // Ítem: único por (cod_item, actividad, ubicacion)
                DB::table('items')->updateOrInsert(
                    ['cod_item' => $codItem, 'id_actividad' => $actId, 'id_ubicacion' => $ubicId],
                    ['nombre_item' => $nombreItem, 'created_at' => now(), 'updated_at' => now()]
                );
                $itemId = DB::table('items')
                    ->where('cod_item', $codItem)
                    ->where('id_actividad', $actId)
                    ->where('id_ubicacion', $ubicId)
                    ->value('id_item');

                // Relación actividad ↔ fuente (global, sin cédula)
                DB::table('actividad_fuente')->updateOrInsert(
                    ['id_actividad' => $actId, 'id_fuente' => $fuenteId],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                // Presupuesto del ítem para la cédula 2025
                DB::table('fuente_items')->updateOrInsert(
                    ['id_fuente' => $fuenteId, 'id_item' => $itemId, 'id_cedula_presupuestaria' => $cid],
                    [
                        'asignado'   => (float) $asignado,
                        'modificado' => (float) $modificado,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->command->info("Presupuesto 2025 cargado — cédula ID: {$cid}, " . count($rows) . ' registros procesados.');
    }
}
