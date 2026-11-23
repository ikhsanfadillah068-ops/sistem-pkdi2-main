<x-layouts.admin :title="'Persetujuan BA Selisih '.$report->ba_number" group="Portal Client" role="client">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('client.orders.show', $order['order_slug']) }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                <x-ui.icon name="arrow-left" class="h-4 w-4" />
                Kembali ke Detail Pesanan {{ $order['order_number'] }}
            </a>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-2xl font-semibold tracking-tight tabular-nums">{{ $report->ba_number }}</h1>
                <x-ui.badge :variant="$report->isPending() ? 'danger' : 'success'" :dot="true">
                    {{ $report->isPending() ? 'Menunggu Persetujuan Digital (ACC)' : 'Disetujui (ACC)' }}
                </x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Berita Acara Selisih Kuantitas / Kondisi Drum · Pesanan {{ $order['order_number'] }} · {{ $order['client_company'] }}
            </p>
        </div>

        <x-ui.card title="Perbandingan Angka PO vs Aktual Gudang PKDI" description="Periksa perbandingan kuantitas dan dokumentasi foto kondisi drum sebelum melakukan ACC digital.">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Angka Pengajuan PO Client</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ $report->po_qty }} <span class="text-sm font-normal text-muted-foreground">{{ $order['unit'] }}</span></p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Angka Aktual Gate-In Gudang</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ $report->total_physical_qty }} <span class="text-sm font-normal text-muted-foreground">{{ $order['unit'] }}</span></p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <span class="badge badge-success">Baik: {{ $report->actual_good_qty }}</span>
                        <span class="badge badge-warning">Penyok: {{ $report->actual_dent_qty }}</span>
                        <span class="badge badge-danger">Reject: {{ $report->actual_reject_qty }}</span>
                    </div>
                </div>
            </div>

            @if (!empty($report->notes))
                <div class="mt-4 rounded-lg border bg-muted/30 p-3.5 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Catatan Kepala Gudang</p>
                    <p class="mt-1">{{ $report->notes }}</p>
                </div>
            @endif

            @if (!empty($report->evidence_urls) && $report->gate_log_id)
                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Dokumentasi Foto Bukti Kondisi Drum</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($report->evidence_urls as $idx => $url)
                            <a href="{{ route('gate-logs.evidence.show', ['gateLog' => $report->gate_log_id, 'index' => $idx]) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                <x-ui.icon name="image" class="h-4 w-4" />
                                Buka Foto Bukti #{{ $idx + 1 }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 border-t pt-4">
                @if ($report->isPending())
                    <form action="{{ route('client.discrepancies.approve', $report->id) }}" method="POST" class="space-y-4" data-prevent-double-submit>
                        @csrf
                        <p class="text-xs leading-relaxed text-muted-foreground">
                            Dengan menekan tombol <strong>Setujui (ACC) Berita Acara</strong>, sistem akan merekam alamat IP, timestamp, dan identitas akun Anda sebagai tanda tangan digital sah untuk membuka kunci proses rekondisi dan penagihan berdasarkan kuantitas layak ({{ $report->actual_good_qty + $report->actual_dent_qty }} {{ $order['unit'] }}).
                        </p>
                        <button type="submit" class="btn btn-success w-full sm:w-auto">
                            <x-ui.icon name="check-circle" class="h-4 w-4" />
                            Setujui (ACC) Berita Acara
                        </button>
                    </form>
                @else
                    <x-ui.alert variant="success" title="Berita Acara Telah Disetujui (ACC)">
                        Disetujui secara digital oleh <strong>{{ $report->approved_by_name }}</strong> pada {{ $report->approved_at?->timezone('Asia/Jakarta')->format('d M Y, H.i') }} WIB (IP: {{ $report->approved_ip }}).
                    </x-ui.alert>
                @endif
            </div>
        </x-ui.card>
    </div>
</x-layouts.admin>
