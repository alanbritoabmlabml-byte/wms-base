<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SyncBatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\NavCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Flota de colectores Zebra: registro, almacén asignado, operador habitual y
 * telemetría (último reporte, batería, cola offline pendiente).
 */
class DeviceController extends Controller
{
    public const MODELS = ['Zebra TC52', 'Zebra TC57', 'Zebra TC21', 'Zebra TC26', 'Zebra MC3300', 'Zebra MC9300', 'Otro Android'];

    public function index(Request $request): View
    {
        $wh = $request->attributes->get('warehouse');
        $all = $request->boolean('todos');

        $devices = Device::with(['user', 'warehouse'])
            ->when(! $all, fn ($q) => $q->where('warehouse_id', $wh->id))
            ->orderByDesc('is_active')->orderBy('serial')->get();

        $online = $devices->filter->isOnline()->count();

        $recentSync = SyncBatch::with(['device', 'user'])->latest('received_at')->limit(15)->get();

        return view('config.devices', [
            'title' => 'Colectores',
            'devices' => $devices,
            'online' => $online,
            'all' => $all,
            'models' => self::MODELS,
            'warehouses' => Warehouse::orderBy('code')->get(),
            'operators' => User::whereIn('client_type', ['COLECTOR', 'AMBOS'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username']),
            'editing' => $request->query('editar') ? Device::find($request->query('editar')) : null,
            'recentSync' => $recentSync,
            'pendingQueue' => $devices->sum('pending_queue'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $d = Device::create($data + ['is_active' => true]);
        NavCounts::forget($d->warehouse_id);

        return redirect()->route('config.devices')->with('toast', "Colector {$d->serial} registrado.");
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $data = $this->validated($request, $device->id);
        $device->fill($data + ['is_active' => $request->boolean('is_active', true)])->save();
        NavCounts::forget($device->warehouse_id);

        return redirect()->route('config.devices')->with('toast', "Colector {$device->serial} actualizado.");
    }

    private function validated(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'serial' => ['required', 'string', 'max:60', Rule::unique('devices', 'serial')->ignore($ignore)],
            'model' => ['required', 'string', 'max:60'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'user_id' => ['nullable', 'exists:users,id'],
        ], [], ['serial' => 'serie', 'model' => 'modelo', 'warehouse_id' => 'almacén', 'user_id' => 'operador']);
    }
}
