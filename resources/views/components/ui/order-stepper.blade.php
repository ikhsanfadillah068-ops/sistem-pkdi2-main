@props(['order'])
@php
    $progress = $order['progress'] ?? [];
    $steps = $progress['steps'] ?? [];
    $activeStep = $progress['active_step'] ?? 1;
    $totalSteps = $progress['total_steps'] ?? count($steps);
    $trackVariant = $progress['track_variant'] ?? (($order['is_waste_transport'] ?? false) ? 'WASTE_TRANSPORT' : 'RECONDITIONING');
    $trackTitle = $progress['track_title'] ?? ($trackVariant === 'WASTE_TRANSPORT'
        ? 'Pengangkutan Limbah'
        : 'Rekondisi Drum');
    $issueAlert = $progress['issue_alert'] ?? null;
    $invoice = $order['invoice'] ?? null;
    $hasPublishedInvoice = ! empty($invoice) && ($invoice['status'] ?? '') !== 'DRAFT';
    $timelineCount = count($order['timeline'] ?? []);
    $gridColsClass = $totalSteps === 4 ? 'grid-cols-4' : 'grid-cols-5';
    $bst = $order['billing_status_meta'] ?? ['label' => 'Belum Ditagihkan', 'tone' => 'neutral'];
    $pst = $order['payment_status_meta'] ?? ['label' => 'Menunggu Bayar', 'tone' => 'warning'];
@endphp
<section class="card overflow-hidden" aria-label="Status progres permintaan">
    <div class="flex flex-col gap-3 border-b bg-muted/25 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge {{ $trackVariant === 'WASTE_TRANSPORT' ? 'badge-info' : 'badge-soft' }}">{{ $trackTitle }}</span>
                <span class="text-xs font-medium text-muted-foreground">
                    Tahap {{ $activeStep }} dari {{ $totalSteps }}
                    @if (! empty($progress['updated_at']))
                        · Diperbarui {{ $progress['updated_at'] }}
                    @endif
                </span>
            </div>
            <h2 class="mt-1.5 text-base font-semibold tracking-tight text-foreground sm:text-lg">{{ $progress['headline'] ?? 'Status Permintaan' }}</h2>
            <p class="mt-0.5 text-sm text-muted-foreground">{{ $progress['summary'] ?? '' }}</p>
        </div>

        @if ($hasPublishedInvoice)
            @php
                $invoiceSlug = $invoice['invoice_slug'] ?? str_replace('/', '-', $invoice['invoice_number']);
            @endphp
            <div class="shrink-0">
                <a href="{{ route('client.invoices.show', $invoiceSlug) }}"
                    class="btn {{ ($invoice['balance'] ?? 0) > 0 ? 'btn-primary' : 'btn-outline btn-sm' }} w-full sm:w-auto">
                    <x-ui.icon name="receipt" />
                    {{ ($invoice['balance'] ?? 0) > 0 ? 'Lihat & Bayar Tagihan' : 'Lihat Tagihan Lunas' }}
                </a>
            </div>
        @endif
    </div>

    @if ($issueAlert)
        <div class="px-5 pt-4 sm:px-6">
            <x-ui.alert :variant="$issueAlert['variant']" :title="$issueAlert['title']">
                {{ $issueAlert['text'] }}
            </x-ui.alert>
        </div>
    @endif

    <div class="px-3 py-5 sm:px-6">
        <ol class="grid {{ $gridColsClass }} items-start" aria-label="Tahapan pemrosesan layanan">
            @foreach ($steps as $idx => $step)
                @php
                    $isLast = $idx === count($steps) - 1;
                    $prevDone = $idx > 0 && ($steps[$idx - 1]['state'] ?? '') === 'done';
                    $lineDone = ($step['state'] ?? '') === 'done';
                    $circleClass = match ($step['state']) {
                        'done' => 'step-done',
                        'current' => 'step-current',
                        'warning' => 'step-warning',
                        'danger' => 'step-danger',
                        default => 'step-todo',
                    };
                @endphp
                <li class="relative flex flex-col items-center px-1 text-center"{!! $step['state'] === 'current' ? ' aria-current="step"' : '' !!}>
                    @if ($idx > 0)
                        <span aria-hidden="true" class="absolute left-0 right-1/2 top-4 h-0.5 -translate-y-1/2 {{ $prevDone ? 'bg-primary' : 'bg-border' }}"></span>
                    @endif
                    @if (! $isLast)
                        <span aria-hidden="true" class="absolute left-1/2 right-0 top-4 h-0.5 -translate-y-1/2 {{ $lineDone ? 'bg-primary' : 'bg-border' }}"></span>
                    @endif

                    <span class="step relative z-10 {{ $circleClass }}" aria-hidden="true">
                        @if ($step['state'] === 'done')
                            <x-ui.icon name="check" class="h-4 w-4" />
                        @elseif ($step['state'] === 'danger')
                            <x-ui.icon name="alert-triangle" class="h-4 w-4" />
                        @elseif ($step['state'] === 'warning')
                            <x-ui.icon name="alert-circle" class="h-4 w-4" />
                        @else
                            <x-ui.icon :name="$step['icon']" class="h-4 w-4" />
                        @endif
                    </span>

                    <span class="sr-only">Tahap {{ $step['number'] }}: {{ $step['label'] }} ({{ $step['state_text'] }})</span>

                    <span @class([
                        'mt-2 text-[11px] leading-tight sm:text-xs',
                        'font-semibold text-foreground' => in_array($step['state'], ['done', 'current', 'warning', 'danger'], true),
                        'font-medium text-muted-foreground' => $step['state'] === 'todo',
                    ])>
                        <span class="sm:hidden">{{ $step['short_label'] }}</span>
                        <span class="hidden sm:inline">{{ $step['label'] }}</span>
                    </span>

                    @if (! empty($step['time']))
                        <time class="mt-0.5 hidden text-[11px] text-muted-foreground tabular-nums md:block">{{ $step['time'] }}</time>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-t bg-muted/15 px-5 py-2.5 text-xs sm:px-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-muted-foreground">Jalur Penagihan:</span>
            <x-ui.badge :variant="$bst['tone']">{{ $bst['label'] }}</x-ui.badge>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-muted-foreground">Jalur Pembayaran:</span>
            <x-ui.badge :variant="$pst['tone']">{{ $pst['label'] }}</x-ui.badge>
        </div>
    </div>

    <div class="accordion rounded-none border-0 border-t">
        <details>
            <summary>
                <span class="inline-flex items-center gap-2">
                    <x-ui.icon name="history" class="h-4 w-4 text-muted-foreground" />
                    <span>Riwayat Progres ({{ $timelineCount }} aktivitas)</span>
                </span>
                <x-ui.icon name="chevron-down" class="h-4 w-4 text-muted-foreground transition-transform" />
            </summary>
            <div>
                @if (! empty($order['timeline']))
                    <x-ui.timeline :items="$order['timeline']" />
                @else
                    <p class="text-sm text-muted-foreground">Belum ada catatan aktivitas untuk permintaan ini.</p>
                @endif
            </div>
        </details>
    </div>
</section>
