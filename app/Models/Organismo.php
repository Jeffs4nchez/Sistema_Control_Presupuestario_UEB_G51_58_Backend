<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organismo extends Model
{
    protected $table = 'organismos';
    protected $primaryKey = 'id_organismo';
    protected $fillable = ['cod_organismo', 'nombre_organismo'];
    public $timestamps = true;

    public function naturalezasPrestacion()
    {
        return $this->hasMany(NaturalezaPrestacion::class, 'id_organismo');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'id_organismo');
    }
}
