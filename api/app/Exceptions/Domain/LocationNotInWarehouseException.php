<?php

namespace App\Exceptions\Domain;

class LocationNotInWarehouseException extends DomainException
{
    public function __construct(int $locationId, int $warehouseId)
    {
        parent::__construct(
            'LOCATION_NOT_IN_WAREHOUSE',
            "La ubicacion {$locationId} no pertenece al almacen {$warehouseId}",
            ['location_id' => $locationId, 'warehouse_id' => $warehouseId],
            422,
        );
    }
}
