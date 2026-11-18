@extends('layouts.app')

@section('title', "Admin · {$business->name} — Aisy")

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
    <header class="flex flex-wrap items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full" style="background: {{ $business->primary_color }}"></span>
                <h1 class="text-2xl font-extrabold text-slate-900">{{ $business->name }}</h1>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 font-bold uppercase text-slate-700">{{ $business->branch }}</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-sky-50 font-semibold text-sky-800">Odoo Company #{{ $business->odoo_company_id }}</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Dueño: {{ $business->owner_name }} · WhatsApp: {{ $business->owner_phone }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('business.landing', ['slug' => $business->slug]) }}" target="_blank"
               class="px-4 py-2 rounded-xl text-xs font-bold text-white" style="background: {{ $business->primary_color }}">
                🌐 Ver Landing ({{ $business->slug }})
            </a>
            <button type="button" onclick="triggerDeadTimes()"
               class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold cursor-pointer">
                ⚡ Detectar Tiempos Muertos
            </button>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold cursor-pointer">Salir</button>
            </form>
        </div>
    </header>

    <div class="grid sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500">Ventas Hoy (Odoo)</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">${{ number_format($metrics['sales_today'], 2) }}</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500">Órdenes Hoy</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $metrics['orders_today'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500">Ventas 7 días</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">${{ number_format($metrics['sales_week'], 2) }}</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500">
                @if($business->branch === 'estetica') Citas Próximas
                @elseif($business->branch === 'comandas') Comandas Activas
                @else Catálogo Activo @endif
            </div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">
                @if($business->branch === 'estetica') {{ $metrics['upcoming_appointments'] }}
                @elseif($business->branch === 'comandas') {{ $metrics['active_comandas'] }}
                @else {{ $metrics['catalog_count'] }} @endif
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-8">
        {{-- Left: Assistant Console + Feature Toggles --}}
        <div class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-3">🤖 Asistente IA (Mismo cerebro que WhatsApp)</h2>
                <div id="chat-log" class="h-56 overflow-y-auto space-y-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs mb-3">
                    <div class="p-2.5 rounded-lg bg-white border border-slate-200">
                        ¡Hola {{ $business->owner_name }}! Pídeme cobrar una venta, agendar una cita, consultar ventas o crear un anuncio para redes.
                    </div>
                </div>
                <form onsubmit="sendAssistant(event)" class="flex gap-2">
                    <input type="text" id="chat-input" placeholder="Ej. Cóbrale a Ana un corte de 280..."
                        class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-xs">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold cursor-pointer">Enviar</button>
                </form>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button type="button" onclick="quickPrompt('¿Cuánto vendí hoy?')" class="text-[11px] px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 cursor-pointer">📊 Ventas hoy</button>
                    <button type="button" onclick="quickPrompt('Créame un anuncio de 20% de descuento para redes')" class="text-[11px] px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 cursor-pointer">🎨 Crear anuncio IA</button>
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-3">🎛️ Módulos Prendibles / Apagables</h2>
                <div class="space-y-2.5">
                    @foreach(($business->features ?? []) as $key => $enabled)
                        <label class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
                            <span class="text-xs font-semibold text-slate-800">{{ $featureLabels[$key] ?? $key }}</span>
                            <input type="checkbox" {{ $enabled ? 'checked' : '' }} onchange="toggleFeature('{{ $key }}', this.checked)"
                                class="w-4 h-4 accent-sky-600">
                        </label>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Center: Operations / KDS / Agenda / POS --}}
        <div class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-3">📋 Operaciones en Odoo (Tiempo Real)</h2>
                @if($operations->isEmpty())
                    <p class="text-xs text-slate-500">Aún no hay operaciones registradas. Prueba dictando una venta al asistente.</p>
                @else
                    <div class="space-y-2.5 max-h-96 overflow-y-auto">
                        @foreach($operations as $op)
                            <div class="p-3 rounded-xl border border-slate-200 text-xs flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-bold text-slate-900">
                                        #{{ $op->id }} · {{ strtoupper($op->operation_type) }} {{ $op->table_label ? "({$op->table_label})" : '' }}
                                    </div>
                                    <div class="text-slate-600">{{ $op->customer_name }} · ${{ number_format($op->total_amount, 2) }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 font-semibold text-slate-700">{{ $op->status }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-3">🧠 Memoria IA (3 Niveles) & Bitácora</h2>
                <div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200 mb-3">
                    <strong>Nivel 1 (ADN):</strong> {{ $semanticMemory['business_name'] }} · Colores {{ $semanticMemory['primary_color'] }}/{{ $semanticMemory['secondary_color'] }} · Estilo {{ $semanticMemory['style_preset'] }}
                </div>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    @foreach($activities as $act)
                        <div class="text-xs p-2.5 rounded-lg bg-slate-50 border border-slate-200">
                            <span class="font-bold text-slate-800">{{ $act->title }}</span>
                            <div class="text-slate-500">{{ $act->details }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Right: WhatsApp QR + Seller Central + Google Maps Kit --}}
        <div class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-2">📱 WhatsApp del Negocio</h2>
                <p class="text-xs text-slate-500 mb-3">Estado actual: <strong>{{ $waStatus['state'] ?? 'desconectado' }}</strong></p>
                <div id="admin-wa-qr" class="flex justify-center mb-3"></div>
                <button type="button" onclick="loadAdminQr()" class="w-full py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold cursor-pointer">
                    Mostrar / Actualizar QR WhatsApp
                </button>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📣 Redes Sociales (Seller Central)</h2>
                <p class="text-xs text-slate-600">Proyecto vinculado: <strong>#{{ $business->sc_project_id }}</strong> en <code>sc.ceballosleon.com</code>.</p>
                <a href="{{ $scConnectUrl }}" target="_blank"
                   class="block text-center py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                    Conectar Facebook / IG / TikTok en Seller Central →
                </a>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📍 Kit Google Maps & Reseñas 5★</h2>
                <p class="text-xs text-slate-600">{{ $gmapsKit['seo_title'] ?? $business->name }}</p>
                <a href="{{ $gmapsKit['create_url'] ?? 'https://business.google.com/create' }}" target="_blank"
                   class="block text-center py-2 rounded-xl bg-sky-600 text-white text-xs font-bold">
                    Dar de alta en Google Business Profile →
                </a>
                @if(!empty($gmapsKit['review_qr_svg']))
                    <div class="flex justify-center pt-2">
                        <div class="w-40">{!! $gmapsKit['review_qr_svg'] !!}</div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

