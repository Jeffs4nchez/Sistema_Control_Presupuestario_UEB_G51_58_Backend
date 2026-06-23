<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuente_items', function (Blueprint $table) {
            $table->foreignId('id_fuente')->constrained('fuente_financiamiento', 'id_fuente');
            $table->foreignId('id_item')->constrained('items', 'id_item');
            $table->foreignId('id_cedula_presupuestaria')->constrained('cedula_presupuestaria', 'id_cedula_presupuestaria');
            $table->primary(['id_fuente', 'id_item', 'id_cedula_presupuestaria']);
            $table->decimal('asignado', 15, 2)->nullable();
            $table->decimal('modificado', 15, 2)->nullable();
            $table->decimal('comprometido', 15, 2)->nullable();
            $table->decimal('devengado', 15, 2)->nullable();
            $table->decimal('pagado', 15, 2)->nullable();
            $table->decimal('por_comprometer', 15, 2)->nullable();
            $table->decimal('por_devengar', 15, 2)->nullable();
            $table->decimal('por_pagar', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuente_items');
    }
};
