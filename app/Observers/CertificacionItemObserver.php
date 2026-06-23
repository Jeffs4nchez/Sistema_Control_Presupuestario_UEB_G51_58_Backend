<?php

namespace App\Observers;

use App\Models\CertificacionItem;

class CertificacionItemObserver
{
    /**
     * Cuando se crea un certificacion_item
     */
    public function created(CertificacionItem $item): void
    {
        $this->actualizarFuenteItem($item);
    }

    /**
     * Cuando se actualiza un certificacion_item
     */
    public function updated(CertificacionItem $item): void
    {
        $this->actualizarFuenteItem($item);
    }

    /**
     * Cuando se elimina un certificacion_item
     */
    public function deleted(CertificacionItem $item): void
    {
        $this->actualizarFuenteItem($item);
    }

    private function actualizarFuenteItem(CertificacionItem $item): void
    {
        // certificado ya no se almacena en fuente_items; se calcula en tiempo real
    }
}
