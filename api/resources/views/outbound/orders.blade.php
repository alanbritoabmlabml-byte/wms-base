<x-layouts.app :title="$title">
<x-page-head title="Pedidos" sub="Todos los pedidos de venta del almacén, incluidos los despachados y anulados.">
  <a class="btn" href="{{ route('outbound.board') }}"><x-icon name="grid" /> Tablero</a>
</x-page-head>
@include('outbound._tabs', ['tab' => 'orders'])

<x-card :flush="true">
  <form class="toolbar" method="get">
    <div class="search"><x-icon name="search" /><input name="q" value="{{ $q }}" placeholder="Nro pedido, ref. WorkCorp, ola, cliente"></div>
    <div class="chips">
      <a class="chip link {{ !$status ? 'on' : '' }}" href="{{ route('outbound.orders', ['q' => $q]) }}">Todos</a>
      @foreach($labels as $k => $l)<a class="chip link {{ $status === $k ? 'on' : '' }}" href="{{ route('outbound.orders', ['estado' => $k, 'q' => $q]) }}">{{ $l }}</a>@endforeach
    </div>
    <div style="flex:1"></div><button class="btn sm">Buscar</button>
  </form>
  @if($orders->count())
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Ref. ERP</th><th class="r">Líneas</th><th class="r">Bultos</th><th>Ola</th><th>Asignado</th><th>Prioridad</th><th>Estado</th><th></th></tr></thead><tbody>
    @foreach($orders as $o)
    <tr>
      <td class="mono"><a href="{{ route('outbound.show', $o) }}">{{ $o->number }}</a></td>
      <td class="num">{{ $o->ordered_at?->format('d/m/Y') }}</td>
      <td><b>{{ $o->customer?->name ?? '—' }}</b><div class="small muted">{{ $o->customer?->city }}</div></td>
      <td class="mono small">{{ $o->external_ref ?? '—' }}</td>
      <td class="r">{{ $o->lines_count }}</td><td class="r">{{ $o->packages ?: '—' }}</td>
      <td>@if($o->wave_code)<span class="tag">{{ $o->wave_code }}</span>@endif</td>
      <td>{{ $o->assignee?->name ?? '—' }}</td>
      <td><x-pill :status="$o->priority" /></td>
      <td><x-pill :status="$o->status" /></td>
      <td class="tbl-actions"><a class="btn sm ghost" href="{{ route('outbound.show', $o) }}"><x-icon name="eye" /></a></td>
    </tr>
    @endforeach
  </tbody></table></div>
  <div class="tbl-foot"><span>{{ $orders->total() }} pedidos</span>{{ $orders->links('partials.pager') }}</div>
  @else<x-empty icon="out" text="Sin pedidos con ese filtro. Los pedidos llegan desde WorkCorp (Configuración → Integración) o se cargan por CSV." />@endif
</x-card>
</x-layouts.app>
