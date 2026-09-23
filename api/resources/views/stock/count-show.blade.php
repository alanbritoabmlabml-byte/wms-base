<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); $canManage = in_array($whRole, ['ADMIN', 'SUPERVISOR'], true); $diffs = $count->lines->whereIn('status', ['DIFERENCIA', 'RECONTAR']); @endphp
<x-page-head :title="'Conteo '.$count->number.' · '.\App\Models\CycleCount::TYPE_LABELS[$count->type]" :sub="($count->blind ? 'Ciego · ' : '').'abierto '.\App\Support\Ui::date($count->opened_at, true).' · responsable '.($count->responsible?->name ?? '—')" crumbs="<a href='{{ route('stock.counts') }}'>Inventario cíclico</a> › Conteo {{ $count->number }}">
  @if($count->status !== 'FINALIZADO' && $canManage)
    <form method="post" action="{{ route('stock.counts.close', $count) }}" data-confirm="Se aplicarán {{ $diffs->count() }} ajustes al kardex con tu aprobación y el conteo quedará finalizado. ¿Continuar?">@csrf<button class="btn primary"><x-icon name="check" /> Aprobar diferencias y finalizar</button></form>
  @endif
</x-page-head>
<div class="grid g-4" style="margin-bottom:16px">
  <x-kpi label="Avance" :value="$progress['total'] ? round($progress['counted'] / $progress['total'] * 100) : 0" unit="%" :delta="$progress['counted'].' de '.$progress['total'].' líneas'" />
  <x-kpi label="Diferencias" :value="$progress['diff']" delta="pendientes de aprobar" />
  <x-kpi label="IRA parcial" :value="$progress['ira'] !== null ? number_format($progress['ira'], 1, ',', '.') : '—'" :unit="$progress['ira'] !== null ? '%' : null" delta="meta 98 %" />
  <x-kpi label="Estado" :value="\App\Support\Ui::label($count->status)" :delta="$count->closed_at ? 'cerrado '.\App\Support\Ui::date($count->closed_at, true) : ($count->blocks_picking ? 'picking bloqueado en el alcance' : 'picking permitido')" />
</div>
<x-card title="Líneas" :flush="true" aside="<span class='muted small'>ordenadas por recorrido</span>">
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ubicación</th><th>SKU</th><th>Lote</th><th class="r">Sistema</th><th class="r">Contado</th><th class="r">Dif.</th><th>Estado</th><th>Contó</th></tr></thead><tbody>
    @foreach($count->lines->sortBy(fn($l) => [$l->status === 'PENDIENTE' ? 1 : 0, $l->location->sort_seq]) as $l)
    @php $d = $l->qty_counted !== null ? (float) $l->qty_counted - (float) $l->qty_system : null; @endphp
    <tr>
      <td class="mono">{{ $l->location->code }}</td>
      <td>@if($l->item)<span class="mono">{{ $l->item->sku }}</span><div class="small muted">{{ $l->item->name }}</div>@else<span class="muted">ubicación vacía</span>@endif</td>
      <td class="mono small">{{ $l->lot?->code === '-' ? '—' : ($l->lot?->code ?? '—') }}</td>
      <td class="r num">{{ $count->blind && $count->status !== 'FINALIZADO' && $l->status === 'PENDIENTE' ? '·' : $u($l->qty_system) }}</td>
      <td class="r"><b class="num">{{ $l->qty_counted !== null ? $u($l->qty_counted) : '—' }}</b></td>
      <td class="r">@if($d !== null && abs($d) > 0.00005)<span class="pill {{ $d < 0 ? 'bad' : 'warn' }} plain num">{{ $d > 0 ? '+' : '' }}{{ $u($d) }}</span>@elseif($d !== null)<span class="pill ok plain">OK</span>@else<span class="muted">—</span>@endif</td>
      <td><x-pill :status="$l->status" /></td>
      <td class="small">{{ $l->counter?->name }} {{ $l->counted_at ? '· '.\App\Support\Ui::date($l->counted_at, true) : '' }}</td>
    </tr>
    @endforeach
  </tbody></table></div>
</x-card>
</x-layouts.app>
