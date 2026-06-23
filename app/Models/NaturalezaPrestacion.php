<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NaturalezaPrestacion extends Model
{
    protected $table = 'naturaleza_prestacion';
    protected $primaryKey = 'id_naturaleza';
    protected $fillable = ['cod_naturaleza', 'nombre_naturaleza', 'id_organismo'];
    public $timestamps = true;

    public function organismo()
    {
        return $this->belongsTo(Organismo::class, 'id_organismo');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'id_naturaleza');
    }
}
