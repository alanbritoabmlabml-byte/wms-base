<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#003080">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Carmen WMS' }} · Carmen WMS</title>
<link rel="icon" href="{{ asset('img/favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Carlito:ital,wght@0,400;0,700;1,400&family=JetBrains+Mono:wght@400;600&family=Montserrat:wght@800&display=swap">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script>try{var t=localStorage.getItem('cwms-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
@stack('head')
</head>
<body>
@include('partials.logo-defs')
@php
  $navCounts = ($wh ?? null) ? \App\Support\NavCounts::for($wh) : [];
  $nav = [
    ['grp' => 'Principal'],
    ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Inicio', 'icon' => 'home'],
    ['grp' => 'Operación'],
    ['route' => 'receipts.index', 'match' => 'receipts.*', 'label' => 'Ingresos', 'icon' => 'in', 'count' => $navCounts['receipts'] ?? null],
    ['route' => 'outbound.board', 'match' => 'outbound.*', 'label' => 'Pedidos y despacho', 'icon' => 'out', 'count' => $navCounts['orders'] ?? null],
    ['route' => 'stock.balances', 'match' => 'stock.*', 'label' => 'Stock e inventario', 'icon' => 'stock'],
    ['route' => 'map.index', 'match' => 'map.*', 'label' => 'Mapa de almacén', 'icon' => 'map'],
    ['grp' => 'Maestros'],
    ['route' => 'items.index', 'match' => 'items.*', 'label' => 'Productos', 'icon' => 'box'],
    ['route' => 'customers.index', 'match' => 'customers.*', 'label' => 'Clientes', 'icon' => 'users'],
    ['route' => 'transport.index', 'match' => 'transport.*', 'label' => 'Choferes y camiones', 'icon' => 'truck'],
  ];
  if (($whRole ?? null) === 'ADMIN') {
    $nav = array_merge($nav, [
      ['grp' => 'Configuración'],
      ['route' => 'config.import', 'match' => 'config.import*', 'label' => 'Importar datos', 'icon' => 'upload'],
      ['route' => 'config.labels', 'match' => 'config.labels*', 'label' => 'Etiquetas QR', 'icon' => 'qr'],
      ['route' => 'config.users', 'match' => 'config.users*', 'label' => 'Usuarios y roles', 'icon' => 'shield'],
      ['route' => 'config.devices', 'match' => 'config.devices*', 'label' => 'Colectores', 'icon' => 'phone', 'count' => $navCounts['devices_offline'] ?? null],
      ['route' => 'config.settings', 'match' => 'config.settings*', 'label' => 'Parámetros', 'icon' => 'sliders'],
    ]);
  }
  $mobile = [['dashboard', 'dashboard', 'home', 'Inicio'], ['receipts.index', 'receipts.*', 'in', 'Ingresos'], ['outbound.board', 'outbound.*', 'out', 'Pedidos'], ['stock.balances', 'stock.*', 'stock', 'Stock'], ['map.index', 'map.*', 'map', 'Mapa']];
@endphp
<div id="app">
  <aside class="rail">
    <div class="rail-head">
      <svg class="pc" viewBox="0 0 600 430"><use href="#pc-logo-white"/></svg>
      <div><b>Carmen WMS</b><span>Plásticos Carmen</span></div>
    </div>
    <nav>
      @foreach($nav as $n)
        @if(isset($n['grp']))
          <div class="grp">{{ $n['grp'] }}</div>
        @else
          <a class="nav {{ request()->routeIs($n['match']) ? 'active' : '' }}" href="{{ route($n['route']) }}">
            <x-icon :name="$n['icon']" /><span class="lbl">{{ $n['label'] }}</span>
            @if(!empty($n['count']))<span class="cnt">{{ $n['count'] }}</span>@endif
          </a>
        @endif
      @endforeach
    </nav>
    <div class="rail-foot">
      <button type="button" class="btn" onclick="wmsToggleRail()" title="Contraer / expandir menú"><x-icon name="chev" /><span class="lbl">Contraer menú</span></button>
    </div>
  </aside>
  <main>
    <header class="topbar">
      @if($wh ?? null)
      <form method="post" action="{{ route('warehouse.switch') }}" class="ctx">@csrf
        <span class="dot"></span><span class="lbl">Almacén</span>
        <select name="warehouse_id" aria-label="Almacén de trabajo" onchange="this.form.submit()">
          @foreach($whList as $w)<option value="{{ $w->id }}" @selected($w->id === $wh->id)>{{ $w->name }}</option>@endforeach
        </select>
      </form>
      @endif
      <form class="search" method="get" action="{{ route('search') }}">
        <x-icon name="search" />
        <input id="gsearch" name="q" value="{{ request('q') }}" placeholder="Buscar SKU, ubicación, pedido, lote…" aria-label="Búsqueda global" autocomplete="off">
        <kbd>/</kbd>
      </form>
      <div class="spacer"></div>
      <button type="button" class="btn icon ghost" onclick="wmsToggleTheme()" title="Tema claro / oscuro"><x-icon name="moon" /></button>
      <div x-data="{o:false}" style="position:relative">
        <button type="button" class="user-chip" x-on:click="o=!o"><div class="avatar">{{ \App\Support\Ui::initials(auth()->user()->name) }}</div><div class="who"><b>{{ auth()->user()->name }}</b><span>{{ \App\Support\Ui::label($whRole ?? auth()->user()->highestRole()) }}</span></div></button>
        <div x-show="o" x-cloak x-on:click.outside="o=false" class="search-pop" style="left:auto;right:0;width:260px">
          <div class="it muted small" style="cursor:default">{{ auth()->user()->username }} · {{ $wh?->code }}</div>
          <form method="post" action="{{ route('logout') }}">@csrf<button class="it" style="width:100%;border:none;background:none;text-align:left"><x-icon name="logout" /> Cerrar sesión</button></form>
        </div>
      </div>
    </header>
    <section class="content">
      @if($errors->any() && !isset($keepErrorsInline))
        <div class="alert bad"><b>Revisa el formulario:</b> {{ $errors->first() }}</div>
      @endif
      {{ $slot }}
    </section>
    <nav class="mobile-nav">
      @foreach($mobile as [$r, $m, $ic, $lb])<a href="{{ route($r) }}" class="{{ request()->routeIs($m) ? 'active' : '' }}"><x-icon :name="$ic" /><span>{{ $lb }}</span></a>@endforeach
    </nav>
  </main>
</div>
<div class="toasts" id="toasts"></div>
@if(session('toast'))<div id="flash" data-msg="{{ session('toast') }}" data-kind="{{ session('toast_kind', 'ok') }}" hidden></div>@endif
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
