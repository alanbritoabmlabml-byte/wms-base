<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(): View
    {
        return view('auth.login', [
            'warehouses' => \App\Models\Warehouse::active()->orderBy('code')->get(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $ok = Auth::attempt(
            ['username' => $data['username'], 'password' => $data['password'], 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $ok) {
            throw ValidationException::withMessages([
                'username' => 'Usuario o contraseña incorrectos, o usuario deshabilitado.',
            ]);
        }

        $user = $request->user();

        if (! in_array($user->client_type, ['ESCRITORIO', 'AMBOS'], true)) {
            Auth::logout();
            throw ValidationException::withMessages(['username' => 'Este usuario solo tiene acceso desde el colector.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        if (! empty($data['warehouse_id']) && $user->warehouseIds()->contains((int) $data['warehouse_id'])) {
            $request->session()->put('wms.warehouse_id', (int) $data['warehouse_id']);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchWarehouse(Request $request): RedirectResponse
    {
        $id = (int) $request->validate(['warehouse_id' => ['required', 'integer']])['warehouse_id'];

        abort_unless($request->user()->warehouseIds()->contains($id), 403, 'No tienes acceso a ese almacén.');

        $request->session()->put('wms.warehouse_id', $id);

        return back()->with('toast', 'Almacén de trabajo cambiado.');
    }
}
