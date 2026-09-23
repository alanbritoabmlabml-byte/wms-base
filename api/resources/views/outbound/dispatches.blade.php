<x-layouts.app :title="$title">
@php $canSup = in_array($whRole, ['ADMIN', 'SUPERVISOR']); @endphp
<x-page-head title="Despachos" sub="Cada despacho agrupa pedidos embalados en un camión con su chofer. Al marcar «En ruta» se descuenta el stock de todos sus pedidos.">
  @if($canSup)<button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'dsp')" @disabled(!$ready->count())><x-icon name="truck" /> Armar despacho</button>@endif
</x-page-head>
@include('outbound._tabs', ['tab' => 'dispatches'])

<div class="grid g-2-1">
  <x-card :flush="true">
    <div class="toolbar">
      <div class="chips"><a class="chip link {{ !$status ? 'on' : '' }}" href="{{ route('outbound.dispatches') }}">Todos</a>@foreach($labels as $k => $l)<a class="chip link {{ $status === $k ? 'on' : '' }}" href="{{ route('outbound.dispatches', ['estado' => $k]) }}">{{ $l }}</a>@endforeach</div>
    </div>
    @if($dispatches->count())
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Despacho</th><th>Camión</th><th>Chofer</th><th>Destino</th><th>Pedidos</th><th class="r">Bultos</th><th>Salida</th><th>Estado</th><th></th></tr></thead><tbody>
      @foreach($dispatches as $d)
      <tr>
        <td class="mono"><b>{{ $d->number }}</b><div class="small muted">{{ $d->scheduled_at?->format('d/m/Y H:i') }}</div></td>
        <td><b class="mono">{{ $d->vehicle?->plate ?? '—' }}</b><div class="small muted">{{ $d->vehicle?->type }}</div></td>
        <td>{{ $d->driver?->full_name ?? '—' }}</td>
        <td>{{ $d->destination ?? '—' }}</td>
        <td>@foreach($d->orders as $o)<a class="tag" href="{{ route('outbound.show', $o) }}" title="{{ $o->customer?->name }}" style="margin:1px;text-decoration:none">{{ $o->number }}</a>@endforeach</td>
        <td class="r">{{ $d->packages }}</td>
        <td class="small">{{ $d->departed_at?->format('d/m H:i') ?? '—' }}@if($d->delivered_at)<div class="muted">entregado {{ $d->delivered_at->format('d/m H:i') }}</div>@endif</td>
        <td><x-pill :status="$d->status" /></td>
        <td class="tbl-actions">
          @if($d->status === 'CARGANDO')
            <form method="post" action="{{ route('outbound.dispatches.status', $d) }}" class="inline-form" data-confirm="Se descontará el stock de {{ $d->orders->count() }} pedidos y el camión {{ $d->vehicle?->plate }} quedará en ruta. ¿Confirmar salida?">@csrf<input type="hidden" name="estado" value="EN_RUTA"><button class="btn sm primary"><x-icon name="truck" /> Salió</button></form>
            @if($canSup)<form method="post" action="{{ route('outbound.dispatches.status', $d) }}" class="inline-form" data-confirm="¿Anular el despacho {{ $d->number }}? Los pedidos vuelven a quedar disponibles.">@csrf<input type="hidden" name="estado" value="ANULADO"><button class="btn sm ghost" title="Anular"><x-icon name="x" /></button></form>@endif
          @elseif($d->status === 'EN_RUTA')
            <form method="post" action="{{ route('outbound.dispatches.status', $d) }}" class="inline-form">@csrf<input type="hidden" name="estado" value="ENTREGADO"><button class="btn sm"><x-icon name="check" /> Entregado</button></form>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody></table></div>
    <div class="tbl-foot"><span>{{ $dispatches->total() }} despachos</span>{{ $dispatches->links('partials.pager') }}</div>
    @else<x-empty icon="truck" text="Sin despachos con ese filtro." />@endif
  </x-card>

  <x-card :title="'Listos para despachar · '.$ready->count()" :flush="true">
    @if($ready->count())
    <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Pedido</th><th>Cliente</th><th class="r">Bultos</th><th>Estado</th></tr></thead><tbody>
      @foreach($ready as $o)<tr><td class="mono"><a href="{{ route('outbound.show', $o) }}">{{ $o->number }}</a></td><td>{{ $o->customer?->name }}<div class="muted">{{ $o->customer?->city }}</div></td><td class="r">{{ $o->packages ?: '—' }}</td><td><x-pill :status="$o->status" /></td></tr>@endforeach
    </tbody></table></div>
    @else<x-empty icon="pkg" text="No hay pedidos embalados pendientes de camión." />@endif
  </x-card>
</div>

@if($canSup)
<x-modal id="dsp" title="Armar despacho {{ $nextNumber }}" width="720px">
  <form method="post" action="{{ route('outbound.dispatches.store') }}" x-data="{ sel: [], v: '' }">@csrf
    <div class="m-b stack">
      <div class="grid g-2">
        <x-field label="Camión" name="vehicle_id" type="select" required x-model="v"><option value="">— Elegir —</option>@foreach($vehicles as $veh)<option value="{{ $veh->id }}" data-driver="{{ $veh->driver_id }}">{{ $veh->plate }} · {{ $veh->type }}{{ $veh->capacity_kg ? ' · '.number_format($veh->capacity_kg).' kg' : '' }}</option>@endforeach</x-field>
        <x-field label="Chofer" name="driver_id" type="select" required x-ref="drv"><option value="">— Elegir —</option>@foreach($drivers as $dr)<option value="{{ $dr->id }}">{{ $dr->full_name }}{{ $dr->license_category ? ' · '.$dr->license_category : '' }}</option>@endforeach</x-field>
        <x-field label="Destino / ruta" name="destination" placeholder="Vacío = ciudades de los pedidos" />
        <x-field label="Salida programada" name="scheduled_at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" />
      </div>
      <div class="field"><label>Pedidos a cargar <span class="muted">(<span x-text="sel.length"></span> seleccionados)</span></label>
        <div class="tbl-wrap" style="max-height:260px;overflow:auto"><table class="tbl small"><tbody>
          @foreach($ready as $o)<tr><td style="width:30px"><input type="checkbox" name="orders[]" value="{{ $o->id }}" x-model="sel"></td><td class="mono">{{ $o->number }}</td><td>{{ $o->customer?->name }}</td><td class="muted">{{ $o->customer?->city }}</td><td class="r">{{ $o->packages ?: '—' }} bultos</td></tr>@endforeach
        </tbody></table></div></div>
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary" :disabled="!sel.length"><x-icon name="truck" /> Crear despacho</button></div>
  </form>
</x-modal>
@push('scripts')<script>
document.addEventListener('change', e => { if (e.target.name === 'vehicle_id') { const d = e.target.selectedOptions[0]?.dataset.driver; const drv = e.target.closest('form').querySelector('[name=driver_id]'); if (d && drv && !drv.value) drv.value = d; } });
</script>@endpush
@endif
</x-layouts.app>
