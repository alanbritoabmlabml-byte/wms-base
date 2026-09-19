<?php

namespace App\Exceptions\Domain;

class BarcodeUnknownException extends DomainException
{
    public function __construct(string $barcode)
    {
        parent::__construct(
            'BARCODE_UNKNOWN',
            "Codigo desconocido: {$barcode}",
            ['barcode' => $barcode],
            404,
        );
    }
}
