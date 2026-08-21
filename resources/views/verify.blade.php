<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Dokumen — {{ $company['name'] }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-900 text-slate-100 antialiased min-h-screen flex items-center justify-center p-4" style="background-color:#0d1c26">
    <div class="w-full max-w-md">
        <div class="flex items-center justify-center gap-2 mb-6">
            <img src="{{ asset('images/logo-gep.png') }}" alt="Logo" class="h-9 w-auto" onerror="this.style.display='none'">
            <span class="font-semibold tracking-tight text-white">{{ $company['name'] }}</span>
        </div>

        @if ($valid)
            <div class="card text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-emerald-500/15 ring-1 ring-inset ring-emerald-500/30 flex items-center justify-center mb-3">
                    <svg viewBox="0 0 24 24" fill="none" class="w-8 h-8 text-emerald-400" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                </div>
                <h1 class="text-lg font-semibold text-white">Dokumen Terverifikasi</h1>
                <p class="text-sm text-slate-400 mt-1">Dokumen ini terdaftar &amp; sah diterbitkan oleh {{ $company['name'] }}.</p>

                <dl class="text-sm text-left mt-5 space-y-2.5">
                    <div class="flex justify-between gap-3 border-b border-slate-700/60 pb-2.5">
                        <dt class="text-slate-400">Nomor</dt>
                        <dd class="font-mono font-semibold text-white text-right">{{ $doc['number'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-700/60 pb-2.5">
                        <dt class="text-slate-400">Jenis</dt>
                        <dd class="text-slate-100 text-right">{{ $doc['type_label'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-700/60 pb-2.5">
                        <dt class="text-slate-400">Tanggal</dt>
                        <dd class="text-slate-100 text-right">{{ $doc['doc_date'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-slate-700/60 pb-2.5">
                        <dt class="text-slate-400">Status</dt>
                        <dd class="text-right"><span class="badge badge-green">{{ $doc['status'] }}</span></dd>
                    </div>
                    @if ($doc['released_at'])
                    <div class="flex justify-between gap-3 border-b border-slate-700/60 pb-2.5">
                        <dt class="text-slate-400">Dirilis</dt>
                        <dd class="text-slate-100 text-right">{{ $doc['released_at'] }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-400">Kode Verifikasi</dt>
                        <dd class="font-mono text-slate-300 text-right">{{ $doc['code'] }}</dd>
                    </div>
                </dl>
            </div>
        @else
            <div class="card text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-red-500/15 ring-1 ring-inset ring-red-500/30 flex items-center justify-center mb-3">
                    <svg viewBox="0 0 24 24" fill="none" class="w-8 h-8 text-red-400" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </div>
                <h1 class="text-lg font-semibold text-white">Dokumen Tidak Terverifikasi</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Dokumen tidak ditemukan atau belum diterbitkan secara resmi. Pastikan Anda memindai QR dari dokumen asli.
                </p>
            </div>
        @endif

        <p class="text-center text-xs text-slate-500 mt-5">
            Halaman verifikasi resmi · {{ $company['website'] }}
        </p>
    </div>
</body>
</html>
