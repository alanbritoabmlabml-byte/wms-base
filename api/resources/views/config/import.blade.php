<x-layouts.app :title="$title">
<x-page-head title="Importar datos" sub="Carga masiva de maestros desde CSV (exportación de WorkCorp o Excel). Cuatro pasos: archivo → mapeo → validación → confirmación.">
  <a class="btn" href="{{ route('config.import.template', $dataset) }}"><x-icon name="download" /> Plantilla {{ $datasets[$dataset]['label'] }}</a>
</x-page-head>

@if($open)
<div class="alert warn" style="margin-bottom:14px"><x-icon name="alert" /> Tienes un lote en proceso: <b>{{ $open->filename }}</b> ({{ $datasets[$open->dataset]['label'] }}, {{ $open->rows_total }} filas).
  <a class="btn sm primary" href="{{ route('config.import.show', $open) }}">Continuar</a>
  <form method="post" action="{{ route('config.import.discard', $open) }}" class="inline-form">@csrf<button class="btn sm ghost">Descartar</button></form>
</div>
@endif

<div class="grid g-1-2" x-data="importForm('{{ $dataset }}')">
  <x-card title="1. ¿Qué vas a cargar?">
    <div class="stack">
      @foreach($datasets as $id => $d)
      <button type="button" class="dataset" :class="dataset === '{{ $id }}' && 'on'" x-on:click="pick('{{ $id }}')">
        <div class="ic"><x-icon :name="$d['icon']" /></div>
        <div><b>{{ $d['label'] }}</b><span>{{ $d['desc'] }}</span></div>
        <span class="tag" style="margin-left:auto">clave: {{ $d['key'] }}</span>
      </button>
      @endforeach
    </div>
  </x-card>

  <div class="stack">
    <x-card title="2. Archivo y modo de carga">
      <form method="post" action="{{ route('config.import.upload') }}" enctype="multipart/form-data" x-ref="form">@csrf
        <input type="hidden" name="dataset" :value="dataset">
        <div class="drop" :class="over && 'over'" x-on:dragover.prevent="over=true" x-on:dragleave="over=false" x-on:drop.prevent="over=false; drop($event)" x-on:click="$refs.file.click()">
          <x-icon name="upload" />
          <div><b x-text="fileName || 'Arrastra el CSV aquí o haz clic para elegirlo'"></b></div>
          <div class="small muted">CSV con encabezados · separador , o ; · UTF-8 o Windows-1252 · máx. 20 MB</div>
          <input type="file" name="file" accept=".csv,.txt,text/csv" x-ref="file" hidden x-on:change="fileName = $event.target.files[0]?.name">
        </div>
        @error('file')<div class="form-error">{{ $message }}</div>@enderror
        <div class="grid g-3" style="margin-top:14px">
          @foreach([['UPSERT', 'Actualizar o crear', 'Si la clave existe la actualiza; si no, la crea. Recomendado.'], ['INSERT', 'Solo nuevos', 'Omite las filas cuya clave ya existe.'], ['REPLACE', 'Reemplazar', 'Igual que actualizar y desactiva lo que no venga en el archivo.']] as [$m, $l, $h])
          <label class="dataset" :class="mode === '{{ $m }}' && 'on'" style="align-items:flex-start">
            <input type="radio" name="mode" value="{{ $m }}" x-model="mode" style="margin-top:3px">
            <div><b>{{ $l }}</b><span>{{ $h }}</span></div>
          </label>
          @endforeach
        </div>
        <div class="row" style="justify-content:space-between;margin-top:14px">
          <span class="small muted">Campos de <b x-text="labels[dataset]"></b>: <span class="mono" x-text="fields[dataset]"></span></span>
          <button class="btn primary" :disabled="!fileName"><x-icon name="arrow" /> Cargar y mapear columnas</button>
        </div>
      </form>
    </x-card>

    <x-card title="Historial de cargas" :flush="true">
      @if($recent->count())
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Fecha</th><th>Tabla</th><th>Archivo</th><th>Modo</th><th class="r">Filas</th><th class="r">OK</th><th class="r">Errores</th><th>Estado</th><th>Usuario</th><th></th></tr></thead><tbody>
        @foreach($recent as $b)
        <tr><td class="num">{{ $b->created_at->format('d/m H:i') }}</td><td>{{ $datasets[$b->dataset]['label'] ?? $b->dataset }}</td><td class="mono small">{{ $b->filename }}</td><td><span class="tag">{{ $b->mode }}</span></td><td class="r">{{ $b->rows_total }}</td><td class="r">{{ $b->rows_ok }}</td><td class="r {{ $b->rows_error ? 'bad' : '' }}">{{ $b->rows_error }}</td><td><x-pill :status="$b->status" /></td><td>{{ $b->user?->name }}</td>
          <td class="tbl-actions"><a class="btn sm ghost" href="{{ route('config.import.show', $b) }}"><x-icon name="eye" /></a></td></tr>
        @endforeach
      </tbody></table></div>
      @else<x-empty icon="upload" text="Aún no hay cargas en este almacén." />@endif
    </x-card>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('importForm', (initial) => ({
    dataset: initial, mode: 'UPSERT', over: false, fileName: '',
    labels: @json(collect($datasets)->map(fn ($d) => $d['label'])),
    fields: @json(collect($datasets)->map(fn ($d) => implode(', ', array_keys($d['fields'])))),
    pick(id) { this.dataset = id; history.replaceState(null, '', '?dataset=' + id); },
    drop(e) { const f = e.dataTransfer.files; if (f.length) { this.$refs.file.files = f; this.fileName = f[0].name; } },
  }));
});
</script>
@endpush
</x-layouts.app>
