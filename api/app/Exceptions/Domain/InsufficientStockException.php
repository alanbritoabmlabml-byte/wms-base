<?php

namespace App\Exceptions\Domain;

class InsufficientStockException extends DomainException
{
    public function __construct(string $locationCode, float $available, float $requested)
    {
        parent::__construct(
            'INSUFFICIENT_STOCK',
            "Stock insuficiente en {$locationCode}",
            ['available' => $available, 'requested' => $requested, 'location' => $locationCode],
            422,
        );
    }
}
