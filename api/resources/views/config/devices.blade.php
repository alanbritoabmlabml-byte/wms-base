<x-layouts.app :title="$title">
@php $e = $editing; @endphp
<x-page-head title="Colectores" sub="Flota de terminales Zebra. Un colector se enlaza a un almacén y a un operador habitual; la telemetría la reporta la app en cada sincronización.">
  <a class="btn {{ $all ? 'primary' : '' }}" href="{{ route('config.devices', $all ? [] : ['todos' => 1]) }}"><x-icon name="grid" /> {{ $all ? 'Todos los almacenes' : 'Solo '.$wh->code }}</a>
  <button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'dev')"><x-icon name="plus" /> Registrar colector</button>
</x-page-head>

<div class="grid g-4" style="margin-bottom:14px">
  <x-kpi label="Colectores registrados" :value="$devices->count()" :delta="$devices->where('is_active', false)->count().' inactivos'" />
  <x-kpi label="En línea (últimos 10 min)" :value="$online" :unit="'de '.$devices->where('is_active', true)->count()" />
  <x-kpi label="Movimientos en cola offline" :value="$pendingQueue" :delta="$pendingQueue ? 'pendientes de sincronizar' : 'todo sincronizado'" :deltaKind="$pendingQueue ? 'warn' : 'ok'" />
  <x-kpi label="Batería promedio" :value="$devices->whereNotNull('battery_pct')->count() ? round($devices->avg('battery_pct')) : '—'" :unit="$devices->whereNotNull('battery_pct')->count() ? '%' : null" />
</div>

<div class="grid g-2-1">
  <x-card title="Flota" :flush="true">
    @if($devices->count())
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Colector</th><th>Almacén</th><th>Operador</th><th>Estado</th><th>Último reporte</th><th class="r">Batería</th><th class="r">Cola</th><th>App</th><th></th></tr></thead><tbody>
      @foreach($devices as $d)
      <tr class="{{ $d->is_active ? '' : 'muted' }}">
        <td><div class="row" style="gap:10px"><span class="dot {{ !$d->is_active ? '' : ($d->isOnline() ? 'ok' : 'off') }}"></span><div><b class="mono">{{ $d->serial }}</b><div class="small muted">{{ $d->model }}{{ $d->os_version ? ' · Android '.$d->os_version : '' }}</div></div></div></td>
        <td>{{ $d->warehouse?->code }}</td>
        <td>@if($d->user)<div class="row" style="gap:8px"><span class="avatar" style="width:28px;height:28px;font-size:11px">{{ \App\Support\Ui::initials($d->user->name) }}</span>{{ $d->user->name }}</div>@else<span class="small muted">Sin asignar</span>@endif</td>
        <td>@if(!$d->is_active)<x-pill status="INACTIVO" />@elseif($d->isOnline())<span class="pill ok">En línea</span>@else<span class="pill neutral">Sin conexión</span>@endif</td>
        <td class="small">{{ $d->last_seen_at ? $d->last_seen_at->diffForHumans() : 'Nunca' }}</td>
        <td class="r">@if($d->battery_pct !== null)<span style="color:{{ $d->battery_pct < 20 ? 'var(--bad)' : ($d->battery_pct < 40 ? 'var(--warn)' : 'inherit') }}">{{ $d->battery_pct }}%</span>@else—@endif</td>
        <td class="r {{ $d->pending_queue ? 'bad' : '' }}">{{ $d->pending_queue }}</td>
        <td class="mono small">{{ $d->app_version ?? '—' }}</td>
        <td class="tbl-actions"><a class="btn sm ghost" href="{{ route('config.devices', ['editar' => $d->id, 'todos' => $all ? 1 : null]) }}" title="Editar"><x-icon name="edit" /></a></td>
      </tr>
      @endforeach
    </tbody></table></div>
    <div class="tbl-foot"><span>{{ $devices->count() }} colectores</span></div>
    @else<x-empty icon="phone" text="Aún no hay colectores registrados en este almacén." />@endif
  </x-card>

  <div class="stack">
    <x-card title="Últimas sincronizaciones" :flush="true">
      @if($recentSync->count())
      <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Cuándo</th><th>Colector</th><th class="r">Movs.</th><th>Estado</th></tr></thead><tbody>
        @foreach($recentSync as $s)
        <tr><td class="small">{{ $s->received_at?->format('d/m H:i') ?? $s->created_at->format('d/m H:i') }}</td><td class="mono small">{{ $s->device?->serial ?? '—' }}<div class="muted">{{ $s->user?->name }}</div></td><td class="r">{{ $s->movements_count }}</td><td><span class="pill {{ ['OK' => 'ok', 'PARCIAL' => 'warn', 'ERROR' => 'bad'][$s->status] ?? 'neutral' }}">{{ $s->status }}</span></td></tr>
        @endforeach
      </tbody></table></div>
      @else<x-empty icon="refresh" text="Sin sincronizaciones registradas." />@endif
    </x-card>
    <x-card title="Instalación en el colector">
      <ol class="small" style="margin:0;padding-left:18px;line-height:1.7">
        <li>Abre Chrome en el Zebra y entra a <code class="mono">{{ rtrim(config('app.url'), '/') }}/colector</code>.</li>
        <li>Menú ⋮ → <b>Instalar aplicación</b> (queda como app con ícono propio).</li>
        <li>En DataWedge crea el perfil <b>CarmenWMS</b> con salida por teclado + Enter.</li>
        <li>Inicia sesión con usuario y PIN; la app registra la serie automáticamente.</li>
      </ol>
      <p class="small muted" style="margin:10px 0 0">Guía completa en <code>docs/06-integracion-zebra.md</code>.</p>
    </x-card>
  </div>
