<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#002048">
<meta name="mobile-web-app-capable" content="yes">
<link rel="manifest" href="{{ asset('carmen/manifest.webmanifest') }}">
<title>Carmen WMS</title>
<link rel="icon" href="{{ asset('img/favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Carlito:ital,wght@0,400;0,700;1,400&family=JetBrains+Mono:wght@400;600&family=Montserrat:wght@800&display=swap">
<link rel="stylesheet" href="{{ asset('carmen/app.css') }}?v={{ $version }}">
</head>
<body>
@include('carmen._shell')
<script src="{{ asset('carmen/vendor.js') }}?v={{ $version }}"></script>
<script src="{{ route('carmen.data') }}?t={{ now()->timestamp }}"></script>
@foreach (['core', 'views-ops', 'views-stock', 'views-master', 'views-config', 'collector', 'boot'] as $js)
<script src="{{ asset('carmen/'.$js.'.js') }}?v={{ $version }}"></script>
@endforeach
</body>
</html>
