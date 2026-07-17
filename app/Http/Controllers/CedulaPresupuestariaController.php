<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Programa;
use App\Models\Subprograma;
use App\Models\Proyecto;
use App\Models\Actividad;
use App\Models\Geografica;
use App\Models\FuenteFinanciamiento;
use App\Models\Organismo;
use App\Models\NaturalezaPrestacion;
use App\Models\Item;

class CedulaPresupuestariaController extends Controller
{
    /**
     * Cargar CSV unificado de cédula presupuestaria (29 columnas, delimitador coma).
     * Construye la jerarquía presupuestaria y guarda los valores financieros en un solo paso.
     *
     * Columnas esperadas:
     *   0-8  : DESCRIPCIONG1..9  (nombres de los 9 niveles)
     *   9-18 : COL1..COL10       (valores; COL3=codificado, COL4=certificado y COL20=% no se guardan)
     *   19   : COL20             (% ejecución, se ignora)
     *   20-28: CODIGOG1..9       (códigos de los 9 niveles)
     */
    public function upload(Request $request)
    {
        $rolesPermitidos = ['Director(a) financiero', 'Administrador del sistema'];
        if (!in_array(Auth::user()?->cargo, $rolesPermitidos)) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para cargar la cédula presupuestaria'], 403);
        }

        $request->validate([
            'csv_file'                 => 'required|file|mimes:csv,txt|max:10240',
            'id_cedula_presupuestaria' => 'nullable|integer|exists:cedula_presupuestaria,id_cedula_presupuestaria',
        ]);

        $idCedula = $request->input('id_cedula_presupuestaria');
        if (!$idCedula) {
            $cedActual = DB::table('cedula_presupuestaria')->where('anio', now()->year)->first();
            $idCedula  = $cedActual?->id_cedula_presupuestaria;
        }

        try {
            $file     = $request->file('csv_file');
            $raw      = $file->getContent();
            $encoding = mb_detect_encoding($raw, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'UTF-16'], true);
            $content  = $encoding && $encoding !== 'UTF-8'
                ? mb_convert_encoding($raw, 'UTF-8', $encoding)
                : $raw;
            $content = ltrim($content, "\xEF\xBB\xBF");
            $content = str_replace("\r\n", "\n", str_replace("\r", "\n", $content));
            $lines   = explode("\n", $content);

            // ── FASE 1: Validar encabezado ──────────────────────────────────
            if (count($lines) < 2) {
                return response()->json(['success' => false, 'message' => 'El archivo está vacío o solo contiene el encabezado.'], 422);
            }

            // Auto-detectar delimitador: coma o punto y coma
            $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';

            $header    = str_getcsv($lines[0], $delimiter);
            $headerStr = strtoupper(implode('|', array_map('trim', $header)));

