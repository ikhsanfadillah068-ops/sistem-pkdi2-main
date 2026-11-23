@props([
    'id' => 'pkdi-puzzle-captcha',
])
<div
    id="{{ $id }}"
    class="space-y-2 select-none"
    style="user-select: none; -webkit-user-select: none; -webkit-touch-callout: none;"
    data-puzzle-captcha
    data-challenge-url="{{ route('captcha.challenge') }}"
    data-verify-url="{{ route('captcha.verify') }}">
    <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">Verifikasi Keamanan</span>
        <span class="text-[11px] font-medium text-emerald-600 hidden" data-captcha-badge>Terverifikasi</span>
    </div>

    <input type="hidden" name="captcha_verified_token" value="" data-captcha-token>

    <div
        class="relative w-full overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 shadow-xs"
        style="aspect-ratio: 320 / 156; touch-action: none; user-select: none; -webkit-user-select: none;">
        <canvas width="320" height="156" class="block h-full w-full" data-captcha-bg></canvas>
        <canvas width="320" height="156" class="pointer-events-none absolute inset-0 block h-full w-full" data-captcha-piece></canvas>

        <button
            type="button"
            title="Muat ulang puzzle"
            aria-label="Muat ulang puzzle keamanan"
            data-captcha-refresh
            class="absolute right-2.5 top-2.5 z-10 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-white/95 text-zinc-700 shadow-md ring-1 ring-black/10 transition hover:bg-white hover:text-[#800000] active:scale-95">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                <polyline points="21 3 21 9 15 9" />
            </svg>
        </button>
    </div>

    <div
        class="relative flex h-12 w-full items-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 transition-colors"
        style="height: 48px; touch-action: none; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none;"
        data-captcha-track>
        <div
            class="pointer-events-none absolute inset-y-0 left-0 bg-[#800000]/12 transition-colors"
            style="width: 0px;"
            data-captcha-fill></div>

        <span
            class="pointer-events-none w-full pr-3 text-center text-xs font-medium text-zinc-500 transition-colors"
            style="padding-left: 56px;"
            data-captcha-label>
            Geser kepingan ke tempatnya
        </span>

        <button
            type="button"
            aria-label="Geser untuk mencocokkan puzzle"
            data-captcha-handle
            class="absolute left-0 top-0 bottom-0 z-10 flex cursor-grab items-center justify-center rounded-xl text-white shadow-md transition-colors active:cursor-grabbing"
            style="width: 56px; height: 100%; background: linear-gradient(135deg, #800000 0%, #5a0018 100%); transform: translate3d(0px, 0, 0); touch-action: none; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; will-change: transform;">
            <svg data-icon-arrows xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m6 17 5-5-5-5" />
                <path d="m13 17 5-5-5-5" />
            </svg>
            <svg data-icon-check xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6 9 17l-5-5" />
            </svg>
        </button>
    </div>

    <p class="text-xs text-red-600 {{ $errors->has('captcha') ? '' : 'hidden' }}" data-captcha-error role="alert">
        {{ $errors->first('captcha') }}
    </p>
</div>

