<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificacion extends Model
{
    protected $table = 'certificacion';
    protected $primaryKey = 'id_certificacion';
    protected $fillable = [
        'numero_certificado',
        'descripcion',
        'fecha_elaboracion',
        'clase_registro',
        'clase_gasto',
        'tipo_doc_respaldo',
        'clase_doc_respaldo',
        'estado',
        'id_usuario',
        'id_unidad_requiriente',
        'id_cedula_presupuestaria',
        'seccion_memorando'
    ];

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'fecha_elaboracion' => 'datetime:Y-m-d',
        ];
    }

    // RELACIONES

    /**
     * Relación: Usuario que creó el certificado
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relación: Entidad Requiriente
     */
    public function unidadRequiriente(): BelongsTo
    {
        return $this->belongsTo(UnidadRequiriente::class, 'id_unidad_requiriente', 'id_unidad_requiriente');
    }

    /**
     * Relación: Cédula Presupuestaria
     */
    public function cedulaPresupuestaria(): BelongsTo
    {
        return $this->belongsTo(CedulaPresupuestaria::class, 'id_cedula_presupuestaria', 'id_cedula_presupuestaria');
    }

    /**
     * Relación: Items agregados al certificado (muchos a muchos)
     */
    public function items(): HasMany
    {
        return $this->hasMany(CertificacionItem::class, 'id_certificacion', 'id_certificacion');
    }

    /**
     * monto_total se calcula dinámicamente desde items()->sum('monto').
     * No existe columna monto_total en BD; este método es no-op.
     */
    public function actualizarMontoTotal(): void {}

    /**
     * Obtener resumen del certificado con todos los datos
     */
    public function getDetalles()
    {
        return [
            'id_certificacion'         => $this->id_certificacion,
            'id_cedula_presupuestaria' => $this->id_cedula_presupuestaria,
            'numero_certificado'       => $this->numero_certificado,
            'descripcion' => $this->descripcion,
            'fecha_elaboracion' => $this->fecha_elaboracion,
            'monto_total' => (float) $this->items()->sum('monto'),
            'seccion_memorando' => $this->seccion_memorando,
            'clase_registro' => $this->clase_registro,
            'clase_gasto' => $this->clase_gasto,
            'tipo_doc_respaldo' => $this->tipo_doc_respaldo,
            'clase_doc_respaldo' => $this->clase_doc_respaldo,
            'estado' => $this->estado,
            'motivo_rechazo' => $this->motivo_rechazo,
            'usuario' => $this->usuario ? trim($this->usuario->nombres . ' ' . $this->usuario->apellidos) : null,
            'id_unidad_requiriente' => $this->id_unidad_requiriente,
            'entidad' => $this->unidadRequiriente?->nombre_entidad,
            'items' => $this->items()->with([
                'item',
                'programa',
                'subprograma',
                'proyecto',
                'actividad',
                'fuente',
                'ubicacion',
                'organismo',
                'naturaleza'
            ])->get(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
