<?php

namespace App\Exceptions\Domain;

class ReceiptClosedException extends DomainException
{
    public function __construct(string $number, string $status)
    {
        parent::__construct(
            'RECEIPT_CLOSED',
            "La nota de ingreso {$number} no admite escaneos (estado {$status})",
            ['number' => $number, 'status' => $status],
            409,
        );
    }
}
