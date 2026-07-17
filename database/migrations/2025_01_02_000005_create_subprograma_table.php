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
        Schema::create('subprograma', function (Blueprint $table) {
            $table->id('id_subprograma');
            $table->string('cod_subprograma', 50);
            $table->string('nombre_subprograma', 100);
            $table->foreignId('id_programa')->constrained('programa', 'id_programa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subprograma');
    }
};
