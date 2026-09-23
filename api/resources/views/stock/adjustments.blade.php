<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); $canApprove = in_array($whRole, ['ADMIN', 'SUPERVISOR'], true); @endphp
<x-page-head title="Stock e inventario" sub="Los ajustes pasan por el ledger con motivo obligatorio; los que exigen aprobación esperan a un encargado." />
<x-card :flush="true">
  @include('stock._tabs')
  <div class="card-b">
    <div class="grid g-1-2">
      <div class="stack">
        <x-card title="Nuevo ajuste" style="box-shadow:none">
          <form method="get" class="row" style="margin-bottom:12px">
            <input class="input mono" name="ubicacion" value="{{ $loc }}" placeholder="Escanea o escribe la ubicación · E1-C03-N2" style="flex:1">
            <button class="btn">Buscar</button>
          </form>
          @if($loc !== '' && !$location)<div class="alert bad">No existe la ubicación «{{ $loc }}» en {{ $wh->name }}.</div>@endif
          @if($location)
            <div class="small muted" style="margin-bottom:8px">Saldos en <b class="mono">{{ $location->code }}</b> · {{ $location->zone?->name }}</div>
            @forelse($balances as $b)
              <div class="cline" style="margin-bottom:8px">
                <div><b class="mono small">{{ $b->item->sku }}</b><span>{{ $b->item->name }}<br>lote {{ $b->lot->code }} · {{ \App\Support\Ui::label($b->status) }}</span></div>
                <div class="row"><span class="q">{{ $u($b->qty) }}</span>
                  <button type="button" class="btn sm primary" x-data x-on:click="$dispatch('open-adjust', {location_id: {{ $location->id }}, item_id: {{ $b->item_id }}, lot_id: {{ $b->lot_id }}, sku: '{{ $b->item->sku }}', loc: '{{ $location->code }}', lot: '{{ $b->lot->code }}', qty: {{ (float) $b->qty }} }); $dispatch('open-modal', 'adjust')">Ajustar</button></div>
              </div>
            @empty<x-empty icon="box" text="La ubicación está vacía" />@endforelse
            <button type="button" class="btn block" x-data x-on:click="$dispatch('open-adjust', {location_id: {{ $location->id }}, item_id: '', lot_id: '', sku: '', loc: '{{ $location->code }}', lot: '', qty: 0}); $dispatch('open-modal', 'adjust')"><x-icon name="plus" /> Ajuste positivo de un producto nuevo en esta ubicación</button>
          @endif
          <p class="small muted" style="margin:12px 0 0"><x-icon name="lock" /> Requiere aprobación de un <b>Encargado</b> cuando el motivo lo exige. El movimiento queda en el kardex con usuario, equipo y hora.</p>
        </x-card>
      </div>
      <div class="stack">
        <x-card title="Pendientes de aprobación" :flush="true" aside="<span class='pill {{ $pending->count() ? 'warn' : 'ok' }}'>{{ $pending->count() }}</span>">
          @if($pending->count())
          <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Fecha</th><th>Ubicación</th><th>SKU</th><th class="r">Ajuste</th><th>Motivo</th><th>Solicita</th><th></th></tr></thead><tbody>
            @foreach($pending as $a)
            <tr>
              <td class="small num">{{ \App\Support\Ui::date($a->created_at, true) }}</td>
              <td class="mono">{{ $a->location->code }}</td>
              <td><span class="mono">{{ $a->item->sku }}</span><div class="small muted">lote {{ $a->lot?->code }}</div></td>
              <td class="r"><b class="num" style="color:{{ $a->signedQty() < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $a->signedQty() > 0 ? '+' : '' }}{{ $u($a->signedQty()) }}</b></td>
              <td>{{ $a->reasonCode?->name }}<div class="small muted">{{ $a->note }}</div></td>
              <td>{{ $a->requester->name }}</td>
              <td class="tbl-actions">
                @if($canApprove)
                <form method="post" action="{{ route('stock.adjustments.resolve', $a) }}" class="inline-form">@csrf<input type="hidden" name="accion" value="rechazar"><button class="btn sm">Rechazar</button></form>
                <form method="post" action="{{ route('stock.adjustments.resolve', $a) }}" class="inline-form">@csrf<input type="hidden" name="accion" value="aprobar"><button class="btn sm primary">Aprobar</button></form>
                @else<span class="muted small">espera encargado</span>@endif
              </td>
            </tr>
            @endforeach
          </tbody></table></div>
          @else<x-empty icon="check" text="Nada pendiente" />@endif
        </x-card>
        <x-card title="Últimos ajustes resueltos" :flush="true">
          @if($history->count())
          <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Resuelto</th><th>Ubicación</th><th>SKU</th><th class="r">Ajuste</th><th>Estado</th><th>Aprobó</th></tr></thead><tbody>
            @foreach($history as $a)<tr><td class="num">{{ \App\Support\Ui::date($a->resolved_at, true) }}</td><td class="mono">{{ $a->location->code }}</td><td class="mono">{{ $a->item->sku }}</td><td class="r num">{{ $a->signedQty() > 0 ? '+' : '' }}{{ $u($a->signedQty()) }}</td><td><x-pill :status="$a->status" /></td><td>{{ $a->approver?->name }}</td></tr>@endforeach
          </tbody></table></div>
          @else<x-empty icon="history" text="Sin historial" />@endif
        </x-card>
      </div>
    </div>
  </div>
