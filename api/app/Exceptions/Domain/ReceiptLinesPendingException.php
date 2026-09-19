<?php

namespace App\Exceptions\Domain;

/** Cierre de nota con lineas sin confirmar (doc 05: 409 salvo force:true). */
class ReceiptLinesPendingException extends DomainException
{
    public function __construct(array $pendingLineIds)
    {
        parent::__construct(
            'VALIDATION_FAILED',
            'La nota tiene lineas sin confirmar',
            ['pending_line_ids' => $pendingLineIds],
            409,
        );
    }
}
