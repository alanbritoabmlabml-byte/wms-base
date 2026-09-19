<?php

namespace App\Exceptions\Domain;

class MixingNotAllowedException extends DomainException
{
    public function __construct(string $locationCode, int $occupiedByItemId)
    {
        parent::__construct(
            'MIXING_NOT_ALLOWED',
            "La ubicacion {$locationCode} no admite mezcla de items",
            ['location' => $locationCode, 'occupied_by_item_id' => $occupiedByItemId],
            422,
        );
    }
}
