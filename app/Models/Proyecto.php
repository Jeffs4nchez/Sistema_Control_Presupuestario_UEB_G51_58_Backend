<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proyecto extends Model
{
    protected $table = 'proyecto';
    protected $primaryKey = 'id_proyecto';
    protected $fillable = ['cod_proyecto', 'nombre_proyecto', 'id_subprograma'];
    public $timestamps = true;

    public function subprograma()
    {
        return $this->belongsTo(Subprograma::class, 'id_subprograma');
    }

    public function actividades()
    {
        return $this->hasMany(Actividad::class, 'id_proyecto');
    }
}
