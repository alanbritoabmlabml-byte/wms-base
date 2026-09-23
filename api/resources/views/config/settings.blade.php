<x-layouts.app :title="$title">
@php $g = $schema[$group]; $isLocal = $scope === 'almacen'; @endphp
<x-page-head title="Parámetros" sub="Reglas de negocio del WMS. Los valores <b>globales</b> aplican a todos los almacenes; cada almacén puede sobreescribir los que necesite.">
  @if($lastUpdate)<span class="small muted">Última edición: {{ $lastUpdate->updated_at->diffForHumans() }}{{ $lastUpdate->editor ? ' · '.$lastUpdate->editor->name : '' }}</span>@endif
</x-page-head>

<div class="grid" style="grid-template-columns:280px minmax(0,1fr)">
  <x-card title="Grupos" :flush="true">
    <ul class="tree" style="padding:8px">
      @foreach($schema as $k => $s)
      <li><a class="node {{ $k === $group ? 'on' : '' }}" href="{{ route('config.settings', ['grupo' => $k, 'alcance' => $scope]) }}" style="text-decoration:none;color:inherit"><x-icon :name="$s['icon']" /> {{ $s['label'] }}@if($overrides[$k])<span class="cnt" title="Parámetros sobreescritos en {{ $wh->code }}">{{ $overrides[$k] }} ⚑</span>@endif</a></li>
      @endforeach
    </ul>
  </x-card>

  <form method="post" action="{{ route('config.settings.update') }}">@csrf @method('PUT')
    <input type="hidden" name="grupo" value="{{ $group }}"><input type="hidden" name="alcance" value="{{ $scope }}">
    <div class="card">
      <div class="card-h"><div><h3>{{ $g['label'] }}</h3><div class="small muted">{{ $g['desc'] }}</div></div>
        <div class="seg">
          <a class="{{ !$isLocal ? 'on' : '' }}" href="{{ route('config.settings', ['grupo' => $group]) }}">Global</a>
          <a class="{{ $isLocal ? 'on' : '' }}" href="{{ route('config.settings', ['grupo' => $group, 'alcance' => 'almacen']) }}">Almacén {{ $wh->code }}</a>
        </div>
      </div>
      <div class="card-b" style="padding-top:4px">
        @foreach($g['fields'] as $key => $f)
        @php $v = $values[$key]; $val = $isLocal ? ($v['local'] ?? $v['global']) : $v['global']; $inherits = $isLocal && $v['local'] === null; @endphp
        <div class="opt-row" style="padding:12px 0;align-items:flex-start" x-data="{ inherit: {{ $inherits ? 'true' : 'false' }} }">
          <div style="flex:1;min-width:0">
            <label for="v-{{ $key }}" style="font-weight:700;font-size:14px">{{ $f['label'] }}</label>
            @if(!empty($f['hint']))<div class="small muted">{{ $f['hint'] }}</div>@endif
            @if($isLocal)<div class="small" style="margin-top:4px"><label class="row" style="gap:6px;color:var(--ink-3)"><input type="checkbox" name="heredar[{{ $key }}]" value="1" x-model="inherit"> Heredar valor global (<span class="mono">{{ is_array($v['global']) ? implode(' | ', $v['global']) : (is_bool($v['global']) ? ($v['global'] ? 'sí' : 'no') : $v['global']) }}</span>)</label></div>@endif
          </div>
          <div style="width:min(360px,45%);flex:none" :style="inherit && 'opacity:.45;pointer-events:none'">
            @if($f['type'] === 'bool')
              <label class="row" style="justify-content:flex-end;gap:10px" x-data="{ on: {{ $val ? 'true' : 'false' }} }"><span class="small muted" x-text="on ? 'Activado' : 'Desactivado'"></span>
                <input type="hidden" name="v[{{ $key }}]" :value="on ? 1 : 0">
                <button type="button" class="tog" :class="on && 'on'" x-on:click="on = !on" id="v-{{ $key }}" aria-label="{{ $f['label'] }}"></button></label>
            @elseif($f['type'] === 'number')
              <input class="input num" type="number" step="any" id="v-{{ $key }}" name="v[{{ $key }}]" value="{{ $val }}" @isset($f['min']) min="{{ $f['min'] }}" @endisset @isset($f['max']) max="{{ $f['max'] }}" @endisset>
            @elseif($f['type'] === 'select')
              <select class="select" id="v-{{ $key }}" name="v[{{ $key }}]">@foreach($f['options'] as $ok => $ol)<option value="{{ $ok }}" @selected($val === $ok)>{{ $ol }}</option>@endforeach</select>
            @elseif($f['type'] === 'list')
              <textarea class="input mono" rows="4" id="v-{{ $key }}" name="v[{{ $key }}]">{{ implode("\n", (array) $val) }}</textarea>
            @else
              <input class="input {{ !empty($f['mono']) ? 'mono' : '' }}" type="text" id="v-{{ $key }}" name="v[{{ $key }}]" value="{{ $val }}">
            @endif
          </div>
        </div>
        @endforeach
        <div class="row" style="justify-content:space-between;margin-top:16px">
          <span class="small muted">@if($isLocal)Guardas solo los valores de <b>{{ $wh->name }}</b>; los marcados «heredar» toman el global.@else Estos valores aplican a todos los almacenes que no los sobreescriban.@endif</span>
          <button class="btn primary"><x-icon name="check" /> Guardar {{ $isLocal ? 'para '.$wh->code : 'globales' }}</button>
        </div>
      </div>
    </div>
  </form>
</div>
</x-layouts.app>
