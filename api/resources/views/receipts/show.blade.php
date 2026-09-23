<x-layouts.app :title="$title">
@php
  $u = fn($n) => \App\Support\Ui::qty($n);
  $exp = (float) $receipt->lines->sum('qty_expected'); $rec = (float) $receipt->lines->sum('qty_received');
  $step = ['ABIERTA' => 1, 'EN_PROCESO' => 2, 'CERRADA' => 3, 'ANULADA' => -1][$receipt->status] ?? 0;
  $pending = $receipt->lines->whereNotIn('status', ['COMPLETA', 'EXCEDIDA'])->count();
  $canManage = in_array($whRole, ['ADMIN', 'SUPERVISOR'], true);
@endphp
<x-page-head :title="'Orden '.$receipt->number" :sub="\App\Support\Ui::label($receipt->type).' · '.($receipt->supplier_name ?? 'sin origen').' · '.\App\Support\Ui::date($receipt->expected_at)" crumbs="<a href='{{ route('receipts.index') }}'>Ingresos</a> › {{ $receipt->number }}">
  @if($receipt->status === 'ABIERTA' && $rec == 0 && $canManage)
    <form method="post" action="{{ route('receipts.cancel', $receipt) }}" data-confirm="¿Anular la orden {{ $receipt->number }}? No tiene recepciones registradas.">@csrf<button class="btn">Anular</button></form>
  @endif
  <a class="btn" href="{{ route('config.labels', ['tpl' => 'ITEM', 'receipt' => $receipt->id]) }}"><x-icon name="print" /> Etiquetas</a>
  @if(in_array($receipt->status, \App\Models\Receipt::OPEN_STATUSES) && $canManage)
    <button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'close')"><x-icon name="check" /> {{ $pending ? 'Cerrar con diferencias' : 'Cerrar orden' }}</button>
  @endif
</x-page-head>

<div class="steps" style="margin-bottom:18px">
  @foreach(['Creada', 'Habilitada', 'En recepción', 'Cerrada'] as $i => $s)
    <div class="st {{ $step < 0 ? '' : ($i < $step ? 'done' : ($i === $step ? 'cur' : '')) }}">{{ $s }}</div>
  @endforeach
</div>
@if($receipt->status === 'ANULADA')<div class="alert bad">Orden anulada.</div>@endif

<div class="grid g-2-1">
  <div class="stack">
    <x-card title="Líneas" :flush="true" aside="<span class='muted small'>{{ $u($rec) }} / {{ $u($exp) }} unidades</span>">
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>#</th><th>SKU</th><th>Lote</th><th class="r">Esperado</th><th class="r">Recibido</th><th class="r">Dif.</th><th>Estado</th></tr></thead><tbody>
        @foreach($receipt->lines as $l)
        @php $d = (float) $l->qty_received - (float) $l->qty_expected; @endphp
        <tr>
          <td class="muted">{{ $l->line_no }}</td>
          <td><a class="mono link" href="{{ route('items.show', $l->item) }}">{{ $l->item->sku }}</a><div class="small muted">{{ $l->item->name }}</div></td>
          <td><span class="mono small">{{ $l->lot_code ?? '—' }}</span></td>
          <td class="r">{{ $u($l->qty_expected) }} <span class="muted small">{{ $l->item->baseUom->code }}</span></td>
          <td class="r"><b class="num" style="color:{{ $l->status === 'COMPLETA' ? 'var(--ok)' : ($l->qty_received > 0 ? 'var(--warn)' : 'var(--ink-3)') }}">{{ $u($l->qty_received) }}</b></td>
          <td class="r">@if(abs($d) > 0.00005)<span class="pill {{ $d < 0 ? 'warn' : 'bad' }} plain num">{{ $d > 0 ? '+' : '' }}{{ $u($d) }}</span>@else<span class="muted">—</span>@endif</td>
          <td><x-pill :status="$l->status" /></td>
        </tr>
        @endforeach
      </tbody></table></div>
    </x-card>
    <x-card title="Escaneos registrados" :flush="true">
      @php $scans = $receipt->lines->flatMap->scans->sortByDesc('occurred_at'); @endphp
      @if($scans->count())
      <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Fecha / hora</th><th>Línea</th><th>Ubicación</th><th class="r">Cantidad</th><th>Usuario</th></tr></thead><tbody>
        @foreach($scans->take(50) as $s)<tr><td class="num">{{ \App\Support\Ui::date($s->occurred_at, true) }}</td><td>{{ $s->receipt_line_id }}</td><td class="mono">{{ $s->location?->code }}</td><td class="r num">{{ $u($s->qty) }}</td><td>{{ $s->user?->name }}</td></tr>@endforeach
      </tbody></table></div>
      @else<x-empty icon="scan" text="Aún no hay escaneos del colector" />@endif
    </x-card>
  </div>
  <div class="stack">
    <x-card title="Datos">
      <dl class="kv">
        <dt>Estado</dt><dd><x-pill :status="$receipt->status" /></dd>
        <dt>Tipo</dt><dd>{{ \App\Support\Ui::label($receipt->type) }}</dd>
        <dt>Documento</dt><dd class="mono">{{ $receipt->external_ref ?? '—' }}</dd>
        <dt>Origen</dt><dd>{{ $receipt->supplier_name ?? '—' }}</dd>
        <dt>Esperada</dt><dd>{{ \App\Support\Ui::date($receipt->expected_at) }}</dd>
        @if($receipt->closed_at)<dt>Cerrada</dt><dd>{{ \App\Support\Ui::date($receipt->closed_at, true) }}</dd>@endif
      </dl>
    </x-card>
    <x-card title="Historial" :flush="true">
      <div class="card-b" style="padding:8px 16px">
        <ul class="timeline">
          @forelse($audit as $a)<li><span class="ic"><x-icon name="clock" /></span><div><b>{{ ucfirst(strtolower(str_replace('_', ' ', $a->action))) }}</b><span>{{ $a->user?->name ?? 'sistema' }} · {{ \App\Support\Ui::date($a->created_at, true) }}</span></div></li>@empty<li><span class="ic"><x-icon name="clock" /></span><div><b>Creada</b><span>{{ \App\Support\Ui::date($receipt->created_at, true) }}</span></div></li>@endforelse
        </ul>
      </div>
    </x-card>
  </div>
</div>

<x-modal id="close" title="Cerrar orden {{ $receipt->number }}" width="560px">
  <form method="post" action="{{ route('receipts.close', $receipt) }}">@csrf
    <div class="m-b">
      @if($pending)<div class="alert bad">Hay <b>{{ $pending }}</b> líneas sin completar. El cierre quedará registrado como <b>cierre forzado</b> a tu nombre.</div>@endif
      <x-field label="Motivo / observación" name="reason" type="textarea" :required="$pending > 0" placeholder="Ej.: faltante confirmado con producción, bultos dañados…" />
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Cerrar orden</button></div>
  </form>
</x-modal>
</x-layouts.app>
