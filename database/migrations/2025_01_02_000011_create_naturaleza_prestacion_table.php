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
        Schema::create('naturaleza_prestacion', function (Blueprint $table) {
            $table->id('id_naturaleza');
            $table->string('cod_naturaleza', 50);
            $table->string('nombre_naturaleza', 100);
            $table->foreignId('id_organismo')->constrained('organismos', 'id_organismo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('naturaleza_prestacion');
    }
};
