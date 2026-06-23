<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id_item';
    protected $fillable = [
        'cod_item',
        'nombre_item',
        'id_actividad',
        'id_ubicacion',
        'id_organismo',
        'id_naturaleza',
        'asignado',
        'modificado',
        'certificado',
        'comprometido',
        'devengado',
        'pagado',
        'por_comprometer',
        'por_devengar',
        'por_pagar'
    ];
    public $timestamps = true;

    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'id_actividad');
    }

    public function ubicacion()
    {
        return $this->belongsTo(Geografica::class, 'id_ubicacion');
    }

    public function organismo()
    {
        return $this->belongsTo(Organismo::class, 'id_organismo');
    }

    public function naturalezaPrestacion()
    {
        return $this->belongsTo(NaturalezaPrestacion::class, 'id_naturaleza');
    }

    public function fuentesFinanciamiento()
    {
        return $this->belongsToMany(FuenteFinanciamiento::class, 'fuente_items', 'id_item', 'id_fuente');
    }
}
