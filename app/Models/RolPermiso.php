<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolPermiso extends Model
{
    protected $table    = 'rol_permisos';
    protected $fillable = ['cargo', 'modulo', 'accion'];

    public static function tiene(?string $cargo, string $modulo, string $accion): bool
    {
        if (!$cargo) return false;

        return static::where('cargo', $cargo)
                     ->where('modulo', $modulo)
                     ->where('accion', $accion)
                     ->exists();
    }

    public static function porCargo(string $cargo): array
    {
        return static::where('cargo', $cargo)
                     ->get()
                     ->groupBy('modulo')
                     ->map(fn($items) => $items->pluck('accion')->values())
                     ->toArray();
    }
}
