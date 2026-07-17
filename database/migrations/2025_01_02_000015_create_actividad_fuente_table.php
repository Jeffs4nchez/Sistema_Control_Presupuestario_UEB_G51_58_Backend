<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('actividad_fuente', function (Blueprint $table) {
            $table->foreignId('id_actividad')->constrained('actividad', 'id_actividad');
            $table->foreignId('id_fuente')->constrained('fuente_financiamiento', 'id_fuente');
            $table->primary(['id_actividad', 'id_fuente']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividad_fuente');
    }
};
