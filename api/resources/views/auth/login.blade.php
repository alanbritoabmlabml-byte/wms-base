<x-layouts.guest title="Iniciar sesión · Carmen WMS">
<div id="login">
  <section class="login-brand">
    <div class="grid"></div>
    <div>
      <div class="brand-lockup">
        <svg class="pc" viewBox="0 0 600 430"><use href="#pc-logo-white"/></svg>
        <div><b class="wordmark">PLÁSTICOS CARMEN</b><span>Tecnología en plásticos</span></div>
      </div>
      <h1>Carmen WMS. Un solo sistema para todos los almacenes.</h1>
      <p>Recepción, ubicación, picking, despacho e inventario cíclico. La misma verdad en el colector Zebra y en la pantalla del supervisor.</p>
    </div>
    <div class="login-stats">
      <div><b>{{ $warehouses->count() }}</b><span>almacenes configurados</span></div>
      <div><b>{{ config('app.version', env('WMS_VERSION', '0.2')) }}</b><span>versión</span></div>
      <div><b>Laravel {{ \Illuminate\Foundation\Application::VERSION }}</b><span>plataforma</span></div>
    </div>
  </section>
  <section class="login-form">
    <form class="login-card" method="post" action="{{ route('login.attempt') }}" autocomplete="off">
      @csrf
      <div class="login-logo"><svg viewBox="0 0 600 430"><use href="#pc-logo"/></svg><span class="wordmark">PLÁSTICOS CARMEN</span><small>tecnología en plásticos</small></div>
      <h2>Iniciar sesión</h2>
      <div class="sub">Accede con tu usuario de escritorio.</div>
      @if($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
      <x-field label="Usuario" name="username" required autofocus />
      <x-field label="Contraseña" name="password" type="password" required />
      <x-field label="Almacén de trabajo" name="warehouse_id" type="select">
        <option value="">El primero que tenga asignado</option>
        @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }} ({{ $w->code }})</option>@endforeach
      </x-field>
      <label class="row small" style="margin-bottom:10px"><input type="checkbox" name="remember" value="1"> Mantener la sesión en este equipo</label>
      <button class="btn primary block" type="submit">Entrar</button>
      <div class="login-foot"><span>v{{ env('WMS_VERSION', '0.2.0') }} · escritorio</span><span>Sistemas · Plásticos Carmen</span></div>
    </form>
  </section>
</div>
</x-layouts.guest>
