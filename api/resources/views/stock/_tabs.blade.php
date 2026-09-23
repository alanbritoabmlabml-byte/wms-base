<div class="tabs">
  @foreach([['balances', 'stock.balances', 'Stock por ubicación'], ['kardex', 'stock.kardex', 'Kardex'], ['counts', 'stock.counts', 'Inventario cíclico'], ['adjustments', 'stock.adjustments', 'Ajustes'], ['abc', 'stock.abc', 'Rotación ABC'], ['minmax', 'stock.minmax', 'Máximos y mínimos']] as [$k, $r, $l])
    <a class="{{ $tab === $k ? 'on' : '' }}" href="{{ route($r) }}">{{ $l }}</a>
  @endforeach
</div>
