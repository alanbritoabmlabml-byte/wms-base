<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    protected $fillable = ['code', 'name', 'width_mm', 'height_mm', 'qr_format', 'qr_size', 'font_scale', 'fields', 'printer', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'width_mm' => 'integer', 'height_mm' => 'integer', 'qr_size' => 'integer', 'font_scale' => 'integer', 'version' => 'integer'];
    }

    public const FIELDS = ['qr', 'code128', 'title', 'description', 'logo', 'stripe', 'lot', 'expiry', 'qty', 'date'];

    public static function defaults(): array
    {
        $all = array_fill_keys(self::FIELDS, true);

        return [
            ['code' => 'UBICACION', 'name' => 'Ubicación', 'width_mm' => 100, 'height_mm' => 50, 'qr_format' => 'PLAIN',
                'fields' => array_merge($all, ['code128' => false, 'lot' => false, 'expiry' => false, 'qty' => false, 'date' => false])],
            ['code' => 'ITEM', 'name' => 'Ítem / bulto', 'width_mm' => 100, 'height_mm' => 50, 'qr_format' => 'GS1', 'fields' => $all],
            ['code' => 'PALLET', 'name' => 'Pallet (SSCC)', 'width_mm' => 100, 'height_mm' => 150, 'qr_format' => 'GS1',
                'fields' => array_merge($all, ['expiry' => false])],
            ['code' => 'DESPACHO', 'name' => 'Bulto de despacho', 'width_mm' => 100, 'height_mm' => 100, 'qr_format' => 'JSON',
                'fields' => array_merge($all, ['lot' => false, 'expiry' => false])],
        ];
    }
}
