<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Geografica extends Model
{
    protected $table = 'ubicacion';
    protected $primaryKey = 'id_ubicacion';
    protected $fillable = ['cod_ubicacion', 'nombre_ubicacion'];
    public $timestamps = true;

    public function items()
    {
        return $this->hasMany(Item::class, 'id_ubicacion');
    }
}
