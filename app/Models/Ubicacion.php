<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ubicacion extends Model
{
    use HasFactory;

    protected $table = 'ubicacion';

    protected $primaryKey = 'id_ubicacion';

    protected $fillable = [
        'cod_ubicacion',
        'nombre_ubicacion',
    ];

    public $timestamps = true;

    // Relaciones
    public function items()
    {
        return $this->hasMany(Item::class, 'id_ubicacion', 'id_ubicacion');
    }
}
