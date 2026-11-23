@props([
    'id' => 'pkdi-map-picker',
    'targetSelector' => 'textarea[name="address"]',
    'latitude' => null,
    'longitude' => null,
    'googleMapsUrl' => null,
    'height' => '230px',
    'lightMode' => false,
])
@php
    $initLat = ($latitude !== null && $latitude !== '' && is_numeric($latitude)) ? (float) $latitude : null;
    $initLng = ($longitude !== null && $longitude !== '' && is_numeric($longitude)) ? (float) $longitude : null;
    $initUrl = $googleMapsUrl ?: (($initLat !== null && $initLng !== null) ? sprintf('https://www.google.com/maps?q=%.6F,%.6F', $initLat, $initLng) : '');
    $boxBorder = $lightMode ? 'border-zinc-200 bg-zinc-50 text-zinc-900' : 'border bg-muted/20 text-foreground';
    $inputClass = $lightMode
        ? 'w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 placeholder:text-zinc-400 focus:border-[#800000] focus:outline-none'
        : 'input text-xs';
    $btnClass = $lightMode
        ? 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 shadow-2xs transition hover:bg-zinc-100 whitespace-nowrap'
        : 'btn btn-outline btn-sm whitespace-nowrap';
    $btnLocateClass = $lightMode
        ? 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-[#800000] px-3 py-2 text-xs font-semibold text-white shadow-2xs transition hover:bg-[#660000] whitespace-nowrap'
        : 'btn btn-primary btn-sm whitespace-nowrap';
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<div id="{{ $id }}-container" class="rounded-xl p-3.5 space-y-3 {{ $boxBorder }}"
    data-pkdi-location-picker
    data-target-selector="{{ $targetSelector }}"
    data-init-lat="{{ $initLat ?? '' }}"
    data-init-lng="{{ $initLng ?? '' }}">
    <input type="hidden" name="latitude" value="{{ $initLat ?? '' }}" data-lat-input>
    <input type="hidden" name="longitude" value="{{ $initLng ?? '' }}" data-lng-input>
    <input type="hidden" name="google_maps_url" value="{{ $initUrl }}" data-gmaps-input>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-1.5">
            <x-ui.icon name="map-pin" class="h-4 w-4 text-[#800000]" />
            <span class="text-xs font-semibold">Titik Lokasi Pabrik / Perusahaan (Klik Peta untuk Auto-Fill Alamat)</span>
        </div>
        <span class="text-[11px] opacity-75">OpenStreetMap · Gratis & Otomatis</span>
    </div>

    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="relative flex-1">
            <input type="text"
                class="{{ $inputClass }}"
                placeholder="Cari kawasan industri, jalan, kecamatan, atau kota..."
                aria-label="Cari wilayah di peta"
                data-map-search-input>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="{{ $btnClass }}" data-map-search-btn>
                <x-ui.icon name="search" class="h-3.5 w-3.5" />
                Cari Wilayah
            </button>
            <button type="button" class="{{ $btnLocateClass }}" data-map-locate-btn>
                <x-ui.icon name="navigation" class="h-3.5 w-3.5" />
                Gunakan Lokasi Saat Ini
            </button>
        </div>
    </div>

    <div data-map-search-results class="hidden rounded-lg border bg-white text-zinc-900 divide-y text-xs max-h-40 overflow-y-auto shadow-sm"></div>

    <div id="{{ $id }}-canvas"
        class="w-full overflow-hidden rounded-lg border z-0"
        style="height: {{ $height }}; min-height: 200px;"
        data-map-canvas></div>

    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
        <p class="opacity-80" data-map-status>
            @if ($initLat !== null && $initLng !== null)
                Titik koordinat terpilih: <strong class="font-mono tabular-nums">{{ number_format($initLat, 6, '.', '') }}, {{ number_format($initLng, 6, '.', '') }}</strong>
            @else
                Klik atau geser pin pada peta untuk mengisi alamat lengkap perusahaan secara otomatis.
            @endif
        </p>
        <a href="{{ $initUrl ?: '#' }}"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-1 font-semibold text-[#800000] hover:underline {{ $initUrl ? '' : 'hidden' }}"
            data-map-gmaps-link>
            <x-ui.icon name="external-link" class="h-3.5 w-3.5" />
            Buka di Google Maps
        </a>
    </div>
</div>

<script>
(function () {
    function initPkdiLocationPickers() {
        var containers = document.querySelectorAll('[data-pkdi-location-picker]');
        containers.forEach(function (container) {
            if (container.dataset.initialized === '1') return;
            if (typeof window.L === 'undefined') {
                setTimeout(initPkdiLocationPickers, 250);
                return;
            }
            container.dataset.initialized = '1';

            var canvas = container.querySelector('[data-map-canvas]');
            var latInput = container.querySelector('[data-lat-input]');
            var lngInput = container.querySelector('[data-lng-input]');
            var gmapsInput = container.querySelector('[data-gmaps-input]');
            var searchInput = container.querySelector('[data-map-search-input]');
            var searchBtn = container.querySelector('[data-map-search-btn]');
            var resultsBox = container.querySelector('[data-map-search-results]');
            var locateBtn = container.querySelector('[data-map-locate-btn]');
            var statusEl = container.querySelector('[data-map-status]');
            var gmapsLink = container.querySelector('[data-map-gmaps-link]');
            var targetSelector = container.getAttribute('data-target-selector') || 'textarea[name="address"]';
            var form = container.closest('form') || document;
            var addressField = form.querySelector(targetSelector) || document.querySelector(targetSelector);

            var initLat = parseFloat(container.getAttribute('data-init-lat'));
            var initLng = parseFloat(container.getAttribute('data-init-lng'));
            var hasInit = !isNaN(initLat) && !isNaN(initLng);

            // Default center: Kawasan Industri Cilegon / Jabodetabek
            var defaultLat = hasInit ? initLat : -6.0174;
            var defaultLng = hasInit ? initLng : 106.0538;
            var defaultZoom = hasInit ? 15 : 11;

            var map = window.L.map(canvas).setView([defaultLat, defaultLng], defaultZoom);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            var marker = window.L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

            function setStatus(text) {
                if (statusEl) statusEl.innerHTML = text;
            }

            function buildStructuredAddress(data) {
                if (!data) return '';
                var a = data.address || {};
                var parts = [];
                var road = a.road || a.industrial || a.commercial || a.amenity || a.building || a.hamlet || '';
                if (a.house_number && road) {
                    road = road + ' No. ' + a.house_number;
                }
                if (road) parts.push(road);
                var village = a.village || a.suburb || a.neighbourhood || a.quarter || '';
                if (village) parts.push('Kel./Desa ' + village);
                var district = a.city_district || a.municipality || a.county || a.town || '';
                if (district) parts.push('Kec. ' + district);
                var city = a.city || a.regency || a.state_district || '';
                if (city && city !== district) parts.push(city);
                if (a.state) parts.push(a.state);
                if (a.postcode) parts.push('Kode Pos ' + a.postcode);

                var composed = parts.join(', ');
                if (composed.length >= 12) {
                    return composed;
                }
                return data.display_name || composed;
            }

            function updateCoordinates(lat, lng, shouldReverseGeocode) {
                var fixedLat = Number(lat).toFixed(6);
                var fixedLng = Number(lng).toFixed(6);
                var gmapsUrl = 'https://www.google.com/maps?q=' + fixedLat + ',' + fixedLng;

                if (latInput) latInput.value = fixedLat;
                if (lngInput) lngInput.value = fixedLng;
                if (gmapsInput) gmapsInput.value = gmapsUrl;
                if (gmapsLink) {
                    gmapsLink.href = gmapsUrl;
                    gmapsLink.classList.remove('hidden');
                }

                marker.setLatLng([lat, lng]);

                if (!shouldReverseGeocode) {
                    setStatus('Titik koordinat terpilih: <strong class="font-mono tabular-nums">' + fixedLat + ', ' + fixedLng + '</strong>');
                    return;
                }

                setStatus('Mengambil detail alamat jalan, kecamatan, kota & kode pos dari titik peta...');

                fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(fixedLat) + '&lon=' + encodeURIComponent(fixedLng) + '&accept-language=id', {
                    headers: { 'Accept': 'application/json' }
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    var fullAddress = buildStructuredAddress(data);
                    if (fullAddress && addressField) {
                        addressField.value = fullAddress;
                        addressField.dispatchEvent(new Event('input', { bubbles: true }));
                        addressField.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    setStatus('Alamat terisi otomatis (<strong class="font-mono tabular-nums">' + fixedLat + ', ' + fixedLng + '</strong>). Anda tetap dapat mengedit detail kavling/blok di bawah.');
                })
                .catch(function () {
                    setStatus('Koordinat tersimpan (<strong class="font-mono tabular-nums">' + fixedLat + ', ' + fixedLng + '</strong>). Silakan lengkapi teks alamat di bawah.');
                });
            }

            map.on('click', function (e) {
                updateCoordinates(e.latlng.lat, e.latlng.lng, true);
            });

            marker.on('dragend', function () {
                var pos = marker.getLatLng();
                updateCoordinates(pos.lat, pos.lng, true);
            });

            function performSearch() {
                if (!searchInput) return;
                var q = (searchInput.value || '').trim();
                if (q.length < 2) return;
                setStatus('Mencari wilayah "' + q + '"...');
                fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&countrycodes=id&addressdetails=1&limit=5&accept-language=id&q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(function (res) { return res.json(); })
                .then(function (items) {
                    if (!Array.isArray(items) || items.length === 0) {
                        if (resultsBox) resultsBox.classList.add('hidden');
                        setStatus('Wilayah tidak ditemukan. Coba kata kunci kota atau kecamatan lain.');
                        return;
                    }
                    if (items.length === 1) {
                        var first = items[0];
                        var lat = parseFloat(first.lat);
                        var lng = parseFloat(first.lon);
                        map.setView([lat, lng], 16);
                        updateCoordinates(lat, lng, true);
                        if (resultsBox) resultsBox.classList.add('hidden');
                        return;
                    }
                    if (resultsBox) {
                        resultsBox.innerHTML = '';
                        items.forEach(function (item) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'block w-full text-left px-3 py-2 hover:bg-zinc-100 transition';
                            btn.textContent = item.display_name;
                            btn.addEventListener('click', function () {
                                var lat = parseFloat(item.lat);
                                var lng = parseFloat(item.lon);
                                resultsBox.classList.add('hidden');
                                map.setView([lat, lng], 16);
                                updateCoordinates(lat, lng, true);
                            });
                            resultsBox.appendChild(btn);
                        });
                        resultsBox.classList.remove('hidden');
                    }
                    setStatus('Pilih salah satu hasil pencarian wilayah di bawah kotak cari.');
                })
                .catch(function () {
                    setStatus('Gagal mencari wilayah. Anda tetap dapat menggeser peta secara langsung.');
                });
            }

            if (searchBtn) {
                searchBtn.addEventListener('click', performSearch);
            }
            if (searchInput) {
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        performSearch();
                    }
                });
            }

            if (locateBtn) {
                locateBtn.addEventListener('click', function () {
                    if (!navigator.geolocation) {
                        setStatus('Browser Anda tidak mendukung fitur Geolocation.');
                        return;
                    }
                    setStatus('Mendeteksi titik lokasi perangkat Anda saat ini...');
                    navigator.geolocation.getCurrentPosition(
                        function (pos) {
                            var lat = pos.coords.latitude;
                            var lng = pos.coords.longitude;
                            map.setView([lat, lng], 16);
                            updateCoordinates(lat, lng, true);
                        },
                        function () {
                            setStatus('Akses lokasi ditolak atau gagal dideteksi. Silakan klik titik lokasi secara langsung di peta.');
                        },
                        { enableHighAccuracy: true, timeout: 10000 }
                    );
                });
            }

            setTimeout(function () {
                map.invalidateSize();
            }, 250);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPkdiLocationPickers);
    } else {
        initPkdiLocationPickers();
    }
})();
</script>
