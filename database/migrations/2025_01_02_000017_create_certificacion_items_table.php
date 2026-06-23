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
        Schema::create('certificacion_items', function (Blueprint $table) {
            $table->id('id_certificacion_item');
            $table->foreignId('id_certificacion')->constrained('certificacion', 'id_certificacion')->onDelete('cascade');
            $table->foreignId('id_item')->constrained('items', 'id_item');
            $table->foreignId('id_programa')->nullable()->constrained('programa', 'id_programa');
            $table->foreignId('id_subprograma')->nullable()->constrained('subprograma', 'id_subprograma');
            $table->foreignId('id_proyecto')->nullable()->constrained('proyecto', 'id_proyecto');
            $table->foreignId('id_actividad')->nullable()->constrained('actividad', 'id_actividad');
            $table->foreignId('id_fuente')->nullable()->constrained('fuente_financiamiento', 'id_fuente');
            $table->foreignId('id_ubicacion')->nullable()->constrained('ubicacion', 'id_ubicacion');
            $table->foreignId('id_organismo')->nullable()->constrained('organismos', 'id_organismo');
            $table->foreignId('id_naturaleza')->nullable()->constrained('naturaleza_prestacion', 'id_naturaleza');
            $table->decimal('monto', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificacion_items');
    }
};
