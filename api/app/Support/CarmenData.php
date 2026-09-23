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
        $out[] = 'const rnd = (() => { let s = 20260922; return () => { s = (s * 1664525 + 1013904223) % 4294967296; return s / 4294967296; }; })();';
        $out[] = 'const pick = a => a[Math.floor(rnd() * a.length)];';
        $out[] = 'const ri = (a, b) => a + Math.floor(rnd() * (b - a + 1));';
        $out[] = 'const pad = (n, w = 2) => String(n).padStart(w, "0");';
        $out[] = 'const fmt = n => new Intl.NumberFormat("es-BO").format(n);';
        $out[] = 'const fmtQ = n => new Intl.NumberFormat("es-BO", { maximumFractionDigits: 2 }).format(n);';
        $out[] = 'const dISO = d => d.toISOString().slice(0, 10);';
        $out[] = 'const fmtD = s => { if (!s) return "—"; const [y, m, d] = String(s).slice(0, 10).split("-"); return `${d}/${m}/${y}`; };';
        $out[] = 'const daysAgo = n => { const d = new Date(); d.setDate(d.getDate() - n); return dISO(d); };';

        foreach (array_keys(CarmenSchema::DATASETS) as $name) {
            $out[] = "const {$name} = ".$j(array_values($datasets[$name] ?? [])).';';
        }

        $out[] = 'const ING_TYPES = ["Producción", "Compra", "Devolución", "Transferencia"];';
        $out[] = 'const ING_STATES = ["Borrador", "Habilitado", "En recepción", "Cerrado", "Anulado"];';
        $out[] = 'const OUT_STATES = ["Recibido", "Preparación", "Validado", "Embalado", "Despachado"];';
        $out[] = 'const MOV_TYPES = [["Ingreso", "in"], ["Ubicación", "mv"], ["Picking", "out"], ["Reubicación", "mv"], ["Ajuste +", "in"], ["Ajuste −", "out"], ["Despacho", "out"], ["Devolución", "in"], ["Conteo", "mv"]];';
        $out[] = 'const P = code => PRODUCTS.find(p => p.codigo === code) || { codigo: code, desc: "(producto no registrado)", um: "UN", cat: "—", sub: "—", rot: "C", min: 0, max: 0, vidaUtil: 0, precio: 0, estado: "INACTIVO", fabrica: "—", peso: 0, serializado: false };';
        $out[] = 'const stockOf = code => STOCK.filter(s => s.codigo === code).reduce((a, s) => a + s.qty, 0);';
        $out[] = 'const occupancy = loc => { if (!loc) return 0; const s = STOCK.filter(x => x.loc === loc.id); return s.length ? Math.min(1, s.reduce((a, x) => a + x.qty, 0) / (loc.cap * 20)) : 0; };';
        // En BD el pedido guarda el código del cliente; la interfaz espera el objeto.
        $out[] = 'PEDIDOS.forEach(p => { const c = CLIENTS.find(x => x.codigo === p.cliente); p.cliente = c || { codigo: p.cliente, nombre: p.cliente || "—", dpto: "—", ciudad: "—", canal: "—", tel: "—", nit: "—", estado: "ACTIVO", pedidos: 0 }; });';

        return implode("\n", $out)."\n";
    }
}
