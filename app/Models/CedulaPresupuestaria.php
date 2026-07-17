<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CedulaPresupuestaria extends Model
{
    protected $table = 'cedula_presupuestaria';
    protected $primaryKey = 'id_cedula_presupuestaria';
    protected $fillable = ['numero_cedula', 'anio'];
    public $timestamps = true;
}
