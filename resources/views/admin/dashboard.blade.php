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

    {{-- Métricas --}}
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

    {{-- Módulos Habilitados Interactivos --}}
    <div class="grid lg:grid-cols-3 gap-6">
        @if($business->isFeatureEnabled('agenda') && $business->branch === 'estetica')
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📅 Agendar Nueva Cita</h2>
                <form onsubmit="submitAppointment(event)" class="space-y-3 text-xs">
                    <input type="text" id="appt-customer" required placeholder="Nombre del cliente" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <input type="text" id="appt-phone" placeholder="WhatsApp del cliente (opcional)" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <select id="appt-service" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        @foreach($catalog as $item)
                            <option value="{{ $item->name }}">{{ $item->name }} (${{ number_format($item->price, 0) }})</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" id="appt-date" value="{{ now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl text-white font-bold cursor-pointer" style="background: {{ $business->primary_color }}">
                        Guardar Cita en Calendario
                    </button>
                </form>
            </section>
        @endif

        @if($business->isFeatureEnabled('ventas_pos') || $business->isFeatureEnabled('pos') || $business->isFeatureEnabled('pos_voz'))
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">💳 Cobro Rápido / POS</h2>
                <form onsubmit="submitPosSale(event)" class="space-y-3 text-xs">
                    <input type="text" id="pos-customer" placeholder="Cliente (o Mostrador)" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <select id="pos-item" onchange="syncPosPrice()" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        @foreach($catalog as $item)
                            <option value="{{ $item->id }}" data-price="{{ $item->price }}" data-name="{{ $item->name }}">
                                {{ $item->name }} — ${{ number_format($item->price, 2) }}
                            </option>
                        @endforeach
                        <option value="0" data-price="150" data-name="Concepto personalizado">Otro concepto personalizado...</option>
                    </select>
                    <input type="text" id="pos-custom-name" placeholder="Nombre si es personalizado" class="hidden w-full rounded-xl border border-slate-300 px-3 py-2">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" id="pos-qty" value="1" min="1" placeholder="Cant." class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        <input type="number" id="pos-price" value="{{ $catalog->first()?->price ?? 150 }}" step="0.01" placeholder="Precio" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    @if($business->branch === 'comandas')
                        <input type="text" id="pos-table" placeholder="Mesa / Para llevar" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    @endif
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold cursor-pointer">
                        Registrar Cobro Ahora
                    </button>
                </form>
            </section>
        @endif

        @if($business->isFeatureEnabled('promociones'))
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">🏷️ Crear Promoción</h2>
                <form onsubmit="submitPromotion(event)" class="space-y-3 text-xs">
                    <input type="text" id="promo-title" required placeholder="Título (ej. 20% en Tinte Martes)" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <input type="number" id="promo-discount" value="20" min="1" max="90" placeholder="% Descuento" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <textarea id="promo-desc" rows="2" placeholder="Condiciones visibles en tu sitio web..." class="w-full rounded-xl border border-slate-300 px-3 py-2"></textarea>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold cursor-pointer">
                        Publicar Promoción en Sitio
                    </button>
                </form>
            </section>
        @endif

        @if($business->isFeatureEnabled('menu_qr'))
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📱 Menú Digital QR</h2>
                <p class="text-xs text-slate-500">Comparte tu menú interactivo en mesas: <a href="{{ route('business.landing', ['slug' => $business->slug]) }}#catalogo" target="_blank" class="text-sky-600 font-semibold underline">Abrir Menú</a></p>
                <form onsubmit="submitCatalogItem(event, 'Platillo')" class="space-y-2.5 text-xs">
                    <input type="text" id="cat-name-qr" required placeholder="Nuevo platillo o bebida" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <input type="number" id="cat-price-qr" value="120" step="0.01" placeholder="Precio ($)" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <button type="submit" class="w-full py-2 rounded-xl bg-slate-900 text-white font-bold cursor-pointer">Agregar al Menú QR</button>
                </form>
            </section>
        @endif

        @if($business->isFeatureEnabled('catalogo_web'))
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📦 Catálogo e Inventario</h2>
                <p class="text-xs text-slate-500">Productos activos: <strong>{{ $catalog->count() }}</strong></p>
                <form onsubmit="submitCatalogItem(event, 'General')" class="space-y-2.5 text-xs">
                    <input type="text" id="cat-name-web" required placeholder="Nombre del producto" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <input type="number" id="cat-price-web" value="199" step="0.01" placeholder="Precio ($)" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    <button type="submit" class="w-full py-2 rounded-xl bg-slate-900 text-white font-bold cursor-pointer">Agregar Producto</button>
                </form>
            </section>
        @endif

        @if($business->isFeatureEnabled('estadisticas'))
            @php
                $avgTicket = $metrics['orders_today'] > 0 ? ($metrics['sales_today'] / $metrics['orders_today']) : 0;
            @endphp
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📈 Estadísticas e Insights IA</h2>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="text-slate-500">Ticket Promedio</div>
                        <div class="text-lg font-extrabold text-slate-900">${{ number_format($avgTicket, 2) }}</div>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="text-slate-500">Ítems Activos</div>
                        <div class="text-lg font-extrabold text-slate-900">{{ $metrics['catalog_count'] }}</div>
                    </div>
                </div>
                <button type="button" onclick="quickPrompt('Dame un análisis de mis ventas y qué promocionar hoy')" class="w-full py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold cursor-pointer">
                    Pedir Análisis a la IA →
                </button>
            </section>
        @endif
    </div>

    {{-- Comandas: Pantalla de Cocina (KDS) y Control de Mesas --}}
    @if($business->isFeatureEnabled('comandas_kds') || $business->isFeatureEnabled('mesas'))
        <div class="grid lg:grid-cols-2 gap-6">
            @if($business->isFeatureEnabled('comandas_kds'))
                @php
                    $kdsOrders = $operations->where('operation_type', 'comanda')->whereIn('status', ['pending', 'preparing', 'ready']);
                @endphp
                <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h2 class="font-bold text-slate-900">🍳 Pantalla de Cocina (KDS)</h2>
                    @if($kdsOrders->isEmpty())
                        <p class="text-xs text-slate-500">No hay comandas activas en cocina.</p>
                    @else
                        <div class="space-y-2.5">
                            @foreach($kdsOrders as $kds)
                                <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <div>
                                        <div class="font-bold text-slate-900">Ref. #{{ $kds->odoo_record_id ?? $kds->id }} · {{ $kds->table_label ?: $kds->customer_name }}</div>
                                        <div class="text-slate-600">${{ number_format($kds->total_amount, 2) }} · {{ $statusLabels[$kds->status] ?? $kds->status }}</div>
                                    </div>
                                    <div class="flex gap-1.5">
                                        @if($kds->status !== 'ready')
                                            <button type="button" onclick="updateOpStatus({{ $kds->id }}, 'ready')" class="px-2.5 py-1 rounded-lg bg-amber-500 text-white font-bold cursor-pointer">Marcar Lista</button>
                                        @endif
                                        <button type="button" onclick="updateOpStatus({{ $kds->id }}, 'completed')" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold cursor-pointer">Entregada</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            @if($business->isFeatureEnabled('mesas'))
                <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h2 class="font-bold text-slate-900">🪑 Control de Mesas</h2>
                    <div class="grid grid-cols-3 gap-3 text-xs">
                        @for($m = 1; $m <= 6; $m++)
                            @php
                                $tableName = "Mesa {$m}";
                                $activeTable = $operations->where('operation_type', 'comanda')->where('table_label', $tableName)->whereIn('status', ['pending', 'preparing', 'ready'])->first();
                            @endphp
                            <button type="button" onclick="selectTable('{{ $tableName }}')"
                                class="p-3 rounded-xl border text-left cursor-pointer {{ $activeTable ? 'bg-amber-50 border-amber-300' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                                <div class="font-bold text-slate-900">{{ $tableName }}</div>
                                <div class="text-[11px] {{ $activeTable ? 'text-amber-800 font-semibold' : 'text-slate-500' }}">
                                    {{ $activeTable ? 'Ocupada ($' . number_format($activeTable->total_amount, 0) . ')' : 'Libre' }}
                                </div>
                            </button>
                        @endfor
                    </div>
                </section>
            @endif
        </div>
    @endif

    {{-- Gestión de Envíos (Tienda) --}}
    @if($business->isFeatureEnabled('envios'))
        @php
            $shipOrders = $operations->where('operation_type', 'sale')->take(6);
        @endphp
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
            <h2 class="font-bold text-slate-900">🚚 Gestión de Envíos</h2>
            @if($shipOrders->isEmpty())
                <p class="text-xs text-slate-500">Sin pedidos pendientes de envío.</p>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                    @foreach($shipOrders as $so)
                        <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
                            <div class="font-bold text-slate-900">Ref. #{{ $so->odoo_record_id ?? $so->id }} · {{ $so->customer_name }}</div>
                            <div class="text-slate-600">${{ number_format($so->total_amount, 2) }} · {{ $statusLabels[$so->status] ?? $so->status }}</div>
                            <div class="flex gap-1.5">
                                <button type="button" onclick="updateOpStatus({{ $so->id }}, 'shipped')" class="px-2.5 py-1 rounded-lg bg-sky-600 text-white font-bold cursor-pointer">Enviada</button>
                                <button type="button" onclick="updateOpStatus({{ $so->id }}, 'completed')" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold cursor-pointer">Entregada</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- Calendario Semanal/Diario/Mensual para Estéticas --}}
    @if($business->branch === 'estetica' && $business->isFeatureEnabled('agenda'))
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

    {{-- Modo Avanzado: Personalización Completa de Landing y Módulos --}}
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-slate-900 text-base">🎨 Personalización de Sitio Web y Modo Avanzado</h2>
                <p class="text-xs text-slate-500">Activa el modo avanzado para editar textos, imágenes, colores y secciones de tu sitio público.</p>
            </div>
            <label class="inline-flex items-center gap-2.5 px-4 py-2 rounded-xl bg-slate-100 border border-slate-200 cursor-pointer">
                <input type="checkbox" id="toggle-advanced-mode" {{ $business->advanced_mode ? 'checked' : '' }} onchange="toggleAdvancedMode(this.checked)" class="w-4 h-4 accent-sky-600">
                <span class="text-xs font-bold text-slate-800">Modo Avanzado</span>
            </label>
        </div>

        <form id="advanced-landing-form" onsubmit="saveLandingConfig(event)" class="{{ $business->advanced_mode ? '' : 'hidden' }} space-y-5 pt-4 border-t border-slate-200">
            <div class="grid sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Color Principal</label>
                    <input type="color" id="adv-primary" value="{{ $business->primary_color }}" class="w-full h-10 rounded-lg border border-slate-300 p-1 cursor-pointer">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Color Secundario</label>
                    <input type="color" id="adv-secondary" value="{{ $business->secondary_color }}" class="w-full h-10 rounded-lg border border-slate-300 p-1 cursor-pointer">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Estilo Visual</label>
                    <select id="adv-style" class="w-full h-10 rounded-lg border border-slate-300 px-3">
                        <option value="moderno" {{ $business->style_preset === 'moderno' ? 'selected' : '' }}>Moderno</option>
                        <option value="elegante" {{ $business->style_preset === 'elegante' ? 'selected' : '' }}>Elegante</option>
                        <option value="vibrante" {{ $business->style_preset === 'vibrante' ? 'selected' : '' }}>Vibrante</option>
                    </select>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Título Principal (Hero)</label>
                    <input type="text" id="adv-headline" value="{{ $business->landingSetting('hero_headline', '') }}" placeholder="Ej. Realza tu mejor versión" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Subtítulo</label>
                    <input type="text" id="adv-subheadline" value="{{ $business->landingSetting('hero_subheadline', '') }}" placeholder="Descripción atractiva de tu negocio" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Etiqueta Superior (Badge)</label>
                    <input type="text" id="adv-badge" value="{{ $business->landingSetting('hero_badge', '') }}" placeholder="✨ Belleza & Bienestar" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Horario Visible</label>
                    <input type="text" id="adv-hours" value="{{ $business->landingSetting('business_hours', '') }}" placeholder="Lun-Sáb 9am - 8pm" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 mb-1">URL de Imagen Principal (opcional)</label>
                    <input type="url" id="adv-hero-img" value="{{ $business->landingSetting('hero_image_url', '') }}" placeholder="https://..." class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div>
                <div class="text-xs font-bold text-slate-700 mb-2">Secciones visibles en el sitio público</div>
                <div class="grid sm:grid-cols-5 gap-3 text-xs">
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
                        <input type="checkbox" id="adv-show-pillars" {{ $business->landingSetting('show_pillars', true) ? 'checked' : '' }}> Pilares
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
                        <input type="checkbox" id="adv-show-gallery" {{ $business->landingSetting('show_gallery', true) ? 'checked' : '' }}> Galería
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
                        <input type="checkbox" id="adv-show-reviews" {{ $business->landingSetting('show_reviews', true) ? 'checked' : '' }}> Reseñas
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
                        <input type="checkbox" id="adv-show-map" {{ $business->landingSetting('show_map', true) ? 'checked' : '' }}> Mapa
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
                        <input type="checkbox" id="adv-show-promos" {{ $business->landingSetting('show_promotions', true) ? 'checked' : '' }}> Promos
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4">
                <span id="adv-save-status" class="text-xs text-emerald-700 font-semibold"></span>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold cursor-pointer">
                    Guardar Cambios del Sitio →
                </button>
            </div>
        </form>
    </section>

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

        {{-- Centro: Movimientos + Memoria IA --}}
        <div class="space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-bold text-slate-900 mb-3">📋 Movimientos y Ventas Recientes</h2>
                @if($operations->isEmpty())
                    <p class="text-xs text-slate-500">Aún no hay movimientos registrados. Prueba creando una cita o venta arriba.</p>
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

            @if($business->isFeatureEnabled('anuncios_ia'))
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-slate-900">📣 Anuncios IA y Redes</h2>
                <p class="text-xs text-slate-600">Campaña vinculada: <strong>Ref. #{{ $business->sc_project_id }}</strong></p>
                <button type="button" onclick="quickPrompt('Créame un anuncio atractivo para publicar hoy en mis redes sociales')"
                    class="w-full py-2 rounded-xl bg-slate-900 text-white text-xs font-bold cursor-pointer">
                    ✨ Generar y Programar Anuncio IA
                </button>
                <a href="{{ $scConnectUrl }}" target="_blank"
                   class="block text-center py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                    Conectar Facebook / Instagram / TikTok →
                </a>
            </section>
            @endif

            @if($business->isFeatureEnabled('google_maps'))
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
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

@if($business->branch === 'estetica' && $business->isFeatureEnabled('agenda'))
let aisyCalendar = null;
document.addEventListener('DOMContentLoaded', () => {
    const calEl = document.getElementById('agenda-calendar');
    if (calEl && window.FullCalendar) {
        aisyCalendar = new FullCalendar.Calendar(calEl, {
            locale: 'es',
            initialView: 'timeGridWeek',
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
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
    window.location.reload();
}

function syncPosPrice() {
    const sel = document.getElementById('pos-item');
    const opt = sel.options[sel.selectedIndex];
    const customInput = document.getElementById('pos-custom-name');
    document.getElementById('pos-price').value = opt.dataset.price || 150;
    if (sel.value === '0') {
        customInput.classList.remove('hidden');
    } else {
        customInput.classList.add('hidden');
    }
}

async function submitAppointment(e) {
    e.preventDefault();
    await fetch("{{ route('business.admin.appointments', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({
            customer_name: document.getElementById('appt-customer').value,
            customer_phone: document.getElementById('appt-phone').value,
            service_name: document.getElementById('appt-service').value,
            scheduled_at: document.getElementById('appt-date').value,
        })
    });
    window.location.reload();
}

async function submitPosSale(e) {
    e.preventDefault();
    const sel = document.getElementById('pos-item');
    const opt = sel.options[sel.selectedIndex];
    const customName = document.getElementById('pos-custom-name').value;
    await fetch("{{ route('business.admin.pos_sale', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({
            catalog_item_id: parseInt(sel.value, 10) || 0,
            item_name: sel.value === '0' ? customName : (opt.dataset.name || 'Venta'),
            customer_name: document.getElementById('pos-customer').value || 'Cliente Mostrador',
            qty: parseInt(document.getElementById('pos-qty').value, 10) || 1,
            price: parseFloat(document.getElementById('pos-price').value) || 0,
            table_label: document.getElementById('pos-table')?.value || null,
        })
    });
    window.location.reload();
}

