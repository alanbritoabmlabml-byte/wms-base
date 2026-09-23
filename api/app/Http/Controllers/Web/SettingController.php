<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\NavCounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Parámetros de negocio. El esquema (grupos, claves, tipos y defaults) vive aquí;
 * los valores en la tabla `settings` (globales o por almacén).
 */
class SettingController extends Controller
{
    public static function schema(): array
    {
        return [
            'reglas' => ['label' => 'Reglas de operación', 'icon' => 'sliders', 'desc' => 'Tolerancias, aprobaciones y comportamiento de recepción.', 'fields' => [
                'recepcion_ciega_produccion' => ['label' => 'Recepción ciega desde producción', 'type' => 'bool', 'default' => false, 'hint' => 'El operador no ve la cantidad esperada; se compara al cerrar.'],
                'tolerancia_recepcion_pct' => ['label' => 'Tolerancia de recepción (%)', 'type' => 'number', 'default' => 5, 'min' => 0, 'max' => 50, 'hint' => 'Diferencia permitida entre esperado y recibido antes de exigir motivo.'],
                'tolerancia_ajuste_sin_aprobacion' => ['label' => 'Ajuste sin aprobación hasta (unidades)', 'type' => 'number', 'default' => 0, 'min' => 0, 'hint' => '0 = todo ajuste de operador requiere aprobación del encargado.'],
                'cierre_forzado_requiere_motivo' => ['label' => 'Cierre forzado con motivo obligatorio', 'type' => 'bool', 'default' => true],
                'permitir_stock_negativo' => ['label' => 'Permitir stock negativo', 'type' => 'bool', 'default' => false, 'hint' => 'Solo para cargas iniciales. Desactívalo en operación normal.'],
                'bloquear_picking_en_conteo' => ['label' => 'Bloquear picking durante un conteo', 'type' => 'bool', 'default' => true],
                'dias_alerta_vencimiento' => ['label' => 'Alerta de vencimiento (días antes)', 'type' => 'number', 'default' => 30, 'min' => 0],
            ]],
            'putaway' => ['label' => 'Put-away y picking', 'icon' => 'move', 'desc' => 'Cómo sugiere ubicación el colector y en qué orden se recoge.', 'fields' => [
                'estrategia' => ['label' => 'Estrategia de put-away', 'type' => 'select', 'default' => 'FIJA_LUEGO_CERCANA', 'options' => ['FIJA_LUEGO_CERCANA' => 'Ubicación fija del ítem, si no la más cercana libre', 'MISMO_ITEM' => 'Consolidar con el mismo ítem', 'ZONA_ABC' => 'Por clase ABC (A cerca del despacho)', 'MANUAL' => 'Sin sugerencia (manual)']],
                'rotacion' => ['label' => 'Regla de salida', 'type' => 'select', 'default' => 'FEFO', 'options' => ['FEFO' => 'FEFO · primero lo que vence antes', 'FIFO' => 'FIFO · primero lo que ingresó antes', 'LIFO' => 'LIFO · último en entrar, primero en salir']],
                'permitir_mezcla_por_defecto' => ['label' => 'Nuevas ubicaciones permiten mezcla de ítems', 'type' => 'bool', 'default' => true],
                'max_lineas_por_ola' => ['label' => 'Máximo de pedidos por ola', 'type' => 'number', 'default' => 12, 'min' => 1, 'max' => 100],
                'confirmar_ubicacion_escaneo' => ['label' => 'Exigir escaneo de ubicación destino', 'type' => 'bool', 'default' => true, 'hint' => 'Si está apagado, el operador puede confirmar tocando la sugerida.'],
            ]],
            'codigos' => ['label' => 'Códigos y numeración', 'icon' => 'barcode', 'desc' => 'Formato de ubicaciones, correlativos de documentos y capacidad.', 'fields' => [
                'formato_ubicacion' => ['label' => 'Formato de código de ubicación', 'type' => 'text', 'default' => '{RACK}-C{COL:2}-N{NIVEL}', 'mono' => true, 'hint' => 'Variables: {RACK} {COL} {COL:2} {NIVEL} {ZONA}. Ej.: E7-C01-N1'],
                'capacidad_por_ubicacion' => ['label' => 'Capacidad estándar por ubicación (pallets)', 'type' => 'number', 'default' => 6, 'min' => 1, 'max' => 99, 'hint' => 'Se usa para el mapa de calor cuando la ubicación no define la suya.'],
                'prefijo_ingreso' => ['label' => 'Prefijo de recepciones', 'type' => 'text', 'default' => 'ING', 'mono' => true],
                'prefijo_pedido' => ['label' => 'Prefijo de pedidos', 'type' => 'text', 'default' => 'PED', 'mono' => true],
                'prefijo_despacho' => ['label' => 'Prefijo de despachos', 'type' => 'text', 'default' => 'DSP', 'mono' => true],
                'prefijo_conteo' => ['label' => 'Prefijo de conteos', 'type' => 'text', 'default' => 'INV', 'mono' => true],
                'gs1_prefijo_empresa' => ['label' => 'Prefijo de empresa GS1', 'type' => 'text', 'default' => '', 'mono' => true, 'hint' => 'Asignado por GS1 Bolivia. Necesario para GTIN/SSCC reales; vacío = códigos internos.'],
            ]],
            'etiquetas' => ['label' => 'Impresión', 'icon' => 'print', 'desc' => 'Impresoras disponibles para etiquetas y documentos.', 'fields' => [
                'impresoras' => ['label' => 'Impresoras (una por línea)', 'type' => 'list', 'default' => LabelTemplateController::PRINTERS_DEFAULT, 'hint' => 'Nombre visible · ubicación. Aparecen en el editor de etiquetas y en el colector.'],
                'copias_por_defecto' => ['label' => 'Copias por defecto', 'type' => 'number', 'default' => 1, 'min' => 1, 'max' => 10],
            ]],
            'integracion' => ['label' => 'Integración WorkCorp / SIMEC', 'icon' => 'link', 'desc' => 'Sincronización de maestros y documentos con el ERP.', 'fields' => [
                'modo' => ['label' => 'Modo de integración', 'type' => 'select', 'default' => 'CSV', 'options' => ['CSV' => 'Manual por CSV (módulo Importar)', 'API' => 'API REST programada', 'BD' => 'Lectura directa de base de datos']],
                'url_api' => ['label' => 'URL de la API del ERP', 'type' => 'text', 'default' => '', 'mono' => true],
                'frecuencia_min' => ['label' => 'Frecuencia de sincronización (minutos)', 'type' => 'number', 'default' => 30, 'min' => 5, 'max' => 1440],
                'enviar_movimientos' => ['label' => 'Enviar movimientos de stock al ERP', 'type' => 'bool', 'default' => false],
                'sincronizar_pedidos' => ['label' => 'Traer pedidos de venta automáticamente', 'type' => 'bool', 'default' => false],
            ]],
            'respaldo' => ['label' => 'Respaldo y retención', 'icon' => 'cloud', 'desc' => 'Copias de seguridad y limpieza de históricos.', 'fields' => [
                'hora_respaldo' => ['label' => 'Hora del respaldo diario', 'type' => 'text', 'default' => '23:30', 'mono' => true],
                'retencion_dias_respaldo' => ['label' => 'Conservar respaldos (días)', 'type' => 'number', 'default' => 30, 'min' => 1],
                'retencion_meses_kardex' => ['label' => 'Kardex en línea (meses)', 'type' => 'number', 'default' => 24, 'min' => 6, 'hint' => 'Lo anterior se archiva, no se borra.'],
                'ruta_respaldo' => ['label' => 'Carpeta de respaldo', 'type' => 'text', 'default' => 'D:\\Respaldos\\CarmenWMS', 'mono' => true],
            ]],
            'empresa' => ['label' => 'Empresa', 'icon' => 'home', 'desc' => 'Datos que aparecen en etiquetas, reportes y documentos.', 'fields' => [
                'nombre' => ['label' => 'Razón social', 'type' => 'text', 'default' => 'Plásticos Carmen'],
                'nit' => ['label' => 'NIT', 'type' => 'text', 'default' => '', 'mono' => true],
                'direccion' => ['label' => 'Dirección', 'type' => 'text', 'default' => 'Santa Cruz de la Sierra, Bolivia'],
                'telefono' => ['label' => 'Teléfono', 'type' => 'text', 'default' => ''],
                'pie_documentos' => ['label' => 'Pie de página en documentos', 'type' => 'text', 'default' => 'Documento generado por Carmen WMS'],
            ]],
        ];
    }

