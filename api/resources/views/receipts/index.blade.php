<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); @endphp
<x-page-head title="Ingresos" sub="Órdenes de ingreso desde producción, compras, devoluciones y transferencias.">
  <a class="btn primary" href="{{ route('receipts.create') }}"><x-icon name="plus" /> Nueva orden de ingreso</a>
</x-page-head>

<div class="grid g-4" style="margin-bottom:16px">
  @foreach([['ABIERTA', 'Listas para recibir'], ['EN_PROCESO', 'Recibiendo ahora'], ['CERRADA', 'Cerradas'], ['ANULADA', 'Anuladas']] as [$s, $l])
  <a class="card kpi" style="text-decoration:none;color:inherit;border-color:{{ $status === $s ? 'var(--navy)' : 'var(--line)' }}" href="{{ route('receipts.index', ['estado' => $status === $s ? null : $s, 'tipo' => $type]) }}">
    <span class="lbl">{{ $l }}</span><span class="val">{{ $counts[$s] ?? 0 }}</span><x-pill :status="$s" />
  </a>
  @endforeach
</div>

<x-card :flush="true">
  <form class="toolbar" method="get">
    <input type="hidden" name="estado" value="{{ $status }}">
    <div class="search"><x-icon name="search" /><input name="q" value="{{ $q }}" placeholder="Nro, documento origen, proveedor…"></div>
    <div class="chips">
      @foreach(['' => 'Todos', 'PRODUCCION' => 'Producción', 'COMPRA' => 'Compra', 'DEVOLUCION' => 'Devolución', 'TRASPASO' => 'Transferencia'] as $k => $l)
        <a class="chip link {{ ($type ?? '') === $k ? 'on' : '' }}" href="{{ route('receipts.index', ['estado' => $status, 'tipo' => $k ?: null, 'q' => $q]) }}">{{ $l }}</a>
      @endforeach
    </div>
    <div style="flex:1"></div>
    <button class="btn sm">Filtrar</button>
  </form>
  @if($receipts->count())
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Orden</th><th>Fecha</th><th>Tipo</th><th>Origen</th><th class="r">Líneas</th><th>Avance</th><th>Estado</th><th></th></tr></thead><tbody>
    @foreach($receipts as $r)
    @php $pct = $r->qty_expected > 0 ? round($r->qty_received / $r->qty_expected * 100) : 0; @endphp
    <tr class="clickable" onclick="location.href='{{ route('receipts.show', $r) }}'">
      <td><span class="mono link">{{ $r->number }}</span><div class="small muted">{{ $r->external_ref ?? '—' }}</div></td>
      <td>{{ \App\Support\Ui::date($r->expected_at) }}</td>
      <td><span class="tag">{{ \App\Support\Ui::label($r->type) }}</span></td>
      <td>{{ $r->supplier_name ?? '—' }}</td>
      <td class="r">{{ $r->lines_done }}/{{ $r->lines_total }}</td>
      <td><div class="row"><div class="bar {{ $pct >= 100 ? 'ok' : '' }}" style="width:90px"><i style="width:{{ min(100, $pct) }}%"></i></div><span class="small num">{{ $pct }}%</span></div></td>
      <td><x-pill :status="$r->status" /></td>
      <td class="tbl-actions"><a class="btn sm ghost" href="{{ route('receipts.show', $r) }}"><x-icon name="chev" /></a></td>
    </tr>
    @endforeach
  </tbody></table></div>
  <div class="tbl-foot"><span>{{ $receipts->total() }} órdenes</span>{{ $receipts->links('partials.pager') }}</div>
  @else<x-empty icon="in" text="No hay órdenes con ese filtro" />@endif
</x-card>
</x-layouts.app>
