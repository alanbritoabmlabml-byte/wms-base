<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); $isAdmin = $whRole === 'ADMIN'; @endphp
<x-page-head title="Productos" sub="Los ítems se crean en WorkCorp y bajan al WMS; aquí se administran los parámetros logísticos por almacén.">
  @if($isAdmin)<a class="btn" href="{{ route('config.import', ['dataset' => 'items']) }}"><x-icon name="upload" /> Importar</a><a class="btn" href="{{ route('config.labels', ['tpl' => 'ITEM']) }}"><x-icon name="qr" /> Etiquetas</a>@endif
  <button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'newitem')"><x-icon name="plus" /> Nuevo producto</button>
</x-page-head>
<div class="grid g-4" style="margin-bottom:16px">
  <x-kpi label="SKU activos" :value="$kpis['active']" :delta="$kpis['inactive'].' inactivos'" />
  <x-kpi label="Sin clase ABC en este almacén" :value="$kpis['noParams']" delta="asignar en la ficha" />
  <x-kpi label="Con vencimiento" :value="$kpis['expiry']" delta="FEFO obligatorio" />
  <x-kpi label="Almacén" :value="$wh->code" :delta="$wh->name" />
</div>
<x-card :flush="true">
  <form class="toolbar" method="get">
    <div class="search"><x-icon name="search" /><input name="q" value="{{ $q }}" placeholder="Código, descripción, subcategoría"></div>
    <div class="chips">
      @foreach(['' => 'Todos', 'A' => 'Clase A', 'B' => 'Clase B', 'C' => 'Clase C'] as $k => $l)<a class="chip link {{ ($cls ?? '') === $k && !$inactive ? 'on' : '' }}" href="{{ route('items.index', ['clase' => $k ?: null, 'q' => $q]) }}">{{ $l }}</a>@endforeach
      <a class="chip link {{ $inactive ? 'on' : '' }}" href="{{ route('items.index', ['inactivos' => 1]) }}">Inactivos</a>
    </div>
    <div style="flex:1"></div><button class="btn sm">Buscar</button>
  </form>
  @if($items->count())
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Código</th><th>Descripción</th><th>UM</th><th>Clase</th><th class="r">Mín / Máx</th><th class="r">Stock</th><th class="r">Ubic.</th><th>Vida útil</th><th>Estado</th></tr></thead><tbody>
    @foreach($items as $i)@php $s = $settings[$i->id] ?? null; $st = $stock[$i->id] ?? null; @endphp
    <tr class="clickable" onclick="location.href='{{ route('items.show', $i) }}'">
      <td><span class="mono link">{{ $i->sku }}</span></td>
      <td><b>{{ $i->name }}</b><div class="small muted">{{ $i->category }}{{ $i->subcategory ? ' › '.$i->subcategory : '' }}{{ $i->factory_code ? ' · '.$i->factory_code : '' }}</div></td>
      <td>{{ $i->baseUom->code }}</td>
      <td>@if($s?->abc_class)<span class="tag {{ $s->abc_class === 'A' ? 'a' : '' }}">{{ $s->abc_class }}</span>@else<span class="muted">—</span>@endif</td>
      <td class="r num">{{ $u($s?->min_stock ?? $i->min_stock ?? 0) }} / {{ $u($s?->max_stock ?? $i->max_stock ?? 0) }}</td>
      <td class="r"><b class="num">{{ $u($st?->q ?? 0) }}</b></td>
      <td class="r">{{ $st?->locs ?? 0 }}</td>
      <td>{{ $i->shelf_life_days ? $i->shelf_life_days.' d' : '—' }}</td>
      <td><x-pill :status="$i->is_active ? 'ACTIVO' : 'INACTIVO'" /></td>
    </tr>
    @endforeach
  </tbody></table></div>
  <div class="tbl-foot"><span>{{ $items->total() }} productos</span>{{ $items->links('partials.pager') }}</div>
  @else<x-empty icon="box" text="Sin productos con ese filtro" />@endif
</x-card>

<x-modal id="newitem" title="Nuevo producto" width="720px">
  <form method="post" action="{{ route('items.store') }}">@csrf
    <div class="m-b">
      <div class="alert"><x-icon name="alert" /> Los ítems comerciales se crean en <b>WorkCorp</b> y bajan al WMS. Usa este formulario para ítems logísticos internos (embalajes, pallets, insumos de almacén) o para arrancar sin ERP.</div>
      <div class="grid g-2">
        <x-field label="Código (SKU)" name="sku" required mono />
        <x-field label="Descripción" name="name" required />
        <x-field label="Unidad base" name="base_uom_id" type="select" required>@foreach($uoms as $um)<option value="{{ $um->id }}">{{ $um->code }} · {{ $um->name }}</option>@endforeach</x-field>
        <x-field label="Código de fábrica" name="factory_code" mono />
        <x-field label="Categoría" name="category" />
        <x-field label="Subcategoría" name="subcategory" />
        <x-field label="Mínimo (global)" name="min_stock" type="number" step="0.01" />
        <x-field label="Máximo (global)" name="max_stock" type="number" step="0.01" />
        <x-field label="Vida útil (días)" name="shelf_life_days" type="number" />
        <x-field label="Peso por bulto (kg)" name="weight_kg" type="number" step="0.001" />
      </div>
      <div class="row wrap"><label class="row"><input type="checkbox" name="tracks_lot" value="1" checked> Maneja lote</label><label class="row"><input type="checkbox" name="tracks_expiry" value="1"> Maneja vencimiento (FEFO)</label></div>
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Guardar</button></div>
  </form>
</x-modal>
</x-layouts.app>
