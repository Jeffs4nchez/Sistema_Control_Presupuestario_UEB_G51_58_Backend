<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Programa extends Model
{
    protected $table = 'programa';
    protected $primaryKey = 'id_programa';
    protected $fillable = ['cod_programa', 'nombre_programa'];
    public $timestamps = true;

    public function subprogramas()
    {
        return $this->hasMany(Subprograma::class, 'id_programa');
    }
}
