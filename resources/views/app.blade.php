<!DOCTYPE html>
<html lang="id" class="theme-light">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover: konten memakai seluruh layar termasuk area notch (iPhone) --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f4f7fb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="bg-slate-900 text-slate-100 antialiased" style="background-color:#f4f7fb">
    @inertia
</body>
</html>
