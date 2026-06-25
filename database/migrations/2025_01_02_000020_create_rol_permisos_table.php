<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_permisos', function (Blueprint $table) {
            $table->id();
            $table->string('cargo');
            $table->string('modulo');
            $table->string('accion');
            $table->timestamps();

            $table->unique(['cargo', 'modulo', 'accion']);
            $table->index(['cargo', 'modulo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permisos');
    }
};
