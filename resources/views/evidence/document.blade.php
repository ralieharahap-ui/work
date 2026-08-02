<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->number ? $document->number . ' — ' : '' }}{{ $document->title }}</title>
    <style>
        @page { margin: 18mm 16mm 20mm 16mm; }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11.5pt;
            line-height: 1.55;
            color: #16181d;
            margin: 0;
        }

        /* Di layar, halaman ditampilkan seperti lembar A4 agar hasil cetaknya terduga. */
        .sheet { background: #fff; }
        @media screen {
            body { background: #eceff3; padding: 24px 12px; }
            .sheet {
                width: 210mm;
                min-height: 297mm;
                margin: 0 auto;
                padding: 18mm 16mm 20mm 16mm;
                box-sizing: border-box;
                box-shadow: 0 8px 28px rgba(15, 23, 42, .18);
            }
            .landscape .sheet { width: 297mm; min-height: 210mm; }
        }

        .letterhead { border-bottom: 2.5px solid #0f8cbd; padding-bottom: 8px; margin-bottom: 18px; }
        .letterhead td { vertical-align: middle; border: 0; padding: 0; }
        .letterhead .logo { width: 86px; }
        /* Rasio asli logo 397×238 dipertahankan agar tidak gepeng saat dicetak. */
        .letterhead .logo img { width: 73px; height: 44px; }
        .letterhead .company { font-size: 15pt; font-weight: bold; letter-spacing: .4px; color: #0b5f80; }
        .letterhead .tagline { font-size: 8.5pt; color: #5b6472; text-transform: uppercase; letter-spacing: 1.4px; }

        .doc-title { text-align: center; margin: 0 0 2px; font-size: 14pt; text-transform: uppercase; letter-spacing: .6px; }
        .doc-number { text-align: center; margin: 0 0 20px; font-size: 10pt; color: #5b6472; }

        .content { text-align: justify; }
        .content h1, .content h2, .content h3, .content h4 { margin: 14px 0 6px; line-height: 1.3; }
        .content p { margin: 0 0 9px; }
        .content ul, .content ol { margin: 0 0 9px; padding-left: 20px; }
        .content table { border-collapse: collapse; width: 100%; margin: 0 0 12px; font-size: 10.5pt; }
        .content table td, .content table th { border: 1px solid #b8c0cc; padding: 6px 8px; vertical-align: top; }
        .content table th { background: #eef4f8; text-align: left; }
        .content img { max-width: 100%; }
        .content blockquote { margin: 0 0 9px; padding-left: 12px; border-left: 3px solid #b8c0cc; color: #444b56; }

        .fields { margin: 0 0 16px; font-size: 10.5pt; border-collapse: collapse; width: 100%; }
        .fields td { border: 1px solid #b8c0cc; padding: 6px 8px; }
        .fields td.label { width: 32%; background: #f5f7fa; font-weight: bold; }

        .sign-block { width: 100%; margin-top: 26px; }
        .sign-block td { vertical-align: top; border: 0; }
        .sign-box { width: 46%; font-size: 11pt; }
        .sign-place { margin: 0 0 2px; }
        .sign-role { margin: 0 0 4px; }
        .sign-area { height: 96px; }
        .sign-area img { max-height: 92px; max-width: 220px; }
        .sign-name { margin: 0; font-weight: bold; text-decoration: underline; }
        .sign-position { margin: 2px 0 0; font-size: 9.5pt; color: #5b6472; }
        .sign-pending { color: #a5581b; font-style: italic; font-size: 10pt; padding-top: 34px; }

        .doc-footer { margin-top: 26px; border-top: 1px solid #d5dbe3; padding-top: 6px; font-size: 8pt; color: #79828f; }

        .draft-mark { display: inline-block; border: 1px solid #d97706; color: #b45309; background: #fffbeb;
                      padding: 2px 8px; font-size: 8.5pt; border-radius: 3px; letter-spacing: 1px; }

        .toolbar { max-width: 210mm; margin: 0 auto 14px; font-family: Arial, sans-serif; }
        .toolbar button, .toolbar a {
            display: inline-block; background: #0f8cbd; color: #fff; border: 0; border-radius: 6px;
            padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
        }
        .toolbar .ghost { background: #fff; color: #33404f; border: 1px solid #c7ced8; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="{{ $document->orientation === 'landscape' ? 'landscape' : '' }}">

@unless($forPdf ?? false)
    <div class="toolbar">
        <button type="button" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
        <a class="ghost" href="javascript:window.close()">Tutup</a>
    </div>
@endunless

<div class="sheet">
    <table class="letterhead" width="100%">
        <tr>
            @if($logo)
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="company">{{ config('app.name', 'PT Geosys Energi Prima') }}</div>
                <div class="tagline">Dokumen Bukti Penyelesaian Tugas</div>
            </td>
        </tr>
    </table>

    <h1 class="doc-title">{{ $document->title }}</h1>
    <p class="doc-number">
        @if($document->number) Nomor: {{ $document->number }} @endif
        @unless($document->is_signed)
            <br><span class="draft-mark">DRAF — BELUM DITANDATANGANI</span>
        @endunless
    </p>

    @php
        $fieldLabels = collect($document->template?->fields ?? [])
            ->filter(fn ($f) => is_array($f) && ! empty($f['key']))
            ->mapWithKeys(fn ($f) => [$f['key'] => $f['label'] ?? $f['key']]);
        $filledData = collect($document->data ?? [])->filter(fn ($v) => filled($v) && ! is_array($v));
    @endphp

    @if($filledData->isNotEmpty())
        <table class="fields">
            @foreach($filledData as $key => $value)
                <tr>
                    <td class="label">{{ $fieldLabels[$key] ?? \Illuminate\Support\Str::headline((string) $key) }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="content">{!! $document->content_html !!}</div>

    <table class="sign-block">
        <tr>
            <td class="sign-box">
                <p class="sign-place">&nbsp;</p>
                <p class="sign-role">Mengetahui,</p>
                <div class="sign-area"></div>
                <p class="sign-name">{{ $task?->creator?->name ?: '................................' }}</p>
                <p class="sign-position">Pemberi Tugas</p>
            </td>
            <td style="width: 8%"></td>
            <td class="sign-box">
                @php $signDate = ($document->signed_at ?? now())->copy()->locale('id'); @endphp
                <p class="sign-place">
                    {{ $document->signature_place ? $document->signature_place . ', ' : '' }}{{ $signDate->translatedFormat('j F Y') }}
                </p>
                <p class="sign-role">Penanggung Jawab (PIC),</p>

                @if($signature)
                    <div class="sign-area"><img src="{{ $signature }}" alt="Tanda tangan"></div>
                @else
                    <div class="sign-area"><span class="sign-pending">(belum ditandatangani)</span></div>
                @endif

                <p class="sign-name">{{ $document->signer_name ?: ($task?->pic?->name ?? '................................') }}</p>
                <p class="sign-position">{{ $document->signer_position ?: 'Penanggung Jawab Task' }}</p>
            </td>
        </tr>
    </table>

    <div class="doc-footer">
        Dokumen ini dihasilkan otomatis oleh Workspace Tugas {{ config('app.name') }}.
        Referensi task: <strong>{{ $task?->title }}</strong>.
        ID dokumen: {{ $document->id }}.
        @if($document->is_signed)
            Ditandatangani secara elektronik oleh {{ $document->signer_name }}
            pada {{ $document->signed_at?->copy()->locale('id')->translatedFormat('j F Y, H:i') }} WIB.
        @endif
    </div>
</div>

</body>
</html>
