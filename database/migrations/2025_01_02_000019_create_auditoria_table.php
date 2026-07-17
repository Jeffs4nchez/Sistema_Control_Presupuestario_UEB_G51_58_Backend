<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id('id_auditoria');
            $table->unsignedBigInteger('id_certificacion');
            $table->string('numero_certificado', 50);
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('nombre_usuario', 200)->nullable();
            $table->string('accion', 50);
            $table->string('estado_anterior', 50)->nullable();
            $table->string('estado_nuevo', 50)->nullable();
            $table->decimal('monto_anterior', 15, 2)->nullable();
            $table->decimal('monto_nuevo', 15, 2)->nullable();
            $table->string('campo_modificado', 100)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('fecha_hora')->useCurrent();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
