<?php

namespace App\Exceptions\Domain;

class ItemNotFoundException extends DomainException
{
    public function __construct(int|string $itemRef)
    {
        parent::__construct(
            'ITEM_NOT_FOUND',
            "Item no encontrado: {$itemRef}",
            ['item' => $itemRef],
            404,
        );
    }
}
