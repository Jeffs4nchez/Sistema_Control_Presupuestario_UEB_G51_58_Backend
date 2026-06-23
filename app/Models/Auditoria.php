<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table      = 'auditoria';
    protected $primaryKey = 'id_auditoria';

    protected $fillable = [
        'id_certificacion',
        'numero_certificado',
        'id_usuario',
        'nombre_usuario',
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'monto_anterior',
        'monto_nuevo',
        'campo_modificado',
        'motivo',
        'fecha_hora',
    ];

    protected $casts = [
        'fecha_hora'      => 'datetime',
        'monto_anterior'  => 'float',
        'monto_nuevo'     => 'float',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