</div>

<x-modal id="dev" :title="$e ? 'Editar colector' : 'Registrar colector'" width="560px">
  <form method="post" action="{{ $e ? route('config.devices.update', $e) : route('config.devices.store') }}">@csrf @if($e)@method('PUT')@endif
    <div class="m-b"><div class="grid g-2">
      <x-field label="Número de serie" name="serial" :value="$e?->serial" required mono placeholder="Ej. 21085523020xxx" />
      <x-field label="Modelo" name="model" type="select" required>@foreach($models as $m)<option @selected(($e?->model ?? 'Zebra TC52') === $m)>{{ $m }}</option>@endforeach</x-field>
      <x-field label="Almacén" name="warehouse_id" type="select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(($e?->warehouse_id ?? $wh->id) == $w->id)>{{ $w->code }} · {{ $w->name }}</option>@endforeach</x-field>
      <x-field label="Operador habitual" name="user_id" type="select"><option value="">— Sin asignar —</option>@foreach($operators as $o)<option value="{{ $o->id }}" @selected($e?->user_id == $o->id)>{{ $o->name }} ({{ $o->username }})</option>@endforeach</x-field>
      @if($e)<label class="row" style="gap:8px"><input type="checkbox" name="is_active" value="1" @checked($e->is_active)> Colector activo</label>@endif
    </div>
    @if($e)<dl class="kv" style="margin-top:14px"><dt>Último reporte</dt><dd>{{ $e->last_seen_at?->format('d/m/Y H:i') ?? 'Nunca' }}</dd><dt>Versión app</dt><dd>{{ $e->app_version ?? '—' }}</dd><dt>Android</dt><dd>{{ $e->os_version ?? '—' }}</dd></dl>@endif
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Guardar</button></div>
  </form>
</x-modal>
@if($e)@push('scripts')<script>document.addEventListener('alpine:init',()=>{setTimeout(()=>window.dispatchEvent(new CustomEvent('open-modal',{detail:'dev'})),50)});</script>@endpush @endif
</x-layouts.app>
