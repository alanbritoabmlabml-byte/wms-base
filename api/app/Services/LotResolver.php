<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Lot;
use Illuminate\Support\Carbon;

/**
 * El saldo siempre lleva lote. Si el item no rastrea lotes se usa el lote '-'.
 */
class LotResolver
{
    public function resolve(Item $item, ?string $lotCode, ?string $expiresAt = null): Lot
    {
        $code = $item->tracks_lot ? trim((string) $lotCode) : Lot::NONE;

        if ($code === '') {
            $code = Lot::NONE;
        }

        $lot = Lot::firstOrCreate(
            ['item_id' => $item->id, 'code' => $code],
            ['expires_at' => $this->computeExpiry($item, $expiresAt)],
        );

        // Si el colector manda vencimiento y el lote no lo tenia, se completa.
        if ($expiresAt && ! $lot->expires_at) {
            $lot->update(['expires_at' => Carbon::parse($expiresAt)->toDateString()]);
        }

        return $lot;
    }

    private function computeExpiry(Item $item, ?string $expiresAt): ?string
    {
        if ($expiresAt) {
            return Carbon::parse($expiresAt)->toDateString();
        }

        if ($item->tracks_expiry && $item->shelf_life_days) {
            return Carbon::now()->addDays($item->shelf_life_days)->toDateString();
        }

        return null;
    }
}
