<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
use Illuminate\Support\Facades\Storage;

class EstructuraPresupuestariaController extends Controller
{
    /**
     * Cargar archivo CSV de estructura presupuestaria
     */
    public function upload(Request $request)
    {
        $request->validate([
            'csv_file'               => 'required|file|mimes:csv,txt|max:10240',
            'id_cedula_presupuestaria' => 'nullable|integer|exists:cedula_presupuestaria,id_cedula_presupuestaria',
        ]);

        $idCedulaPresupuestaria = $request->input('id_cedula_presupuestaria');

        // If no cedula provided, default to the current year's cedula
        if (!$idCedulaPresupuestaria) {
            $cedula = DB::table('cedula_presupuestaria')->where('anio', now()->year)->first();
            $idCedulaPresupuestaria = $cedula?->id_cedula_presupuestaria;
        }

        try {
            $file    = $request->file('csv_file');
            $raw     = $file->getContent();
            $encoding = mb_detect_encoding($raw, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'UTF-16'], true);
            $content = $encoding && $encoding !== 'UTF-8'
                ? mb_convert_encoding($raw, 'UTF-8', $encoding)
                : $raw;
            // Eliminar BOM UTF-8 si existe
            $content = ltrim($content, "\xEF\xBB\xBF");
            $content = str_replace("\r\n", "\n", str_replace("\r", "\n", $content));
            $lines   = explode("\n", $content);

            // ── FASE 1: Validar encabezado ──────────────────────────────────
            if (count($lines) < 2) {
                return response()->json(['success' => false, 'message' => 'El archivo está vacío o solo contiene el encabezado.'], 422);
            }
            $header    = str_getcsv($lines[0], ';');
            $headerStr = strtoupper(implode('|', array_map('trim', $header)));

            // Detectar si es un CSV de Cédula Presupuestaria subido por error
            $esCedula = str_contains($headerStr, 'DESCRIPCIONG')
                     || str_contains($headerStr, 'CODIGOG')
                     || preg_match('/\|COL\d+\|/', $headerStr);
            if ($esCedula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo incorrecto: este CSV corresponde a la Cédula Presupuestaria, no a la Estructura. Súbalo en la sección "Cédula Presupuestaria".',
                    'hint'    => 'El encabezado contiene columnas propias de la cédula (DESCRIPCIONG, CODIGOG o COL1-COL10).',
                ], 422);
            }

            if (count($header) < 18) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de archivo incorrecto: el encabezado tiene ' . count($header) . ' columnas, se requieren mínimo 18. Verifica que el delimitador sea punto y coma (;).',
                ], 422);
            }

            // ── FASE 2: Procesar filas de datos ────────────────────────────
            $cache         = [];
            $processedCount = 0;
            $insertedCount  = 0;
            $existingCount  = 0;
            $skippedCount   = 0;
            $errors         = [];
            $dataLines      = array_slice($lines, 1);
            $totalDataRows  = 0;

            foreach ($dataLines as $idx => $line) {
                $rowNum = $idx + 2; // +2: fila 1 es encabezado

                if (empty(trim($line))) continue;
                $totalDataRows++;

                try {
                    $row = str_getcsv($line, ';');

                    // Validar cantidad de columnas
                    if (count($row) < 18) {
                        $errors[] = ['row' => $rowNum, 'error' => 'Columnas insuficientes: se encontraron ' . count($row) . ', se requieren 18'];
                        $skippedCount++;
                        continue;
                    }

                    // Mapear columnas
                    $codPrograma    = trim($row[0]);
                    $nomPrograma    = trim($row[1]);
                    $codSubprograma = trim($row[2]);
                    $nomSubprograma = trim($row[3]);
                    $codProyecto    = trim($row[4]);
                    $nomProyecto    = trim($row[5]);
                    $codActividad   = trim($row[6]);
                    $nomActividad   = trim($row[7]);
                    $codItem        = trim($row[8]);
                    $nomItem        = trim($row[9]);
                    $codUbicacion   = trim($row[10]);
                    $nomUbicacion   = trim($row[11]);
                    $codFuente      = trim($row[12]);
                    $nomFuente      = trim($row[13]);
                    $codOrganismo   = trim($row[14]);
                    $nomOrganismo   = trim($row[15]);
                    $codNaturaleza  = trim($row[16]);
                    $nomNaturaleza  = trim($row[17]);

                    // Restaurar ceros a la izquierda que Excel elimina al guardar como CSV
                    // Los códigos numéricos tienen longitud fija; los que llevan espacios (subprograma,
                    // proyecto, actividad) no pueden ser convertidos a número por Excel, así que
                    // solo se padean los puramente numéricos.
                    if (is_numeric($codPrograma))   $codPrograma   = str_pad($codPrograma,  2, '0', STR_PAD_LEFT);
                    if (is_numeric($codItem))        $codItem       = str_pad($codItem,      6, '0', STR_PAD_LEFT);
                    if (is_numeric($codUbicacion))   $codUbicacion  = str_pad($codUbicacion, 4, '0', STR_PAD_LEFT);
                    if (is_numeric($codFuente))      $codFuente     = str_pad($codFuente,    3, '0', STR_PAD_LEFT);
                    if (is_numeric($codOrganismo))   $codOrganismo  = str_pad($codOrganismo, 4, '0', STR_PAD_LEFT);
                    if (is_numeric($codNaturaleza))  $codNaturaleza = str_pad($codNaturaleza,4, '0', STR_PAD_LEFT);

                    // Validar campos de código obligatorios (no pueden estar vacíos)
                    $camposVacios = [];
                    if ($codPrograma   === '') $camposVacios[] = 'Cód.Programa';
                    if ($codSubprograma === '') $camposVacios[] = 'Cód.Subprograma';
                    if ($codProyecto   === '') $camposVacios[] = 'Cód.Proyecto';
                    if ($codActividad  === '') $camposVacios[] = 'Cód.Actividad';
                    if ($codItem       === '') $camposVacios[] = 'Cód.Ítem';
                    if ($codUbicacion  === '') $camposVacios[] = 'Cód.Ubicación';
                    if ($codFuente     === '') $camposVacios[] = 'Cód.Fuente';
                    if ($codOrganismo  === '') $camposVacios[] = 'Cód.Organismo';
                    if ($codNaturaleza === '') $camposVacios[] = 'Cód.Naturaleza';
                    if (!empty($camposVacios)) {
                        $errors[] = ['row' => $rowNum, 'error' => 'Campos vacíos: ' . implode(', ', $camposVacios)];
                        $skippedCount++;
                        continue;
                    }

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

                    // 9. Insertar ITEM (único por combinación: código + actividad + ubicación + organismo + naturaleza + fuente)
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
                    // 10. Crear relación ACTIVIDAD-FUENTE
                    DB::table('actividad_fuente')->updateOrInsert(
                        ['id_actividad' => $idActividad, 'id_fuente' => $idFuente],
                        ['created_at' => now(), 'updated_at' => now()]
                    );

                    // 11. Crear relación ITEM-FUENTE-CÉDULA (PK compuesta por año)
                    $fuenteItemExistia = DB::table('fuente_items')
                        ->where('id_item',                 $item->id_item)
                        ->where('id_fuente',               $idFuente)
                        ->where('id_cedula_presupuestaria', $idCedulaPresupuestaria)
                        ->exists();

                    if (!$fuenteItemExistia) {
                        DB::table('fuente_items')->insert([
                            'id_item'                  => $item->id_item,
                            'id_fuente'                => $idFuente,
                            'id_cedula_presupuestaria' => $idCedulaPresupuestaria,
                            'created_at'               => now(),
                            'updated_at'               => now(),
                        ]);
                    }

                    if ($fuenteItemExistia) { $existingCount++; } else { $insertedCount++; }

                    $processedCount++;

                } catch (\Exception $e) {
                    $errors[] = ['row' => $rowNum, 'error' => 'Error interno: ' . $e->getMessage()];
                    $skippedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "$processedCount de $totalDataRows filas procesadas. $insertedCount nuevas, $existingCount ya existían, $skippedCount omitidas.",
                'data' => [
                    'total_rows' => $totalDataRows,
                    'processed'  => $processedCount,
                    'inserted'   => $insertedCount,
                    'existing'   => $existingCount,
                    'skipped'    => $skippedCount,
                    'errors'     => array_slice($errors, 0, 20),
                    'timestamp'  => now()
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
     * Obtener resumen de la estructura presupuestaria
     */
    public function summary(Request $request)
    {
        try {
            $idCedula = $request->input('id_cedula_presupuestaria');

            $baseQuery = DB::table('fuente_items');
            if ($idCedula) {
                $baseQuery->where('id_cedula_presupuestaria', $idCedula);
            }
            $itemIds = (clone $baseQuery)->distinct()->pluck('id_item');

            $actIds = DB::table('items')
                ->whereIn('id_item', $itemIds)
                ->distinct()->pluck('id_actividad');

            $proyIds = DB::table('actividad')
                ->whereIn('id_actividad', $actIds)
                ->distinct()->pluck('id_proyecto');

            $subpIds = DB::table('proyecto')
                ->whereIn('id_proyecto', $proyIds)
                ->distinct()->pluck('id_subprograma');

            $progIds = DB::table('subprograma')
                ->whereIn('id_subprograma', $subpIds)
                ->distinct()->pluck('id_programa');

            $summary = [
                'programas_count'    => $progIds->count(),
                'subprogramas_count' => $subpIds->count(),
                'proyectos_count'    => $proyIds->count(),
                'actividades_count'  => $actIds->count(),
                'items_count'        => $itemIds->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de estructura presupuestaria para tabla
     */
    public function getData(Request $request)
    {
        try {
            $page     = $request->input('page', 1);
            $limit    = $request->input('limit', 50);
            $search   = $request->input('search', '');
            $tipo     = $request->input('tipo', 'items');
            $idCedula = $request->input('id_cedula_presupuestaria');

            $offset = ($page - 1) * $limit;

            // Precompute hierarchy IDs filtered by cedula (used by non-item views)
            $itemIdsByCedula = null;
            if ($idCedula) {
                $itemIdsByCedula = DB::table('fuente_items')
                    ->where('id_cedula_presupuestaria', $idCedula)
                    ->distinct()->pluck('id_item');
            }

            $query = null;

            switch ($tipo) {
                case 'programas':
                    $query = Programa::query();
                    if ($idCedula) {
                        $actIds  = DB::table('items')->whereIn('id_item', $itemIdsByCedula)->distinct()->pluck('id_actividad');
                        $proyIds = DB::table('actividad')->whereIn('id_actividad', $actIds)->distinct()->pluck('id_proyecto');
                        $subpIds = DB::table('proyecto')->whereIn('id_proyecto', $proyIds)->distinct()->pluck('id_subprograma');
                        $progIds = DB::table('subprograma')->whereIn('id_subprograma', $subpIds)->distinct()->pluck('id_programa');
                        $query->whereIn('id_programa', $progIds);
                    }
                    if ($search) {
                        $query->where(fn($q) => $q->where('cod_programa', 'LIKE', "%$search%")->orWhere('nombre_programa', 'LIKE', "%$search%"));
                    }
                    break;

                case 'subprogramas':
                    $query = Subprograma::with('programa');
                    if ($idCedula) {
                        $actIds  = DB::table('items')->whereIn('id_item', $itemIdsByCedula)->distinct()->pluck('id_actividad');
                        $proyIds = DB::table('actividad')->whereIn('id_actividad', $actIds)->distinct()->pluck('id_proyecto');
                        $subpIds = DB::table('proyecto')->whereIn('id_proyecto', $proyIds)->distinct()->pluck('id_subprograma');
                        $query->whereIn('id_subprograma', $subpIds);
                    }
                    if ($search) {
                        $query->where(fn($q) => $q->where('cod_subprograma', 'LIKE', "%$search%")->orWhere('nombre_subprograma', 'LIKE', "%$search%"));
                    }
                    break;

                case 'proyectos':
                    $query = Proyecto::with('subprograma.programa');
                    if ($idCedula) {
                        $actIds  = DB::table('items')->whereIn('id_item', $itemIdsByCedula)->distinct()->pluck('id_actividad');
                        $proyIds = DB::table('actividad')->whereIn('id_actividad', $actIds)->distinct()->pluck('id_proyecto');
                        $query->whereIn('id_proyecto', $proyIds);
                    }
                    if ($search) {
                        $query->where(fn($q) => $q->where('cod_proyecto', 'LIKE', "%$search%")->orWhere('nombre_proyecto', 'LIKE', "%$search%"));
                    }
                    break;

                case 'actividades':
                    $query = Actividad::with('proyecto.subprograma.programa');
                    if ($idCedula) {
                        $actIds = DB::table('items')->whereIn('id_item', $itemIdsByCedula)->distinct()->pluck('id_actividad');
                        $query->whereIn('id_actividad', $actIds);
                    }
                    if ($search) {
                        $query->where(fn($q) => $q->where('cod_actividad', 'LIKE', "%$search%")->orWhere('nombre_actividad', 'LIKE', "%$search%"));
                    }
                    break;

                case 'items':
                default:
                    $baseRelations = [
                        'actividad.proyecto.subprograma.programa',
                        'ubicacion',
                        'organismo',
                        'naturalezaPrestacion',
                    ];

                    if ($idCedula) {
                        $query = Item::with(array_merge($baseRelations, [
                            'fuentesFinanciamiento' => fn($q) => $q->wherePivot('id_cedula_presupuestaria', $idCedula),
                        ]))->whereIn('id_item', $itemIdsByCedula);
                    } else {
                        $query = Item::with(array_merge($baseRelations, ['fuentesFinanciamiento']));
                    }

                    if ($search) {
                        $query->where(fn($q) => $q->where('cod_item', 'LIKE', "%$search%")->orWhere('nombre_item', 'LIKE', "%$search%"));
                    }
                    break;
            }

            $total = $query->count();
            $data  = $query->offset($offset)->limit($limit)->get();

            return response()->json([
                'success'    => true,
                'data'       => $data,
                'pagination' => [
                    'total' => $total,
                    'page'  => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Debug: Mostrar conteos de datos
     */
    public function debugCounts()
    {
        return response()->json([
            'programas' => Programa::count(),
            'subprogramas' => Subprograma::count(),
            'proyectos' => Proyecto::count(),
            'actividades' => Actividad::count(),
            'items' => Item::count(),
            'ubicaciones' => Geografica::count(),
            'fuentes' => FuenteFinanciamiento::count(),
            'organismos' => Organismo::count(),
            'naturalezas' => NaturalezaPrestacion::count(),
        ], 200);
    }
}

