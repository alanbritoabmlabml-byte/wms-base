<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n, 2); $canSup = in_array($whRole, ['ADMIN', 'SUPERVISOR']); $open = !in_array($order->status, ['DESPACHADO', 'ANULADO']); $pct = $totals['ordered'] > 0 ? round($totals['picked'] / $totals['ordered'] * 100) : 0; @endphp
<x-page-head :title="'Pedido '.$order->number" :crumbs="'<a href=\''.route('outbound.board').'\'>Pedidos y despacho</a> / '.$order->number" :sub="e($order->customer?->name ?? 'Sin cliente').' · '.($order->ordered_at?->format('d/m/Y') ?? '').($order->external_ref ? ' · Ref. ERP <span class=mono>'.e($order->external_ref).'</span>' : '')">
  <x-pill :status="$order->priority" /><x-pill :status="$order->status" />
  @if($open && $canSup)
  <button type="button" class="btn danger" x-data x-on:click="$dispatch('open-modal', 'cancelOrder')"><x-icon name="x" /> Anular</button>
  @endif
  @if($next && $open)
  <form method="post" action="{{ route('outbound.advance', $order) }}" class="inline-form" @if($next === 'DESPACHADO') data-confirm="Se descontará el stock de {{ $order->lines->count() }} líneas y el pedido quedará como despachado. ¿Continuar?" @endif>@csrf<input type="hidden" name="estado" value="{{ $next }}">
    @if($next === 'EMBALADO')<input type="hidden" name="packages" value="{{ max(1, $order->packages) }}">@endif
    <button class="btn primary"><x-icon name="arrow" /> Pasar a {{ $labels[$next] }}</button></form>
  @endif
</x-page-head>

<x-card :flush="true" style="margin-bottom:14px">
  <div class="steps">
    @foreach(\App\Models\SalesOrder::STATUSES as $i => $st)
    @php $idx = array_search($order->status, \App\Models\SalesOrder::STATUSES, true); @endphp
    <div class="st {{ $order->status === 'ANULADO' ? '' : ($i < $idx || $order->status === 'DESPACHADO' ? 'done' : ($i === $idx ? 'cur' : '')) }}">{{ $labels[$st] }}</div>
    @endforeach
  </div>
</x-card>

