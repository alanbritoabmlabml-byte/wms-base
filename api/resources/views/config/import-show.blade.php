<x-layouts.app :title="$title">
<x-page-head :title="'Importar '.$def['label']" :crumbs="'<a href=\''.route('config.import').'\'>Importar datos</a> / Lote #'.$batch->id" :sub="'<span class=mono>'.e($batch->filename).'</span> · '.$batch->rows_total.' filas · modo '.$batch->mode.' · '.$batch->created_at->format('d/m/Y H:i')">
  @if(in_array($batch->status, ['MAPEADO', 'VALIDADO', 'CARGADO']))
  <form method="post" action="{{ route('config.import.discard', $batch) }}" data-confirm="¿Descartar este lote? El archivo se eliminará.">@csrf<button class="btn"><x-icon name="x" /> Descartar</button></form>
  @else
  <a class="btn" href="{{ route('config.import', ['dataset' => $batch->dataset]) }}"><x-icon name="upload" /> Nueva carga</a>
  @endif
</x-page-head>

<x-card :flush="true" style="margin-bottom:14px">
  <div class="steps">
    <div class="st {{ $step > 1 ? 'done' : 'cur' }}">Archivo</div>
    <div class="st {{ $step > 2 ? 'done' : ($step === 2 ? 'cur' : '') }}">Mapeo de columnas</div>
    <div class="st {{ $step > 3 ? 'done' : ($step === 3 ? 'cur' : '') }}">Validación</div>
    <div class="st {{ $step === 4 && $batch->status === 'CONFIRMADO' ? 'done' : ($step === 4 ? 'cur' : '') }}">Confirmación</div>
  </div>
</x-card>

@if($step === 2)
{{-- ============ PASO 2: MAPEO ============ --}}
<form method="post" action="{{ route('config.import.map', $batch) }}" x-data="{ map: @js((object) ($batch->mapping ?? [])) }">@csrf
<div class="grid g-1-2">
  <x-card title="Columnas del archivo → campos del WMS">
    <p class="small muted" style="margin-top:0">Emparejamos automáticamente por nombre. Revisa los campos marcados con <span style="color:var(--red)">*</span>: son obligatorios.</p>
    @error('map')<div class="alert bad">{{ $message }}</div>@enderror
    <div class="mapping">
      @foreach($def['fields'] as $field => $req)
        <div><b>{{ $field }}</b>@if($req) <span style="color:var(--red)">*</span>@endif</div>
        <div class="arr">←</div>
        <select class="select" name="map[{{ $field }}]" x-model="map['{{ $field }}']">
          <option value="">— sin asignar —</option>
          @foreach($batch->headers as $h)<option value="{{ $h }}">{{ $h }}</option>@endforeach
        </select>
        <span class="small" :class="map['{{ $field }}'] ? 'ok' : 'muted'" x-text="map['{{ $field }}'] ? '✓' : '{{ $req ? 'falta' : 'opcional' }}'"></span>
      @endforeach
    </div>
    <div class="row" style="justify-content:flex-end;margin-top:16px;gap:8px">
      <a class="btn" href="{{ route('config.import', ['dataset' => $batch->dataset]) }}">Volver</a>
      <button class="btn primary"><x-icon name="check" /> Validar {{ $batch->rows_total }} filas</button>
    </div>
  </x-card>

  <x-card title="Vista previa del archivo" :flush="true">
    <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>#</th>@foreach($batch->headers as $h)<th class="mono">{{ $h }}</th>@endforeach</tr></thead><tbody>
      @foreach($preview as $i => $row)<tr><td class="muted">{{ $i + 2 }}</td>@foreach($batch->headers as $h)<td>{{ \Illuminate\Support\Str::limit($row[$h] ?? '', 28) }}</td>@endforeach</tr>@endforeach
    </tbody></table></div>
    <div class="tbl-foot"><span>Primeras {{ count($preview) }} de {{ $batch->rows_total }} filas · {{ count($batch->headers) }} columnas</span></div>
  </x-card>
</div>
</form>

@elseif($step === 3)
{{-- ============ PASO 3: VALIDACIÓN ============ --}}
<div class="grid g-4" style="margin-bottom:14px">
  <x-kpi label="Filas leídas" :value="$batch->rows_total" />
  <x-kpi label="Listas para importar" :value="$batch->rows_ok" />
  <x-kpi label="Con errores (se omiten)" :value="$batch->rows_error" />
  <x-kpi label="Modo" :value="$batch->mode" />
