<x-layouts.app :title="$title">
<x-page-head title="Nueva orden de ingreso" :sub="'Se crea habilitada para recepción en '.$wh->name.'. El colector la ve de inmediato.'" crumbs="<a href='{{ route('receipts.index') }}'>Ingresos</a> › Nueva" />
<form method="post" action="{{ route('receipts.store') }}" x-data="receiptForm({{ Js::from($items->map(fn($i) => ['id' => $i->id, 'sku' => $i->sku, 'name' => $i->name, 'lot' => (bool) $i->tracks_lot])) }}, {{ Js::from(old('lines', [['item_id' => '', 'qty_expected' => '', 'lot_code' => '']])) }})">
  @csrf
  <div class="grid g-2-1">
    <div class="stack">
      <x-card title="Datos de la orden">
        <div class="grid g-2">
          <x-field label="Número" name="number" :value="$nextNumber" required mono />
          <x-field label="Tipo de movimiento" name="type" type="select" required>
            @foreach(['PRODUCCION' => 'Producción', 'COMPRA' => 'Compra', 'DEVOLUCION' => 'Devolución', 'TRASPASO' => 'Transferencia'] as $k => $l)<option value="{{ $k }}" @selected(old('type', 'PRODUCCION') === $k)>{{ $l }}</option>@endforeach
          </x-field>
          <x-field label="Documento origen" name="external_ref" placeholder="OP-4471 / OC-982 / NDV-…" mono />
          <x-field label="Origen / proveedor" name="supplier_name" placeholder="Extrusora EX-02, Polimat Import…" />
          <x-field label="Fecha esperada" name="expected_at" type="date" :value="today()->toDateString()" />
        </div>
      </x-card>
      <x-card title="Líneas" :flush="true" aside='<button type="button" class="btn sm" x-on:click="add()">+ Agregar línea</button>'>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th style="width:45%">Producto</th><th>Lote</th><th class="r" style="width:140px">Cantidad</th><th></th></tr></thead><tbody>
          <template x-for="(l, i) in lines" :key="i">
            <tr>
              <td>
                <select class="select" style="min-height:36px;padding:4px 30px 4px 8px" :name="`lines[${i}][item_id]`" x-model="l.item_id" required>
                  <option value="">— elegir producto —</option>
                  <template x-for="it in items" :key="it.id"><option :value="it.id" x-text="it.sku + ' · ' + it.name"></option></template>
                </select>
              </td>
              <td><input class="input mono" style="min-height:36px;padding:4px 8px" :name="`lines[${i}][lot_code]`" x-model="l.lot_code" :placeholder="needsLot(l) ? 'obligatorio en colector' : 'opcional'"></td>
              <td><input class="input num" type="number" step="0.01" min="0.01" style="min-height:36px;padding:4px 8px;text-align:right" :name="`lines[${i}][qty_expected]`" x-model="l.qty_expected" required></td>
              <td class="tbl-actions"><button type="button" class="btn sm ghost icon" x-on:click="remove(i)" :disabled="lines.length === 1"><x-icon name="x" /></button></td>
            </tr>
          </template>
        </tbody></table></div>
        <div class="tbl-foot"><span x-text="lines.length + ' líneas · ' + total() + ' unidades esperadas'"></span></div>
      </x-card>
    </div>
    <div class="stack">
      <x-card title="Recepción">
        <dl class="kv">
          <dt>Almacén</dt><dd>{{ $wh->name }}</dd>
          <dt>Muelles</dt><dd>@forelse($docks as $d)<span class="tag mono">{{ $d->code }}</span> @empty<span class="muted">sin zona de recepción</span>@endforelse</dd>
          <dt>Modalidad</dt><dd>{{ \App\Models\Setting::get('reglas', 'recepcion_ciega_produccion', false, $wh->id) ? 'Ciega' : 'Con cantidad esperada' }}</dd>
        </dl>
        <p class="small muted">Recibir deja la mercadería en el muelle; ubicarla en rack es una tarea separada que el colector propone según consolidación y clase de rotación.</p>
      </x-card>
      <div class="stack">
        <button class="btn primary block" type="submit"><x-icon name="check" /> Registrar y habilitar</button>
        <a class="btn block" href="{{ route('receipts.index') }}">Cancelar</a>
      </div>
    </div>
  </div>
</form>
@push('scripts')
<script>
function receiptForm(items, initial) {
  return {
    items, lines: initial.length ? initial : [{ item_id: '', qty_expected: '', lot_code: '' }],
    add() { this.lines.push({ item_id: '', qty_expected: '', lot_code: '' }); },
    remove(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
    needsLot(l) { const it = this.items.find(x => String(x.id) === String(l.item_id)); return it && it.lot; },
    total() { return this.lines.reduce((a, l) => a + (parseFloat(l.qty_expected) || 0), 0).toLocaleString('es-BO'); },
  };
}
</script>
@endpush
</x-layouts.app>
