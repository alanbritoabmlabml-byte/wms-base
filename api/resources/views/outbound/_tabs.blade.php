<div class="tabs">
  @foreach([['board', 'outbound.board', 'Tablero'], ['orders', 'outbound.orders', 'Pedidos'], ['dispatches', 'outbound.dispatches', 'Despachos']] as [$k, $r, $l])
    <a class="{{ $tab === $k ? 'on' : '' }}" href="{{ route($r) }}">{{ $l }}</a>
  @endforeach
</div>
