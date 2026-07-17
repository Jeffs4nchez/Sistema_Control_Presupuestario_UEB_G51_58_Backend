<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subprograma extends Model
{
    protected $table = 'subprograma';
    protected $primaryKey = 'id_subprograma';
    protected $fillable = ['cod_subprograma', 'nombre_subprograma', 'id_programa'];
    public $timestamps = true;

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'id_programa');
    }

    public function proyectos()
    {
        return $this->hasMany(Proyecto::class, 'id_subprograma');
    }
}
