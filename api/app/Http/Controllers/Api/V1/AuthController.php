<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * El token se emite por device, no por sesion de navegador, y no expira:
     * el colector no debe re-loguearse en medio de un turno.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', (string) $request->input('username'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Usuario o contrasena incorrectos'],
            ]);
        }

        if (! $user->is_active) {
            abort(403, 'El usuario esta inactivo');
        }

        $deviceSerial = $request->string('device_serial')->toString() ?: 'web';

        if ($request->filled('device_serial')) {
            Device::updateOrCreate(
                ['serial' => $deviceSerial],
                [
                    'model' => $request->input('device_model'),
                    'app_version' => $request->input('app_version'),
                    'last_seen_at' => Carbon::now(),
                ],
            );
        }

        // Un token por device: al reloguear el mismo colector se revoca el anterior.
        $user->tokens()->where('name', $deviceSerial)->delete();
        $token = $user->createToken($deviceSerial)->plainTextToken;

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        return response()->json(array_merge(
            ['token' => $token],
            $this->profilePayload($user),
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // Con Sanctum::actingAs() el token es transitorio y no se puede borrar.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profilePayload($request->user()));
    }

    private function profilePayload(User $user): array
    {
        $user->load('warehouses.branch');

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ],
            'warehouses' => $user->warehouses->map(fn ($w) => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'branch' => $w->branch?->name,
                'role' => $w->pivot->role,
            ])->values(),
            'permissions' => $user->permissions(),
            'server_time' => Carbon::now()->toIso8601String(),
        ];
    }
}
