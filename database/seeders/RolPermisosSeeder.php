<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RolPermiso;

class RolPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'Administrador del sistema' => [
                'certificaciones' => ['ver', 'crear', 'editar', 'aprobar', 'rechazar', 'reenviar', 'errar'],
                'liquidaciones'   => ['ver', 'crear', 'anular'],
            ],
            'Director(a) financiero' => [
                'certificaciones' => ['ver', 'crear', 'editar', 'aprobar', 'rechazar', 'reenviar', 'errar'],
                'liquidaciones'   => ['ver', 'crear', 'anular'],
            ],
            'Analista de presupuesto 3' => [
                'certificaciones' => ['ver', 'crear', 'editar', 'aprobar', 'rechazar', 'reenviar'],
                'liquidaciones'   => ['ver', 'crear'],
            ],
            'Analista de presupuesto 1' => [
                'certificaciones' => ['ver', 'crear', 'editar', 'reenviar'],
                'liquidaciones'   => ['ver', 'crear'],
            ],
            'Director(a) de talento humano' => [
                'certificaciones' => ['ver'],
                'liquidaciones'   => [],
            ],
            'Rector' => [
                'certificaciones' => ['ver'],
                'liquidaciones'   => [],
            ],
        ];

        RolPermiso::truncate();

        foreach ($matrix as $cargo => $modulos) {
            foreach ($modulos as $modulo => $acciones) {
                foreach ($acciones as $accion) {
                    RolPermiso::create([
                        'cargo'  => $cargo,
                        'modulo' => $modulo,
                        'accion' => $accion,
                    ]);
                }
            }
        }
    }
}
