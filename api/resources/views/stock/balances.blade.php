<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); @endphp
<x-page-head title="Stock e inventario" sub="Saldos por ubicación, lote y estado; kardex inmutable; conteos cíclicos con aprobación.">
  <a class="btn primary" href="{{ route('stock.counts') }}"><x-icon name="count" /> Nuevo conteo</a>
</x-page-head>
<x-card :flush="true">
  @include('stock._tabs')
  <form class="toolbar" method="get">
    <div class="search"><x-icon name="search" /><input name="q" value="{{ $q }}" placeholder="SKU, descripción, ubicación o lote"></div>
    <div class="chips">
      @foreach(['' => 'Todos', 'BUENO' => 'Disponible', 'RESERVADO' => 'Reservado', 'CUARENTENA' => 'Cuarentena', 'OBSERVADO' => 'Observado', 'VENCIDO' => 'Vencido'] as $k => $l)
        <a class="chip link {{ ($status ?? '') === $k ? 'on' : '' }}" href="{{ route('stock.balances', ['estado' => $k ?: null, 'q' => $q]) }}">{{ $l }}</a>
      @endforeach
    </div>
    <div style="flex:1"></div>
    <span class="muted small">{{ $u($totals->rows_count) }} saldos · {{ $u($totals->skus) }} SKU · <b class="num">{{ $u($totals->units) }}</b> un. · {{ $u($totals->allocated) }} reservadas</span>
    <button class="btn sm">Buscar</button>
  </form>
  @if($rows->count())
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ubicación</th><th>SKU</th><th>UM</th><th>Lote</th><th>Vence</th><th class="r">Cantidad</th><th class="r">Reservado</th><th>Estado</th><th></th></tr></thead><tbody>
    @foreach($rows as $b)
    <tr>
      <td><a class="mono link" href="{{ route('map.index', ['zona' => $b->location->zone_id, 'ubicacion' => $b->location_id]) }}">{{ $b->location->code }}</a><div class="small muted">{{ $b->location->zone?->name }}</div></td>
      <td><a class="mono link" href="{{ route('items.show', $b->item) }}">{{ $b->item->sku }}</a><div class="small muted">{{ $b->item->name }}</div></td>
      <td>{{ $b->item->baseUom->code }}</td>
      <td><span class="mono small">{{ $b->lot?->code === '-' ? '—' : $b->lot?->code }}</span></td>
      <td>@if($b->lot?->expires_at)<span class="{{ $b->lot->expires_at->isPast() ? 'pill bad plain' : ($b->lot->expires_at->lt(now()->addDays(30)) ? 'pill warn plain' : '') }}">{{ \App\Support\Ui::date($b->lot->expires_at) }}</span>@else<span class="muted">—</span>@endif</td>
      <td class="r"><b class="num">{{ $u($b->qty) }}</b></td>
      <td class="r">@if($b->qty_allocated > 0)<span class="num" style="color:var(--warn)">{{ $u($b->qty_allocated) }}</span>@else<span class="muted">0</span>@endif</td>
      <td><x-pill :status="$b->status" /></td>
      <td class="tbl-actions"><a class="btn sm ghost" title="Ajustar" href="{{ route('stock.adjustments', ['ubicacion' => $b->location->code]) }}"><x-icon name="edit" /></a></td>
    </tr>
    @endforeach
  </tbody></table></div>
  <div class="tbl-foot"><span>{{ $rows->total() }} saldos</span>{{ $rows->links('partials.pager') }}</div>
  @else<x-empty icon="stock" text="Sin saldos con ese filtro" />@endif
</x-card>
</x-layouts.app>
