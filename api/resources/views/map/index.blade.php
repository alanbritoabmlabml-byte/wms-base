<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); $canManage = in_array($whRole, ['ADMIN', 'SUPERVISOR'], true); $typeLabels = ['ALMACENAJE' => 'Almacenaje', 'PICKING' => 'Picking', 'RECEPCION' => 'Recepción', 'DESPACHO' => 'Despacho', 'CUARENTENA' => 'Cuarentena', 'DEVOLUCION' => 'Devolución']; @endphp
<x-page-head title="Mapa de almacén" :sub="$wh->name.' · sucursal → almacén → rack/área → ubicación. Las ubicaciones se crean aquí y nunca se reutilizan.'">
  @if($zone)<a class="btn" href="{{ route('config.labels', ['tpl' => 'UBICACION', 'zona' => $zone->id]) }}"><x-icon name="print" /> Etiquetas del rack</a>@endif
  @if($canManage)
    <button class="btn" x-data x-on:click="$dispatch('open-modal', 'rack')"><x-icon name="plus" /> Nuevo rack / área</button>
    <button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'loc')"><x-icon name="plus" /> Nueva ubicación</button>
  @endif
</x-page-head>

<div class="grid" style="grid-template-columns:260px minmax(0,1fr) 340px">
  <x-card title="Topología" :flush="true">
    <div class="card-b" style="padding:8px">
      <ul class="tree">
        <li><div class="node"><x-icon name="layers" /><b>{{ $wh->branch?->name }} · {{ $wh->name }}</b></div>
          <ul>
            @foreach($zones->groupBy('type') as $t => $zs)
              <li><div class="node muted small" style="text-transform:uppercase;letter-spacing:.06em">{{ $typeLabels[$t] ?? $t }}</div>
                <ul>@foreach($zs as $z)<li><a class="node {{ $zone && $z->id === $zone->id ? 'on' : '' }}" href="{{ route('map.index', ['zona' => $z->id]) }}" style="text-decoration:none;color:inherit"><x-icon name="grid" /><span>{{ $z->code }} · {{ $z->name }}</span><span class="cnt">{{ $z->locations_count }}</span></a></li>@endforeach</ul>
              </li>
            @endforeach
            @if($zones->isEmpty())<li><div class="node muted">Sin racks. Crea el primero.</div></li>@endif
          </ul>
        </li>
      </ul>
    </div>
  </x-card>

  <x-card>
    @if($zone)
    <div class="row between wrap" style="margin-bottom:12px">
      <div><h3 style="font-size:15px">{{ $zone->code }} · {{ $zone->name }}</h3><span class="muted small">{{ $typeLabels[$zone->type] }} · {{ $levels->count() ?: '—' }} niveles × {{ $positions->count() ?: '—' }} columnas · {{ $occupied }}/{{ $locations->count() }} ocupadas</span></div>
      <div class="legend"><span><i style="background:var(--heat-1);border:1px solid var(--line)"></i>vacía</span><span><i style="background:var(--heat-3)"></i>parcial</span><span><i style="background:var(--heat-5)"></i>llena</span><span><i style="background:repeating-linear-gradient(45deg,var(--bad-soft),var(--bad-soft) 3px,transparent 3px,transparent 6px);border:1px solid var(--bad)"></i>bloqueada</span></div>
    </div>
    @if($grid)
      <div class="row" style="align-items:stretch;gap:8px">
        <div class="stack" style="justify-content:space-around;gap:0;font-size:11px;color:var(--ink-3);text-align:right;padding:0 2px">@foreach($levels as $lv)<span>N{{ $lv }}</span>@endforeach</div>
        <div class="rackmap" style="grid-template-columns:repeat({{ $positions->count() }},minmax(0,1fr));flex:1">
          @foreach($levels as $lv)@foreach($positions as $pos)
            @php $l = $locations->first(fn($x) => $x->level === $lv && $x->position === $pos); $ratio = $l ? min(1, (float) ($l->qty ?? 0) / max($capacity, 1)) : 0; @endphp
            @if($l)<div class="cell {{ \App\Support\Ui::heat($ratio) }} {{ !$l->is_active ? 'blocked' : '' }} {{ $selected && $selected->id === $l->id ? 'sel' : '' }}" title="{{ $l->code }} · {{ $u($l->qty ?? 0) }} un."><a href="{{ route('map.index', ['zona' => $zone->id, 'ubicacion' => $l->id]) }}" aria-label="{{ $l->code }}"></a></div>@else<div class="cell" style="visibility:hidden"></div>@endif
          @endforeach@endforeach
        </div>
      </div>
      <div class="row" style="gap:4px;margin-top:6px;padding-left:28px;font-size:11px;color:var(--ink-3)">@foreach($positions as $pos)<span style="flex:1;text-align:center">C{{ $pos }}</span>@endforeach</div>
    @else
      <div class="chips">@foreach($locations as $l)<a class="chip link {{ $selected && $selected->id === $l->id ? 'on' : '' }} mono" href="{{ route('map.index', ['zona' => $zone->id, 'ubicacion' => $l->id]) }}" style="{{ !$l->is_active ? 'text-decoration:line-through' : '' }}">{{ $l->code }} · {{ $u($l->qty ?? 0) }}</a>@endforeach</div>
      @if($locations->isEmpty())<x-empty icon="map" text="Esta zona no tiene ubicaciones" />@endif
    @endif
    <hr class="sep">
    <div class="grid g-3 small"><div><div class="muted">Formato de código</div><b class="mono">{{ $format }}</b></div><div><div class="muted">Capacidad por ubicación</div><b>{{ $u($capacity / 20) }} pallets/bultos</b></div><div><div class="muted">Orden de recorrido</div><b>Serpentina por columna</b> <span class="muted">(sort_seq)</span></div></div>
    @else<x-empty icon="map" text="Crea el primer rack o área para empezar a etiquetar" />@endif
  </x-card>

  <x-card :title="$selected ? '<span class=\'mono\'>'.e($selected->code).'</span>' : 'Ubicación'" :aside="$selected ? \App\Support\Ui::pill(!$selected->is_active ? 'BLOQUEADA' : ($selectedStock->count() ? 'OCUPADA' : 'VACIA'), !$selected->is_active ? 'Bloqueada' : ($selectedStock->count() ? 'Ocupada' : 'Vacía')) : null">
    @if($selected)
      <dl class="kv small" style="margin-bottom:12px"><dt>Rack</dt><dd>{{ $zone->code }} · {{ $zone->name }}</dd><dt>Columna / nivel</dt><dd>C{{ $selected->position ?? '—' }} · N{{ $selected->level ?? '—' }}</dd><dt>Código de barras</dt><dd class="mono">{{ $selected->barcode }}</dd><dt>Recorrido</dt><dd class="num">#{{ $selected->sort_seq }}</dd><dt>Mezcla de lotes</dt><dd>{{ $selected->is_mixing_allowed ? 'permitida' : 'no' }}</dd></dl>
      @if($selectedStock->count())
        <div class="stack">@foreach($selectedStock as $s)<div class="cline"><div><b class="mono small">{{ $s->item->sku }}</b><span>{{ $s->item->name }}<br>lote {{ $s->lot->code }} · {{ \App\Support\Ui::date($s->last_movement_at) }}</span></div><span class="q">{{ $u($s->qty) }}</span></div>@endforeach</div>
      @else<x-empty icon="box" text="Sin stock en esta ubicación" />@endif
      <div class="stack" style="margin-top:12px">
        <a class="btn block" href="{{ route('config.labels', ['tpl' => 'UBICACION', 'ubicacion' => $selected->id]) }}"><x-icon name="print" /> Imprimir etiqueta QR</a>
        @if($canManage)<form method="post" action="{{ route('map.locations.toggle', $selected) }}">@csrf<button class="btn block"><x-icon name="lock" /> {{ $selected->is_active ? 'Bloquear ubicación' : 'Desbloquear' }}</button></form>@endif
        <a class="btn block" href="{{ route('stock.adjustments', ['ubicacion' => $selected->code]) }}"><x-icon name="edit" /> Ajustar stock</a>
      </div>
    @else<x-empty icon="map" text="Selecciona una celda del rack para ver su contenido" />@endif
  </x-card>
