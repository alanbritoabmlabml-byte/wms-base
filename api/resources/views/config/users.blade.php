<x-layouts.app :title="$title">
@php $e = $editing; $isCol = $tab === 'colector'; @endphp
<x-page-head title="Usuarios y roles" sub="Cuentas de escritorio y de colector. El rol se asigna por almacén: un mismo usuario puede ser encargado en Bolsas y operador en Materia prima.">
  <a class="btn" href="{{ route('config.import', ['dataset' => $isCol ? 'users_collector' : 'users_desktop']) }}"><x-icon name="upload" /> Importar</a>
  <button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'usr')"><x-icon name="plus" /> Nuevo usuario</button>
</x-page-head>

@if(session('credentials'))
@php $cr = session('credentials'); @endphp
<div class="alert ok" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><x-icon name="lock" />
  <div style="flex:1"><b>Credenciales de {{ $cr['user'] }}</b> — anótalas ahora, no se vuelven a mostrar.
    @if($cr['password'])<div>Contraseña de escritorio: <code class="mono" style="font-size:15px;background:#fff;padding:2px 8px;border-radius:4px">{{ $cr['password'] }}</code></div>@endif
    @if($cr['pin'])<div>PIN de colector: <code class="mono" style="font-size:15px;background:#fff;padding:2px 8px;border-radius:4px">{{ $cr['pin'] }}</code></div>@endif
  </div>
</div>
@endif

<div class="tabs">
  <a class="{{ !$isCol ? 'on' : '' }}" href="{{ route('config.users', ['tab' => 'escritorio']) }}"><x-icon name="shield" /> Escritorio <span class="badge-num">{{ $counts['escritorio'] }}</span></a>
  <a class="{{ $isCol ? 'on' : '' }}" href="{{ route('config.users', ['tab' => 'colector']) }}"><x-icon name="phone" /> Colector <span class="badge-num">{{ $counts['colector'] }}</span></a>
</div>

