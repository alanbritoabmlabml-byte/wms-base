<?php

namespace App\Exceptions\Domain;

/** Combinacion from/to incoherente para el tipo de movimiento pedido. */
class InvalidMovementException extends DomainException
{
    public function __construct(string $message, array $details = [])
    {
        parent::__construct('VALIDATION_FAILED', $message, $details, 422);
    }
}
