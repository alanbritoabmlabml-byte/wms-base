<?php

namespace App\Exceptions\Domain;

/**
 * Los ajustes exigen motivo. El doc 05 no define un `code` propio para esto,
 * asi que se reporta como VALIDATION_FAILED (que el colector ya sabe mostrar).
 */
class ReasonCodeRequiredException extends DomainException
{
    public function __construct(string $movementType)
    {
        parent::__construct(
            'VALIDATION_FAILED',
            "El movimiento {$movementType} exige un motivo (reason_code_id)",
            ['field' => 'reason_code_id', 'type' => $movementType],
            422,
        );
    }
}