<div class="grid g-2-1">
  <x-card :flush="true">
    <form class="toolbar" method="get"><input type="hidden" name="tab" value="{{ $tab }}">
      <div class="search"><x-icon name="search" /><input name="q" value="{{ $q }}" placeholder="Nombre, usuario o correo"></div>
      <div style="flex:1"></div><button class="btn sm">Buscar</button>
    </form>
    @if($users->count())
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Usuario</th><th>Cuenta</th><th>Acceso</th><th>Roles por almacén</th>@if($isCol)<th>Colector</th>@endif<th>Último acceso</th><th>Estado</th><th></th></tr></thead><tbody>
      @foreach($users as $u)
      <tr>
        <td><div class="row"><span class="avatar">{{ \App\Support\Ui::initials($u->name) }}</span><div><b>{{ $u->name }}</b>@if($u->email)<div class="small muted">{{ $u->email }}</div>@endif</div></div></td>
        <td class="mono">{{ $u->username }}</td>
        <td><span class="tag">{{ ['ESCRITORIO' => 'Escritorio', 'COLECTOR' => 'Colector', 'AMBOS' => 'Ambos'][$u->client_type] ?? $u->client_type }}</span></td>
        <td>@forelse($u->warehouses as $w)<span class="pill {{ ['ADMIN' => 'a', 'SUPERVISOR' => 'info', 'OPERADOR' => 'neutral'][$w->pivot->role] ?? 'neutral' }}" style="margin:1px" title="{{ $w->name }}">{{ $w->code }} · {{ $roles[$w->pivot->role] ?? $w->pivot->role }}</span>@empty<span class="small muted">Sin almacenes</span>@endforelse</td>
        @if($isCol)<td>@if($u->device)<span class="mono small">{{ $u->device->serial }}</span> <span class="dot {{ $u->device->isOnline() ? 'ok' : 'off' }}"></span>@else<span class="small muted">—</span>@endif</td>@endif
        <td class="small">{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Nunca' }}</td>
        <td><x-pill :status="$u->is_active ? 'ACTIVO' : 'INACTIVO'" /></td>
        <td class="tbl-actions">
          <a class="btn sm ghost" href="{{ route('config.users', ['tab' => $tab, 'q' => $q, 'editar' => $u->id]) }}" title="Editar"><x-icon name="edit" /></a>
          <form method="post" action="{{ route('config.users.reset', [$u, 'tab' => $tab]) }}" data-confirm="Se generará una nueva contraseña/PIN para {{ $u->username }} y se cerrarán sus sesiones de colector. ¿Continuar?" class="inline-form">@csrf<button class="btn sm ghost" title="Restablecer credenciales"><x-icon name="lock" /></button></form>
        </td>
      </tr>
      @endforeach
    </tbody></table></div>
    <div class="tbl-foot"><span>{{ $users->total() }} usuarios</span>{{ $users->links('partials.pager') }}</div>
    @else<x-empty icon="users" text="Sin usuarios en esta pestaña." />@endif
  </x-card>

  <x-card title="Matriz de permisos por rol" :flush="true">
    <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Permiso</th>@foreach($roles as $r => $l)<th style="text-align:center">{{ $l }}</th>@endforeach</tr></thead><tbody>
      @foreach($permLabels as $perm => $label)
      <tr><td>{{ $label }}<div class="small muted mono">{{ $perm }}</div></td>
        @foreach($roles as $r => $l)<td style="text-align:center">@if(in_array($perm, $permissions[$r] ?? [], true))<span style="color:var(--ok);font-weight:700">✓</span>@else<span class="muted">—</span>@endif</td>@endforeach</tr>
      @endforeach
    </tbody></table></div>
    <div class="tbl-foot"><span class="small muted">La matriz vive en <code>config/wms.php</code>. Los administradores además acceden a este módulo de Configuración.</span></div>
  </x-card>
</div>

<x-modal id="usr" :title="$e ? 'Editar usuario' : 'Nuevo usuario'" width="760px">
  <form method="post" action="{{ $e ? route('config.users.update', [$e, 'tab' => $tab]) : route('config.users.store') }}" x-data="{ type: '{{ $e?->client_type ?? ($isCol ? 'COLECTOR' : 'ESCRITORIO') }}' }">@csrf @if($e)@method('PUT')@endif
    <div class="m-b"><div class="grid g-2">
      <x-field label="Nombre completo" name="name" :value="$e?->name" required />
      <x-field label="Usuario (cuenta)" name="username" :value="$e?->username" required mono hint="Letras, números, punto o guion" />
      <x-field label="Correo" name="email" type="email" :value="$e?->email" />
      <x-field label="Documento (CI)" name="document_id" :value="$e?->document_id" mono />
      <div class="field"><label>Tipo de acceso</label>
        <div class="seg" style="display:flex">@foreach(['ESCRITORIO' => 'Escritorio', 'COLECTOR' => 'Colector', 'AMBOS' => 'Ambos'] as $k => $l)<button type="button" style="flex:1" :class="type === '{{ $k }}' && 'on'" x-on:click="type = '{{ $k }}'">{{ $l }}</button>@endforeach</div>
        <input type="hidden" name="client_type" :value="type"></div>
      @if($e)<label class="row" style="align-self:end;gap:8px"><input type="checkbox" name="is_active" value="1" @checked($e->is_active)> Usuario activo</label>@endif
      <div x-show="type !== 'COLECTOR'"><x-field label="Contraseña de escritorio" name="password" type="password" :hint="$e ? 'Déjala vacía para no cambiarla' : 'Vacía = se genera una y se muestra al guardar'" autocomplete="new-password" /></div>
      <div x-show="type !== 'ESCRITORIO'"><x-field label="PIN de colector (4 a 6 dígitos)" name="pin" type="text" inputmode="numeric" :hint="$e ? 'Déjalo vacío para no cambiarlo' : 'Vacío = se genera uno y se muestra al guardar'" mono /></div>
    </div>
    <h4 style="margin:16px 0 6px">Rol por almacén</h4>
    <div class="tbl-wrap"><table class="tbl small"><thead><tr><th>Almacén</th><th>Rol</th></tr></thead><tbody>
      @foreach($warehouses as $w)
      @php $cur = $e?->warehouses->firstWhere('id', $w->id)?->pivot->role; @endphp
      <tr><td><b>{{ $w->code }}</b> <span class="muted">{{ $w->name }}</span></td>
        <td><div class="seg" style="display:inline-flex"><label class="{{ !$cur ? 'on' : '' }}" style="cursor:pointer"><input type="radio" name="roles[{{ $w->id }}]" value="" @checked(!$cur) hidden>Sin acceso</label>@foreach($roles as $r => $l)<label class="{{ $cur === $r ? 'on' : '' }}" style="cursor:pointer"><input type="radio" name="roles[{{ $w->id }}]" value="{{ $r }}" @checked($cur === $r) hidden>{{ $l }}</label>@endforeach</div></td></tr>
      @endforeach
    </tbody></table></div>
    </div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Guardar</button></div>
  </form>
</x-modal>
@push('scripts')
<script>
document.addEventListener('change', e => { if (e.target.matches('.seg input[type=radio]')) { const seg = e.target.closest('.seg'); seg.querySelectorAll('label').forEach(l => l.classList.toggle('on', l.querySelector('input').checked)); } });
@if($e) document.addEventListener('alpine:init',()=>{setTimeout(()=>window.dispatchEvent(new CustomEvent('open-modal',{detail:'usr'})),50)}); @endif
</script>
@endpush
</x-layouts.app>
