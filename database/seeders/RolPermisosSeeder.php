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
                'certificaciones' => ['ver', 'crear', 'aprobar', 'rechazar', 'reenviar', 'errar'],
                'liquidaciones'   => ['ver', 'crear', 'anular', 'eliminar'],
            ],
            'Director(a) financiero' => [
                'certificaciones' => ['ver', 'crear', 'aprobar', 'rechazar', 'reenviar', 'errar'],
                'liquidaciones'   => ['ver', 'crear', 'anular', 'eliminar'],
            ],
            'Analista de presupuesto 3' => [
                'certificaciones' => ['ver', 'crear', 'aprobar', 'rechazar', 'reenviar', 'errar'],
                'liquidaciones'   => ['ver', 'crear', 'anular'],
            ],
            'Analista de presupuesto 1' => [
                'certificaciones' => ['ver', 'crear', 'reenviar', 'errar'],
                'liquidaciones'   => ['ver', 'crear'],
            ],
            'Director(a) Talento Humano' => [
                'certificaciones' => ['ver'],
                'liquidaciones'   => ['ver'],
            ],
            'Rector' => [
                'certificaciones' => ['ver'],
                'liquidaciones'   => ['ver'],
            ],
        ];

        foreach ($matrix as $cargo => $modulos) {
            foreach ($modulos as $modulo => $acciones) {
                foreach ($acciones as $accion) {
                    RolPermiso::firstOrCreate([
                        'cargo'  => $cargo,
                        'modulo' => $modulo,
                        'accion' => $accion,
                    ]);
                }
            }
        }
    }
}
