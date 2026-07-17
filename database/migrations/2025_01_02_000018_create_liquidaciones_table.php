<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id('id_liquidacion');
            $table->decimal('cantidad_liquidacion', 15, 2);
            $table->date('fecha_creacion');
            $table->string('memorando', 100);
            $table->string('estado', 50);
            $table->string('motivo_anulacion', 255)->nullable();
            $table->unsignedBigInteger('id_usuario_anulacion')->nullable();
            $table->foreignId('id_item')->constrained('items', 'id_item');
            $table->foreignId('id_certificacion_item')
                  ->nullable()
                  ->constrained('certificacion_items', 'id_certificacion_item')
                  ->onDelete('cascade');
            $table->foreign('id_usuario_anulacion')->references('id_usuario')->on('usuarios')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
