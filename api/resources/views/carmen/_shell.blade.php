
<div id="splash" aria-hidden="true"><div class="splash-logo"><img src="{{ asset('img/logo.png') }}" alt="Plásticos Carmen"><small>Carmen WMS</small></div><div class="splash-bar"><i></i></div></div>

<div id="login" hidden>
  <section class="login-brand">
    <div class="grid"></div>
    <div>
      <div class="brand-lockup"><img src="{{ asset('img/logo.png') }}" alt="Plásticos Carmen"></div>
      <h1>Carmen WMS. Un solo sistema para todos los almacenes.</h1>
      <p>Recepción, ubicación, picking, despacho, pallets e inventario cíclico. La misma verdad en el colector Zebra y en la pantalla del supervisor.</p>
    </div>
    <div class="login-stats">
      <div><b>{{ $stats['warehouses'] }}</b><span>almacenes conectados</span></div>
      <div><b>{{ $stats['ira'] }}</b><span>exactitud por ubicación (IRA)</span></div>
      <div><b>{{ $stats['online'] }}</b><span>colectores en línea</span></div>
    </div>
  </section>
  <section class="login-form">
    <form class="login-card" id="loginForm" method="post" action="{{ route('login.attempt') }}" autocomplete="off">@csrf
      <div class="login-logo"><img src="{{ asset('img/logo.png') }}" alt="Plásticos Carmen"><small>tecnología en plásticos</small></div>
      <h2>Iniciar sesión</h2>
      <div class="seg" id="lgMode" style="display:flex;margin:8px 0 4px"><button type="button" class="on" data-mode="desk" style="flex:1">Escritorio</button><button type="button" data-mode="col" style="flex:1">Colector (PIN)</button></div>
      <div class="sub">Accede con tu usuario de escritorio.</div>
      <div class="field"><label for="lg-user">Usuario</label><input class="input" id="lg-user" name="username" value="{{ old('username') }}" placeholder="usuario" autocomplete="username" required></div>
      <div class="field"><label for="lg-pass">Contraseña</label><input class="input" id="lg-pass" name="password" type="password" value="" placeholder="contraseña" autocomplete="current-password" required></div>
      <div class="field"><label for="lg-wh">Sucursal / almacén de trabajo</label>
        <select class="select" id="lg-wh" name="wh">
          @foreach($warehouses as $w)<option value="{{ $w['id'] }}">{{ $w['name'] }} ({{ $w['id'] }})</option>@endforeach
        </select>
      </div>
      <button class="btn primary block" type="submit">Entrar</button>
      <div class="login-demo" id="lgMsg"><b>Usuarios de prueba:</b> escritorio amoscoso, jguasace, frivero · contraseña <b>wms1234</b>. Colector pgarcia, rsuarez · PIN <b>1234</b>.</div>
      <div class="login-foot"><span>v{{ config('app.wms_version', '0.5.0') }} · Laravel 13</span><span>Sistemas · Plásticos Carmen</span></div>
    </form>
  </section>
</div>

<div id="app" hidden>
  <aside class="rail">
    <div class="rail-head">
      <img class="full" src="{{ asset('img/logo.png') }}" alt="Plásticos Carmen">
      <img class="iso" src="{{ asset('img/isotipo.png') }}" alt="PC">
      <b>Carmen WMS</b>
    </div>
    <nav id="nav"></nav>
    <div class="rail-foot">
      <button class="btn" id="railToggle" title="Contraer menú"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg><span class="lbl">Contraer menú</span></button>
    </div>
  </aside>
  <main>
    <header class="topbar">
      <button class="btn icon ghost only-mobile" id="btnMenu" title="Menú"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
      <div class="ctx"><span class="dot"></span><span class="lbl">Almacén</span>
        <select id="ctxWh" aria-label="Almacén de trabajo"></select>
      </div>
      <div class="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input id="gsearch" placeholder="Buscar SKU, ubicación, pedido, lote…" aria-label="Búsqueda global">
        <kbd>/</kbd>
        <div class="search-pop" id="gsPop" hidden></div>
      </div>
      <div class="spacer"></div>
      <button class="btn icon ghost" id="btnCollector" title="Vista de colector"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg></button>
      <button class="btn icon ghost" id="btnTheme" title="Tema claro / oscuro"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
      <button class="btn icon ghost badge-dot" id="btnNotif" title="Alertas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></button>
      <button class="user-chip" id="btnUser"><div class="avatar" id="uAvatar">··</div><div class="who"><b id="uName">—</b><span id="uRole">—</span></div></button>
    </header>
    <section class="content" id="view"></section>
    <nav class="mobile-nav" id="mnav"></nav>
  </main>
</div>

<div class="scrim" id="scrim"></div>
<aside class="drawer" id="drawer" aria-hidden="true"></aside>
<aside class="menu-sheet" id="menuSheet" aria-hidden="true"></aside>
<div class="modal" id="modal" aria-hidden="true"><div class="m-box" id="modalBox"></div></div>
<div class="col-overlay" id="colOverlay">
  <div class="col-side">
    <h2>Modo colector</h2>
    <p>La misma aplicación, servida a un Zebra TC21/TC52. Un flujo por pantalla, objetivos táctiles de 56 px, confirmación por sonido y vibración, y el gatillo físico como botón principal.</p>
    <p>Simula un escaneo con los botones bajo la pantalla o escribe un código y pulsa Enter.</p>
    <div class="row wrap" id="colSims"></div>
    <button class="btn" id="colClose">Volver al escritorio</button>
  </div>
  <div class="phone"><div class="notch"></div><div class="scrn" id="colScreen"></div></div>
</div>
<div class="toasts" id="toasts"></div>
