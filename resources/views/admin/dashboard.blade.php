@extends('layouts.app')

@section('title', "Panel · {$business->name} — Aisy")

@if($business->branch === 'estetica')
@push('head')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>
<style>
    .fc .fc-button-primary {
        background-color: {{ $business->primary_color ?: '#0ea5e9' }};
        border-color: {{ $business->primary_color ?: '#0ea5e9' }};
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: capitalize;
    }
    .fc .fc-button-primary:hover, .fc .fc-button-primary.fc-button-active {
        opacity: 0.9;
        background-color: #0f172a;
        border-color: #0f172a;
    }
    .fc .fc-toolbar-title {
        font-size: 1rem !important;
        font-weight: 800;
        text-transform: capitalize;
    }
</style>
@endpush
@endif

@php
    $branchLabel = match($business->branch) {
        'estetica' => 'Estética',
        'comandas' => 'Comandas',
        default => 'Tienda',
    };
    $opLabels = [
        'appointment' => 'Cita',
        'sale' => 'Venta',
        'comanda' => 'Comanda',
        'promotion' => 'Promoción',
    ];
    $statusLabels = [
        'pending' => 'Pendiente',
        'preparing' => 'En preparación',
        'ready' => 'Lista',
        'confirmed' => 'Confirmada',
        'shipped' => 'Enviada',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
    ];
    $calendarEvents = $operations
        ->where('operation_type', 'appointment')
        ->map(function ($op) use ($business) {
            $start = $op->scheduled_at ?: $op->created_at;
            $service = $op->items[0]['name'] ?? 'Cita';
            return [
                'id' => $op->id,
                'title' => "{$op->customer_name} · {$service} (Ref. #" . ($op->odoo_record_id ?? $op->id) . ")",
                'start' => $start->toIso8601String(),
                'end' => $start->copy()->addMinutes(45)->toIso8601String(),
                'color' => $business->primary_color ?: '#0ea5e9',
            ];
        })->values();
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
    <header class="flex flex-wrap items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full" style="background: {{ $business->primary_color }}"></span>
                <h1 class="text-2xl font-extrabold text-slate-900">{{ $business->name }}</h1>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 font-bold text-slate-700">{{ $branchLabel }}</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-sky-50 font-semibold text-sky-800">Ref. #{{ $business->odoo_company_id }}</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Propietario: {{ $business->owner_name }} · WhatsApp: {{ $business->owner_phone }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('business.landing', ['slug' => $business->slug]) }}" target="_blank"
               class="px-4 py-2 rounded-xl text-xs font-bold text-white" style="background: {{ $business->primary_color }}">
                🌐 Ver Sitio Público ({{ $business->slug }})
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
            <div class="text-xs text-slate-500">Ventas de Hoy</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">${{ number_format($metrics['sales_today'], 2) }}</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500">Órdenes de Hoy</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $metrics['orders_today'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500">Ventas últimos 7 días</div>
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

    @if($business->branch === 'estetica')
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-bold text-slate-900">📅 Calendario de Citas</h2>
                    <p class="text-xs text-slate-500">Vista semanal predeterminada. Cambia entre diario, semanal o mensual.</p>
                </div>
                <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl">
                    <button type="button" onclick="setCalView('timeGridDay', this)" class="cal-view-btn px-3 py-1.5 rounded-lg text-xs font-bold text-slate-700 hover:bg-white cursor-pointer">Diario</button>
                    <button type="button" onclick="setCalView('timeGridWeek', this)" class="cal-view-btn px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-slate-900 shadow-sm cursor-pointer">Semanal</button>
                    <button type="button" onclick="setCalView('dayGridMonth', this)" class="cal-view-btn px-3 py-1.5 rounded-lg text-xs font-bold text-slate-700 hover:bg-white cursor-pointer">Mensual</button>
                </div>
            </div>
            <div id="agenda-calendar" class="min-h-[460px]"></div>
        </section>
    @endif

    <div class="grid lg:grid-cols-3 gap-8">
        {{-- Izquierda: Consola del Asistente + Módulos --}}
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
                    <button type="button" onclick="quickPrompt('¿Cuánto vendí hoy?')" class="text-[11px] px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 cursor-pointer">📊 Ventas de hoy</button>
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

        {{-- Centro: Operaciones + Memoria IA --}}
        <div class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-3">📋 Movimientos y Ventas Recientes</h2>
                @if($operations->isEmpty())
                    <p class="text-xs text-slate-500">Aún no hay movimientos registrados. Prueba dictando una venta o cita al asistente.</p>
                @else
                    <div class="space-y-2.5 max-h-96 overflow-y-auto">
                        @foreach($operations as $op)
                            <div class="p-3 rounded-xl border border-slate-200 text-xs flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-bold text-slate-900">
                                        Ref. #{{ $op->odoo_record_id ?? $op->id }} · {{ $opLabels[$op->operation_type] ?? $op->operation_type }} {{ $op->table_label ? "({$op->table_label})" : '' }}
                                    </div>
                                    <div class="text-slate-600">
                                        {{ $op->customer_name }} · ${{ number_format($op->total_amount, 2) }}
                                        @if($op->scheduled_at) · {{ $op->scheduled_at->format('d/m H:i') }} @endif
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 font-semibold text-slate-700">
                                    {{ $statusLabels[$op->status] ?? $op->status }}
                                </span>
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
                            <span class="font-bold text-slate-800">{{ str_replace('Odoo', 'Sistema', $act->title) }}</span>
                            <div class="text-slate-500">{{ str_replace('Odoo', 'Sistema', $act->details) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Derecha: WhatsApp QR + Redes + Google Maps --}}
        <div class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-2">📱 WhatsApp del Negocio</h2>
                <p class="text-xs text-slate-500 mb-3">Estado actual: <strong>{{ ($waStatus['state'] ?? '') === 'open' ? 'Conectado' : 'Desconectado' }}</strong></p>
                <div id="admin-wa-qr" class="flex justify-center mb-3"></div>
                <button type="button" onclick="loadAdminQr()" class="w-full py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold cursor-pointer">
                    Mostrar / Actualizar QR de WhatsApp
                </button>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📣 Redes Sociales</h2>
                <p class="text-xs text-slate-600">Campaña vinculada: <strong>Ref. #{{ $business->sc_project_id }}</strong></p>
                <a href="{{ $scConnectUrl }}" target="_blank"
                   class="block text-center py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                    Conectar Facebook / Instagram / TikTok →
                </a>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📍 Kit Google Maps & Reseñas 5★</h2>
                <p class="text-xs text-slate-600">{{ $gmapsKit['seo_title'] ?? $business->name }}</p>
                <a href="{{ $gmapsKit['create_url'] ?? 'https://business.google.com/create' }}" target="_blank"
                   class="block text-center py-2 rounded-xl bg-sky-600 text-white text-xs font-bold">
                    Dar de alta en Google Maps →
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

@if($business->branch === 'estetica')
let aisyCalendar = null;
document.addEventListener('DOMContentLoaded', () => {
    const calEl = document.getElementById('agenda-calendar');
    if (calEl && window.FullCalendar) {
        aisyCalendar = new FullCalendar.Calendar(calEl, {
            locale: 'es',
            initialView: 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            slotMinTime: '08:00:00',
            slotMaxTime: '21:00:00',
            allDaySlot: false,
            height: 480,
            events: @json($calendarEvents),
        });
        aisyCalendar.render();
    }
});

function setCalView(viewName, btn) {
    if (!aisyCalendar) return;
    aisyCalendar.changeView(viewName);
    document.querySelectorAll('.cal-view-btn').forEach((el) => {
        el.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
        el.classList.add('text-slate-700');
    });
    if (btn) {
        btn.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
        btn.classList.remove('text-slate-700');
    }
}
@endif

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
        box.innerHTML = `<span class="text-xs text-amber-700">Estado: Desconectado</span>`;
    }
}

async function triggerDeadTimes() {
    const res = await fetch("{{ route('business.admin.dead_times', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }
    });
    const data = await res.json();
    appendChat('ai', data.recommended ? `⚡ <b>Tiempo muerto detectado:</b> ${data.message}` : '✅ Demanda saludable hoy.');
}
</script>
@endpush