<div class="grid g-2-1">
  <div class="stack">
    <x-card :title="'Líneas · '.$order->lines->count()" :aside="'<span class=small>Avance de picking <b>'.$pct.'%</b></span>'" :flush="true">
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>#</th><th>Producto</th><th>Lote</th><th>Ubicación</th><th class="r">Pedido</th><th class="r">Reservado</th><th class="r">Pickeado</th><th>Estado</th></tr></thead><tbody>
        @foreach($order->lines->sortBy('line_no') as $l)
        @php $ls = (float) $l->qty_picked >= (float) $l->qty_ordered - 0.00005 ? 'COMPLETA' : ((float) $l->qty_picked > 0 ? 'PARCIAL' : ((float) $l->qty_allocated > 0 ? 'RESERVADA' : 'PENDIENTE')); @endphp
        <tr><td class="muted">{{ $l->line_no }}</td>
          <td><b class="mono">{{ $l->item?->sku }}</b><div class="small muted">{{ $l->item?->name }}</div></td>
          <td class="mono">{{ $l->lot && $l->lot->code !== '-' ? $l->lot->code : '—' }}</td>
          <td class="mono">{{ $l->fromLocation?->code ?? '—' }}</td>
          <td class="r">{{ $u($l->qty_ordered) }} <span class="small muted">{{ $l->item?->baseUom?->code }}</span></td>
          <td class="r">{{ $u($l->qty_allocated) }}</td><td class="r"><b>{{ $u($l->qty_picked) }}</b></td>
          <td><x-pill :status="$ls" :label="$ls === 'RESERVADA' ? 'Reservada' : null" /></td></tr>
        @endforeach
      </tbody></table></div>
      <div class="tbl-foot"><span>Total pedido {{ $u($totals['ordered']) }} · pickeado {{ $u($totals['picked']) }}</span>
        @if($whRole === 'ADMIN' || $canSup)<a class="btn sm" href="{{ route('config.labels', ['tpl' => 'DESPACHO', 'q' => $order->number]) }}"><x-icon name="qr" /> Etiquetas de bulto</a>@endif</div>
    </x-card>

    @if($order->dispatches->count())
    <x-card title="Despachos" :flush="true">
      <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Despacho</th><th>Camión</th><th>Chofer</th><th>Destino</th><th>Salida</th><th>Estado</th></tr></thead><tbody>
        @foreach($order->dispatches as $d)<tr><td class="mono"><a href="{{ route('outbound.dispatches') }}">{{ $d->number }}</a></td><td class="mono">{{ $d->vehicle?->plate }}</td><td>{{ $d->driver?->full_name }}</td><td>{{ $d->destination }}</td><td>{{ $d->departed_at?->format('d/m H:i') ?? '—' }}</td><td><x-pill :status="$d->status" /></td></tr>@endforeach
      </tbody></table></div>
    </x-card>
    @endif
  </div>

  <div class="stack">
    <x-card title="Asignación">
      <form method="post" action="{{ route('outbound.assign', $order) }}" class="stack">@csrf
        <x-field label="Operador de picking" name="assigned_to" type="select" :disabled="!$open"><option value="">— Sin asignar —</option>@foreach($pickers as $p)<option value="{{ $p->id }}" @selected($order->assigned_to == $p->id)>{{ $p->name }}</option>@endforeach</x-field>
        <div class="field"><label>Prioridad</label><div class="seg" style="display:flex">@foreach(['NORMAL' => 'Normal', 'URGENTE' => 'Urgente'] as $k => $l)<label class="{{ $order->priority === $k ? 'on' : '' }}" style="flex:1;text-align:center"><input type="radio" name="priority" value="{{ $k }}" @checked($order->priority === $k) hidden>{{ $l }}</label>@endforeach</div></div>
        @if($open)<button class="btn"><x-icon name="check" /> Guardar</button>@endif
      </form>
    </x-card>
    <x-card title="Datos">
      <dl class="kv">
        <dt>Cliente</dt><dd>{{ $order->customer?->name ?? '—' }}<div class="small muted" style="font-weight:400">{{ $order->customer?->address }}{{ $order->customer?->city ? ' · '.$order->customer->city : '' }}</div></dd>
        <dt>Ola</dt><dd>{{ $order->wave_code ?? '—' }}</dd>
        <dt>Bultos</dt><dd>{{ $order->packages ?: '—' }}</dd>
        <dt>Despachado</dt><dd>{{ $order->dispatched_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        <dt>Creado</dt><dd>{{ $order->created_at->format('d/m/Y H:i') }}</dd>
      </dl>
    </x-card>
    <x-card title="Historial">
      @if($audit->count())
      <ul class="timeline">
        @foreach($audit as $a)<li><span class="ic {{ str_starts_with($a->action, 'ANUL') ? 'bad' : (str_contains($a->action, 'DESPACHADO') ? 'ok' : '') }}"><x-icon name="clock" /></span><div><b>{{ \App\Support\Ui::label($a->action) }}</b><span>{{ $a->user?->name }} · {{ $a->created_at->format('d/m H:i') }}</span></div></li>@endforeach
      </ul>
      @else<span class="small muted">Sin eventos registrados.</span>@endif
    </x-card>
  </div>
</div>
@if($open && $canSup)
<x-modal id="cancelOrder" title="Anular pedido {{ $order->number }}" width="520px">
  <form method="post" action="{{ route('outbound.advance', $order) }}">@csrf<input type="hidden" name="estado" value="ANULADO">
    <div class="m-b stack"><p class="small muted" style="margin:0">Se liberará el stock reservado. El pedido queda en el historial como anulado.</p><x-field label="Motivo" name="reason" type="textarea" required placeholder="Ej. cliente canceló, error de carga en WorkCorp…" /></div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn danger"><x-icon name="x" /> Anular pedido</button></div>
  </form>
</x-modal>
@endif
@push('scripts')<script>document.addEventListener('change', e => { if (e.target.matches('.seg input[type=radio]')) e.target.closest('.seg').querySelectorAll('label').forEach(l => l.classList.toggle('on', l.querySelector('input').checked)); });</script>@endpush
</x-layouts.app>