async function submitPromotion(e) {
    e.preventDefault();
    await fetch("{{ route('business.admin.promotions', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({
            title: document.getElementById('promo-title').value,
            discount_percent: parseFloat(document.getElementById('promo-discount').value) || 15,
            description: document.getElementById('promo-desc').value,
        })
    });
    window.location.reload();
}

async function submitCatalogItem(e, category) {
    e.preventDefault();
    const isQr = category === 'Platillo';
    const name = document.getElementById(isQr ? 'cat-name-qr' : 'cat-name-web').value;
    const price = parseFloat(document.getElementById(isQr ? 'cat-price-qr' : 'cat-price-web').value) || 100;
    await fetch("{{ route('business.admin.catalog', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ name, price, category })
    });
    window.location.reload();
}

async function updateOpStatus(opId, status) {
    await fetch(`{{ url("/{$business->slug}/admin/operations") }}/${opId}/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ status })
    });
    window.location.reload();
}

function selectTable(tableName) {
    const input = document.getElementById('pos-table');
    if (input) {
        input.value = tableName;
        input.focus();
    }
}

async function toggleAdvancedMode(enabled) {
    const form = document.getElementById('advanced-landing-form');
    if (enabled) form.classList.remove('hidden');
    else form.classList.add('hidden');

    await fetch("{{ route('business.admin.landing_config', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ advanced_mode: enabled })
    });
}

async function saveLandingConfig(e) {
    e.preventDefault();
    const statusEl = document.getElementById('adv-save-status');
    statusEl.textContent = 'Guardando...';
    await fetch("{{ route('business.admin.landing_config', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({
            advanced_mode: true,
            primary_color: document.getElementById('adv-primary').value,
            secondary_color: document.getElementById('adv-secondary').value,
            style_preset: document.getElementById('adv-style').value,
            landing_config: {
                hero_headline: document.getElementById('adv-headline').value,
                hero_subheadline: document.getElementById('adv-subheadline').value,
                hero_badge: document.getElementById('adv-badge').value,
                business_hours: document.getElementById('adv-hours').value,
                hero_image_url: document.getElementById('adv-hero-img').value,
                show_pillars: document.getElementById('adv-show-pillars').checked,
                show_gallery: document.getElementById('adv-show-gallery').checked,
                show_reviews: document.getElementById('adv-show-reviews').checked,
                show_map: document.getElementById('adv-show-map').checked,
                show_promotions: document.getElementById('adv-show-promos').checked,
            }
        })
    });
    statusEl.textContent = '✅ Cambios guardados en tu sitio público.';
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
