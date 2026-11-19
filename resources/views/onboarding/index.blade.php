@extends('layouts.app')

@section('title', 'Aisy — Crea tu negocio en 1 minuto')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">
    <header class="flex flex-wrap items-center justify-between gap-4 mb-10">
        <div>
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800">
                ⚡ Odoo Headless + IA WhatsApp + Seller Central
            </span>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900 mt-3">
                Aisy — Lanza tu negocio en 60 segundos
            </h1>
            <p class="text-slate-600 mt-2 max-w-2xl">
                Llena estos datos básicos. Nosotros creamos tu empresa aislada en Odoo, tu página web personalizada, tu panel de control modular y tu asistente de ventas por WhatsApp.
            </p>
        </div>
        <div class="flex items-center gap-3">
            @auth
                <span class="text-xs font-semibold text-slate-600">{{ auth()->user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold cursor-pointer">Cerrar sesión</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold">Iniciar sesión</a>
            @endauth
        </div>
    </header>

    <div class="grid lg:grid-cols-5 gap-8">
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <h2 class="text-lg font-bold text-slate-900 mb-5">Registrar nuevo negocio</h2>

            @if($errors->any())
                <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('onboarding.store') }}" method="POST" class="space-y-5">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Tu nombre completo *</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name') }}" required placeholder="Ej. Sofía Méndez"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Número de WhatsApp *</label>
                        <input type="text" name="owner_phone" value="{{ old('owner_phone') }}" required placeholder="Ej. 5215512345678"
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre del negocio *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Ej. Estética Glam Sofía"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Rubro / Giro del negocio *</label>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="branch" value="estetica" class="peer sr-only" {{ old('branch', 'estetica') === 'estetica' ? 'checked' : '' }}>
                            <div class="p-3.5 rounded-xl border-2 border-slate-200 peer-checked:border-sky-500 peer-checked:bg-sky-50 transition">
                                <div class="font-bold text-sm">✂️ Estética</div>
                                <div class="text-xs text-slate-500 mt-0.5">Agenda, ventas y promos</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="branch" value="tienda" class="peer sr-only" {{ old('branch') === 'tienda' ? 'checked' : '' }}>
                            <div class="p-3.5 rounded-xl border-2 border-slate-200 peer-checked:border-sky-500 peer-checked:bg-sky-50 transition">
                                <div class="font-bold text-sm">🛍️ Tienda</div>
                                <div class="text-xs text-slate-500 mt-0.5">POS, estadísticas y envíos</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="branch" value="comandas" class="peer sr-only" {{ old('branch') === 'comandas' ? 'checked' : '' }}>
                            <div class="p-3.5 rounded-xl border-2 border-slate-200 peer-checked:border-sky-500 peer-checked:bg-sky-50 transition">
                                <div class="font-bold text-sm">🍽️ Comandas</div>
                                <div class="text-xs text-slate-500 mt-0.5">Menú QR, mesas y cocina</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="space-y-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="block text-sm font-semibold text-slate-700">Dirección del local (OpenStreetMap)</label>
                        <button type="button" id="btn-geolocate" onclick="autoDetectLocation()"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold border border-sky-200 cursor-pointer">
                            📍 Autodetectar mi ubicación
                        </button>
                    </div>

                    <div class="relative">
                        <div class="flex gap-2">
                            <input type="text" id="address-search" placeholder="Buscar calle, colonia o ciudad..."
                                class="flex-1 rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <button type="button" onclick="searchAddress()"
                                class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold cursor-pointer">
                                🔍 Buscar
                            </button>
                        </div>
                        <div id="address-results" class="hidden absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-48 overflow-y-auto divide-y divide-slate-100"></div>
                    </div>

                    <input type="text" name="address" id="address-input" value="{{ old('address') }}" placeholder="Dirección confirmada (puedes ajustarla manualmente)"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                    <input type="hidden" name="latitude" id="lat-input" value="{{ old('latitude') }}">
                    <input type="hidden" name="longitude" id="lng-input" value="{{ old('longitude') }}">

                    <div id="osm-map" class="w-full h-48 rounded-xl border border-slate-200 z-10"></div>
                    <p id="geo-status" class="text-[11px] text-slate-500">Arrastra el pin o busca tu dirección para guardar coordenadas exactas.</p>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Color Principal</label>
                        <input type="color" name="primary_color" value="{{ old('primary_color', '#0ea5e9') }}"
                            class="w-full h-10 rounded-lg border border-slate-300 cursor-pointer p-1">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Color Secundario</label>
                        <input type="color" name="secondary_color" value="{{ old('secondary_color', '#6366f1') }}"
                            class="w-full h-10 rounded-lg border border-slate-300 cursor-pointer p-1">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Estilo</label>
                        <select name="style_preset" class="w-full h-10 rounded-lg border border-slate-300 px-2 text-sm">
                            <option value="moderno">Moderno</option>
                            <option value="elegante">Elegante</option>
                            <option value="vibrante">Vibrante</option>
                        </select>
                    </div>
                </div>

                <button type="submit"
                    class="w-full py-3.5 px-6 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow-sm transition cursor-pointer">
                    Crear mi negocio en Aisy →
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-3">Negocios activos en Aisy</h3>
                @if($businesses->isEmpty())
                    <p class="text-sm text-slate-500">Aún no hay negocios registrados. ¡Crea el primero a la izquierda!</p>
                @else
                    <div class="space-y-3">
                        @foreach($businesses as $b)
                            <div class="p-3.5 rounded-xl border border-slate-200 hover:border-slate-300 transition">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" style="background: {{ $b->primary_color }}"></span>
                                        <span class="font-bold text-sm text-slate-900">{{ $b->name }}</span>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 capitalize">{{ $b->branch }}</span>
                                </div>
                                <div class="mt-2.5 flex items-center gap-2 text-xs">
                                    <a href="{{ route('business.landing', ['slug' => $b->slug]) }}" class="px-2.5 py-1 rounded-lg bg-sky-50 text-sky-700 font-semibold hover:bg-sky-100">Ver Landing</a>
                                    <a href="{{ route('business.admin', ['slug' => $b->slug]) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">Panel Admin</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const defaultLat = parseFloat(document.getElementById('lat-input').value) || 19.4326;
    const defaultLng = parseFloat(document.getElementById('lng-input').value) || -99.1332;
    const statusEl = document.getElementById('geo-status');
    const addrInput = document.getElementById('address-input');
    const searchInput = document.getElementById('address-search');
    const resultsBox = document.getElementById('address-results');
    const latInput = document.getElementById('lat-input');
    const lngInput = document.getElementById('lng-input');

    const map = L.map('osm-map').setView([defaultLat, defaultLng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    function setCoords(lat, lng, updateAddress = true) {
        latInput.value = Number(lat).toFixed(6);
        lngInput.value = Number(lng).toFixed(6);
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng], 16);
        if (updateAddress) reverseGeocode(lat, lng);
    }

    async function reverseGeocode(lat, lng) {
        statusEl.textContent = 'Obteniendo dirección exacta...';
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`, {
                headers: { 'Accept-Language': 'es' }
            });
            const data = await res.json();
            if (data && data.display_name) {
                addrInput.value = data.display_name;
                statusEl.textContent = `📍 Ubicación detectada (${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)})`;
            }
        } catch {
            statusEl.textContent = `📍 Coordenadas listas (${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)})`;
        }
    }

    marker.on('dragend', () => {
        const pos = marker.getLatLng();
        setCoords(pos.lat, pos.lng, true);
    });

    window.autoDetectLocation = function () {
        if (!navigator.geolocation) {
            statusEl.textContent = 'Tu navegador no soporta geolocalización; usa el buscador.';
            return;
        }
        statusEl.textContent = 'Detectando tu ubicación actual...';
        navigator.geolocation.getCurrentPosition(
            (pos) => setCoords(pos.coords.latitude, pos.coords.longitude, true),
            () => { statusEl.textContent = 'No se pudo obtener permiso GPS; busca tu dirección manualmente.'; },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    };

    window.searchAddress = async function () {
        const q = searchInput.value.trim();
        if (q.length < 3) return;
        statusEl.textContent = 'Buscando direcciones...';
        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=5&q=${encodeURIComponent(q)}`, {
                headers: { 'Accept-Language': 'es' }
            });
            const list = await res.json();
            resultsBox.innerHTML = '';
            if (!list.length) {
                resultsBox.innerHTML = '<div class="p-3 text-xs text-slate-500">Sin resultados. Intenta con calle y ciudad.</div>';
            } else {
                list.forEach((item) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full text-left px-3.5 py-2.5 text-xs hover:bg-sky-50 text-slate-800 cursor-pointer';
                    btn.textContent = item.display_name;
                    btn.onclick = () => {
                        addrInput.value = item.display_name;
                        setCoords(parseFloat(item.lat), parseFloat(item.lon), false);
                        resultsBox.classList.add('hidden');
                        statusEl.textContent = `📍 Dirección seleccionada (${Number(item.lat).toFixed(5)}, ${Number(item.lon).toFixed(5)})`;
                    };
                    resultsBox.appendChild(btn);
                });
            }
            resultsBox.classList.remove('hidden');
        } catch {
            statusEl.textContent = 'Error al consultar el buscador de direcciones.';
        }
    };

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            window.searchAddress();
        }
    });

    // Auto-detect gently on initial load if no address yet
    if (!addrInput.value && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => setCoords(pos.coords.latitude, pos.coords.longitude, true),
            () => {},
            { timeout: 5000 }
        );
    }
})();
</script>
@endpush
