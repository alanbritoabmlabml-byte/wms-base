<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); @endphp
<x-page-head title="Stock e inventario" sub="La clase se calcula sobre salidas de 90 días. Cambios de clase sugieren reubicación: clase A cerca de picking, clase C al fondo." />
<x-card :flush="true">
  @include('stock._tabs')
  <div class="card-b">
    <div class="grid g-3" style="margin-bottom:16px">
      @foreach($groups as $g)
      <x-kpi :label="'Clase '.$g['cls'].' · '.['A' => 'alta rotación, cerca de picking', 'B' => 'rotación media', 'C' => 'baja rotación, fondo de nave'][$g['cls']]" :value="$g['n']" unit="SKU" :delta="round($g['u'] / $totalUnits * 100).' % de las unidades en stock'" style="box-shadow:none" />
      @endforeach
    </div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>SKU</th><th>Clase</th><th class="r">Salidas 90 d</th><th class="r">Stock</th><th>Zona actual</th><th>Sugerencia</th></tr></thead><tbody>
      @foreach($items as $r)
      <tr>
        <td><a class="mono link" href="{{ route('items.show', $r['item']) }}">{{ $r['item']->sku }}</a><div class="small muted">{{ $r['item']->name }}</div></td>
        <td>@if($r['cls'])<span class="tag {{ $r['cls'] === 'A' ? 'a' : '' }}">{{ $r['cls'] }}</span>@else<span class="muted small">sin clase</span>@endif</td>
        <td class="r num">{{ $u($r['out']) }}</td>
        <td class="r num">{{ $u($r['stock']) }}</td>
        <td>{{ $r['zone'] ?? '—' }} @if($r['misplaced'])<span class="pill warn plain">reubicar</span>@endif</td>
        <td class="small">{{ ['A' => 'zona de picking', 'B' => 'reserva', 'C' => 'volumen / fondo de nave'][$r['cls']] ?? 'asignar clase en la ficha del producto' }}</td>
      </tr>
      @endforeach
    </tbody></table></div>
  </div>
</x-card>
</x-layouts.app>
