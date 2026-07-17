<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permiso_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->string('nombre_usuario');
            $table->string('cargo_modificado');
            $table->string('modulo');
            $table->json('acciones_anteriores');
            $table->json('acciones_nuevas');
            $table->enum('tipo_documento', ['quipux', 'oficio', 'memorando']);
            $table->string('numero_documento');
            $table->date('fecha_documento');
            $table->text('observacion')->nullable();
            $table->string('archivo_documento')->nullable();
            $table->string('nombre_archivo_original')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->onDelete('restrict');
            $table->index(['cargo_modificado', 'modulo']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso_historial');
    }
};
