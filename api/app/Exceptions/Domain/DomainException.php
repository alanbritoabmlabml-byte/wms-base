<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * Base de todas las excepciones de dominio del WMS.
 *
 * Cada una lleva el `code` del doc 05 para que el colector pueda distinguirlas
 * sin parsear el mensaje.
 */
abstract class DomainException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public function toArray(): array
    {
        return [
            'error' => array_filter([
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details ?: null,
            ], fn ($v) => $v !== null),
        ];
    }
}
