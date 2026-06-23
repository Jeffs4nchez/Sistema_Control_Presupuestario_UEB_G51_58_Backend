<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificacion', function (Blueprint $table) {
            $table->id('id_certificacion');
            $table->string('numero_certificado', 100)->unique();
            $table->string('descripcion', 1000)->nullable();
            $table->date('fecha_elaboracion');
            $table->string('clase_registro', 100)->nullable();
            $table->string('clase_gasto', 100)->nullable();
            $table->string('tipo_doc_respaldo', 100)->nullable();
            $table->string('clase_doc_respaldo', 100)->nullable();
            $table->string('seccion_memorando', 100)->nullable();
            $table->string('estado', 50)->default('REGISTRADO');
            $table->string('motivo_rechazo', 500)->nullable();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $table->foreignId('id_unidad_requiriente')->constrained('unidad_requiriente', 'id_unidad_requiriente');
            $table->foreignId('id_cedula_presupuestaria')->constrained('cedula_presupuestaria', 'id_cedula_presupuestaria');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificacion');
    }
};
