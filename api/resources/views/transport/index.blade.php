<x-layouts.app :title="$title">
@php $ed = $editDriver; $ev = $editVehicle; @endphp
<x-page-head title="Choferes y camiones" sub="Flota propia y terceros. Cada despacho queda asociado a vehículo y conductor.">
  @if($whRole === 'ADMIN')<a class="btn" href="{{ route('config.import', ['dataset' => 'drivers']) }}"><x-icon name="upload" /> Importar</a>@endif
  <button class="btn" x-data x-on:click="$dispatch('open-modal', 'veh')"><x-icon name="plus" /> Nuevo vehículo</button>
  <button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'drv')"><x-icon name="plus" /> Nuevo chofer</button>
</x-page-head>
<div class="grid g-2">
  <x-card title="Choferes" :flush="true" aside="<span class='muted small'>{{ $drivers->where('status', '!=', 'INACTIVO')->count() }} activos</span>">
    @if($drivers->count())
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Chofer</th><th>Licencia</th><th>Teléfono</th><th class="r">Viajes</th><th>Estado</th><th></th></tr></thead><tbody>
      @foreach($drivers as $d)<tr><td><div class="row"><span class="mini-av">{{ \App\Support\Ui::initials($d->full_name) }}</span><div><b>{{ $d->full_name }}</b><div class="small muted mono">CI {{ $d->document_id }}</div></div></div></td><td>@if($d->license_category)<span class="tag">{{ $d->license_category }}</span>@endif</td><td>{{ $d->phone ?? '—' }}</td><td class="r">{{ $d->dispatches_count }}</td><td><x-pill :status="$d->status" /></td><td class="tbl-actions"><a class="btn sm ghost" href="{{ route('transport.index', ['chofer' => $d->id]) }}"><x-icon name="edit" /></a></td></tr>@endforeach
    </tbody></table></div>
    @else<x-empty icon="users" text="Sin choferes registrados" />@endif
  </x-card>
  <x-card title="Vehículos" :flush="true" aside="<span class='muted small'>{{ $vehicles->where('status', 'DISPONIBLE')->count() }} disponibles</span>">
    @if($vehicles->count())
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Placa</th><th>Tipo</th><th>Capacidad</th><th>Chofer habitual</th><th>Estado</th><th></th></tr></thead><tbody>
      @foreach($vehicles as $v)<tr><td><b class="mono">{{ $v->plate }}</b><div class="small muted">{{ $v->brand }} {{ $v->model }}</div></td><td>{{ $v->type }}</td><td class="small">{{ $v->capacity_kg ? \App\Support\Ui::qty($v->capacity_kg).' kg' : '' }}{{ $v->volume_m3 ? ' · '.\App\Support\Ui::qty($v->volume_m3).' m³' : '' }}</td><td>{{ $v->driver?->full_name ?? '—' }}</td><td><x-pill :status="$v->status" /></td><td class="tbl-actions"><a class="btn sm ghost" href="{{ route('transport.index', ['vehiculo' => $v->id]) }}"><x-icon name="edit" /></a></td></tr>@endforeach
    </tbody></table></div>
    @else<x-empty icon="truck" text="Sin vehículos registrados" />@endif
  </x-card>
</div>

<x-modal id="drv" :title="$ed ? 'Editar chofer' : 'Nuevo chofer'" width="620px">
  <form method="post" action="{{ $ed ? route('transport.drivers.update', $ed) : route('transport.drivers.store') }}">@csrf @if($ed)@method('PUT')@endif
    <div class="m-b"><div class="grid g-2">
      <x-field label="CI" name="document_id" :value="$ed?->document_id" required />
      <x-field label="Licencia" name="license_category" type="select">@foreach(['CAT-A', 'CAT-B', 'CAT-C', 'CAT-P'] as $l)<option @selected($ed?->license_category === $l)>{{ $l }}</option>@endforeach</x-field>
      <x-field label="Nombre" name="first_name" :value="$ed?->first_name" required />
      <x-field label="Apellidos" name="last_name" :value="$ed?->last_name" required />
      <x-field label="Teléfono" name="phone" :value="$ed?->phone" />
      <x-field label="Estado" name="status" type="select" required>@foreach(['ACTIVO', 'EN_RUTA', 'INACTIVO'] as $s)<option value="{{ $s }}" @selected(($ed?->status ?? 'ACTIVO') === $s)>{{ \App\Support\Ui::label($s) }}</option>@endforeach</x-field>
      <div style="grid-column:1/-1"><x-field label="Dirección" name="address" :value="$ed?->address" /></div>
    </div></div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Guardar</button></div>
  </form>
</x-modal>
<x-modal id="veh" :title="$ev ? 'Editar vehículo' : 'Nuevo vehículo'" width="620px">
  <form method="post" action="{{ $ev ? route('transport.vehicles.update', $ev) : route('transport.vehicles.store') }}">@csrf @if($ev)@method('PUT')@endif
    <div class="m-b"><div class="grid g-2">
      <x-field label="Placa" name="plate" :value="$ev?->plate" required mono />
      <x-field label="Tipo" name="type" type="select" required>@foreach(['Camión 5 t', 'Camión 8 t', 'Camión 12 t', 'Tráiler', 'Camioneta', 'Furgón'] as $t)<option @selected($ev?->type === $t)>{{ $t }}</option>@endforeach</x-field>
      <x-field label="Marca" name="brand" :value="$ev?->brand" />
      <x-field label="Modelo" name="model" :value="$ev?->model" />
      <x-field label="Capacidad (kg)" name="capacity_kg" type="number" step="0.01" :value="$ev?->capacity_kg" />
      <x-field label="Volumen (m³)" name="volume_m3" type="number" step="0.01" :value="$ev?->volume_m3" />
      <x-field label="Chofer habitual" name="driver_id" type="select"><option value="">—</option>@foreach($drivers as $d)<option value="{{ $d->id }}" @selected($ev?->driver_id === $d->id)>{{ $d->full_name }}</option>@endforeach</x-field>
      <x-field label="Estado" name="status" type="select" required>@foreach(['DISPONIBLE', 'EN_RUTA', 'MANTENIMIENTO', 'INACTIVO'] as $s)<option value="{{ $s }}" @selected(($ev?->status ?? 'DISPONIBLE') === $s)>{{ \App\Support\Ui::label($s) }}</option>@endforeach</x-field>
    </div></div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Guardar</button></div>
  </form>
</x-modal>
@if($ed || $ev)@push('scripts')<script>document.addEventListener('alpine:init',()=>{setTimeout(()=>window.dispatchEvent(new CustomEvent('open-modal',{detail:'{{ $ed ? 'drv' : 'veh' }}'})),50)});</script>@endpush @endif
</x-layouts.app>
