<!DOCTYPE html>
<html lang="id">
<head>
    <x-layouts.head title="Verifikasi Dokumen Resmi" :force-light="true" />
</head>
<body class="min-h-screen bg-muted/40 text-foreground antialiased">
    <div class="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-4 py-10">
        <div class="card overflow-hidden shadow-lg">
            <div class="px-6 py-5 text-white" style="background-color: #881337;">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest" style="color: rgba(255,255,255,0.82);">Sistem Verifikasi Digital Resmi</p>
                        <h1 class="mt-1 text-lg font-bold tracking-tight">PT Pulo Kencana Drum Industri (PKDI)</h1>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-bold tracking-wider" style="background: rgba(255,255,255,0.16);">PKDI</span>
                </div>
            </div>

            <div class="p-6 sm:p-8">
                @if ($valid)
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-2">
                            <span class="badge badge-success">
                                TERVERIFIKASI ASLI
                            </span>
                            <h2 class="text-xl font-bold leading-snug text-foreground">
                                Dokumen Ini Asli dan Terdaftar di Sistem PT Pulo Kencana Drum Industri
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Jenis Dokumen: <strong class="text-foreground">{{ $documentType }}</strong>
                            </p>
                        </div>
                        @if (!empty($qrSvg))
                            <div class="shrink-0 rounded-xl border bg-white p-2 shadow-xs">
                                {!! $qrSvg !!}
                            </div>
                        @endif
                    </div>

                    <dl class="mt-6 divide-y rounded-xl border bg-background">
                        @foreach ($details as $label => $value)
                            <div class="grid gap-1 px-4 py-3 text-sm sm:grid-cols-3">
                                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                                <dd class="font-semibold text-foreground sm:col-span-2 tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                        <div class="grid gap-1 px-4 py-3 text-sm sm:grid-cols-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Waktu Terbit Tercatat</dt>
                            <dd class="font-medium text-foreground sm:col-span-2 tabular-nums">{{ $issuedAt ?? '-' }}</dd>
                        </div>
                        <div class="grid gap-1 px-4 py-3 text-sm sm:grid-cols-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Hash Verifikasi</dt>
                            <dd class="break-all font-mono text-xs text-muted-foreground sm:col-span-2">{{ $qrHash }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="rounded-xl border border-red-200 bg-red-50 p-5 text-center">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                            TIDAK DITEMUKAN
                        </span>
                        <h2 class="mt-3 text-lg font-bold text-red-900">Dokumen Tidak Terdaftar di Sistem PKDI</h2>
                        <p class="mt-1 text-sm text-red-700">
                            Kode QR verifikasi <code class="font-mono">{{ $qrHash }}</code> tidak cocok dengan Sertifikat Rekondisi, Surat Jalan, atau Invoice resmi mana pun di PT Pulo Kencana Drum Industri.
                        </p>
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t pt-4 text-xs text-muted-foreground">
                    <span>© {{ date('Y') }} PT Pulo Kencana Drum Industri</span>
                    <a href="{{ route('login') }}" class="font-semibold text-[#881337] hover:underline">Masuk ke Portal PKDI</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
