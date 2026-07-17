<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuenteFinanciamiento extends Model
{
    protected $table = 'fuente_financiamiento';
    protected $primaryKey = 'id_fuente';
    protected $fillable = ['cod_fuente', 'nombre_fuente'];
    public $timestamps = true;

    public function items()
    {
        return $this->belongsToMany(Item::class, 'fuente_items', 'id_fuente', 'id_item');
    }

    public function actividades()
    {
        return $this->belongsToMany(Actividad::class, 'actividad_fuente', 'id_fuente', 'id_actividad');
    }
}
