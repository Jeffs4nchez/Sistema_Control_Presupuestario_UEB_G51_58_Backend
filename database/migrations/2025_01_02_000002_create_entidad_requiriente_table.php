<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_requiriente', function (Blueprint $table) {
            $table->id('id_unidad_requiriente');
            $table->string('nombre_entidad', 100);
            $table->string('responsable_entidad', 100);
            $table->string('correo_institucional', 100);
            $table->string('memorando', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_requiriente');
    }
};
