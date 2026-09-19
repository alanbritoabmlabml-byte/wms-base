<?php

namespace App\Exceptions\Domain;

class LocationInactiveException extends DomainException
{
    public function __construct(string $locationCode)
    {
        parent::__construct(
            'LOCATION_INACTIVE',
            "La ubicacion {$locationCode} esta inactiva",
            ['location' => $locationCode],
            422,
        );
    }
}
