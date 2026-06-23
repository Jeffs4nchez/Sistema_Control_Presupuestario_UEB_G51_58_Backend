<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('correo_institucional', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('contrasena');
            $table->string('api_token', 80)->unique()->nullable();
            $table->string('cargo', 100);
            $table->string('estado', 50);
            $table->boolean('contrasena_temporal')->default(false);
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->string('password_reset_token')->nullable()->unique();
            $table->timestamp('password_reset_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Insertar usuario administrador por defecto
        // Cambiar ADMIN_EMAIL y ADMIN_PASSWORD en .env antes del primer deploy
        DB::table('usuarios')->insert([
            'nombres'              => 'Administrador',
            'apellidos'            => 'Sistema',
            'correo_institucional' => env('ADMIN_EMAIL', 'admin@ueb.edu.ec'),
            'email_verified_at'    => now(),
            'contrasena'           => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe@2024!')),
            'cargo'                => 'Administrador del sistema',
            'estado'               => 'activo',
            'contrasena_temporal'  => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
    }
};
