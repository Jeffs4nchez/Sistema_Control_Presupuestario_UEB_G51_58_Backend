<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Programa;
use App\Models\Subprograma;
use App\Models\Proyecto;
use App\Models\Actividad;
use App\Models\Geografica;
use App\Models\FuenteFinanciamiento;
use App\Models\Organismo;
use App\Models\NaturalezaPrestacion;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class ImportEstructuraPresupuestaria extends Command
{
    protected $signature = 'import:estructura-presupuestaria {csv_path}';
    protected $description = 'Importa la estructura presupuestaria desde un archivo CSV';

    public function handle()
    {
        $csvPath = $this->argument('csv_path');

        if (!file_exists($csvPath)) {
            $this->error("Archivo no encontrado: $csvPath");
            return 1;
        }

        $this->info('Iniciando importación...');
        
        // Abrir el CSV
        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file, null, ';'); // Leer encabezado con delimitador ;

        $rowNum = 1;
        $cache = [];

        while (($row = fgetcsv($file, null, ';')) !== false) {
            $rowNum++;
            
            if (empty(array_filter($row))) {
                continue; // Saltar filas vacías
            }

            try {
                // Mapear columnas
                $codPrograma = trim($row[0]);
                $nomPrograma = trim($row[1]);
                $codSubprograma = trim($row[2]);
                $nomSubprograma = trim($row[3]);
                $codProyecto = trim($row[4]);
                $nomProyecto = trim($row[5]);
                $codActividad = trim($row[6]);
                $nomActividad = trim($row[7]);
                $codItem = trim($row[8]);
                $nomItem = trim($row[9]);
                $codUbicacion = trim($row[10]);
                $nomUbicacion = trim($row[11]);
                $codFuente = trim($row[12]);
                $nomFuente = trim($row[13]);
                $codOrganismo = trim($row[14]);
                $nomOrganismo = trim($row[15]);
                $codNaturaleza = trim($row[16]);
                $nomNaturaleza = trim($row[17]);

                // 1. Insertar o buscar PROGRAMA
                $cacheKeyPrograma = "programa_$codPrograma";
                if (!isset($cache[$cacheKeyPrograma])) {
                    $programa = Programa::firstOrCreate(
                        ['cod_programa' => $codPrograma],
                        ['nombre_programa' => $nomPrograma]
                    );
                    $cache[$cacheKeyPrograma] = $programa->id_programa;
                }
                $idPrograma = $cache[$cacheKeyPrograma];

                // 2. Insertar o buscar SUBPROGRAMA
                $cacheKeySubprograma = "subprograma_{$codPrograma}_{$codSubprograma}";
                if (!isset($cache[$cacheKeySubprograma])) {
                    $subprograma = Subprograma::firstOrCreate(
                        ['cod_subprograma' => $codSubprograma, 'id_programa' => $idPrograma],
                        [
                            'nombre_subprograma' => $nomSubprograma,
                            'id_programa' => $idPrograma
                        ]
                    );
                    $cache[$cacheKeySubprograma] = $subprograma->id_subprograma;
                }
                $idSubprograma = $cache[$cacheKeySubprograma];

                // 3. Insertar o buscar PROYECTO
                $cacheKeyProyecto = "proyecto_{$codSubprograma}_{$codProyecto}";
                if (!isset($cache[$cacheKeyProyecto])) {
                    $proyecto = Proyecto::firstOrCreate(
                        ['cod_proyecto' => $codProyecto, 'id_subprograma' => $idSubprograma],
                        [
                            'nombre_proyecto' => $nomProyecto,
                            'id_subprograma' => $idSubprograma
                        ]
                    );
                    $cache[$cacheKeyProyecto] = $proyecto->id_proyecto;
                }
                $idProyecto = $cache[$cacheKeyProyecto];

                // 4. Insertar o buscar ACTIVIDAD
                $cacheKeyActividad = "actividad_{$codProyecto}_{$codActividad}";
                if (!isset($cache[$cacheKeyActividad])) {
                    $actividad = Actividad::firstOrCreate(
                        ['cod_actividad' => $codActividad, 'id_proyecto' => $idProyecto],
                        [
                            'nombre_actividad' => $nomActividad,
                            'id_proyecto' => $idProyecto
                        ]
                    );
                    $cache[$cacheKeyActividad] = $actividad->id_actividad;
                }
                $idActividad = $cache[$cacheKeyActividad];

                // 5. Insertar o buscar UBICACIÓN GEOGRÁFICA
                $cacheKeyUbicacion = "ubicacion_$codUbicacion";
                if (!isset($cache[$cacheKeyUbicacion])) {
                    $ubicacion = Geografica::firstOrCreate(
                        ['cod_ubicacion' => $codUbicacion],
                        ['nombre_ubicacion' => $nomUbicacion]
                    );
                    $cache[$cacheKeyUbicacion] = $ubicacion->id_ubicacion;
                }
                $idUbicacion = $cache[$cacheKeyUbicacion];

                // 6. Insertar o buscar FUENTE DE FINANCIAMIENTO
                $cacheKeyFuente = "fuente_$codFuente";
                if (!isset($cache[$cacheKeyFuente])) {
                    $fuente = FuenteFinanciamiento::firstOrCreate(
                        ['cod_fuente' => $codFuente],
                        ['nombre_fuente' => $nomFuente]
                    );
                    $cache[$cacheKeyFuente] = $fuente->id_fuente;
                }
                $idFuente = $cache[$cacheKeyFuente];

                // 7. Insertar o buscar ORGANISMO
                $cacheKeyOrganismo = "organismo_$codOrganismo";
                if (!isset($cache[$cacheKeyOrganismo])) {
                    $organismo = Organismo::firstOrCreate(
                        ['cod_organismo' => $codOrganismo],
                        ['nombre_organismo' => $nomOrganismo]
                    );
                    $cache[$cacheKeyOrganismo] = $organismo->id_organismo;
                }
                $idOrganismo = $cache[$cacheKeyOrganismo];

                // 8. Insertar o buscar NATURALEZA PRESTACIÓN
                $cacheKeyNaturaleza = "naturaleza_{$codOrganismo}_{$codNaturaleza}";
                if (!isset($cache[$cacheKeyNaturaleza])) {
                    $naturaleza = NaturalezaPrestacion::firstOrCreate(
                        ['cod_naturaleza' => $codNaturaleza, 'id_organismo' => $idOrganismo],
                        [
                            'nombre_naturaleza' => $nomNaturaleza,
                            'id_organismo' => $idOrganismo
                        ]
                    );
                    $cache[$cacheKeyNaturaleza] = $naturaleza->id_naturaleza;
                }
                $idNaturaleza = $cache[$cacheKeyNaturaleza];

                // 9. Insertar ITEM (permitiendo duplicados con diferentes combinaciones)
                $item = Item::firstOrCreate(
                    [
                        'cod_item' => $codItem,
                        'id_actividad' => $idActividad,
                        'id_ubicacion' => $idUbicacion,
                        'id_organismo' => $idOrganismo,
                        'id_naturaleza' => $idNaturaleza
                    ],
                    [
                        'nombre_item' => $nomItem,
                        'id_actividad' => $idActividad,
                        'id_ubicacion' => $idUbicacion,
                        'id_organismo' => $idOrganismo,
                        'id_naturaleza' => $idNaturaleza
                    ]
                );

                // 10. Crear relación ACTIVIDAD-FUENTE (sin duplicados)
                DB::table('actividad_fuente')->updateOrInsert(
                    ['id_actividad' => $idActividad, 'id_fuente' => $idFuente],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                // 11. Crear relación ITEM-FUENTE (sin duplicados)
                DB::table('fuente_items')->updateOrInsert(
                    ['id_item' => $item->id_item, 'id_fuente' => $idFuente],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                $this->info("✓ Fila $rowNum procesada");

            } catch (\Exception $e) {
                $this->error("✗ Error en fila $rowNum: " . $e->getMessage());
            }
        }

        fclose($file);
        $this->info('✅ Importación completada');
        return 0;
    }
}
