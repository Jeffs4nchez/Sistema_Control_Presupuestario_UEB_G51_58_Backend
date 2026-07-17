<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermisoHistorial extends Model
{
    public $timestamps = false;

    protected $table = 'permiso_historial';

    protected $fillable = [
        'id_usuario',
        'nombre_usuario',
        'cargo_modificado',
        'modulo',
        'acciones_anteriores',
        'acciones_nuevas',
        'tipo_documento',
        'numero_documento',
        'fecha_documento',
        'observacion',
        'archivo_documento',
        'nombre_archivo_original',
    ];

    protected $casts = [
        'acciones_anteriores' => 'array',
        'acciones_nuevas'     => 'array',
        'fecha_documento'     => 'date',
        'created_at'          => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