async function toggleFeature(feature, enabled) {
    await fetch("{{ route('business.admin.toggle', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ feature, enabled })
    });
}

function appendChat(who, html) {
    const log = document.getElementById('chat-log');
    const div = document.createElement('div');
    div.className = who === 'user'
        ? 'p-2.5 rounded-lg bg-slate-900 text-white ml-8'
        : 'p-2.5 rounded-lg bg-white border border-slate-200 mr-4';
    div.innerHTML = html;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
}

function quickPrompt(text) {
    document.getElementById('chat-input').value = text;
    sendAssistant(new Event('submit'));
}

async function sendAssistant(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    appendChat('user', text);

    const res = await fetch("{{ route('business.admin.assistant', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ text })
    });
    const data = await res.json();
    let html = data.reply || 'Listo.';
    if (data.media_url) {
        html += `<div class="mt-2"><img src="${data.media_url}" class="w-36 rounded-lg border"></div>`;
    }
    appendChat('ai', html);
}

async function loadAdminQr() {
    const box = document.getElementById('admin-wa-qr');
    box.innerHTML = '<span class="text-xs text-slate-500">Cargando QR...</span>';
    const res = await fetch("{{ route('business.admin.wa_qr', ['slug' => $business->slug]) }}");
    const data = await res.json();
    if (data.qrcode) {
        box.innerHTML = `<img src="${data.qrcode}" class="w-44 h-44 rounded-xl border">`;
    } else if (data.state === 'open') {
        box.innerHTML = `<span class="text-xs font-bold text-emerald-700">✅ Conectado (${data.connectedPhone || 'activo'})</span>`;
    } else {
        box.innerHTML = `<span class="text-xs text-amber-700">Estado: ${data.state || 'offline'}</span>`;
    }
}

async function triggerDeadTimes() {
    const res = await fetch("{{ route('business.admin.dead_times', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf }
    });
    const data = await res.json();
    appendChat('ai', data.recommended ? `⚡ <b>Tiempo muerto detectado:</b> ${data.message}` : '✅ Demanda saludable hoy.');
}
</script>
@endpush
