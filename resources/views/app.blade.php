<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover: konten memakai seluruh layar termasuk area notch (iPhone) --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d1c26">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="bg-slate-900 text-white antialiased" style="background-color:#0d1c26">
    @inertia
</body>
</html>
