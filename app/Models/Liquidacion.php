<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Liquidacion extends Model
{
    protected $table = 'liquidaciones';
    protected $primaryKey = 'id_liquidacion';

    protected $fillable = [
        'id_item',
        'id_certificacion_item',
        'cantidad_liquidacion',
        'fecha_creacion',
        'memorando',
        'estado',
        'motivo_anulacion',
        'id_usuario_anulacion',
    ];

    public $timestamps = true;

    public function certificacionItem()
    {
        return $this->belongsTo(CertificacionItem::class, 'id_certificacion_item', 'id_certificacion_item');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'id_item', 'id_item');
    }
}
