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
        Schema::create('items', function (Blueprint $table) {
            $table->id('id_item');
            $table->string('cod_item', 50);
            $table->string('nombre_item', 255);
            $table->foreignId('id_actividad')->nullable()->constrained('actividad', 'id_actividad');
            $table->foreignId('id_ubicacion')->nullable()->constrained('ubicacion', 'id_ubicacion');
            $table->foreignId('id_organismo')->nullable()->constrained('organismos', 'id_organismo');
            $table->foreignId('id_naturaleza')->nullable()->constrained('naturaleza_prestacion', 'id_naturaleza');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
