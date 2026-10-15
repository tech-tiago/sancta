@extends('layouts.site', ['title' => 'Mapa de igrejas', 'description' => 'Encontre igrejas católicas, horários de missa, confissão e adoração perto de você.', 'hideFooter' => true])

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/markercluster/MarkerCluster.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/markercluster/MarkerCluster.Default.css') }}">
@endpush

@section('content')
<div class="map-shell">
    <section class="map-panel" aria-labelledby="igrejas-titulo">
        <header class="map-panel-head">
            <h1 id="igrejas-titulo">Igrejas</h1>
            <p class="muted mb-2"><span id="count">{{ $total }}</span> igrejas no mapa</p>
            <label class="sr-only" for="filter">Buscar igreja</label>
            <input id="filter" type="search" placeholder="Buscar por nome, bairro ou cidade" autocomplete="off">
            <div class="actions">
                <button class="btn btn-outline btn-sm" type="button" id="locate"><x-sancta-icon name="pin" /> Perto de mim</button>
                <a class="btn btn-primary btn-sm" href="{{ route('churches.create') }}"><x-sancta-icon name="plus" /> Cadastrar igreja</a>
            </div>
        </header>
        <div class="map-list" id="list" aria-live="polite"></div>
    </section>
    <div id="map" role="application" aria-label="Mapa de igrejas"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" nonce="{{ $cspNonce }}"></script>
<script src="{{ asset('vendor/markercluster/leaflet.markercluster.js') }}" nonce="{{ $cspNonce }}"></script>
<script nonce="{{ $cspNonce }}">
(function () {
    var tiles = { light: @json(config('sancta.maps.tiles_light')), dark: @json(config('sancta.maps.tiles_dark')) };
    var attribution = @json(config('sancta.maps.attribution'));
    var showBase = @json(url('/igrejas'));

    function isDark() {
        var t = document.documentElement.getAttribute('data-theme');
        return t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    var darkFilter = @json(config('sancta.maps.dark_filter'));
    var mapEl = document.getElementById('map');
    function applyTheme() {
        mapEl.classList.toggle('map-dark', darkFilter && isDark());
        layer.setUrl(isDark() ? tiles.dark : tiles.light);
    }

    var map = L.map('map', { zoomControl: true }).setView([-14.5, -51], 4);
    var layer = L.tileLayer(isDark() ? tiles.dark : tiles.light, { attribution: attribution, maxZoom: 19 }).addTo(map);
    applyTheme();
    document.addEventListener('sancta:theme', applyTheme);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);

    var icon = L.divIcon({ className: '', html: '<div class="pin"></div>', iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -28] });
    var cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });
    map.addLayer(cluster);

    var list = document.getElementById('list');
    var count = document.getElementById('count');
    var all = [];

    // Monta elementos com textContent (nunca innerHTML com dados) — sem risco de XSS.
    function popup(c) {
        var box = document.createElement('div');
        var title = document.createElement('strong'); title.textContent = c.n; box.appendChild(title);
        var meta = document.createElement('div'); meta.className = 'map-popup-meta'; meta.textContent = c.t + (c.c ? ' · ' + c.c : ''); box.appendChild(meta);
        var link = document.createElement('a'); link.className = 'map-popup-link'; link.href = showBase + '/' + encodeURIComponent(c.s); link.textContent = 'Ver horários →';
        box.appendChild(link);
        return box;
    }

    function render(items) {
        cluster.clearLayers();
        list.replaceChildren();
        items.forEach(function (c) {
            var marker = L.marker([c.lat, c.lng], { icon: icon }).bindPopup(popup(c));
            cluster.addLayer(marker);
            var a = document.createElement('a');
            a.className = 'map-item'; a.href = showBase + '/' + encodeURIComponent(c.s);
            var s = document.createElement('strong'); s.textContent = c.n + (c.v ? ' ✓' : '');
            var sm = document.createElement('small'); sm.textContent = c.t + ' · ' + (c.a || c.c || '');
            a.append(s, sm);
            a.addEventListener('mouseenter', function () { marker.openPopup(); });
            list.appendChild(a);
        });
        if (!items.length) {
            var empty = document.createElement('p');
            empty.className = 'map-empty';
            empty.textContent = 'Nenhuma igreja encontrada. Tente outro nome ou cadastre a sua paróquia.';
            list.appendChild(empty);
        }
        count.textContent = items.length;
        if (items.length) map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 14 });
    }

    fetch(@json(route('churches.map-data')), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { all = data; render(all); });

    var timer;
    document.getElementById('filter').addEventListener('input', function (e) {
        clearTimeout(timer);
        var q = e.target.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        timer = setTimeout(function () {
            render(!q ? all : all.filter(function (c) {
                return (c.n + ' ' + c.a + ' ' + c.c).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').indexOf(q) !== -1;
            }));
        }, 200);
    });

    document.getElementById('locate').addEventListener('click', function () {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(function (pos) {
            map.setView([pos.coords.latitude, pos.coords.longitude], 13);
            L.circleMarker([pos.coords.latitude, pos.coords.longitude], { radius: 8, color: '#1F3D9C', fillColor: '#E3B65B', fillOpacity: .9, weight: 3 }).addTo(map);
        });
    });
})();
</script>
@endpush
