<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Usuarios de escritorio y de colector, con su rol por almacén.
 * La contraseña (escritorio) y el PIN (colector) nunca se muestran: se generan
 * y se enseñan UNA sola vez al crear o al restablecer credenciales.
 */
class UserController extends Controller
{
    public const ROLES = ['ADMIN' => 'Administrador', 'SUPERVISOR' => 'Encargado', 'OPERADOR' => 'Operador'];

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'colector' ? 'colector' : 'escritorio';
        $q = trim((string) $request->query('q', ''));
        $types = $tab === 'colector' ? ['COLECTOR', 'AMBOS'] : ['ESCRITORIO', 'AMBOS'];

        $users = User::with(['warehouses', 'device'])
            ->whereIn('client_type', $types)
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('name', 'like', "%$q%")->orWhere('username', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->orderByDesc('is_active')->orderBy('name')->paginate(30)->withQueryString();

        return view('config.users', [
            'title' => 'Usuarios y roles',
            'tab' => $tab, 'q' => $q,
            'users' => $users,
            'warehouses' => Warehouse::orderBy('code')->get(),
            'roles' => self::ROLES,
            'permissions' => config('wms.permissions', []),
            'permLabels' => self::permissionLabels(),
            'editing' => $request->query('editar') ? User::with('warehouses')->find($request->query('editar')) : null,
            'counts' => [
                'escritorio' => User::whereIn('client_type', ['ESCRITORIO', 'AMBOS'])->count(),
                'colector' => User::whereIn('client_type', ['COLECTOR', 'AMBOS'])->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $isCollector = in_array($data['client_type'], ['COLECTOR', 'AMBOS'], true);
        $isDesktop = in_array($data['client_type'], ['ESCRITORIO', 'AMBOS'], true);

        $password = $isDesktop ? ($data['password'] ?? null) ?: Str::password(10, symbols: false) : null;
        $pin = $isCollector ? ($data['pin'] ?? null) ?: (string) random_int(1000, 9999) : null;

        $user = User::create([
            'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?? null,
            'client_type' => $data['client_type'], 'document_id' => $data['document_id'] ?? null,
            'password' => $password ?? Str::password(16), 'pin' => $pin, 'is_active' => true,
        ]);
        $this->syncWarehouses($user, $data['roles'] ?? []);
        AuditLog::create(['user_id' => $request->user()->id, 'model' => User::class, 'model_id' => $user->id, 'action' => 'CREACION', 'after' => $user->only(['username', 'client_type']), 'ip' => $request->ip()]);

        return redirect()->route('config.users', ['tab' => $isDesktop ? 'escritorio' : 'colector', 'q' => $user->username])
            ->with('toast', "Usuario {$user->username} creado.")
            ->with('credentials', ['user' => $user->username, 'password' => $password, 'pin' => $pin]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user->id);

        if ($user->id === $request->user()->id && ! $request->boolean('is_active', true)) {
            return back()->withErrors(['is_active' => 'No puedes desactivar tu propio usuario.']);
        }

        $user->fill([
            'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?? null,
            'client_type' => $data['client_type'], 'document_id' => $data['document_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if (! empty($data['pin'])) {
            $user->pin = $data['pin'];
        }
        $user->save();
        $this->syncWarehouses($user, $data['roles'] ?? [], $request->user());

        AuditLog::create(['user_id' => $request->user()->id, 'model' => User::class, 'model_id' => $user->id, 'action' => 'EDICION', 'after' => $user->only(['username', 'client_type', 'is_active']), 'ip' => $request->ip()]);

        return redirect()->route('config.users', ['tab' => $request->query('tab', 'escritorio'), 'q' => $user->username])->with('toast', 'Usuario actualizado.');
    }

    /** Genera contraseña y/o PIN nuevos y los muestra una sola vez. */
    public function resetCredentials(Request $request, User $user): RedirectResponse
    {
        $isCollector = in_array($user->client_type, ['COLECTOR', 'AMBOS'], true);
        $isDesktop = in_array($user->client_type, ['ESCRITORIO', 'AMBOS'], true);

        $password = $isDesktop ? Str::password(10, symbols: false) : null;
        $pin = $isCollector ? (string) random_int(1000, 9999) : null;

        if ($password) {
            $user->password = $password;
        }
        if ($pin) {
            $user->pin = $pin;
        }
        $user->save();
        $user->tokens()->delete(); // cierra sesiones del colector

        AuditLog::create(['user_id' => $request->user()->id, 'model' => User::class, 'model_id' => $user->id, 'action' => 'RESET_CREDENCIALES', 'ip' => $request->ip()]);

        return redirect()->route('config.users', ['tab' => $request->query('tab', 'escritorio'), 'q' => $user->username])
            ->with('toast', "Credenciales de {$user->username} restablecidas.")
            ->with('credentials', ['user' => $user->username, 'password' => $password, 'pin' => $pin]);
    }

    // -----------------------------------------------------------------

    private function validated(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9._-]+$/i', Rule::unique('users', 'username')->ignore($ignore)],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($ignore)],
            'client_type' => ['required', 'in:ESCRITORIO,COLECTOR,AMBOS'],
            'document_id' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'max:64'],
            'pin' => ['nullable', 'digits_between:4,6'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'in:,ADMIN,SUPERVISOR,OPERADOR'],
        ], [], ['name' => 'nombre', 'username' => 'usuario', 'email' => 'correo', 'client_type' => 'tipo de acceso', 'password' => 'contraseña']);
    }

    /** @param  array<int|string, string|null>  $roles  warehouse_id => rol ('' = sin acceso) */
    private function syncWarehouses(User $user, array $roles, ?User $actor = null): void
    {
        $sync = [];
        foreach ($roles as $whId => $role) {
            if ($role) {
                $sync[(int) $whId] = ['role' => $role];
            }
        }

        // Un admin no puede quitarse a sí mismo el rol ADMIN del almacén actual.
        if ($actor && $actor->id === $user->id) {
            foreach ($actor->warehouses as $wh) {
                if ($wh->pivot->role === 'ADMIN' && ($sync[$wh->id]['role'] ?? null) !== 'ADMIN') {
                    $sync[$wh->id] = ['role' => 'ADMIN'];
                }
            }
        }

        $user->warehouses()->sync($sync);
    }

    public static function permissionLabels(): array
    {
        return [
            'stock.view' => 'Consultar stock y kardex', 'stock.view_all_warehouses' => 'Ver todos los almacenes', 'receipt.scan' => 'Recepcionar (escanear)',
            'receipt.close' => 'Cerrar / anular recepciones', 'move.relocate' => 'Reubicar y put-away', 'count.adjust' => 'Ajustes e inventarios',
        ];
    }
}
