<?php

namespace App\Services\Import;

/**
 * Lector de CSV tolerante: detecta separador (, ; tab), BOM UTF-8 y codificación
 * Windows-1252 (lo habitual al exportar desde Excel en español).
 */
class CsvReader
{
    /** @return array{headers: string[], rows: array<int, array<string,string>>} */
    public static function read(string $path, int $limit = 0): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['headers' => [], 'rows' => []];
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
        if (! $lines) {
            return ['headers' => [], 'rows' => []];
        }

        $first = $lines[0];
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');

        $headers = array_map(fn ($h) => trim($h, " \t\"'"), str_getcsv($first, $sep, '"', '\\'));
        $rows = [];
        foreach (array_slice($lines, 1, $limit ?: null) as $line) {
            $cells = str_getcsv($line, $sep, '"', '\\');
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);

        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    /** Emparejamiento automático encabezado → campo destino. */
    public static function autoMap(array $headers, array $fields): array
    {
        $aliases = [
            'sku' => ['codigo', 'code', 'item', 'articulo', 'producto'], 'descripcion' => ['nombre', 'name', 'detalle', 'description'], 'um' => ['unidad', 'unidadmedida', 'uom', 'un'],
            'minimo' => ['min', 'stockmin', 'cantidadminima'], 'maximo' => ['max', 'stockmax', 'cantidadmaxima'], 'clase_abc' => ['clase', 'abc', 'rotacion'],
            'razon_social' => ['razonsocial', 'cliente', 'nombre'], 'nit' => ['ruc', 'taxid'], 'telefono' => ['celular', 'fono', 'phone'], 'ciudad' => ['localidad'], 'departamento' => ['dpto', 'depto'],
            'ci' => ['documento', 'carnet', 'dni'], 'nombre' => ['nombres', 'firstname'], 'apellidos' => ['apellido', 'lastname'], 'licencia' => ['categoria', 'cat'],
            'placa' => ['matricula', 'plate'], 'chofer_ci' => ['chofer', 'conductor'], 'capacidad_kg' => ['capacidad', 'kg'], 'volumen_m3' => ['volumen', 'm3'],
            'usuario' => ['cuenta', 'user', 'login', 'username'], 'rol' => ['role', 'perfil', 'tipo'], 'almacen' => ['warehouse', 'bodega', 'sucursal'], 'almacenes' => ['warehouses', 'bodegas'],
            'codigo' => ['code', 'ubicacion', 'cod'], 'rack' => ['estante', 'zona', 'area'], 'columna' => ['col', 'posicion'], 'nivel' => ['fila', 'level'], 'orden' => ['sortseq', 'secuencia', 'recorrido'],
        ];
        $map = [];
        $norm = array_map([self::class, 'normalize'], $headers);
        foreach ($fields as $field) {
            $candidates = array_merge([$field], $aliases[$field] ?? []);
            foreach ($headers as $i => $h) {
                if (in_array($norm[$i], array_map([self::class, 'normalize'], $candidates), true) && ! in_array($h, $map, true)) {
                    $map[$field] = $h;
                    break;
                }
            }
        }

        return $map; // campo destino => encabezado origen
    }
}