    public function index(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $schema = self::schema();
        $group = array_key_exists((string) $request->query('grupo'), $schema) ? $request->query('grupo') : 'reglas';
        $scope = $request->query('alcance') === 'almacen' ? 'almacen' : 'global';

        $global = Setting::allFor(null);
        $local = Setting::query()->where('warehouse_id', $wh->id)->get()->groupBy('group')->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->key => $r->value['v'] ?? null]))->toArray();

        $values = [];
        foreach ($schema[$group]['fields'] as $key => $f) {
            $values[$key] = [
                'global' => $global[$group][$key] ?? $f['default'],
                'local' => $local[$group][$key] ?? null,
                'effective' => $local[$group][$key] ?? $global[$group][$key] ?? $f['default'],
            ];
        }

        return view('config.settings', [
            'title' => 'Parámetros',
            'schema' => $schema, 'group' => $group, 'scope' => $scope,
            'values' => $values,
            'overrides' => array_map(fn ($g) => count($local[$g] ?? []), array_keys($schema)),
            'lastUpdate' => Setting::query()->latest('updated_at')->with('editor')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $schema = self::schema();
        $group = $request->input('grupo');
        abort_unless(isset($schema[$group]), 404);

        $wh = $request->attributes->get('warehouse');
        $scope = $request->input('alcance') === 'almacen' ? 'almacen' : 'global';
        $whId = $scope === 'almacen' ? $wh->id : null;

        $input = (array) $request->input('v', []);
        $changed = [];

        foreach ($schema[$group]['fields'] as $key => $f) {
            // Quitar sobreescritura de almacén
            if ($scope === 'almacen' && $request->boolean("heredar.$key")) {
                Setting::query()->where(['group' => $group, 'key' => $key, 'warehouse_id' => $whId])->delete();
                $changed[$key] = 'heredado';

                continue;
            }

            $raw = $input[$key] ?? null;
            $value = match ($f['type']) {
                'bool' => (bool) $raw,
                'number' => $raw === null || $raw === '' ? $f['default'] : max($f['min'] ?? PHP_INT_MIN, min($f['max'] ?? PHP_INT_MAX, (float) $raw + 0)),
                'select' => array_key_exists((string) $raw, $f['options']) ? $raw : $f['default'],
                'list' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw)))),
                default => trim((string) $raw),
            };
            if ($f['type'] === 'number' && floor($value) == $value) {
                $value = (int) $value;
            }

            Setting::put($group, $key, $value, $request->user()->id, $whId);
            $changed[$key] = $value;
        }

        Cache::forget('wms.settings.'.($whId ?? 'global'));
        Cache::forget('wms.settings.global');

        NavCounts::forget($wh->id);
        AuditLog::create(['user_id' => $request->user()->id, 'model' => Setting::class, 'model_id' => 0, 'action' => 'PARAMETROS_'.strtoupper($group), 'after' => ['scope' => $scope, 'values' => $changed], 'ip' => $request->ip()]);

        return redirect()->route('config.settings', ['grupo' => $group, 'alcance' => $scope])
            ->with('toast', 'Parámetros de «'.$schema[$group]['label'].'» guardados'.($scope === 'almacen' ? " para {$wh->code}." : ' (globales).'));
    }
}
