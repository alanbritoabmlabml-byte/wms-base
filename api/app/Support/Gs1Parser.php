<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Parser GS1-128 (GS1 Application Identifiers).
 *
 * Soporta los AI que realmente aparecen en planta:
 *   00  SSCC (18, fijo)
 *   01  GTIN (14, fijo)
 *   10  Lote (<=20, variable)
 *   11  Fecha de fabricacion (6, fijo, AAMMDD)
 *   17  Fecha de vencimiento (6, fijo, AAMMDD)
 *   21  Serie (<=20, variable)
 *   30  Cantidad de unidades (<=8, variable)
 *   37  Cantidad contenida (<=8, variable)
 *   310n Peso neto en kg con `n` decimales (6, fijo)
 *
 * Los AI de longitud variable terminan con FNC1 (0x1D) o al final de la cadena.
 * Tambien acepta la forma legible con parentesis: "(01)07501234567890(10)L2609A".
 */
class Gs1Parser
{
    public const FNC1 = "\x1D";

    /** AI de longitud fija: prefijo => largo del dato (sin contar el AI). */
    private const FIXED_LENGTH = [
        '00' => 18,
        '01' => 14,
        '02' => 14,
        '11' => 6,
        '12' => 6,
        '13' => 6,
        '15' => 6,
        '16' => 6,
        '17' => 6,
        '20' => 2,
    ];

    /** AI de longitud variable: prefijo => largo maximo. */
    private const VARIABLE_LENGTH = [
        '10' => 20,
        '21' => 20,
        '22' => 20,
        '30' => 8,
        '37' => 8,
        '240' => 30,
        '241' => 30,
        '400' => 30,
        '401' => 30,
    ];

    /** AI de medida con decimal implicito (310n..316n, 320n.., 330n.., 390n..). */
    private const DECIMAL_PREFIXES = ['310', '311', '312', '313', '314', '315', '316', '320', '330', '390', '392'];

    /** AI que representan fechas AAMMDD. */
    private const DATE_AIS = ['11', '12', '13', '15', '16', '17'];

    /** ¿Parece un GS1-128? Heuristica barata para el endpoint /scan/resolve. */
    public static function looksLikeGs1(string $code): bool
    {
        if (str_contains($code, self::FNC1)) {
            return true;
        }

        if (preg_match('/^\((\d{2,4})\)/', $code)) {
            return true;
        }

        // ]C1 es el identificador de simbologia Code128 con FNC1.
        if (str_starts_with($code, ']C1')) {
            return true;
        }

        // Un GS1 crudo empieza por un AI conocido y es mas largo que un EAN.
        return strlen($code) > 16
            && (str_starts_with($code, '01') || str_starts_with($code, '00'));
    }

    /**
     * Parsea el codigo y devuelve un mapa AI => valor.
     * Las fechas vuelven como `YYYY-MM-DD` y las medidas con decimales como string numerico.
     *
     * @return array<string,string>
     */
    public function parse(string $raw): array
    {
        $code = $this->normalize($raw);

        // Forma legible con parentesis.
        if (str_contains($code, '(')) {
            return $this->parseParenthesized($code);
        }

        $result = [];
        $i = 0;
        $len = strlen($code);

        while ($i < $len) {
            // Un separador suelto no aporta nada.
            if ($code[$i] === self::FNC1) {
                $i++;

                continue;
            }

            [$ai, $aiLen] = $this->matchAi($code, $i);

            if ($ai === null) {
                // AI desconocido: no se puede seguir leyendo con seguridad.
                break;
            }

            $i += $aiLen;

            [$value, $consumed] = $this->readValue($code, $i, $ai);

            if ($value === null) {
                break;
            }

            $result[$ai] = $this->formatValue($ai, $value);
            $i += $consumed;
        }

        return $result;
    }

    /** Trae el GTIN (AI 01 o 02) si el codigo lo lleva. */
    public function gtin(array $parsed): ?string
    {
        return $parsed['01'] ?? $parsed['02'] ?? null;
    }

