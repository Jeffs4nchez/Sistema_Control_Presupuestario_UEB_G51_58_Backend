<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $correo = 'jefferson.sanchez@ueb.edu.ec';

        $existe = DB::table('usuarios')->where('correo_institucional', $correo)->exists();

        if ($existe) {
            DB::table('usuarios')
                ->where('correo_institucional', $correo)
                ->update([
                    'nombres'             => 'Jefferson',
                    'apellidos'           => 'Sánchez',
                    'contrasena'          => Hash::make('Jeff2003..'),
                    'cargo'               => 'Administrador del sistema',
                    'estado'              => 'activo',
                    'contrasena_temporal' => false,
                    'intentos_fallidos'   => 0,
                    'updated_at'          => now(),
                ]);
            \Log::info('UserSeeder: usuario actualizado: ' . $correo);
        } else {
            DB::table('usuarios')->insert([
                'correo_institucional' => $correo,
                'nombres'             => 'Jefferson',
                'apellidos'           => 'Sánchez',
                'contrasena'          => Hash::make('Jeff2003..'),
                'cargo'               => 'Administrador del sistema',
                'estado'              => 'activo',
                'contrasena_temporal' => false,
                'intentos_fallidos'   => 0,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
            \Log::info('UserSeeder: usuario creado: ' . $correo);
        }
    }
}