            if (!str_contains($headerStr, 'DESCRIPCIONG') || !str_contains($headerStr, 'CODIGOG')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato incorrecto: el encabezado no corresponde al CSV de Cédula Presupuestaria. Se esperan columnas DESCRIPCIONG1-9 y CODIGOG1-9.',
                ], 422);
            }

            if (count($header) !== 29) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato incorrecto: el encabezado tiene ' . count($header) . ' columnas, se requieren exactamente 29.',
                ], 422);
            }

            // ── FASE 2: Procesar filas ──────────────────────────────────────
            $cache         = [];
            $processedCount = 0;
            $insertCount   = 0;
            $updateCount   = 0;
            $skippedCount  = 0;
            $errors        = [];
            $dataLines     = array_slice($lines, 1);
            $totalDataRows = 0;

            foreach ($dataLines as $idx => $line) {
                $rowNum = $idx + 2;
                if (empty(trim($line))) continue;
                $totalDataRows++;

                try {
                    $row = str_getcsv($line, $delimiter);

                    if (count($row) !== 29) {
                        $errors[] = ['row' => $rowNum, 'error' => 'Columnas: esperadas 29, encontradas ' . count($row)];
                        $skippedCount++;
                        continue;
                    }

                    // Descripciones (índices 0-8)
                    $nomPrograma    = trim($row[0]);
                    $nomSubprograma = trim($row[1]);
                    $nomProyecto    = trim($row[2]);
                    $nomActividad   = trim($row[3]);
                    $nomItem        = trim($row[4]);
                    $nomUbicacion   = trim($row[5]);
                    $nomFuente      = trim($row[6]);
                    $nomOrganismo   = trim($row[7]);
                    $nomNaturaleza  = trim($row[8]);

                    // Valores financieros: COL1=9, COL2=10, (COL3=11 codificado y COL4=12 certificado se ignoran),
                    // COL5=13, COL6=14, COL7=15, COL8=16, COL9=17, COL10=18, (COL20=19 se ignora)
                    $asignado        = $this->parseDecimal($row[9]);
                    $modificado      = $this->parseDecimal($row[10]);
                    $comprometido    = $this->parseDecimal($row[13]);
                    $devengado       = $this->parseDecimal($row[14]);
                    $pagado          = $this->parseDecimal($row[15]);
                    $por_comprometer = $this->parseDecimal($row[16]);
                    $por_devengar    = $this->parseDecimal($row[17]);
                    $por_pagar       = $this->parseDecimal($row[18]);

                    // Códigos (índices 20-28)
                    $codPrograma    = trim($row[20]);
                    $codSubprograma = trim($row[21]);
                    $codProyecto    = trim($row[22]);
                    $codActividad   = trim($row[23]);
                    $codItem        = trim($row[24]);
                    $codUbicacion   = trim($row[25]);
                    $codFuente      = trim($row[26]);
                    $codOrganismo   = trim($row[27]);
                    $codNaturaleza  = trim($row[28]);

                    // Restaurar ceros a la izquierda
                    if (is_numeric($codPrograma))   $codPrograma   = str_pad($codPrograma,   2, '0', STR_PAD_LEFT);
                    if (is_numeric($codItem))        $codItem       = str_pad($codItem,       6, '0', STR_PAD_LEFT);
                    if (is_numeric($codUbicacion))   $codUbicacion  = str_pad($codUbicacion,  4, '0', STR_PAD_LEFT);
                    if (is_numeric($codFuente))      $codFuente     = str_pad($codFuente,     3, '0', STR_PAD_LEFT);
                    if (is_numeric($codOrganismo))   $codOrganismo  = str_pad($codOrganismo,  4, '0', STR_PAD_LEFT);
                    if (is_numeric($codNaturaleza))  $codNaturaleza = str_pad($codNaturaleza, 4, '0', STR_PAD_LEFT);

                    // Validar códigos obligatorios
                    $vacios = array_filter([
                        $codPrograma    === '' ? 'CODIGOG1' : null,
                        $codSubprograma === '' ? 'CODIGOG2' : null,
                        $codProyecto    === '' ? 'CODIGOG3' : null,
                        $codActividad   === '' ? 'CODIGOG4' : null,
                        $codItem        === '' ? 'CODIGOG5' : null,
                        $codUbicacion   === '' ? 'CODIGOG6' : null,
                        $codFuente      === '' ? 'CODIGOG7' : null,
                        $codOrganismo   === '' ? 'CODIGOG8' : null,
                        $codNaturaleza  === '' ? 'CODIGOG9' : null,
                    ]);
                    if (!empty($vacios)) {
                        $errors[] = ['row' => $rowNum, 'error' => 'Campos vacíos: ' . implode(', ', $vacios)];
                        $skippedCount++;
                        continue;
                    }

                    // 1. Programa
                    $ck = "prog_$codPrograma";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = Programa::firstOrCreate(
                            ['cod_programa' => $codPrograma],
                            ['nombre_programa' => $nomPrograma]
                        )->id_programa;
                    }
                    $idPrograma = $cache[$ck];

                    // 2. Subprograma
                    $ck = "sub_{$codPrograma}_{$codSubprograma}";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = Subprograma::firstOrCreate(
                            ['cod_subprograma' => $codSubprograma, 'id_programa' => $idPrograma],
                            ['nombre_subprograma' => $nomSubprograma, 'id_programa' => $idPrograma]
                        )->id_subprograma;
                    }
                    $idSubprograma = $cache[$ck];

                    // 3. Proyecto
                    $ck = "proy_{$codSubprograma}_{$codProyecto}";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = Proyecto::firstOrCreate(
                            ['cod_proyecto' => $codProyecto, 'id_subprograma' => $idSubprograma],
                            ['nombre_proyecto' => $nomProyecto, 'id_subprograma' => $idSubprograma]
                        )->id_proyecto;
                    }
                    $idProyecto = $cache[$ck];

                    // 4. Actividad
                    $ck = "act_{$codProyecto}_{$codActividad}";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = Actividad::firstOrCreate(
                            ['cod_actividad' => $codActividad, 'id_proyecto' => $idProyecto],
                            ['nombre_actividad' => $nomActividad, 'id_proyecto' => $idProyecto]
                        )->id_actividad;
                    }
                    $idActividad = $cache[$ck];

                    // 5. Ubicación
                    $ck = "ubic_$codUbicacion";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = Geografica::firstOrCreate(
                            ['cod_ubicacion' => $codUbicacion],
                            ['nombre_ubicacion' => $nomUbicacion]
                        )->id_ubicacion;
                    }
                    $idUbicacion = $cache[$ck];

                    // 6. Fuente
                    $ck = "fte_$codFuente";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = FuenteFinanciamiento::firstOrCreate(
                            ['cod_fuente' => $codFuente],
                            ['nombre_fuente' => $nomFuente]
                        )->id_fuente;
                    }
                    $idFuente = $cache[$ck];

                    // 7. Organismo
                    $ck = "org_$codOrganismo";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = Organismo::firstOrCreate(
                            ['cod_organismo' => $codOrganismo],
                            ['nombre_organismo' => $nomOrganismo]
                        )->id_organismo;
                    }
                    $idOrganismo = $cache[$ck];

                    // 8. Naturaleza Prestación
                    $ck = "nat_{$codOrganismo}_{$codNaturaleza}";
                    if (!isset($cache[$ck])) {
                        $cache[$ck] = NaturalezaPrestacion::firstOrCreate(
                            ['cod_naturaleza' => $codNaturaleza, 'id_organismo' => $idOrganismo],
                            ['nombre_naturaleza' => $nomNaturaleza, 'id_organismo' => $idOrganismo]
                        )->id_naturaleza;
                    }
                    $idNaturaleza = $cache[$ck];

                    // 9. Ítem
                    $item = Item::firstOrCreate(
                        [
                            'cod_item'     => $codItem,
                            'id_actividad' => $idActividad,
                            'id_ubicacion' => $idUbicacion,
                            'id_organismo' => $idOrganismo,
                            'id_naturaleza' => $idNaturaleza,
                        ],
                        [
                            'nombre_item'  => $nomItem,
                            'id_actividad' => $idActividad,
                            'id_ubicacion' => $idUbicacion,
                            'id_organismo' => $idOrganismo,
                            'id_naturaleza' => $idNaturaleza,
                        ]
                    );

                    // 10. Relación Actividad-Fuente
                    DB::table('actividad_fuente')->updateOrInsert(
                        ['id_actividad' => $idActividad, 'id_fuente' => $idFuente],
                        ['created_at' => now(), 'updated_at' => now()]
                    );

                    // 11. fuente_items con valores financieros (insert o update)
                    $financials = [
                        'asignado'        => $asignado,
                        'modificado'      => $modificado,
                        'comprometido'    => $comprometido,
                        'devengado'       => $devengado,
                        'pagado'          => $pagado,
                        'por_comprometer' => $por_comprometer,
                        'por_devengar'    => $por_devengar,
                        'por_pagar'       => $por_pagar,
                        'updated_at'      => now(),
                    ];

                    $existe = DB::table('fuente_items')
                        ->where('id_item',                  $item->id_item)
                        ->where('id_fuente',                $idFuente)
                        ->where('id_cedula_presupuestaria', $idCedula)
                        ->exists();

                    if ($existe) {
                        DB::table('fuente_items')
                            ->where('id_item',                  $item->id_item)
                            ->where('id_fuente',                $idFuente)
                            ->where('id_cedula_presupuestaria', $idCedula)
                            ->update($financials);
                        $updateCount++;
                    } else {
                        DB::table('fuente_items')->insert(array_merge($financials, [
                            'id_item'                  => $item->id_item,
                            'id_fuente'                => $idFuente,
                            'id_cedula_presupuestaria' => $idCedula,
                            'created_at'               => now(),
                        ]));
                        $insertCount++;
                    }

                    $processedCount++;

                } catch (\Exception $e) {
                    $errors[] = ['row' => $rowNum, 'error' => 'Error interno: ' . $e->getMessage()];
                    $skippedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "$processedCount de $totalDataRows filas procesadas. $insertCount nuevas, $updateCount actualizadas, $skippedCount omitidas.",
                'data' => [
                    'total_rows'      => $totalDataRows,
                    'processed_count' => $processedCount,
                    'insert_count'    => $insertCount,
                    'update_count'    => $updateCount,
                    'skipped'         => $skippedCount,
                    'errors'          => array_slice($errors, 0, 20),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fatal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen de items con valores financieros
     */
    public function summary()
    {
        try {
            $summary = [
                'items_con_datos' => \DB::table('fuente_items')->whereNotNull('asignado')->count(),
                'valor_total_asignado' => \DB::table('fuente_items')->sum('asignado'),
                'valor_total_modificado' => \DB::table('fuente_items')->sum('modificado'),
                'valor_total_comprometido' => \DB::table('fuente_items')->sum('comprometido'),
                'valor_total_devengado' => \DB::table('fuente_items')->sum('devengado'),
                'valor_total_pagado' => \DB::table('fuente_items')->sum('pagado'),
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
     * Obtener datos de items desde fuente_items (para mostrar los 46 registros)
     */
    public function getData(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 50);
            $search = $request->input('search', '');

            $offset = ($page - 1) * $limit;

            // Traer desde fuente_items (46 registros) con joins a todas las relaciones
            // Estructura: programa -> subprograma -> proyecto -> actividad -> items
            $query = \DB::table('fuente_items')
                ->leftJoin('items', 'fuente_items.id_item', '=', 'items.id_item')
                ->leftJoin('fuente_financiamiento', 'fuente_items.id_fuente', '=', 'fuente_financiamiento.id_fuente')
                ->leftJoin('actividad', 'items.id_actividad', '=', 'actividad.id_actividad')
                ->leftJoin('proyecto', 'actividad.id_proyecto', '=', 'proyecto.id_proyecto')
                ->leftJoin('subprograma', 'proyecto.id_subprograma', '=', 'subprograma.id_subprograma')
                ->leftJoin('programa', 'subprograma.id_programa', '=', 'programa.id_programa')
                ->leftJoin('ubicacion', 'items.id_ubicacion', '=', 'ubicacion.id_ubicacion')
                ->select(
                    'items.id_item',
                    'fuente_items.id_fuente',
                    'items.cod_item',
                    'items.nombre_item',
                    'programa.cod_programa',
                    'actividad.cod_actividad',
                    'fuente_financiamiento.cod_fuente',
                    'ubicacion.cod_ubicacion',
                    'fuente_items.asignado',
                    'fuente_items.modificado',
                    'fuente_items.comprometido',
                    'fuente_items.devengado',
                    'fuente_items.pagado',
                    'fuente_items.por_comprometer',
                    'fuente_items.por_devengar',
                    'fuente_items.por_pagar',
                    'fuente_items.updated_at'
                );

            if ($search) {
                $query->where('items.cod_item', 'LIKE', "%$search%")
                      ->orWhere('items.nombre_item', 'LIKE', "%$search%");
            }

            $total = $query->count();
            $items = $query->offset($offset)
                          ->limit($limit)
                          ->get();

            // Transformar datos para mostrar valores por defecto si están vacíos
            $data = $items->map(function ($item) {
                // Certificado = SUM(cert_items.monto) - SUM(liquidaciones) para este item+fuente
                $totalCertificado = (float) \DB::table('certificacion_items')
                    ->join('certificacion', 'certificacion_items.id_certificacion', '=', 'certificacion.id_certificacion')
                    ->where('certificacion_items.id_item', $item->id_item)
                    ->where('certificacion_items.id_fuente', $item->id_fuente)
                    ->whereIn('certificacion.estado', ['APROBADO', 'LIQUIDADO'])
                    ->sum('certificacion_items.monto');

                $totalLiquidado = (float) \DB::table('liquidaciones')
                    ->join('certificacion_items', 'liquidaciones.id_certificacion_item', '=', 'certificacion_items.id_certificacion_item')
                    ->join('certificacion', 'certificacion_items.id_certificacion', '=', 'certificacion.id_certificacion')
                    ->where('certificacion_items.id_item', $item->id_item)
                    ->where('certificacion_items.id_fuente', $item->id_fuente)
                    ->whereIn('certificacion.estado', ['APROBADO', 'LIQUIDADO'])
                    ->where('liquidaciones.estado', '!=', 'ANULADA')
                    ->sum('liquidaciones.cantidad_liquidacion');

                $certificado = max(0, $totalCertificado - $totalLiquidado);

                return [
                    'id_item' => $item->id_item,
                    'cod_item' => $item->cod_item,
                    'nombre_item' => $item->nombre_item,
                    'cod_programa' => $item->cod_programa ?? '-',
                    'cod_actividad' => $item->cod_actividad ?? '-',
                    'cod_fuente' => $item->cod_fuente ?? '-',
                    'cod_ubicacion' => $item->cod_ubicacion ?? '-',
                    'asignado' => $item->asignado ?? '-',
                    'modificado' => $item->modificado ?? '-',
                    'certificado' => $certificado,
                    'comprometido' => $item->comprometido ?? '-',
                    'devengado' => $item->devengado ?? '-',
                    'pagado' => $item->pagado ?? '-',
                    'por_comprometer' => $item->por_comprometer ?? '-',
                    'por_devengar' => $item->por_devengar ?? '-',
                    'por_pagar' => $item->por_pagar ?? '-',
                    'updated_at' => $item->updated_at
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
     * Parsear valores decimales desde string
     * Maneja formato europeo: 1.000.000,50 (puntos de miles, coma de decimal)
     */
    private function parseDecimal($value)
    {
        $original = $value;
        
        // Manejar null
        if ($value === null) {
            return null;
        }
        
        // Convertir a string si no lo es
        $value = (string) $value;
        
        // Limpiar espacios y caracteres especiales
        $value = trim($value);
        
        // Remover comillas dobles y simples
        $value = str_replace('"', '', $value);
        $value = str_replace("'", '', $value);
        
        // Vacío después de limpiar
        if ($value === '' || $value === '-') {
            return null;
        }
        
        // Si es "0" retornar 0.0
        if ($value === '0' || $value === '0.0') {
            return 0.0;
        }
        
        // ========== MANEJO DE FORMATO EUROPEO ==========
        // El formato es: 1.000.000,50 (puntos = miles, coma = decimal)
        
        // Detectar si tiene coma (separador decimal)
        if (strpos($value, ',') !== false) {
            // Tiene coma: asumir formato europeo
            // Remover todos los puntos (separadores de miles)
            $value = str_replace('.', '', $value);
            // Convertir coma a punto (decimal)
            $value = str_replace(',', '.', $value);
        }
        // Si NO tiene coma pero SÍ tiene puntos, asumir que son miles
        elseif (strpos($value, '.') !== false) {
            // Podría ser: 1.000 (mil) o 1.5 (decimal con punto)
            // Heurística: si el punto es el 4to carácter desde el final, probablemente es decimal
            $parts = explode('.', $value);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // 1.5 o 1.50 -> es decimal
                // Dejar como está (ya tiene punto como decimal)
            } else {
                // 1.000.000 o similar -> son miles
                // Remover todos los puntos
                $value = str_replace('.', '', $value);
            }
        }
        
        // Remover espacios adicionales
        $value = trim($value);
        
        // Validar que sea numérico
        if (!is_numeric($value)) {
            \Log::warning("parseDecimal: NO es numérico después procesar", [
                'original' => $original,
                'processed' => $value
            ]);
            return null;
        }
        
        // Convertir a float
        $floatValue = (float) $value;
        
        // Retornar null si es NaN o infinito
        if (is_nan($floatValue) || is_infinite($floatValue)) {
            return null;
        }
        
        return $floatValue;
    }
}
