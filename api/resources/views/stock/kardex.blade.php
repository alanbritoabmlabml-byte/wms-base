<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); $in = \App\Models\StockMovement::INBOUND_TYPES; $out = \App\Models\StockMovement::OUTBOUND_TYPES;
$typeLabels = ['RECEPCION' => 'Ingreso', 'PUTAWAY' => 'Ubicación', 'REUBICACION' => 'Reubicación', 'PICKING' => 'Picking', 'DESPACHO' => 'Despacho', 'AJUSTE_POS' => 'Ajuste +', 'AJUSTE_NEG' => 'Ajuste −', 'TRASPASO_OUT' => 'Traspaso salida', 'TRASPASO_IN' => 'Traspaso entrada', 'CAMBIO_ESTADO' => 'Cambio de estado']; @endphp
<x-page-head title="Stock e inventario" sub="Cada movimiento es un asiento inmutable. El saldo es una consecuencia; el kardex es la verdad." />
<x-card :flush="true">
  @include('stock._tabs')
  <form class="toolbar" method="get">
    <div class="search"><x-icon name="search" /><input name="q" value="{{ $q }}" placeholder="SKU, lote, usuario, equipo…"></div>
    <select class="select" name="tipo" style="min-height:36px;padding:6px 32px 6px 10px"><option value="">Todos los movimientos</option>@foreach($typeLabels as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option>@endforeach</select>
    <input class="input" type="date" name="desde" value="{{ $from }}" style="width:150px;min-height:36px">
    <input class="input" type="date" name="hasta" value="{{ $to }}" style="width:150px;min-height:36px">
    <button class="btn sm">Filtrar</button>
  </form>
  @if($rows->count())
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Fecha / hora</th><th>Movimiento</th><th>SKU</th><th>Lote</th><th>Desde</th><th>Hacia</th><th class="r">Cantidad</th><th>Documento</th><th>Usuario / equipo</th></tr></thead><tbody>
    @foreach($rows as $m)
    @php $dir = in_array($m->type, $in) ? 'in' : (in_array($m->type, $out) ? 'out' : 'mv'); $signed = $dir === 'out' ? -1 * (float) $m->qty : (float) $m->qty; @endphp
    <tr>
      <td><span class="num small">{{ \App\Support\Ui::date($m->occurred_at, true) }}</span></td>
      <td><span class="pill {{ $dir === 'in' ? 'ok' : ($dir === 'out' ? 'bad' : 'info') }} plain">{{ $typeLabels[$m->type] ?? $m->type }}</span>@if($m->reasonCode)<div class="small muted">{{ $m->reasonCode->name }}</div>@endif</td>
      <td><a class="mono link" href="{{ route('items.show', $m->item) }}">{{ $m->item->sku }}</a><div class="small muted">{{ $m->item->name }}</div></td>
      <td><span class="mono small">{{ $m->lot?->code === '-' ? '—' : $m->lot?->code }}</span></td>
      <td><span class="mono small">{{ $m->fromLocation?->code ?? ($dir === 'in' ? 'externo' : '—') }}</span></td>
      <td><span class="mono small">{{ $m->toLocation?->code ?? ($dir === 'out' ? 'externo' : '—') }}</span></td>
      <td class="r"><b class="num" style="color:{{ $dir === 'out' ? 'var(--bad)' : ($dir === 'in' ? 'var(--ok)' : 'inherit') }}">{{ $dir === 'in' ? '+' : '' }}{{ $u($signed) }}</b></td>
      <td><span class="mono small">{{ $m->document_type ? $m->document_type.'-'.$m->document_id : '—' }}</span></td>
      <td>{{ $m->user?->name }}<div class="small muted">{{ $m->device_id ?? '—' }}@if($m->posted_batch_id) · <span style="color:var(--warn)">en cola</span>@endif</div></td>
    </tr>
    @endforeach
  </tbody></table></div>
  <div class="tbl-foot"><span>{{ $rows->total() }} movimientos</span>{{ $rows->links('partials.pager') }}</div>
  @else<x-empty icon="history" text="Sin movimientos en el periodo" />@endif
</x-card>
</x-layouts.app>
