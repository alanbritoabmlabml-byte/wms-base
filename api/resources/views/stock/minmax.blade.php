<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); @endphp
<x-page-head title="Stock e inventario" sub="Mínimos y máximos por almacén (o los del producto si no hay específicos). Los días de stock se estiman con las salidas de 30 días." />
<x-card :flush="true">
  @include('stock._tabs')
  <div class="toolbar">
    <div class="chips">
      @foreach(['' => 'Todos', 'BAJO' => 'Bajo mínimo', 'SOBRE' => 'Sobre máximo', 'OK' => 'En rango'] as $k => $l)<a class="chip link {{ ($filter ?? '') === $k ? 'on' : '' }}" href="{{ route('stock.minmax', ['f' => $k ?: null]) }}">{{ $l }}</a>@endforeach
    </div>
    <div style="flex:1"></div>
    <span class="muted small">{{ $rows->where('sit', 'BAJO')->count() }} bajo mínimo · {{ $rows->where('sit', 'SOBRE')->count() }} sobre máximo</span>
  </div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>SKU</th><th>Clase</th><th class="r">Mín</th><th class="r">Máx</th><th class="r">Stock</th><th>Cobertura</th><th>Situación</th><th class="r">Días de stock</th></tr></thead><tbody>
    @foreach($rows as $r)
    @php $pct = $r['max'] > 0 ? min(100, round($r['qty'] / $r['max'] * 100)) : 0; @endphp
    <tr>
      <td><a class="mono link" href="{{ route('items.show', $r['item']) }}">{{ $r['item']->sku }}</a><div class="small muted">{{ $r['item']->name }}</div></td>
      <td>@if($r['cls'])<span class="tag {{ $r['cls'] === 'A' ? 'a' : '' }}">{{ $r['cls'] }}</span>@else<span class="muted">—</span>@endif</td>
      <td class="r num">{{ $r['min'] ? $u($r['min']) : '—' }}</td>
      <td class="r num">{{ $r['max'] ? $u($r['max']) : '—' }}</td>
      <td class="r"><b class="num">{{ $u($r['qty']) }}</b></td>
      <td><div class="row"><div class="bar {{ $r['sit'] === 'BAJO' ? 'bad' : ($r['sit'] === 'SOBRE' ? 'warn' : 'ok') }}" style="width:120px"><i style="width:{{ $pct }}%"></i></div><span class="small num muted">{{ $r['max'] ? $pct.'%' : '—' }}</span></div></td>
      <td>@if($r['sit'] === 'BAJO')<span class="pill bad">Bajo mínimo</span>@elseif($r['sit'] === 'SOBRE')<span class="pill warn">Sobre máximo</span>@else<span class="pill ok">En rango</span>@endif</td>
      <td class="r num">{{ $r['days'] !== null ? $r['days'].' d' : '—' }}</td>
    </tr>
    @endforeach
  </tbody></table></div>
</x-card>
</x-layouts.app>
