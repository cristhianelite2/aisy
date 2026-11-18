@extends('layouts.app')

@section('title', "{$business->name} — Aisy")

@push('head')
<script type="application/ld+json">{!! json_encode($gmapsKit['json_ld'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="min-h-screen" style="--brand-primary: {{ $business->primary_color }}; --brand-secondary: {{ $business->secondary_color }};">
    <header class="text-white py-14 px-4" style="background: linear-gradient(135deg, {{ $business->primary_color }}, {{ $business->secondary_color }});">
        <div class="max-w-5xl mx-auto flex flex-wrap items-center justify-between gap-6">
            <div>
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-white/20 backdrop-blur uppercase tracking-wider">
                    {{ $business->branch }} · {{ $business->style_preset }}
                </span>
                <h1 class="text-3xl sm:text-5xl font-extrabold mt-3">{{ $business->name }}</h1>
                <p class="mt-2 text-white/90 text-sm sm:text-base">📍 {{ $business->address }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $waOwnerLink }}" target="_blank" class="px-5 py-3 rounded-xl bg-white text-slate-900 font-bold text-sm shadow-sm hover:bg-slate-100">
                    💬 Escribir por WhatsApp
                </a>
                <a href="{{ route('business.admin', ['slug' => $business->slug]) }}" class="px-4 py-3 rounded-xl bg-black/25 text-white font-semibold text-sm hover:bg-black/35">
                    Panel Admin
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-10 space-y-10">
        @if(session('landing_success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 font-semibold text-sm">
                ✅ {{ session('landing_success') }}
            </div>
        @endif

        @if($business->isFeatureEnabled('promociones') && $promotions->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-lg font-extrabold text-slate-900">🔥 Promociones Activas</h2>
                <div class="grid sm:grid-cols-3 gap-4">
                    @foreach($promotions as $promo)
                        <div class="p-4 rounded-2xl border-2 bg-white shadow-sm" style="border-color: {{ $business->primary_color }}">
                            <div class="font-bold text-slate-900">{{ $promo->customer_name }}</div>
                            <div class="text-xs text-slate-600 mt-1">{{ $promo->notes }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-4">
                <h2 class="text-xl font-extrabold text-slate-900">
                    @if($business->branch === 'estetica') ✂️ Nuestros Servicios
                    @elseif($business->branch === 'comandas') 🍽️ Menú Digital
                    @else 🛍️ Catálogo Disponible
                    @endif
                </h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($catalog as $item)
                        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between"
                             onclick="trackLandingEvent('click_item', {item: '{{ addslashes($item->name) }}'})">
                            <div>
                                <div class="text-xs font-semibold text-slate-400">{{ $item->category }}</div>
                                <div class="font-bold text-slate-900 mt-0.5">{{ $item->name }}</div>
                                @if($item->duration_minutes)
                                    <div class="text-xs text-slate-500 mt-1">⏱ {{ $item->duration_minutes }} min</div>
                                @endif
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <span class="text-lg font-extrabold" style="color: {{ $business->primary_color }}">${{ number_format($item->price, 2) }}</span>
                                <button type="button" onclick="selectItem({{ $item->id }}, '{{ addslashes($item->name) }}')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold text-white cursor-pointer"
                                    style="background: {{ $business->primary_color }}">
                                    Seleccionar
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 h-fit">
                <h3 class="text-lg font-extrabold text-slate-900 mb-4">
                    @if($business->branch === 'estetica') 📅 Agendar Cita
                    @elseif($business->branch === 'comandas') 🛎️ Hacer Pedido / Comanda
                    @else 🛒 Solicitar Pedido
                    @endif
                </h3>
                <form action="{{ route('business.landing.action', ['slug' => $business->slug]) }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="catalog_item_id" id="selected_item_id">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tu nombre *</label>
                        <input type="text" name="customer_name" required placeholder="Ej. Ana López"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tu WhatsApp</label>
                        <input type="text" name="customer_phone" placeholder="52155..."
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    @if($business->branch === 'estetica')
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Servicio</label>
                            <input type="text" name="service_name" id="selected_service_name" value="{{ $catalog->first()?->name ?? 'Corte' }}"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Fecha y hora</label>
                            <input type="datetime-local" name="scheduled_at" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Producto / Platillo</label>
                            <input type="text" name="item_name" id="selected_service_name" value="{{ $catalog->first()?->name ?? 'Pedido' }}"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Cantidad</label>
                                <input type="number" name="qty" value="1" min="1" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            @if($business->branch === 'comandas')
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mesa / Para llevar</label>
                                    <input type="text" name="table_label" placeholder="Mesa 3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                </div>
                            @endif
                        </div>
                    @endif
                    <button type="submit" class="w-full py-3 rounded-xl text-white font-bold text-sm cursor-pointer"
                        style="background: {{ $business->primary_color }}">
                        Confirmar ahora
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
function selectItem(id, name) {
    document.getElementById('selected_item_id').value = id;
    const svc = document.getElementById('selected_service_name');
    if (svc) svc.value = name;
}
function trackLandingEvent(eventType, metadata = {}) {
    fetch("{{ route('business.landing.event', ['slug' => $business->slug]) }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ event_type: eventType, metadata })
    }).catch(() => {});
}
</script>
@endpush
