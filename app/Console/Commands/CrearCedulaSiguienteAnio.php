<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrearCedulaSiguienteAnio extends Command
{
    protected $signature   = 'cedula:next-year';
    protected $description = 'Crea la cédula presupuestaria del año siguiente si aún no existe';

    public function handle(): int
    {
        $siguienteAnio = now()->year + 1;

        $existe = DB::table('cedula_presupuestaria')
            ->where('anio', $siguienteAnio)
            ->exists();

        if ($existe) {
            $this->info("La cédula para {$siguienteAnio} ya existe. Sin cambios.");
            return self::SUCCESS;
        }

        DB::table('cedula_presupuestaria')->insert([
            'anio'       => $siguienteAnio,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('cedula_presupuestaria')
            ->where('anio', $siguienteAnio)
            ->value('id_cedula_presupuestaria');

        $this->info("Cédula presupuestaria creada: id={$id}, año={$siguienteAnio}");
        return self::SUCCESS;
    }
}
