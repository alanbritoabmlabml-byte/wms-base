<x-layouts.app :title="$title">
@php $canSup = in_array($whRole, ['ADMIN', 'SUPERVISOR']); @endphp
<x-page-head title="Pedidos y despacho" sub="Flujo de salida: recibido → preparación (picking) → validado → embalado → despachado. Arrastra mentalmente; aquí cada tarjeta avanza con un clic.">
  <a class="btn" href="{{ route('outbound.dispatches') }}"><x-icon name="truck" /> Despachos</a>
  @if($canSup)<button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'wave')"><x-icon name="wave" /> Liberar ola de picking</button>@endif
</x-page-head>

@include('outbound._tabs', ['tab' => 'board'])

<div class="grid g-4" style="margin-bottom:14px">
  <x-kpi label="Pedidos en proceso" :value="$stats['open']" />
  <x-kpi label="Urgentes" :value="$stats['urgent']" :delta="$stats['urgent'] ? 'atender primero' : 'sin urgencias'" :deltaKind="$stats['urgent'] ? 'bad' : 'ok'" />
  <x-kpi label="Sin asignar" :value="$stats['unassigned']" :delta="'en columna Recibido'" />
  <x-kpi label="Despachados hoy" :value="$stats['today']" />
</div>

<div class="kanban">
  @foreach($columns as $st => $cards)
  <div class="kcol">
    <h4>{{ $labels[$st] }} <span class="badge-num">{{ $cards->count() }}</span></h4>
    @forelse($cards as $o)
    <a class="kcard {{ $o->priority === 'URGENTE' ? 'urgent' : '' }}" href="{{ route('outbound.show', $o) }}" style="text-decoration:none;color:inherit">
      <div class="t"><span class="mono">{{ $o->number }}</span><span>{{ $o->ordered_at?->format('d/m') }}</span></div>
      <b>{{ $o->customer?->name ?? 'Sin cliente' }}</b>
      <div class="m">
        <span><x-icon name="list" /> {{ $o->lines_count }} líneas</span>
        @if($o->packages)<span><x-icon name="pkg" /> {{ $o->packages }} bultos</span>@endif
        @if($o->wave_code)<span class="tag">{{ $o->wave_code }}</span>@endif
        @if($o->priority === 'URGENTE')<span class="pill bad">Urgente</span>@endif
        @if($o->assignee)<span class="mini-av" title="{{ $o->assignee->name }}">{{ \App\Support\Ui::initials($o->assignee->name) }}</span>@elseif($st === 'RECIBIDO')<span class="muted">sin asignar</span>@endif
      </div>
    </a>
    @empty
    <div class="small muted" style="text-align:center;padding:20px 0">—</div>
    @endforelse
  </div>
  @endforeach
</div>

@if($canSup)
<x-modal id="wave" title="Liberar ola de picking" width="640px">
  <form method="post" action="{{ route('outbound.wave') }}">@csrf
    <div class="m-b stack">
      <p class="small muted" style="margin:0">Toma los pedidos en <b>Recibido</b> (urgentes primero), reserva el stock según la regla <b>FEFO/FIFO</b> configurada y los pasa a <b>Preparación</b> para que el colector los recoja en un solo recorrido.</p>
      <div class="grid g-2">
        <x-field label="Máximo de pedidos" name="max" type="number" :value="$waveMax" min="1" max="100" />
        <x-field label="Asignar a" name="assigned_to" type="select"><option value="">— Sin asignar (lo toma quien esté libre) —</option>@foreach($pickers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</x-field>
      </div>
      @if($columns['RECIBIDO']->count())
      <div class="field"><label>Pedidos a incluir (vacío = automático)</label>
        <div class="tbl-wrap" style="max-height:240px;overflow:auto"><table class="tbl small"><tbody>
          @foreach($columns['RECIBIDO'] as $o)<tr><td style="width:30px"><input type="checkbox" name="orders[]" value="{{ $o->id }}"></td><td class="mono">{{ $o->number }}</td><td>{{ $o->customer?->name }}</td><td>{{ $o->lines_count }} líneas</td><td>@if($o->priority === 'URGENTE')<span class="pill bad">Urgente</span>@endif</td></tr>@endforeach
        </tbody></table></div></div>
      @endif
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary" @disabled(!$columns['RECIBIDO']->count())><x-icon name="wave" /> Liberar ola</button></div>
  </form>
</x-modal>
@endif
</x-layouts.app>
