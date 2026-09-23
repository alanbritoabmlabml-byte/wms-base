<x-layouts.app :title="$title">
<div x-data="labelEditor(@js([
  'tpl' => $tpl->only(['id', 'code', 'name', 'width_mm', 'height_mm', 'qr_format', 'qr_size', 'font_scale', 'fields', 'printer', 'version']),
  'samples' => $samples, 'wh' => $wh->code, 'company' => $company, 'samplesUrl' => route('config.labels.samples', $tpl),
]))">
<form method="post" action="{{ route('config.labels.update', $tpl) }}" id="tplForm">@csrf @method('PUT')
<x-page-head title="Etiquetas QR" :sub="'Define una plantilla por tipo de etiqueta. Lo que ves es lo que imprime la Zebra. Versión actual: <b>v'.$tpl->version.'</b>'">
  <button type="button" class="btn" x-on:click="testPrint()"><x-icon name="print" /> Imprimir prueba</button>
  <button class="btn primary"><x-icon name="check" /> Guardar plantilla</button>
</x-page-head>

<div class="grid" style="grid-template-columns:300px minmax(0,1fr) 300px">
  {{-- Columna izquierda: plantilla + contenido del QR --}}
  <div class="stack">
    <x-card title="Plantilla">
      <div class="stack" style="gap:8px">
        @foreach($templates as $t)
        <a class="dataset {{ $t->id === $tpl->id ? 'on' : '' }}" href="{{ route('config.labels', ['tpl' => $t->code]) }}">
          <span class="ic"><x-icon :name="['UBICACION' => 'map', 'ITEM' => 'box', 'PALLET' => 'layers', 'DESPACHO' => 'truck'][$t->code] ?? 'tag'" /></span>
          <span><b>{{ $t->name }}</b><span>{{ $t->width_mm }} × {{ $t->height_mm }} mm · {{ $t->qr_format }} · v{{ $t->version }}</span></span>
        </a>
        @endforeach
      </div>
    </x-card>

    <x-card title="Contenido del QR">
      <div class="stack">
        <div class="seg" style="display:flex">
          @foreach(['PLAIN' => 'Texto', 'GS1' => 'GS1', 'JSON' => 'JSON', 'URL' => 'URL'] as $k => $l)
          <button type="button" style="flex:1" :class="tpl.qr_format === '{{ $k }}' && 'on'" x-on:click="tpl.qr_format = '{{ $k }}'; render()">{{ $l }}</button>
          @endforeach
        </div>
        <input type="hidden" name="qr_format" :value="tpl.qr_format">
        <pre class="mono small" x-text="payload()" style="margin:0;background:var(--surface-3);padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-all"></pre>
        <p class="small muted" style="margin:0" x-text="{PLAIN: 'Solo el código. Compatible con el SGLA anterior y con lectores 1D vía Code 128.', GS1: 'Identificadores de aplicación GS1: (01) GTIN, (10) lote, (17) vencimiento, (37) cantidad, (00) SSCC. Lo lee cualquier WMS.', JSON: 'Estructura propia. Un solo escaneo entrega tipo, id, lote y cantidad al colector.', URL: 'Abre la ficha en el navegador de cualquier teléfono. Útil para supervisores.'}[tpl.qr_format]"></p>
      </div>
    </x-card>
  </div>

  {{-- Centro: vista previa --}}
  <div class="card"><div class="card-h"><h3>Vista previa · <span x-text="tpl.width_mm + ' × ' + tpl.height_mm + ' mm'"></span></h3>
      <div class="row"><button type="button" class="btn sm icon" x-on:click="idx = (idx - 1 + samples.length) % samples.length; render()">‹</button><span class="small muted" x-text="'muestra ' + (idx + 1) + ' de ' + samples.length"></span><button type="button" class="btn sm icon" x-on:click="idx = (idx + 1) % samples.length; render()">›</button></div></div>
    <div class="card-b">
    <div class="label-stage" x-ref="stage"></div>
    <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:14px">
      <div class="row" style="flex-wrap:wrap;gap:10px">
        <div class="field" style="margin:0"><label>Impresora</label>
          <select class="select" name="printer" x-model="tpl.printer" style="min-height:36px;padding:6px 32px 6px 10px">
            @foreach($printers as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
          </select></div>
        <div class="field" style="margin:0"><label>Buscar muestra</label>
          <div class="search" style="min-height:36px"><x-icon name="search" /><input x-model.debounce.400ms="q" x-on:input.debounce.400ms="fetchSamples()" placeholder="Código o descripción" style="min-height:0"></div></div>
      </div>
      <div class="row">
        <button type="button" class="btn" x-on:click="window.print()"><x-icon name="print" /> Imprimir esta</button>
        <button type="button" class="btn primary" x-on:click="$dispatch('open-modal', 'batchPrint')"><x-icon name="print" /> Imprimir lote…</button>
      </div>
    </div>
  </div></div>

  {{-- Derecha: campos + formato --}}
  <div class="stack">
    <x-card title="Campos">
      <div style="margin:-10px 0">
        @foreach($fieldLabels as $k => $l)
        <div class="opt-row"><span>{{ $l }}</span>
          <button type="button" class="tog" :class="tpl.fields.{{ $k }} && 'on'" x-on:click="tpl.fields.{{ $k }} = !tpl.fields.{{ $k }}; render()" aria-label="{{ $l }}"></button>
          <input type="hidden" name="fields[{{ $k }}]" :value="tpl.fields.{{ $k }} ? 1 : 0">
        </div>
        @endforeach
      </div>
    </x-card>
    <x-card title="Formato">
      <div class="stack">
        <x-field label="Nombre" name="name" :value="$tpl->name" required />
        <div class="grid g-2">
          <div class="field"><label>Ancho (mm)</label><input class="input num" type="number" name="width_mm" min="20" max="210" x-model.number="tpl.width_mm" x-on:input="render()"></div>
          <div class="field"><label>Alto (mm)</label><input class="input num" type="number" name="height_mm" min="15" max="297" x-model.number="tpl.height_mm" x-on:input="render()"></div>
        </div>
        <div class="chips">@foreach(['50×30' => [50, 30], '100×50' => [100, 50], '100×100' => [100, 100], '100×150' => [100, 150]] as $l => [$w, $h])<button type="button" class="chip" :class="tpl.width_mm === {{ $w }} && tpl.height_mm === {{ $h }} && 'on'" x-on:click="tpl.width_mm = {{ $w }}; tpl.height_mm = {{ $h }}; render()">{{ $l }}</button>@endforeach</div>
        <div class="field"><label>Tamaño del QR</label>
          <div class="seg" style="display:flex">@foreach(['S', 'M', 'L', 'XL'] as $i => $s)<button type="button" style="flex:1" :class="tpl.qr_size === {{ $i + 1 }} && 'on'" x-on:click="tpl.qr_size = {{ $i + 1 }}; render()">{{ $s }}</button>@endforeach</div>
          <input type="hidden" name="qr_size" :value="tpl.qr_size"></div>
        <div class="field"><label>Tamaño de texto · <span x-text="tpl.font_scale"></span> %</label><input type="range" min="60" max="160" step="5" name="font_scale" x-model.number="tpl.font_scale" x-on:input="render()"></div>
        <p class="small muted" style="margin:0">Corrección de error <b>M</b>; módulo mínimo 0,5 mm a 203 dpi. La etiqueta de ubicación se lee desde el montacargas.</p>
      </div>
    </x-card>
  </div>
</div>
</form>

<x-modal id="batchPrint" title="Imprimir lote de etiquetas" width="560px">
  <div class="m-b stack">
    <p class="small muted" style="margin:0">Se generará una hoja con una etiqueta por cada registro filtrado, con la plantilla <b>{{ $tpl->name }}</b> tal como está guardada.</p>
    <div class="field"><label>Registros</label><textarea class="input mono" rows="5" id="batchList" placeholder="Un código por línea (o deja vacío para usar las {{ count($samples) }} muestras actuales)"></textarea></div>
    <div class="grid g-2"><div class="field"><label>Copias por registro</label><input class="input num" type="number" min="1" max="20" value="1" id="batchCopies"></div>
    <div class="field"><label>Impresora</label><div class="input" style="display:flex;align-items:center" x-text="tpl.printer || '—'"></div></div></div>
  </div>
  <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button type="button" class="btn primary" x-on:click="batchPrint(); open=false"><x-icon name="print" /> Generar e imprimir</button></div>
</x-modal>
</div>

@push('scripts')
<script src="{{ asset('vendor/qrcode.js') }}"></script>
<script src="{{ asset('vendor/jsbarcode.code128.min.js') }}"></script>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('labelEditor', (init) => ({
    tpl: init.tpl, samples: init.samples, idx: 0, q: '', wh: init.wh, company: init.company,
    init() { this.$nextTick(() => this.render()); },
    s() { return this.samples[this.idx] || this.samples[0]; },
    esc(t) { return String(t ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); },
    payload(s = this.s()) {
      const f = this.tpl.qr_format;
      if (!s) return '';
      if (f === 'PLAIN') return s.code;
      if (f === 'GS1') {
        if (s.kind === 'pallet') return `(00)${s.code}`;
        const gtin = ('0' + String(s.code).replace(/\D/g, '').padEnd(12, '0').slice(0, 12) + '0');
        return `(01)${gtin}${s.lot ? `(10)${s.lot}` : ''}${s.expiry ? `(17)${s.expiry.split('/').reverse().join('').slice(2)}` : ''}${s.qty ? `(37)${String(s.qty).split(' ')[0]}` : ''}`;
      }
      if (f === 'URL') return `${location.origin}/s/${encodeURIComponent(s.code)}`;
      return JSON.stringify({ t: s.kind, id: s.code, lot: s.lot || undefined, q: s.qty ? +String(s.qty).split(' ')[0] : undefined, wh: this.wh });
    },
    html(s, scale = 3.2) {
      const c = this.tpl, w = c.width_mm, h = c.height_mm, W = w * scale, H = h * scale, fs = c.font_scale / 100, F = c.fields;
      const qrPx = Math.round(Math.min(H * .72, W * .42) * [0.8, 0.9, 1, 1.1][c.qr_size - 1]);
      const tall = h > w * 0.8, e = this.esc.bind(this);
      const cells = [F.lot && s.lot ? `<div><span class="lbl-small">LOTE</span><br><b class="lbl-code">${e(s.lot)}</b></div>` : '', F.expiry && s.expiry ? `<div><span class="lbl-small">VENCE</span><br><b>${e(s.expiry)}</b></div>` : '', F.qty && s.qty ? `<div><span class="lbl-small">CANT.</span><br><b>${e(s.qty)}</b></div>` : '', F.date && s.date ? `<div><span class="lbl-small">FECHA</span><br><b>${e(s.date)}</b></div>` : ''].filter(Boolean);
      return `<div class="label-preview" style="width:${W}px;height:${H}px;padding:${8 * scale / 3}px">${F.stripe ? `<div class="corner ${s.kind === 'ped' ? 'red' : ''}"></div>` : ''}
        <div style="display:flex;gap:${8 * scale / 3}px;height:100%;${tall ? 'flex-direction:column;align-items:center;text-align:center' : ''}">
          ${F.qr ? `<div style="flex:none;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px">${window.wmsQrSvg(this.payload(s), qrPx)}${c.qr_format === 'GS1' ? `<span class="lbl-small" style="font-size:${7 * fs}px">GS1 · ${s.kind === 'pallet' ? 'AI 00' : 'AI 01 10 17'}</span>` : ''}</div>` : ''}
          <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:space-between;${tall ? 'width:100%' : ''}">
            <div>${F.logo ? `<div style="display:flex;align-items:center;gap:4px;margin-bottom:${3 * fs}px;${tall ? 'justify-content:center' : ''}"><svg viewBox="0 0 600 430" width="${20 * fs}" height="${14 * fs}"><use href="#pc-logo"/></svg><span class="wordmark" style="font-size:${8 * fs}px;color:#E00010">${e(this.company).toUpperCase()}</span><span style="font-size:${7 * fs}px;color:#666;margin-left:auto">${e(this.wh)}</span></div>` : ''}
            ${F.title ? `<div class="lbl-code" style="font-size:${(s.kind === 'loc' ? 30 : 15) * fs * (tall ? 1.1 : 1)}px;line-height:1;word-break:break-all">${e(s.title)}</div>` : ''}
            ${F.description ? `<div class="lbl-title" style="font-size:${10 * fs}px;margin-top:${3 * fs}px;line-height:1.15">${e(s.desc)}</div><div class="lbl-small" style="font-size:${8 * fs}px">${e(s.sub)}</div>` : ''}</div>
            <div style="display:grid;grid-template-columns:repeat(${cells.length || 1},auto);gap:${6 * fs}px;font-size:${8 * fs}px;margin-top:${4 * fs}px;${tall ? 'justify-content:center' : ''}">${cells.join('')}</div>
            ${F.code128 ? `<svg class="c128" data-code="${e(s.code)}" style="width:100%;height:${Math.max(28, 40 * fs)}px;margin-top:${3 * fs}px"></svg>` : ''}
          </div></div></div>`;
    },
    render() {
      const s = this.s(); if (!s || !this.$refs.stage) return;
      this.$refs.stage.innerHTML = this.html(s);
      this.$refs.stage.querySelectorAll('svg.c128').forEach(svg => window.wmsBarcode(svg, svg.dataset.code, Math.max(9, 11 * this.tpl.font_scale / 100)));
    },
    async fetchSamples() {
      try { const r = await fetch(init.samplesUrl + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }); this.samples = await r.json(); this.idx = 0; this.render(); }
      catch (e) { wmsToast('No se pudo buscar muestras', 'bad'); }
    },
    testPrint() { wmsToast('Etiqueta de prueba enviada a ' + (this.tpl.printer || 'la impresora predeterminada') + '.'); },
    batchPrint() {
      const codes = document.getElementById('batchList').value.split(/\n/).map(x => x.trim()).filter(Boolean);
      const copies = Math.max(1, +document.getElementById('batchCopies').value || 1);
      let list = codes.length ? codes.map(c => this.samples.find(x => x.code === c) || { kind: 'loc', code: c, title: c, desc: '', sub: this.wh, lot: '', expiry: '', qty: '', date: '' }) : this.samples;
      list = list.flatMap(s => Array(copies).fill(s));
      const w = window.open('', '_blank'); if (!w) return wmsToast('El navegador bloqueó la ventana de impresión', 'bad');
      const css = document.querySelector('link[href*="app.css"]').href;
      w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Etiquetas · ${this.esc(this.tpl.name)}</title><link rel="stylesheet" href="${css}"><style>body{background:#fff;padding:10mm;display:flex;flex-wrap:wrap;gap:6mm}.label-preview{box-shadow:none;page-break-inside:avoid}@page{margin:8mm}</style></head><body>${document.getElementById('pc-logo') ? document.getElementById('pc-logo').closest('svg').outerHTML : ''}${list.map(s => this.html(s, 3.78)).join('')}<script src="${document.querySelector('script[src*="qrcode"]').src}"><\/script><script src="${document.querySelector('script[src*="jsbarcode"]').src}"><\/script><script src="${document.querySelector('script[src*="app.js"]').src}"><\/script><script>window.addEventListener('load',()=>{document.querySelectorAll('svg.c128').forEach(s=>window.wmsBarcode(s,s.dataset.code,${Math.max(9, 11 * this.tpl.font_scale / 100)}));setTimeout(()=>window.print(),300)})<\/script></body></html>`);
      w.document.close();
      wmsToast(`${list.length} etiquetas generadas.`);
    },
  }));
});
</script>
@endpush
</x-layouts.app>
