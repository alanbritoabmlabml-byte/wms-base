<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransportController extends Controller
{
    public function index(Request $request): View
    {
        return view('transport.index', [
            'title' => 'Choferes y camiones',
            'drivers' => Driver::withCount('dispatches')->orderBy('last_name')->get(),
            'vehicles' => Vehicle::with('driver')->orderBy('plate')->get(),
            'editDriver' => $request->query('chofer') ? Driver::find($request->query('chofer')) : null,
            'editVehicle' => $request->query('vehiculo') ? Vehicle::find($request->query('vehiculo')) : null,
        ]);
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $d = Driver::create($this->driverData($request));

        return back()->with('toast', "Chofer {$d->full_name} registrado.");
    }

    public function updateDriver(Request $request, Driver $driver): RedirectResponse
    {
        $driver->fill($this->driverData($request, $driver->id))->save();

        return redirect()->route('transport.index')->with('toast', 'Chofer actualizado.');
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $v = Vehicle::create($this->vehicleData($request));

        return back()->with('toast', "Vehículo {$v->plate} registrado.");
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->fill($this->vehicleData($request, $vehicle->id))->save();

        return redirect()->route('transport.index')->with('toast', 'Vehículo actualizado.');
    }

    private function driverData(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'document_id' => ['required', 'string', 'max:20', 'unique:drivers,document_id'.($ignore ? ",$ignore" : '')],
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'license_category' => ['nullable', 'string', 'max:10'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:200'],
            'status' => ['required', 'in:ACTIVO,EN_RUTA,INACTIVO'],
        ], [], ['document_id' => 'CI', 'first_name' => 'nombre', 'last_name' => 'apellidos']);
    }

    private function vehicleData(Request $request, ?int $ignore = null): array
    {
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:15', 'unique:vehicles,plate'.($ignore ? ",$ignore" : '')],
            'type' => ['required', 'string', 'max:40'], 'brand' => ['nullable', 'string', 'max:60'], 'model' => ['nullable', 'string', 'max:60'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'], 'volume_m3' => ['nullable', 'numeric', 'min:0'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'status' => ['required', 'in:DISPONIBLE,EN_RUTA,MANTENIMIENTO,INACTIVO'],
        ], [], ['plate' => 'placa']);
        $data['plate'] = strtoupper($data['plate']);

        return $data;
    }
}
