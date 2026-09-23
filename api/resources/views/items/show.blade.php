<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); $total = (float) $balances->sum('qty'); $alloc = (float) $balances->sum('qty_allocated'); $daily = $out30 / 30; $canEdit = in_array($whRole, ['ADMIN', 'SUPERVISOR'], true); @endphp
<x-page-head :title="$item->name" :sub="'<span class=\'mono\'>'.e($item->sku).'</span> · '.e($item->category).($item->subcategory ? ' › '.e($item->subcategory) : '').' · '.e($item->baseUom->code)" crumbs="<a href='{{ route('items.index') }}'>Productos</a> › {{ $item->sku }}">
  <a class="btn" href="{{ route('config.labels', ['tpl' => 'ITEM', 'item' => $item->id]) }}"><x-icon name="qr" /> Etiqueta</a>
  <a class="btn" href="{{ route('stock.kardex', ['q' => $item->sku, 'desde' => now()->subDays(90)->toDateString()]) }}"><x-icon name="history" /> Kardex</a>
</x-page-head>
<div class="grid g-4" style="margin-bottom:16px">
  <x-kpi label="Stock en {{ $wh->code }}" :value="$u($total)" :unit="$item->baseUom->code" :delta="$balances->count().' ubicaciones'" />
  <x-kpi label="Reservado" :value="$u($alloc)" delta="en olas activas" />
  <x-kpi label="Salidas 30 días" :value="$u($out30)" :delta="$daily > 0 ? number_format($daily, 1, ',', '.').' por día' : 'sin salidas'" />
  <x-kpi label="Cobertura" :value="$daily > 0 ? (int) floor($total / $daily) : '—'" :unit="$daily > 0 ? 'días' : null" :delta="($setting?->min_stock ?? $item->min_stock) && $total < (float) ($setting?->min_stock ?? $item->min_stock) ? 'bajo mínimo' : 'en rango'" :delta-kind="($setting?->min_stock ?? $item->min_stock) && $total < (float) ($setting?->min_stock ?? $item->min_stock) ? 'down' : ''" />
</div>
<div class="grid g-2-1">
  <div class="stack">
    <x-card title="Saldos por ubicación" :flush="true">
      @if($balances->count())
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Ubicación</th><th>Zona</th><th>Lote</th><th>Vence</th><th class="r">Cant.</th><th class="r">Reserv.</th><th>Estado</th></tr></thead><tbody>
        @foreach($balances as $b)<tr><td><a class="mono link" href="{{ route('map.index', ['zona' => $b->location->zone_id, 'ubicacion' => $b->location_id]) }}">{{ $b->location->code }}</a></td><td>{{ $b->location->zone?->name }}</td><td class="mono small">{{ $b->lot->code === '-' ? '—' : $b->lot->code }}</td><td>{{ \App\Support\Ui::date($b->lot->expires_at) }}</td><td class="r"><b class="num">{{ $u($b->qty) }}</b></td><td class="r num">{{ $u($b->qty_allocated) }}</td><td><x-pill :status="$b->status" /></td></tr>@endforeach
      </tbody></table></div>
      @else<x-empty icon="box" text="Sin stock en este almacén" />@endif
    </x-card>
    <x-card title="Últimos movimientos" :flush="true">
      @if($movements->count())
      <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Fecha</th><th>Mov.</th><th>Desde</th><th>Hacia</th><th class="r">Cant.</th><th>Usuario</th></tr></thead><tbody>
        @foreach($movements as $m)@php $out = in_array($m->type, \App\Models\StockMovement::OUTBOUND_TYPES); @endphp<tr><td class="num">{{ \App\Support\Ui::date($m->occurred_at, true) }}</td><td><span class="pill {{ $out ? 'bad' : (in_array($m->type, \App\Models\StockMovement::INBOUND_TYPES) ? 'ok' : 'info') }} plain">{{ $m->type }}</span></td><td class="mono">{{ $m->fromLocation?->code ?? '—' }}</td><td class="mono">{{ $m->toLocation?->code ?? '—' }}</td><td class="r"><b class="num">{{ $out ? '−' : '+' }}{{ $u($m->qty) }}</b></td><td>{{ $m->user?->name }}</td></tr>@endforeach
      </tbody></table></div>
      @else<x-empty icon="history" text="Sin movimientos" />@endif
    </x-card>
  </div>
  <div class="stack">
    <x-card title="Ficha y parámetros" aside="<span class='muted small'>{{ $wh->code }}</span>">
      <form method="post" action="{{ route('items.update', $item) }}">@csrf @method('PUT')
        <fieldset style="border:none;padding:0;margin:0" @disabled(!$canEdit)>
        <x-field label="Descripción" name="name" :value="$item->name" required />
        <div class="grid g-2">
          <x-field label="Categoría" name="category" :value="$item->category" />
          <x-field label="Subcategoría" name="subcategory" :value="$item->subcategory" />
          <x-field label="Cód. fábrica" name="factory_code" :value="$item->factory_code" mono />
          <x-field label="Vida útil (días)" name="shelf_life_days" type="number" :value="$item->shelf_life_days" />
          <x-field label="Peso bulto (kg)" name="weight_kg" type="number" step="0.001" :value="$item->weight_kg" />
          <x-field label="Precio ref." name="price" type="number" step="0.01" :value="$item->price" />
          <x-field label="Mín. global" name="min_stock" type="number" step="0.01" :value="$item->min_stock" />
          <x-field label="Máx. global" name="max_stock" type="number" step="0.01" :value="$item->max_stock" />
        </div>
        <div class="row wrap" style="margin-bottom:12px"><label class="row"><input type="checkbox" name="tracks_lot" value="1" @checked($item->tracks_lot)> Lote</label><label class="row"><input type="checkbox" name="tracks_expiry" value="1" @checked($item->tracks_expiry)> Vencimiento (FEFO)</label><label class="row"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Activo</label></div>
        <hr class="sep">
        <h4 style="font-size:13px;margin-bottom:8px">Parámetros en {{ $wh->name }}</h4>
        <div class="grid g-2">
          <x-field label="Clase ABC" name="abc_class" type="select"><option value="">—</option>@foreach(['A', 'B', 'C'] as $c)<option value="{{ $c }}" @selected($setting?->abc_class === $c)>{{ $c }}</option>@endforeach</x-field>
          <x-field label="Ubicación fija" name="default_location_id" type="select"><option value="">— ninguna —</option>@foreach($locations as $l)<option value="{{ $l->id }}" @selected($setting?->default_location_id === $l->id)>{{ $l->code }}</option>@endforeach</x-field>
          <x-field label="Mínimo" name="wh_min" type="number" step="0.01" :value="$setting?->min_stock" />
          <x-field label="Máximo" name="wh_max" type="number" step="0.01" :value="$setting?->max_stock" />
        </div>
        @if($canEdit)<button class="btn primary block"><x-icon name="check" /> Guardar parámetros</button>@else<p class="small muted">Solo encargados y administradores editan parámetros.</p>@endif
        </fieldset>
      </form>
    </x-card>
    <x-card title="Códigos de barras" :flush="true">
      <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Código</th><th>Tipo</th><th>UM</th><th class="r">× escaneo</th></tr></thead><tbody>
        @foreach($item->barcodes as $bc)<tr><td class="mono">{{ $bc->barcode }}</td><td>{{ $bc->type }}</td><td>{{ $bc->uom->code }}</td><td class="r num">{{ $u($bc->qty_per_scan) }}</td></tr>@endforeach
      </tbody></table></div>
    </x-card>
  </div>
</div>
</x-layouts.app>