</div>
<div class="grid g-2-1">
  <x-card title="Errores por fila" :flush="true">
    @if($errors_)
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Fila</th><th>Clave</th><th>Datos</th><th>Problemas</th></tr></thead><tbody>
      @foreach($errors_ as $e)
      <tr><td class="num">{{ $e['line'] }}</td><td class="mono">{{ $e['key'] ?: '—' }}</td><td class="small muted">{{ implode(' · ', array_filter($e['row'])) }}</td><td>@foreach($e['errors'] as $m)<span class="pill bad" style="margin:1px">{{ $m }}</span>@endforeach</td></tr>
      @endforeach
    </tbody></table></div>
    @if(count($errors_) >= 200)<div class="tbl-foot"><span class="muted">Mostrando los primeros 200 errores.</span></div>@endif
    @else<x-empty text="Sin errores. Todas las filas pasaron la validación." />@endif
  </x-card>
  <div class="stack">
    <x-card title="Confirmar importación">
      <p class="small" style="margin-top:0">Se aplicarán <b>{{ $batch->rows_ok }}</b> filas a <b>{{ $def['label'] }}</b> del almacén <b>{{ $wh->name }}</b>@if($batch->rows_error), omitiendo {{ $batch->rows_error }} con errores@endif.
      @if($batch->mode === 'REPLACE')<br><span style="color:var(--bad)">Modo Reemplazar: los registros que no vengan en el archivo se desactivarán.</span>@endif</p>
      <form method="post" action="{{ route('config.import.commit', $batch) }}" data-confirm="¿Confirmar la importación de {{ $batch->rows_ok }} filas?">@csrf
        <div class="stack">
          <button class="btn primary lg" @disabled(!$batch->rows_ok)><x-icon name="check" /> Confirmar {{ $batch->rows_ok }} filas</button>
        </div>
      </form>
      <form method="post" action="{{ route('config.import.map', $batch) }}" style="margin-top:8px">@csrf<input type="hidden" name="back" value="1">@foreach($batch->mapping as $f => $h)<input type="hidden" name="map[{{ $f }}]" value="{{ $h }}">@endforeach<button class="btn" style="width:100%"><x-icon name="edit" /> Corregir mapeo</button></form>
    </x-card>
    <x-card title="Mapeo aplicado">
      <dl class="kv">@foreach($batch->mapping as $f => $h)<dt>{{ $f }}</dt><dd class="mono">{{ $h }}</dd>@endforeach</dl>
    </x-card>
  </div>
</div>

@else
{{-- ============ PASO 4: RESULTADO ============ --}}
<div class="grid g-4" style="margin-bottom:14px">
  <x-kpi label="Filas leídas" :value="$batch->rows_total" />
  <x-kpi label="Aplicadas" :value="$batch->rows_ok" />
  <x-kpi label="Omitidas" :value="$batch->rows_error" />
  <x-kpi label="Estado" :value="\App\Support\Ui::label($batch->status)" />
</div>
<div class="grid g-2-1">
  <x-card title="Filas omitidas" :flush="true">
    @if($errors_)
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Fila</th><th>Clave</th><th>Problemas</th></tr></thead><tbody>
      @foreach($errors_ as $e)<tr><td class="num">{{ $e['line'] }}</td><td class="mono">{{ $e['key'] ?: '—' }}</td><td>{{ implode(' · ', $e['errors']) }}</td></tr>@endforeach
    </tbody></table></div>
    @else<x-empty text="Todas las filas se aplicaron." />@endif
  </x-card>
  <x-card title="Siguiente paso">
    <div class="stack">
      @php $go = ['items' => 'items.index', 'customers' => 'customers.index', 'locations' => 'map.index', 'drivers' => 'transport.index', 'vehicles' => 'transport.index', 'users_collector' => 'config.users', 'users_desktop' => 'config.users'][$batch->dataset] ?? 'dashboard'; @endphp
      <a class="btn primary" href="{{ route($go) }}"><x-icon name="eye" /> Ver {{ $def['label'] }}</a>
      <a class="btn" href="{{ route('config.import', ['dataset' => $batch->dataset]) }}"><x-icon name="upload" /> Cargar otro archivo</a>
    </div>
    <dl class="kv" style="margin-top:14px"><dt>Usuario</dt><dd>{{ $batch->user?->name }}</dd><dt>Actualizado</dt><dd>{{ $batch->updated_at->format('d/m/Y H:i') }}</dd></dl>
  </x-card>
</div>
@endif
</x-layouts.app>
