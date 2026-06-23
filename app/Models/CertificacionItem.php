<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificacionItem extends Model
{
    protected $table = 'certificacion_items';
    protected $primaryKey = 'id_certificacion_item';
    protected $fillable = [
        'id_certificacion',
        'id_item',
        'id_programa',
        'id_subprograma',
        'id_proyecto',
        'id_actividad',
        'id_fuente',
        'id_ubicacion',
        'id_organismo',
        'id_naturaleza',
        'monto'
    ];

    public $timestamps = true;

    // RELACIONES

    /**
     * Relación: Certificado padre
     */
    public function certificacion(): BelongsTo
    {
        return $this->belongsTo(Certificacion::class, 'id_certificacion', 'id_certificacion');
    }

    /**
     * Relación: Item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'id_item', 'id_item');
    }

    /**
     * Relación: Programa
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'id_programa', 'id_programa');
    }

    /**
     * Relación: Subprograma
     */
    public function subprograma(): BelongsTo
    {
        return $this->belongsTo(Subprograma::class, 'id_subprograma', 'id_subprograma');
    }

    /**
     * Relación: Proyecto
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    /**
     * Relación: Actividad
     */
    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'id_actividad', 'id_actividad');
    }

    /**
     * Relación: Fuente Financiamiento
     */
    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciamiento::class, 'id_fuente', 'id_fuente');
    }

    /**
     * Relación: Ubicación
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'id_ubicacion', 'id_ubicacion');
    }

    /**
     * Relación: Organismo
     */
    public function organismo(): BelongsTo
    {
        return $this->belongsTo(Organismo::class, 'id_organismo', 'id_organismo');
    }

    /**
     * Relación: Naturaleza Prestación
     */
    public function naturaleza(): BelongsTo
    {
        return $this->belongsTo(NaturalezaPrestacion::class, 'id_naturaleza', 'id_naturaleza');
    }
}
