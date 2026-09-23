<x-layouts.app :title="$title">
@php $u = fn($n) => \App\Support\Ui::qty($n); $canManage = in_array($whRole, ['ADMIN', 'SUPERVISOR'], true); @endphp
<x-page-head title="Stock e inventario" sub="Se cuenta un subconjunto cada día sin parar la operación. La métrica es IRA por ubicación, no por total.">
  @if($canManage)<button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'count')"><x-icon name="count" /> Nuevo conteo</button>@endif
</x-page-head>
<x-card :flush="true">
  @include('stock._tabs')
  <div class="card-b">
    <div class="grid g-1-2">
      <div class="stack">
        <x-kpi label="IRA · últimos conteos" :value="count($iraSeries) ? number_format(end($iraSeries), 1, ',', '.') : '—'" :unit="count($iraSeries) ? '%' : null" delta="meta 98 %" :spark="count($iraSeries) > 1 ? $iraSeries : null" style="box-shadow:none" />
        <x-card title="Plan cíclico" style="box-shadow:none">
          <div class="stack small">
            @foreach($plan as $p)
            <div><div class="row between"><b>Clase {{ $p['cls'] }}</b><span class="muted">cada {{ $p['days'] }} días</span></div>
              <div class="row"><div class="bar {{ ($p['pct'] ?? 0) >= 80 ? 'ok' : (($p['pct'] ?? 0) >= 50 ? 'warn' : '') }}" style="flex:1"><i style="width:{{ $p['pct'] ?? 0 }}%"></i></div><span class="num muted" style="width:40px;text-align:right">{{ $p['pct'] !== null ? $p['pct'].'%' : '—' }}</span></div></div>
            @endforeach
            <div class="muted">Cobertura = ubicaciones con stock de esa clase contadas dentro de la ventana. Sin clase ABC asignada no hay plan: asígnala en Rotación ABC.</div>
          </div>
        </x-card>
      </div>
      <x-card title="Conteos" :flush="true" style="box-shadow:none">
        @if($counts->count())
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Nro</th><th>Tipo</th><th>Apertura</th><th>Avance</th><th class="r">Dif.</th><th class="r">IRA</th><th>Responsable</th><th>Estado</th><th></th></tr></thead><tbody>
          @foreach($counts as $c)@php $p = $c->progress(); @endphp
          <tr class="clickable" onclick="location.href='{{ route('stock.counts.show', $c) }}'">
            <td><b class="num">{{ $c->number }}</b></td>
            <td>{{ \App\Models\CycleCount::TYPE_LABELS[$c->type] }}@if($c->blind) <span class="tag">ciego</span>@endif</td>
            <td class="small num">{{ \App\Support\Ui::date($c->opened_at, true) }}</td>
            <td><div class="row"><div class="bar {{ $p['total'] && $p['counted'] === $p['total'] ? 'ok' : '' }}" style="width:80px"><i style="width:{{ $p['total'] ? round($p['counted'] / $p['total'] * 100) : 0 }}%"></i></div><span class="small num">{{ $p['counted'] }}/{{ $p['total'] }}</span></div></td>
            <td class="r">@if($p['diff'])<span class="pill warn plain num">{{ $p['diff'] }}</span>@else 0 @endif</td>
            <td class="r"><b class="num">{{ $p['ira'] !== null ? number_format($p['ira'], 1, ',', '.').' %' : '—' }}</b></td>
            <td>{{ $c->responsible?->name }}</td>
            <td><x-pill :status="$c->status" /></td>
            <td class="tbl-actions"><x-icon name="chev" /></td>
          </tr>
          @endforeach
        </tbody></table></div>
        <div class="tbl-foot"><span>{{ $counts->total() }} conteos</span>{{ $counts->links('partials.pager') }}</div>
        @else<x-empty icon="count" text="Aún no hay conteos en este almacén" />@endif
      </x-card>
    </div>
  </div>
</x-card>

<x-modal id="count" title="Nuevo conteo de inventario" width="720px">
  <form method="post" action="{{ route('stock.counts.store') }}">@csrf
    <div class="m-b">
      <div class="grid g-2">
        <x-field label="Tipo" name="type" type="select" required>
          @foreach(\App\Models\CycleCount::TYPE_LABELS as $k => $l)<option value="{{ $k }}" @selected($k === 'CICLICO_A')>{{ $l }}</option>@endforeach
        </x-field>
        <x-field label="Alcance (rack / área)" name="zone_id" type="select">
          <option value="">Todo el almacén</option>
          @foreach($zones as $z)<option value="{{ $z->id }}">{{ $z->code }} · {{ $z->name }}</option>@endforeach
        </x-field>
        <x-field label="Responsable" name="responsible_id" type="select">
          @foreach($operators as $o)<option value="{{ $o->id }}" @selected($o->id === auth()->id())>{{ $o->name }}</option>@endforeach
        </x-field>
        <div class="stack" style="justify-content:center">
          <label class="row"><input type="checkbox" name="blind" value="1" checked> Ciego (el colector no muestra la cantidad esperada)</label>
          <label class="row"><input type="checkbox" name="blocks_picking" value="1"> Bloquear picking en las ubicaciones mientras se cuentan</label>
        </div>
      </div>
      <p class="small muted">Las líneas se generan al confirmar: una por cada saldo de las ubicaciones del alcance (y una vacía por ubicación sin stock, para confirmar que sigue vacía).</p>
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Habilitar conteo</button></div>
  </form>
</x-modal>
</x-layouts.app>