</x-card>

<x-modal id="adjust" title="Ajuste de stock" width="620px">
  <form method="post" action="{{ route('stock.adjustments.store') }}" x-data="{ f: {location_id:'', item_id:'', lot_id:'', sku:'', loc:'', lot:'', qty:0}, type: 'AJUSTE_NEG' }" x-on:open-adjust.window="f = $event.detail; type = f.item_id ? 'AJUSTE_NEG' : 'AJUSTE_POS'">
    @csrf
    <div class="m-b">
      <input type="hidden" name="location_id" :value="f.location_id">
      <input type="hidden" name="lot_id" :value="f.lot_id">
      <div class="cline" style="margin-bottom:14px"><div><span class="small muted">Ubicación</span><b class="mono" x-text="f.loc"></b></div><div style="text-align:right"><span class="small muted" x-show="f.sku">Saldo actual</span><b class="num" x-text="f.sku ? f.qty.toLocaleString('es-BO') + ' · ' + f.sku + ' · lote ' + f.lot : ''"></b></div></div>
      <template x-if="!f.item_id">
        <div class="grid g-2">
          <div class="field"><label>Producto</label><select class="select" name="item_id" required><option value="">— elegir —</option>@foreach($items as $i)<option value="{{ $i->id }}">{{ $i->sku }} · {{ $i->name }}</option>@endforeach</select></div>
          <div class="field"><label>Lote</label><input class="input mono" name="lot_code" placeholder="L263502-1 (vacío si no maneja lote)"></div>
        </div>
      </template>
      <template x-if="f.item_id"><input type="hidden" name="item_id" :value="f.item_id"></template>
      <div class="grid g-2">
        <div class="field"><label>Tipo</label>
          <select class="select" name="type" x-model="type">
            <option value="AJUSTE_POS">Ajuste + (sobrante)</option>
            <option value="AJUSTE_NEG" :disabled="!f.item_id">Ajuste − (faltante)</option>
            <option value="MERMA" :disabled="!f.item_id">Baja por merma</option>
          </select>
        </div>
        <div class="field"><label>Cantidad</label><input class="input num" type="number" step="0.01" min="0.01" name="qty" required></div>
      </div>
      <div class="field"><label>Motivo <span style="color:var(--red)">*</span></label>
        <select class="select" name="reason_code_id" required>
          @foreach($reasons as $r)<option value="{{ $r->id }}" data-type="{{ $r->movement_type }}">{{ $r->name }}{{ $r->requires_approval ? ' · requiere aprobación' : '' }}</option>@endforeach
        </select>
      </div>
      <div class="field"><label>Observación</label><textarea class="input" name="note" rows="2"></textarea></div>
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">{{ $canApprove ? 'Registrar ajuste' : 'Enviar a aprobación' }}</button></div>
  </form>
</x-modal>
</x-layouts.app>
