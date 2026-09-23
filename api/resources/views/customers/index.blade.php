<x-layouts.app :title="$title">
@php $e = $editing; @endphp
<x-page-head title="Clientes" sub="Sincronizados desde WorkCorp. Direcciones de entrega y zona logística se administran aquí.">
  @if($whRole === 'ADMIN')<a class="btn" href="{{ route('config.import', ['dataset' => 'customers']) }}"><x-icon name="upload" /> Importar</a>@endif
  <button class="btn primary" x-data x-on:click="$dispatch('open-modal', 'cust')"><x-icon name="plus" /> Nuevo cliente</button>
</x-page-head>
<x-card :flush="true">
  <form class="toolbar" method="get">
    <div class="search"><x-icon name="search" /><input name="q" value="{{ $q }}" placeholder="Código, razón social, NIT, ciudad"></div>
    <div class="chips"><a class="chip link {{ !$dpto ? 'on' : '' }}" href="{{ route('customers.index', ['q' => $q]) }}">Todos</a>@foreach($departments as $d)<a class="chip link {{ $dpto === $d ? 'on' : '' }}" href="{{ route('customers.index', ['dpto' => $d, 'q' => $q]) }}">{{ $d }}</a>@endforeach</div>
    <div style="flex:1"></div><button class="btn sm">Buscar</button>
  </form>
  @if($customers->count())
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Código</th><th>Cliente</th><th>Canal</th><th>Ciudad</th><th>Teléfono</th><th class="r">Pedidos</th><th>Estado</th><th></th></tr></thead><tbody>
    @foreach($customers as $c)
    <tr><td class="mono">{{ $c->code }}</td><td><b>{{ $c->name }}</b><div class="small muted">NIT {{ $c->tax_id ?? '—' }}{{ $c->contact_name ? ' · '.$c->contact_name : '' }}</div></td><td>@if($c->channel)<span class="tag">{{ $c->channel }}</span>@endif</td><td>{{ $c->city }}<div class="small muted">{{ $c->department }}</div></td><td class="num">{{ $c->phone ?? '—' }}</td><td class="r">{{ $c->orders_count }}</td><td><x-pill :status="$c->is_active ? 'ACTIVO' : 'INACTIVO'" /></td>
      <td class="tbl-actions"><a class="btn sm ghost" href="{{ route('customers.index', ['editar' => $c->id, 'q' => $q]) }}" title="Editar"><x-icon name="edit" /></a><a class="btn sm ghost" href="{{ route('outbound.orders', ['q' => $c->code]) }}" title="Pedidos"><x-icon name="out" /></a></td></tr>
    @endforeach
  </tbody></table></div>
  <div class="tbl-foot"><span>{{ $customers->total() }} clientes</span>{{ $customers->links('partials.pager') }}</div>
  @else<x-empty icon="users" text="Sin clientes. Impórtalos desde WorkCorp o crea el primero." />@endif
</x-card>

<x-modal id="cust" :title="$e ? 'Editar cliente' : 'Nuevo cliente'" width="720px">
  <form method="post" action="{{ $e ? route('customers.update', $e) : route('customers.store') }}">@csrf @if($e)@method('PUT')@endif
    <div class="m-b"><div class="grid g-2">
      <x-field label="Código" name="code" :value="$e?->code" required mono />
      <x-field label="NIT" name="tax_id" :value="$e?->tax_id" />
      <div style="grid-column:1/-1"><x-field label="Razón social" name="name" :value="$e?->name" required /></div>
      <x-field label="Contacto" name="contact_name" :value="$e?->contact_name" />
      <x-field label="Teléfono" name="phone" :value="$e?->phone" />
      <div style="grid-column:1/-1"><x-field label="Dirección de entrega" name="address" :value="$e?->address" /></div>
      <x-field label="Departamento" name="department" type="select"><option value="">—</option>@foreach(['Santa Cruz', 'La Paz', 'Cochabamba', 'Beni', 'Pando', 'Tarija', 'Oruro', 'Potosí', 'Chuquisaca'] as $d)<option @selected($e?->department === $d)>{{ $d }}</option>@endforeach</x-field>
      <x-field label="Ciudad" name="city" :value="$e?->city" />
      <x-field label="Provincia" name="province" :value="$e?->province" />
      <x-field label="Zona" name="zone" :value="$e?->zone" />
      <x-field label="Canal" name="channel" type="select"><option value="">—</option>@foreach(['Ferretería', 'Distribuidor', 'Cadena', 'Mayorista', 'Agro', 'Importador', 'Otro'] as $d)<option @selected($e?->channel === $d)>{{ $d }}</option>@endforeach</x-field>
      @if($e)<label class="row" style="align-self:end"><input type="checkbox" name="is_active" value="1" @checked($e->is_active)> Activo</label>@endif
    </div></div>
    <div class="m-f"><button type="button" class="btn" x-on:click="open=false">Cancelar</button><button class="btn primary">Guardar</button></div>
  </form>
</x-modal>
@if($e)@push('scripts')<script>document.addEventListener('alpine:init',()=>{setTimeout(()=>window.dispatchEvent(new CustomEvent('open-modal',{detail:'cust'})),50)});</script>@endpush @endif
</x-layouts.app>
