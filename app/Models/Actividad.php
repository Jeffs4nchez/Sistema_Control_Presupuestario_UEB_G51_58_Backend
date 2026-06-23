<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    protected $table = 'actividad';
    protected $primaryKey = 'id_actividad';
    protected $fillable = ['cod_actividad', 'nombre_actividad', 'id_proyecto'];
    public $timestamps = true;

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'id_actividad');
    }

    public function fuentesFinanciamiento()
    {
        return $this->belongsToMany(FuenteFinanciamiento::class, 'actividad_fuente', 'id_actividad', 'id_fuente');
    }
}