</div>

@if($canManage)
<x-modal id="rack" title="Nuevo rack / área" width="720px">
  <form method="post" action="{{ route('map.racks.store') }}" x-data="{ l: 4, c: 10, code: 'E{{ $zones->count() + 1 }}' }">@csrf
    <div class="m-b">
      <div class="grid g-2">
        <x-field label="Código" name="code" required mono x-model="code" />
        <x-field label="Descripción" name="name" required placeholder="Estante 7" />
        <x-field label="Tipo de zona" name="type" type="select" required>@foreach($typeLabels as $k => $l)<option value="{{ $k }}" @selected($k === 'ALMACENAJE')>{{ $l }}</option>@endforeach</x-field>
        <x-field label="Prioridad de picking" name="picking_priority" type="number" value="100" hint="menor = se propone antes" />
        <div class="field"><label>Niveles (filas)</label><input class="input num" type="number" name="levels" min="1" max="20" x-model.number="l"></div>
        <div class="field"><label>Columnas</label><input class="input num" type="number" name="positions" min="1" max="60" x-model.number="c"></div>
      </div>
      <label class="row"><input type="checkbox" name="mixing" value="1" checked> Permitir mezclar lotes en las ubicaciones</label>
      <p class="small muted" style="margin-top:12px">Se generarán <b x-text="l * c"></b> ubicaciones con formato <span class="mono">{{ $format }}</span> (ej. <span class="mono" x-text="'{{ $format }}'.replace('{RACK}', code.toUpperCase()).replace(/\{COL(?::(\d+))?\}/, (m, w) => String(1).padStart(w || 1, '0')).replace(/\{NIVEL(?::(\d+))?\}/, (m, w) => String(1).padStart(w || 1, '0'))"></span>) y su orden de recorrido en serpentina.</p>
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Crear rack y ubicaciones</button></div>
  </form>
</x-modal>
<x-modal id="loc" title="Nueva ubicación" width="560px">
  <form method="post" action="{{ route('map.locations.store') }}">@csrf
    <div class="m-b">
      <div class="grid g-2">
        <x-field label="Rack / área" name="zone_id" type="select" required>@foreach($zones as $z)<option value="{{ $z->id }}" @selected($zone && $z->id === $zone->id)>{{ $z->code }} · {{ $z->name }}</option>@endforeach</x-field>
        <x-field label="Código" name="code" required mono placeholder="E1-C11-N1" />
        <x-field label="Columna" name="position" placeholder="11" />
        <x-field label="Nivel" name="level" placeholder="1" />
      </div>
      <label class="row"><input type="checkbox" name="mixing" value="1" checked> Permitir mezclar lotes</label>
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Crear</button></div>
  </form>
</x-modal>
@endif
</x-layouts.app>
