<?php

namespace App\Observers;

use App\Models\Auditoria;
use App\Models\Certificacion;
use Illuminate\Support\Facades\Auth;

class CertificacionObserver
{
    private function usuario(): array
    {
        $u = Auth::user();
        return [
            'id'     => $u?->id_usuario ?? null,
            'nombre' => $u ? trim($u->nombres . ' ' . $u->apellidos) : 'Sistema',
        ];
    }

    public function created(Certificacion $cert): void
    {
        try {
            $u = $this->usuario();
            Auditoria::create([
                'id_certificacion'   => $cert->id_certificacion,
                'numero_certificado' => $cert->numero_certificado,
                'id_usuario'         => $u['id'],
                'nombre_usuario'     => $u['nombre'],
                'accion'             => 'CREACIÓN',
                'estado_nuevo'       => $cert->estado,
                'monto_nuevo'        => $cert->monto_total ?? 0,
                'fecha_hora'         => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Auditoria::created error: ' . $e->getMessage());
        }
    }

    // Se usa 'updating' (ANTES del save) para que getDirty() tenga los cambios pendientes.
    // getChanges() (post-save) no es confiable en todas las versiones de Laravel.
    public function updating(Certificacion $cert): void
    {
        $dirty    = $cert->getDirty();
        $original = $cert->getOriginal();
        $u        = $this->usuario();

        try {
            if (array_key_exists('estado', $dirty)) {
                Auditoria::create([
                    'id_certificacion'   => $cert->id_certificacion,
                    'numero_certificado' => $cert->numero_certificado,
                    'id_usuario'         => $u['id'],
                    'nombre_usuario'     => $u['nombre'],
                    'accion'             => 'CAMBIO_ESTADO',
                    'estado_anterior'    => $original['estado'],
                    'estado_nuevo'       => $dirty['estado'],
                    'campo_modificado'   => 'estado',
                    'fecha_hora'         => now(),
                ]);
            }

            if (array_key_exists('monto_total', $dirty)) {
                Auditoria::create([
                    'id_certificacion'   => $cert->id_certificacion,
                    'numero_certificado' => $cert->numero_certificado,
                    'id_usuario'         => $u['id'],
                    'nombre_usuario'     => $u['nombre'],
                    'accion'             => 'EDICIÓN',
                    'monto_anterior'     => $original['monto_total'],
                    'monto_nuevo'        => $dirty['monto_total'],
                    'campo_modificado'   => 'monto_total',
                    'fecha_hora'         => now(),
                ]);
            }

            $camposTexto = ['descripcion', 'seccion_memorando', 'unid_ejecutora', 'des_u_ejecutora',
                            'clase_registro', 'clase_gasto', 'tipo_doc_respaldo', 'clase_doc_respaldo'];
            foreach ($camposTexto as $campo) {
                if (array_key_exists($campo, $dirty)) {
                    Auditoria::create([
                        'id_certificacion'   => $cert->id_certificacion,
                        'numero_certificado' => $cert->numero_certificado,
                        'id_usuario'         => $u['id'],
                        'nombre_usuario'     => $u['nombre'],
                        'accion'             => 'EDICIÓN',
                        'campo_modificado'   => $campo,
                        'fecha_hora'         => now(),
                    ]);
                    break;
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Auditoria::updating error: ' . $e->getMessage());
        }
    }

    public function deleting(Certificacion $cert): void
    {
        try {
            $u = $this->usuario();
            Auditoria::create([
                'id_certificacion'   => $cert->id_certificacion,
                'numero_certificado' => $cert->numero_certificado,
                'id_usuario'         => $u['id'],
                'nombre_usuario'     => $u['nombre'],
                'accion'             => 'ELIMINACIÓN',
                'estado_anterior'    => $cert->estado,
                'campo_modificado'   => null,
                'fecha_hora'         => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Auditoria::deleting error: ' . $e->getMessage());
        }
    }
}
