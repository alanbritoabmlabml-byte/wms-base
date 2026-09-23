<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#003080">
<title>{{ $title ?? 'Carmen WMS' }}</title>
<link rel="icon" href="{{ asset('img/favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Carlito:ital,wght@0,400;0,700;1,400&family=JetBrains+Mono:wght@400;600&family=Montserrat:wght@800&display=swap">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script>try{var t=localStorage.getItem('cwms-theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
</head>
<body>
@include('partials.logo-defs')
{{ $slot }}
<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
