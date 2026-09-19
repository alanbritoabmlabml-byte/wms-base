<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // La migracion de personal_access_tokens vive en este repo (database/migrations),
        // asi que Sanctum no debe cargar la suya y duplicar la tabla.
        Sanctum::ignoreMigrations();
    }

    public function boot(): void
    {
        // El kardex no perdona asignaciones en masa fuera de $fillable.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Doc 05: 300 req/min por device (un colector escaneando rapido hace ~30/min).
        RateLimiter::for('wms', function (Request $request) {
            $user = $request->user();
            $token = $user?->currentAccessToken();

            // Un token por device, asi que la clave del token ES el colector.
            $key = $token instanceof PersonalAccessToken
                ? 'token:'.$token->getKey()
                : ($user ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip());

            return Limit::perMinute((int) config('wms.rate_limit_per_minute'))->by($key);
        });
    }
}
