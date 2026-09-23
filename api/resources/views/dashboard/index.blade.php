<x-layouts.app :title="$title">
@php $u = fn($n, $d = 0) => \App\Support\Ui::qty($n, $d); $occPct = $usable ? round($occupied / $usable * 100) : 0; @endphp
<x-page-head :title="'Buenos días, '.explode(' ', auth()->user()->name)[0]" :sub="$wh->name.' · '.now()->translatedFormat('l d \\d\\e F').' · '.$movementsToday.' movimientos hoy'">
  <a class="btn" href="{{ route('stock.counts') }}"><x-icon name="count" /> Nuevo conteo</a>
  <a class="btn" href="{{ route('receipts.create') }}"><x-icon name="in" /> Orden de ingreso</a>
  <a class="btn primary" href="{{ route('outbound.board') }}"><x-icon name="zap" /> Liberar ola de picking</a>
</x-page-head>

<div class="grid g-4" style="margin-bottom:16px">
  <x-kpi label="Exactitud por ubicación (IRA)" :value="$ira !== null ? number_format($ira, 1, ',', '.') : '—'" :unit="$ira !== null ? '%' : null" :delta="$lastCount ? 'conteo '.$lastCount->number.' · '.\App\Support\Ui::date($lastCount->closed_at) : 'sin conteos finalizados aún'" />
  <x-kpi label="Pedidos en proceso" :value="$ordersOpen" :unit="'de '.$ordersTotal" :delta="$ordersUrgent.' urgentes · '.$ordersUnassigned.' sin asignar'" />
  <x-kpi label="Ocupación de ubicaciones" :value="$occPct" unit="%" :delta="$u($occupied).' de '.$u($usable).' ubicaciones con stock'" />
  <x-kpi label="Ingresos abiertos" :value="$receiptsOpen" :delta="$queueTotal ? $queueTotal.' movimientos en cola offline' : 'colectores al día'" :delta-kind="$queueTotal ? 'down' : ''" />
</div>

<div class="grid g-2-1" style="margin-bottom:16px">
  <x-card title="Flujo de la semana · unidades" aside='<div class="legend"><span><i style="background:var(--primary)"></i>Ingresos</span><span><i style="background:var(--accent)"></i>Salidas</span></div>'>
    @if(array_sum($flow['in']) + array_sum($flow['out']) > 0)
      {!! \App\Support\Chart::lines([['v' => $flow['in'], 'c' => 'var(--primary)'], ['v' => $flow['out'], 'c' => 'var(--accent)']], $flow['labels'], 640, 200) !!}
    @else
      <x-empty icon="wave" text="Sin movimientos en los últimos 7 días" />
    @endif
  </x-card>
  <x-card title="Requiere atención" :flush="true">
    <div class="card-b" style="padding:8px 16px">
      @if($alerts)
      <ul class="timeline">
        @foreach($alerts as $a)<li><span class="ic {{ $a['k'] }}"><x-icon :name="$a['k'] === 'ok' ? 'check' : 'alert'" /></span><div><b>{{ $a['t'] }}</b><span>{{ $a['s'] }}</span></div></li>@endforeach
      </ul>
      @else<x-empty icon="check" text="Todo en orden" />@endif
    </div>
  </x-card>
</div>

<div class="grid g-3">
  <x-card title="Ocupación por rack" aside="<span class='muted small'>{{ $wh->code }} · %</span>">
    @if($racks){!! \App\Support\Chart::bars($racks, 420, 170, collect($racks)->sortByDesc('v')->first()['l'] ?? null, '%') !!}@else<x-empty icon="map" text="Crea racks en Mapa de almacén" />@endif
  </x-card>
  <x-card title="Bajo el mínimo" :flush="true" aside="<span class='pill {{ $lowStock->count() ? 'warn' : 'ok' }}'>{{ $lowStock->count() }} SKU</span>">
    @if($lowStock->count())
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>SKU</th><th class="r">Stock</th></tr></thead><tbody>
      @foreach($lowStock as $r)<tr><td><a class="mono link" href="{{ route('items.show', $r['item']) }}">{{ $r['item']->sku }}</a><div class="small muted">{{ $r['item']->name }}</div></td><td class="r"><b class="num">{{ $u($r['qty']) }}</b> <span class="muted small">/ mín {{ $u($r['item']->min_stock) }}</span></td></tr>@endforeach
    </tbody></table></div>
    @else<x-empty icon="check" text="Ningún producto bajo mínimo" />@endif
  </x-card>
  <x-card title="Colectores" :flush="true" aside="<span class='pill {{ $queueTotal ? 'warn' : 'ok' }}'>{{ $queueTotal }} en cola</span>">
    <div class="card-b stack">
      @forelse($devices as $d)
      <div class="row between">
        <div class="row"><span class="pill {{ $d->isOnline() ? 'ok' : 'bad' }} plain" style="padding:2px 6px">{{ $d->isOnline() ? '●' : '○' }}</span><div><b class="small mono">{{ $d->serial }}</b> <span class="muted small">· {{ $d->user?->name ?? 'libre' }}</span></div></div>
        <div class="row" style="width:120px"><div class="bar {{ ($d->battery_pct ?? 100) < 20 ? 'bad' : (($d->battery_pct ?? 100) < 50 ? 'warn' : 'ok') }}" style="flex:1"><i style="width:{{ $d->battery_pct ?? 0 }}%"></i></div><span class="small num muted" style="width:34px;text-align:right">{{ $d->battery_pct !== null ? $d->battery_pct.'%' : '—' }}</span></div>
      </div>
      @empty<x-empty icon="phone" text="Sin colectores registrados en este almacén" />@endforelse
    </div>
  </x-card>
</div>
</x-layouts.app>
