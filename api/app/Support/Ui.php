<?php

namespace App\Support;

/**
 * Traducciones y colores de estado para las vistas Blade.
 */
class Ui
{
    private const STATUS = [
        // recepciones
        'ABIERTA' => ['warn', 'Habilitada'], 'EN_PROCESO' => ['info', 'En recepción'], 'CERRADA' => ['ok', 'Cerrada'], 'ANULADA' => ['bad', 'Anulada'],
        // líneas
        'PENDIENTE' => ['neutral', 'Pendiente'], 'PARCIAL' => ['warn', 'Parcial'], 'COMPLETA' => ['ok', 'Completa'], 'EXCEDIDA' => ['bad', 'Excedida'],
        // pedidos
        'RECIBIDO' => ['warn', 'Recibido'], 'PREPARACION' => ['info', 'Preparación'], 'VALIDADO' => ['info', 'Validado'], 'EMBALADO' => ['info', 'Embalado'], 'DESPACHADO' => ['ok', 'Despachado'], 'ANULADO' => ['bad', 'Anulado'],
        // despachos
        'CARGANDO' => ['info', 'Cargando'], 'EN_RUTA' => ['info', 'En ruta'], 'ENTREGADO' => ['ok', 'Entregado'],
        // ajustes / conteos
        'APROBADO' => ['ok', 'Aprobado'], 'RECHAZADO' => ['bad', 'Rechazado'], 'HABILITADO' => ['warn', 'Habilitado'], 'EN_CURSO' => ['info', 'En curso'], 'FINALIZADO' => ['ok', 'Finalizado'],
        'CONTADA' => ['ok', 'Contada'], 'DIFERENCIA' => ['warn', 'Diferencia'], 'APROBADA' => ['ok', 'Aprobada'], 'RECONTAR' => ['bad', 'Recontar'],
        // stock
        'BUENO' => ['ok', 'Disponible'], 'OBSERVADO' => ['warn', 'Observado'], 'CUARENTENA' => ['warn', 'Cuarentena'], 'DANADO' => ['bad', 'Dañado'],
        // genéricos
        'ACTIVO' => ['ok', 'Activo'], 'INACTIVO' => ['bad', 'Inactivo'], 'DISPONIBLE' => ['ok', 'Disponible'], 'MANTENIMIENTO' => ['warn', 'Mantenimiento'],
        'CARGADO' => ['neutral', 'Cargado'], 'MAPEADO' => ['info', 'Mapeado'], 'VALIDADO_IMP' => ['info', 'Validado'], 'CONFIRMADO' => ['ok', 'Confirmado'], 'DESCARTADO' => ['bad', 'Descartado'],
        'ADMIN' => ['a', 'Administrador'], 'SUPERVISOR' => ['info', 'Encargado'], 'OPERADOR' => ['neutral', 'Operador'],
        'PRODUCCION' => ['neutral', 'Producción'], 'COMPRA' => ['neutral', 'Compra'], 'DEVOLUCION' => ['neutral', 'Devolución'], 'TRASPASO' => ['neutral', 'Transferencia'],
        'URGENTE' => ['bad', 'Urgente'], 'NORMAL' => ['neutral', 'Normal'],
    ];

    public static function pill(?string $status, ?string $labelOverride = null): string
    {
        $status = (string) $status;
        [$kind, $label] = self::STATUS[$status] ?? ['neutral', ucfirst(strtolower(str_replace('_', ' ', $status)))];

        return '<span class="pill '.$kind.'">'.e($labelOverride ?? $label).'</span>';
    }

    public static function label(?string $status): string
    {
        return self::STATUS[(string) $status][1] ?? ucfirst(strtolower(str_replace('_', ' ', (string) $status)));
    }

    public static function qty(float|int|string|null $n, int $dec = 0): string
    {
        $n = (float) $n;
        $dec = floor($n) == $n ? 0 : min($dec ?: 2, 2);

        return number_format($n, $dec, ',', '.');
    }

    public static function date(?\DateTimeInterface $d, bool $time = false): string
    {
        return $d ? $d->format($time ? 'd/m/Y H:i' : 'd/m/Y') : '—';
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2)))) ?: '?';
    }

    public static function heat(float $ratio): string
    {
        if ($ratio <= 0) {
            return '';
        }
        if ($ratio < .25) {
            return 'h2';
        }
        if ($ratio < .5) {
            return 'h3';
        }

        return $ratio < .8 ? 'h4' : 'h5';
    }
}