<script>
(function () {
    function mulberry32(seed) {
        return function () {
            var t = (seed += 0x6D2B79F5);
            t = Math.imul(t ^ (t >>> 15), t | 1);
            t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    }

    function drawJigsawPath(ctx, x, y, size) {
        var r = size * 0.22;
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x + size * 0.35, y);
        ctx.arc(x + size * 0.5, y - r * 0.45, r, Math.PI * 0.85, Math.PI * 0.15, false);
        ctx.lineTo(x + size, y);
        ctx.lineTo(x + size, y + size * 0.35);
        ctx.arc(x + size + r * 0.45, y + size * 0.5, r, Math.PI * 1.35, Math.PI * 0.65, false);
        ctx.lineTo(x + size, y + size);
        ctx.lineTo(x, y + size);
        ctx.lineTo(x, y + size * 0.65);
        ctx.arc(x + r * 0.45, y + size * 0.5, r, Math.PI * 0.65, Math.PI * 1.35, true);
        ctx.lineTo(x, y);
        ctx.closePath();
    }

    function drawAbstractArt(ctx, width, height, seed) {
        var rand = mulberry32(seed || 12345);

        var palettes = [
            ['#c97b53', '#8b5280', '#4f28a8'],
            ['#b86b4b', '#7b3e6e', '#3b2496'],
            ['#c06b52', '#7d4b7a', '#31329c'],
            ['#b35c4c', '#6e3b68', '#42268f']
        ];
        var pal = palettes[Math.floor(rand() * palettes.length)];
        var grad = ctx.createLinearGradient(0, 0, width, height);
        grad.addColorStop(0, pal[0]);
        grad.addColorStop(0.52, pal[1]);
        grad.addColorStop(1, pal[2]);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, width, height);

        var shapesColors = [
            'rgba(255, 214, 102, 0.55)',
            'rgba(98, 224, 90, 0.58)',
            'rgba(210, 42, 152, 0.60)',
            'rgba(52, 198, 235, 0.50)',
            'rgba(244, 154, 193, 0.48)',
            'rgba(62, 34, 168, 0.65)',
            'rgba(255, 255, 255, 0.25)',
            'rgba(34, 84, 189, 0.55)'
        ];

        for (var i = 0; i < 9; i++) {
            ctx.save();
            ctx.fillStyle = shapesColors[Math.floor(rand() * shapesColors.length)];
            ctx.beginPath();
            ctx.ellipse(
                rand() * width,
                rand() * height,
                16 + rand() * 44,
                12 + rand() * 34,
                rand() * Math.PI,
                0,
                Math.PI * 2
            );
            ctx.fill();
            ctx.restore();
        }

        for (var j = 0; j < 7; j++) {
            ctx.save();
            ctx.fillStyle = shapesColors[Math.floor(rand() * shapesColors.length)];
            var cx = rand() * width;
            var cy = rand() * height;
            ctx.beginPath();
            ctx.moveTo(cx, cy - (20 + rand() * 35));
            ctx.lineTo(cx + (20 + rand() * 40), cy + (18 + rand() * 35));
            ctx.lineTo(cx - (20 + rand() * 40), cy + (14 + rand() * 35));
            ctx.closePath();
            ctx.fill();
            ctx.restore();
        }

        for (var k = 0; k < 14; k++) {
            ctx.save();
            ctx.strokeStyle = shapesColors[Math.floor(rand() * shapesColors.length)];
            ctx.lineWidth = 1 + rand() * 2;
            ctx.beginPath();
            ctx.moveTo(rand() * width, rand() * height);
            ctx.lineTo(rand() * width, rand() * height);
            ctx.stroke();
            ctx.restore();
        }
    }

    function initPuzzleCaptchas() {
        document.querySelectorAll('[data-puzzle-captcha]').forEach(function (root) {
            if (root.dataset.initialized === '1') return;
            root.dataset.initialized = '1';

            var bgCanvas = root.querySelector('[data-captcha-bg]');
            var pieceCanvas = root.querySelector('[data-captcha-piece]');
            var refreshBtn = root.querySelector('[data-captcha-refresh]');
            var track = root.querySelector('[data-captcha-track]');
            var fill = root.querySelector('[data-captcha-fill]');
            var handle = root.querySelector('[data-captcha-handle]');
            var label = root.querySelector('[data-captcha-label]');
            var tokenInput = root.querySelector('[data-captcha-token]');
            var badge = root.querySelector('[data-captcha-badge]');
            var errorEl = root.querySelector('[data-captcha-error]');
            var iconArrows = root.querySelector('[data-icon-arrows]');
            var iconCheck = root.querySelector('[data-icon-check]');
            var challengeUrl = root.getAttribute('data-challenge-url');
            var verifyUrl = root.getAttribute('data-verify-url');
            var parentForm = root.closest('form');

            function getCsrfToken() {
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta && csrfMeta.getAttribute('content')) {
                    return csrfMeta.getAttribute('content');
                }
                if (parentForm) {
                    var formToken = parentForm.querySelector('input[name="_token"]');
                    if (formToken && formToken.value) return formToken.value;
                }
                return '';
            }

            var PIECE_SIZE = 44;
            var START_X = 12;
            var state = {
                token: '',
                width: 320,
                height: 156,
                targetX: 180,
                targetY: 56,
                decoyX: 60,
                decoyY: 50,
                seed: 12345,
                currentX: START_X,
                verified: false,
                verifying: false,
                dragging: false,
                dragMode: null, // 'touch' | 'mouse'
                activeTouchId: null,
                startClientX: 0,
                startHandleOffset: 0,
                maxTrackTravel: 240,
                lastTouchEndAt: 0,
                pieceSnapshot: null
            };

            function showError(msg) {
                if (!errorEl) return;
                if (msg) {
                    errorEl.textContent = msg;
                    errorEl.classList.remove('hidden');
                } else {
                    errorEl.textContent = '';
                    errorEl.classList.add('hidden');
                }
            }

            function getTrackMetrics() {
                var rect = track.getBoundingClientRect();
                var trackW = rect.width || track.clientWidth || 320;
                var handleW = handle.getBoundingClientRect().width || handle.offsetWidth || 56;
                var maxTravel = Math.max(1, trackW - handleW);
                return { rect: rect, trackW: trackW, handleW: handleW, maxTravel: maxTravel };
            }

            function renderPieceAt(x) {
                var pCtx = pieceCanvas.getContext('2d');
                pCtx.clearRect(0, 0, state.width, state.height);
                if (!state.pieceSnapshot) return;

                pCtx.save();
                pCtx.shadowColor = 'rgba(0, 0, 0, 0.45)';
                pCtx.shadowBlur = 8;
                pCtx.shadowOffsetX = 0;
                pCtx.shadowOffsetY = 2;
                pCtx.drawImage(state.pieceSnapshot, x - 12, state.targetY - 12);
                pCtx.restore();
            }

            function syncSliderUi(pieceX) {
                state.currentX = pieceX;
                var maxPieceX = state.width - PIECE_SIZE - 14;
                var ratio = Math.max(0, Math.min(1, (pieceX - START_X) / Math.max(1, maxPieceX - START_X)));
                var m = getTrackMetrics();
                state.maxTrackTravel = m.maxTravel;
                var handleX = ratio * m.maxTravel;

                handle.style.transform = 'translate3d(' + handleX.toFixed(1) + 'px, 0, 0)';
                fill.style.width = (handleX + m.handleW * 0.5).toFixed(1) + 'px';
                renderPieceAt(pieceX);
            }

            function updateByHandleX(handleX) {
                var m = getTrackMetrics();
                var clamped = Math.max(0, Math.min(m.maxTravel, handleX));
                var maxPieceX = state.width - PIECE_SIZE - 14;
                var pieceX = START_X + (clamped / m.maxTravel) * (maxPieceX - START_X);
                syncSliderUi(pieceX);
            }

            function resetSlider(message, isError) {
                state.verified = false;
                state.verifying = false;
                tokenInput.value = '';
                if (badge) badge.classList.add('hidden');
                if (iconArrows) iconArrows.classList.remove('hidden');
                if (iconCheck) iconCheck.classList.add('hidden');
                handle.style.background = isError
                    ? 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)'
                    : 'linear-gradient(135deg, #800000 0%, #5a0018 100%)';
                track.classList.remove('border-emerald-400', 'bg-emerald-50');
                fill.style.backgroundColor = isError ? 'rgba(220, 38, 38, 0.14)' : 'rgba(128, 0, 0, 0.12)';
                label.textContent = message || 'Geser kepingan ke tempatnya';
                label.classList.remove('text-emerald-700');
                label.classList.toggle('text-red-600', Boolean(isError));
                label.classList.toggle('text-zinc-500', !isError);

                syncSliderUi(START_X);
                if (isError) {
                    setTimeout(function () {
                        if (!state.verified && !state.dragging) {
                            handle.style.background = 'linear-gradient(135deg, #800000 0%, #5a0018 100%)';
                            fill.style.backgroundColor = 'rgba(128, 0, 0, 0.12)';
                            label.textContent = 'Geser kepingan ke tempatnya';
                            label.classList.remove('text-red-600');
                            label.classList.add('text-zinc-500');
                        }
                    }, 1200);
                }
            }

            function drawChallenge() {
                var bgCtx = bgCanvas.getContext('2d');
                bgCtx.clearRect(0, 0, state.width, state.height);

                drawAbstractArt(bgCtx, state.width, state.height, state.seed);

                var snap = document.createElement('canvas');
                snap.width = PIECE_SIZE + 28;
                snap.height = PIECE_SIZE + 28;
                var sCtx = snap.getContext('2d');
                sCtx.save();
                drawJigsawPath(sCtx, 12, 12, PIECE_SIZE);
                sCtx.clip();
                sCtx.drawImage(
                    bgCanvas,
                    state.targetX - 12,
                    state.targetY - 12,
                    PIECE_SIZE + 28,
                    PIECE_SIZE + 28,
                    0,
                    0,
                    PIECE_SIZE + 28,
                    PIECE_SIZE + 28
                );
                sCtx.restore();

                sCtx.save();
                drawJigsawPath(sCtx, 12, 12, PIECE_SIZE);
                sCtx.strokeStyle = 'rgba(255, 255, 255, 0.92)';
                sCtx.lineWidth = 2;
                sCtx.stroke();
                sCtx.restore();
                state.pieceSnapshot = snap;

                bgCtx.save();
                drawJigsawPath(bgCtx, state.decoyX, state.decoyY, PIECE_SIZE);
                bgCtx.fillStyle = 'rgba(20, 12, 26, 0.42)';
                bgCtx.fill();
                bgCtx.strokeStyle = 'rgba(255, 255, 255, 0.45)';
                bgCtx.lineWidth = 1.5;
                bgCtx.stroke();
                bgCtx.restore();

                bgCtx.save();
                drawJigsawPath(bgCtx, state.targetX, state.targetY, PIECE_SIZE);
                bgCtx.fillStyle = 'rgba(18, 12, 24, 0.62)';
                bgCtx.fill();
                bgCtx.strokeStyle = 'rgba(255, 255, 255, 0.85)';
                bgCtx.lineWidth = 1.8;
                bgCtx.stroke();
                bgCtx.restore();

                resetSlider('Geser kepingan ke tempatnya', false);
            }

            function loadChallenge() {
                showError('');
                label.textContent = 'Memuat verifikasi keamanan...';
                fetch(challengeUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    state.token = data.token;
                    state.targetX = Number(data.target_x);
                    state.targetY = Number(data.target_y);
                    state.decoyX = Number(data.decoy_x);
                    state.decoyY = Number(data.decoy_y);
                    state.seed = Number(data.seed);
                    drawChallenge();
                })
                .catch(function () {
                    label.textContent = 'Gagal memuat puzzle. Klik muat ulang.';
                });
            }

            function verifyPosition() {
                if (state.verifying || state.verified) return;
                state.verifying = true;
                label.textContent = 'Memverifikasi...';
                fetch(verifyUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        token: state.token,
                        position_x: Math.round(state.currentX)
                    })
                })
                .then(function (res) {
                    return res.json().then(function (body) { return { ok: res.ok, body: body }; });
                })
                .then(function (result) {
                    state.verifying = false;
                    if (result.ok && result.body && result.body.verified) {
                        state.verified = true;
                        tokenInput.value = result.body.verified_token;
                        syncSliderUi(state.targetX);
                        showError('');
                        if (badge) badge.classList.remove('hidden');
                        if (iconArrows) iconArrows.classList.add('hidden');
                        if (iconCheck) iconCheck.classList.remove('hidden');
                        handle.style.background = 'linear-gradient(135deg, #16a34a 0%, #15803d 100%)';
                        fill.style.backgroundColor = 'rgba(22, 163, 74, 0.16)';
                        track.classList.add('border-emerald-400', 'bg-emerald-50');
                        label.textContent = 'Verifikasi keamanan berhasil';
                        label.classList.remove('text-zinc-500', 'text-red-600');
                        label.classList.add('text-emerald-700');
                    } else if (result.body && result.body.expired) {
                        loadChallenge();
                    } else {
                        resetSlider('Belum tepat, coba geser lagi', true);
                    }
                })
                .catch(function () {
                    state.verifying = false;
                    resetSlider('Gagal memverifikasi, coba lagi', true);
                });
            }

            function beginDrag(clientX, mode, touchId, startedOnHandle) {
                if (state.verified || state.verifying || !state.token) return false;
                var m = getTrackMetrics();
                state.dragging = true;
                state.dragMode = mode;
                state.activeTouchId = touchId;

                if (startedOnHandle) {
                    var maxPieceX = state.width - PIECE_SIZE - 14;
                    var curRatio = Math.max(0, Math.min(1, (state.currentX - START_X) / Math.max(1, maxPieceX - START_X)));
                    state.startClientX = clientX;
                    state.startHandleOffset = curRatio * m.maxTravel;
                } else {
                    // Jika pengguna menekan langsung area track di HP, langsung pindahkan handle ke posisi jari
                    var targetHandleX = clientX - m.rect.left - (m.handleW * 0.5);
                    state.startClientX = clientX;
                    state.startHandleOffset = Math.max(0, Math.min(m.maxTravel, targetHandleX));
                    updateByHandleX(state.startHandleOffset);
                }
                return true;
            }

            function moveDrag(clientX) {
                if (!state.dragging || state.verified) return;
                var delta = clientX - state.startClientX;
                updateByHandleX(state.startHandleOffset + delta);
            }

            function finishDrag() {
                if (!state.dragging) return;
                state.dragging = false;
                state.dragMode = null;
                state.activeTouchId = null;
                if (!state.verified && state.currentX > START_X + 4) {
                    verifyPosition();
                } else if (!state.verified) {
                    syncSliderUi(START_X);
                }
            }

            // === TOUCH EVENTS (100% Native Support untuk HP Android & iOS Safari) ===
            function onTouchStart(e) {
                if (state.verified || state.verifying || !state.token) return;
                if (!e.touches || e.touches.length === 0) return;
                var t = e.changedTouches && e.changedTouches.length ? e.changedTouches[0] : e.touches[0];
                var startedOnHandle = e.target === handle || handle.contains(e.target);
                if (beginDrag(t.clientX, 'touch', t.identifier, startedOnHandle)) {
                    if (e.cancelable) e.preventDefault();
                }
            }

            function findActiveTouch(touchList) {
                if (!touchList) return null;
                for (var i = 0; i < touchList.length; i++) {
                    if (touchList[i].identifier === state.activeTouchId) {
                        return touchList[i];
                    }
                }
                return touchList[0] || null;
            }

            function onTouchMove(e) {
                if (!state.dragging || state.dragMode !== 'touch') return;
                var t = findActiveTouch(e.touches);
                if (!t) return;
                if (e.cancelable) e.preventDefault();
                moveDrag(t.clientX);
            }

            function onTouchEnd(e) {
                if (!state.dragging || state.dragMode !== 'touch') return;
                state.lastTouchEndAt = Date.now();
                finishDrag();
            }

            track.addEventListener('touchstart', onTouchStart, { passive: false });
            window.addEventListener('touchmove', onTouchMove, { passive: false });
            window.addEventListener('touchend', onTouchEnd, { passive: true });
            window.addEventListener('touchcancel', onTouchEnd, { passive: true });

            // === MOUSE EVENTS (Desktop) ===
            function onMouseDown(e) {
                if (e.button !== 0) return;
                if (Date.now() - state.lastTouchEndAt < 600) return;
                var startedOnHandle = e.target === handle || handle.contains(e.target);
                if (beginDrag(e.clientX, 'mouse', null, startedOnHandle)) {
                    e.preventDefault();
                }
            }

            function onMouseMove(e) {
                if (!state.dragging || state.dragMode !== 'mouse') return;
                moveDrag(e.clientX);
            }

            function onMouseUp() {
                if (!state.dragging || state.dragMode !== 'mouse') return;
                finishDrag();
            }

            track.addEventListener('mousedown', onMouseDown);
            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);

            // Resize listener agar posisi handle selalu presisi jika rotasi HP berubah
            window.addEventListener('resize', function () {
                if (state.token) {
                    syncSliderUi(state.verified ? state.targetX : state.currentX);
                }
            });

            if (refreshBtn) {
                refreshBtn.addEventListener('click', function () {
                    loadChallenge();
                });
            }

            if (parentForm) {
                parentForm.addEventListener('submit', function (e) {
                    if (!state.verified || !tokenInput.value) {
                        e.preventDefault();
                        showError('Silakan geser kepingan puzzle ke tempatnya terlebih dahulu.');
                    }
                });
            }

            loadChallenge();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPuzzleCaptchas);
    } else {
        initPuzzleCaptchas();
    }
})();
</script>