    /** Cantidad declarada: AI 37 (contenidas) o 30 (unidades). */
    public function quantity(array $parsed): ?float
    {
        foreach (['37', '30'] as $ai) {
            if (isset($parsed[$ai]) && is_numeric($parsed[$ai])) {
                return (float) $parsed[$ai];
            }
        }

        // Peso neto en kg: 310n.
        foreach ($parsed as $ai => $value) {
            if (str_starts_with((string) $ai, '310') && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------

    private function normalize(string $raw): string
    {
        $code = trim($raw);

        // Identificador de simbologia que anteponen algunos colectores.
        foreach ([']C1', ']e0', ']d2'] as $prefix) {
            if (str_starts_with($code, $prefix)) {
                $code = substr($code, strlen($prefix));
            }
        }

        // Representaciones textuales del FNC1 que usan varias impresoras.
        return str_replace(['<GS>', '{GS}', '\\x1D'], self::FNC1, $code);
    }

    /** @return array{0:?string,1:int} */
    private function matchAi(string $code, int $offset): array
    {
        foreach ([4, 3, 2] as $length) {
            $candidate = substr($code, $offset, $length);

            if (strlen($candidate) < $length || ! ctype_digit($candidate)) {
                continue;
            }

            if (isset(self::FIXED_LENGTH[$candidate]) || isset(self::VARIABLE_LENGTH[$candidate])) {
                return [$candidate, $length];
            }

            // AI de 4 digitos con decimal implicito: 3103, 3202, ...
            if ($length === 4 && in_array(substr($candidate, 0, 3), self::DECIMAL_PREFIXES, true)) {
                return [$candidate, 4];
            }
        }

        return [null, 0];
    }

    /** @return array{0:?string,1:int} */
    private function readValue(string $code, int $offset, string $ai): array
    {
        // Longitud fija declarada.
        if (isset(self::FIXED_LENGTH[$ai])) {
            $value = substr($code, $offset, self::FIXED_LENGTH[$ai]);

            return strlen($value) === self::FIXED_LENGTH[$ai] ? [$value, self::FIXED_LENGTH[$ai]] : [null, 0];
        }

        // Medidas con decimal implicito: siempre 6 digitos.
        if (strlen($ai) === 4 && in_array(substr($ai, 0, 3), self::DECIMAL_PREFIXES, true)) {
            $value = substr($code, $offset, 6);

            return strlen($value) === 6 ? [$value, 6] : [null, 0];
        }

        // Longitud variable: hasta FNC1 o hasta el maximo del AI.
        $max = self::VARIABLE_LENGTH[$ai] ?? 30;
        $separator = strpos($code, self::FNC1, $offset);
        $end = $separator === false ? strlen($code) : $separator;
        $length = min($end - $offset, $max);

        if ($length <= 0) {
            return [null, 0];
        }

        $value = substr($code, $offset, $length);
        // El separador, si existe y se consumio hasta el, tambien se descarta.
        $consumed = $length + (($separator !== false && $offset + $length === $separator) ? 1 : 0);

        return [$value, $consumed];
    }

    private function formatValue(string $ai, string $value): string
    {
        if (in_array($ai, self::DATE_AIS, true)) {
            return $this->parseDate($value) ?? $value;
        }

        if (strlen($ai) === 4 && in_array(substr($ai, 0, 3), self::DECIMAL_PREFIXES, true)) {
            $decimals = (int) substr($ai, 3, 1);

            return number_format(((float) $value) / (10 ** $decimals), $decimals, '.', '');
        }

        // Cantidades: se devuelven sin ceros a la izquierda para poder sumarlas.
        if (in_array($ai, ['30', '37'], true) && ctype_digit($value)) {
            return (string) (int) $value;
        }

        return $value;
    }

    /** AAMMDD -> YYYY-MM-DD. DD = 00 significa fin de mes (regla GS1). */
    private function parseDate(string $value): ?string
    {
        if (strlen($value) !== 6 || ! ctype_digit($value)) {
            return null;
        }

        $yy = (int) substr($value, 0, 2);
        $mm = (int) substr($value, 2, 2);
        $dd = (int) substr($value, 4, 2);

        if ($mm < 1 || $mm > 12) {
            return null;
        }

        // Regla GS1: ventana de 50 anios alrededor del anio actual.
        $currentYy = (int) date('y');
        $century = ($yy - $currentYy) > 50 ? (int) date('Y') - $currentYy - 100 : (int) date('Y') - $currentYy;
        $year = $century + $yy;

        if ($dd === 0) {
            return Carbon::create($year, $mm, 1)->endOfMonth()->toDateString();
        }

        if ($dd > 31) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $mm, $dd);
    }

    /** @return array<string,string> */
    private function parseParenthesized(string $code): array
    {
        preg_match_all('/\((\d{2,4})\)([^(]*)/', $code, $matches, PREG_SET_ORDER);

        $result = [];

        foreach ($matches as $match) {
            $ai = $match[1];
            $value = str_replace(self::FNC1, '', $match[2]);
            $result[$ai] = $this->formatValue($ai, $value);
        }

        return $result;
    }
}
