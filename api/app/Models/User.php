<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'pin', 'client_type', 'document_id', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'pin', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function device(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Device::class);
    }

    /** Rol más alto entre todos los almacenes (para etiquetas en pantalla). */
    public function highestRole(): string
    {
        $order = ['ADMIN' => 3, 'SUPERVISOR' => 2, 'OPERADOR' => 1];
        $roles = $this->relationLoaded('warehouses') ? $this->warehouses : $this->warehouses()->get();

        return $roles->pluck('pivot.role')->sortByDesc(fn ($r) => $order[$r] ?? 0)->first() ?? 'OPERADOR';
    }

    public function isAdminSomewhere(): bool
    {
        return $this->warehouses()->wherePivot('role', 'ADMIN')->exists();
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** IDs de almacenes asignados. Sin fila en user_warehouse no ve el almacen. */
    public function warehouseIds(): Collection
    {
        return $this->warehouses()->pluck('warehouses.id');
    }

    public function roleIn(int $warehouseId): ?string
    {
        $pivot = $this->warehouses()->where('warehouses.id', $warehouseId)->first();

        return $pivot?->pivot->role;
    }

    /** Union de permisos de todos los roles que tiene el usuario. */
    public function permissions(): array
    {
        $map = config('wms.permissions', []);

        $permissions = $this->warehouses
            ->pluck('pivot.role')
            ->unique()
            ->flatMap(fn (string $role) => $map[$role] ?? [])
            ->unique()
            ->values()
            ->all();

        sort($permissions);

        return $permissions;
    }
}
