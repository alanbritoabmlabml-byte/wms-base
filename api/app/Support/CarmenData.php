<?php

namespace App\Support;

/**
 * Traduce entre los registros que usa la interfaz de Carmen WMS (mismas claves
 * que el artifact: codigo, desc, qty, lines…) y las filas de las tablas cw_*.
 *
 * No depende de Laravel: se prueba sola y la usan el seeder, el controlador que
 * entrega los datos a la interfaz y los endpoints que guardan cambios.
 */
final class CarmenData
{
    /** Registro de la interfaz → fila de BD (sin id ni timestamps). */
    public static function toRow(string $dataset, array $record, int $position = 0): array
    {
        $row = ['position' => $position];
        foreach (CarmenSchema::DATASETS[$dataset]['fields'] as [$key, $column, $type]) {
            $value = $record[$key] ?? null;
            $row[$column] = match (true) {
                $value === null && $column === 'wh' => 'BOL2',
                $value === null => null,
                $type === 'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
                $type === 'boolean' => (bool) $value,
                $type === 'integer' => (int) $value,
                $type === 'float' => (float) $value,
                default => (string) $value,
            };
        }

        return $row;
    }

    /** Fila de BD (objeto o array, como la devuelva el driver) → registro de la interfaz. */
    public static function fromRow(string $dataset, array|object $row): array
    {
        $row = (array) $row;
        $out = [];
        foreach (CarmenSchema::DATASETS[$dataset]['fields'] as [$key, $column, $type]) {
            $value = $row[$column] ?? null;
            $out[$key] = match (true) {
                $value === null => null,
                $type === 'json' => is_string($value) ? json_decode($value, true) : $value,
                $type === 'boolean' => (bool) (int) $value,
                $type === 'integer' => (int) $value,
                $type === 'float' => (float) $value,
                default => (string) $value,
            };
        }

        return $out;
    }

    /** Columna de BD que corresponde a la clave natural del dataset. */
    public static function keyColumn(string $dataset): ?string
    {
        $key = CarmenSchema::DATASETS[$dataset]['key'];
        foreach (CarmenSchema::DATASETS[$dataset]['fields'] as [$k, $column]) {
            if ($k === $key) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Arma el script de datos que la interfaz espera: las mismas constantes
     * globales que tenía el artifact (WAREHOUSES, PRODUCTS, STOCK, PEDIDOS…),
     * más las funciones auxiliares que las acompañaban.
     *
     * @param  array<string, array<int, array>>  $datasets  dataset => registros ya traducidos
     * @param  array  $session  usuario y almacén de la sesión, rutas y token CSRF
     */
    public static function script(array $datasets, array $session): string
    {
        $j = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        $out = [];
        $out[] = '/* Carmen WMS · datos servidos por Laravel desde la base de datos */';
        $out[] = 'const CW = '.$j($session).';';
        foreach (array_keys(CarmenSchema::DATASETS) as $name) {
            $out[] = "const {$name} = ".$j(array_values($datasets[$name] ?? [])).';';
        }

        return implode("\n", $out)."\n";
    }
}
