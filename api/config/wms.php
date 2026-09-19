<?php

/**
 * Permisos por rol.
 *
 * El doc 02 define tablas `permissions` / `role_permissions`, pero para v0.1 la
 * matriz es fija y no la administra nadie desde la UI: mantenerla en config evita
 * dos tablas de catalogo sin pantalla de mantenimiento. Migrarla a BD mas adelante
 * es aditivo (el login ya devuelve la lista al colector).
 */
return [
    'permissions' => [
        'OPERADOR' => [
            'stock.view',
            'receipt.scan',
            'move.relocate',
        ],
        'SUPERVISOR' => [
            'stock.view',
            'receipt.scan',
            'receipt.close',
            'move.relocate',
            'count.adjust',
        ],
        'ADMIN' => [
            'stock.view',
            'stock.view_all_warehouses',
            'receipt.scan',
            'receipt.close',
            'move.relocate',
            'count.adjust',
        ],
    ],

    // Rate limit del doc 05: 300 req/min por device.
    'rate_limit_per_minute' => (int) env('WMS_RATE_LIMIT', 300),
];
