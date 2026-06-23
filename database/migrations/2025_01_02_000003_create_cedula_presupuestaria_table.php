<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cedula_presupuestaria', function (Blueprint $table) {
            $table->id('id_cedula_presupuestaria');
            $table->integer('anio');
            $table->timestamps();
        });

        // Insertar año anterior y año actual (orden ascendente → IDs 1 y 2)
        // El año siguiente se crea automáticamente el 1 de enero (cedula:next-year)
        $añoActual = now()->year;

        DB::table('cedula_presupuestaria')->insert([
            ['anio' => $añoActual - 1, 'created_at' => now(), 'updated_at' => now()],
            ['anio' => $añoActual,     'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cedula_presupuestaria');
    }
};
