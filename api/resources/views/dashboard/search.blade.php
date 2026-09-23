<x-layouts.app :title="$title">
<x-page-head title="Resultados de búsqueda" :sub="mb_strlen($q) >= 2 ? 'para «'.e($q).'» en '.$wh->name : 'Escribe al menos 2 caracteres'" />
@php $total = collect($results)->sum(fn($c) => $c->count()); @endphp
@if(mb_strlen($q) >= 2 && $total === 0)
  <x-card><x-empty text="Sin resultados. Prueba con el SKU completo, el código de ubicación o el número de documento." /></x-card>
@endif
<div class="grid g-2">
@foreach($results as $group => $rows)
  @if($rows->count())
  <x-card :title="$group" :flush="true" aside="<span class='badge-num'>{{ $rows->count() }}</span>">
    <div class="tbl-wrap"><table class="tbl"><tbody>
      @foreach($rows as $r)<tr class="clickable" onclick="location.href='{{ $r['u'] }}'"><td>{!! $r['t'] !!}</td><td class="tbl-actions"><a class="btn sm ghost" href="{{ $r['u'] }}"><x-icon name="chev" /></a></td></tr>@endforeach
    </tbody></table></div>
  </x-card>
  @endif
@endforeach
</div>
</x-layouts.app>
