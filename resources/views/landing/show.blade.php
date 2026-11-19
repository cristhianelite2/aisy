@extends('layouts.app')

@section('title', "{$business->name} — Sitio Oficial")

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script type="application/ld+json">{!! json_encode($gmapsKit['json_ld'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@if($business->latitude && $business->longitude)
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endif
<style>
    :root {
        --bp: {{ $business->primary_color ?: '#0ea5e9' }};
        --bs: {{ $business->secondary_color ?: '#6366f1' }};
    }
    body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; scroll-behavior: smooth; }
    .brand-gradient { background: linear-gradient(135deg, var(--bp), var(--bs)); }
    .brand-text-gradient {
        background: linear-gradient(135deg, var(--bp), var(--bs));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    @keyframes floatSlow {
        0%, 100% { transform: translateY(0px) scale(1); }
        50% { transform: translateY(-16px) scale(1.04); }
    }
    @keyframes pulseGlow {
        0%, 100% { opacity: 0.45; }
        50% { opacity: 0.75; }
    }
    .animate-float { animation: floatSlow 6s ease-in-out infinite; }
    .animate-glow { animation: pulseGlow 4s ease-in-out infinite; }
    .reveal { opacity: 0; transform: translateY(22px); transition: opacity .65s cubic-bezier(.16,1,.3,1), transform .65s cubic-bezier(.16,1,.3,1); }
    .reveal.visible { opacity: 1; transform: translateY(0); }
</style>
@endpush

@php
    $branchTheme = match($business->branch) {
        'estetica' => [
            'badge' => '✨ Belleza, Estilo & Bienestar',
            'headline' => "Realza tu mejor versión en {$business->name}",
            'sub' => 'Experiencias personalizadas de cabello, color, uñas y cuidado personal con reserva inmediata en línea.',
            'cta' => 'Agendar mi cita',
            'catalog_title' => 'Nuestros Servicios Exclusivos',
            'form_title' => 'Reserva tu Cita en Segundos',
            'hero_img' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1200&q=80',
            'gallery' => [
                ['img' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=800&q=80', 'label' => 'Color & Balayage'],
                ['img' => 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?auto=format&fit=crop&w=800&q=80', 'label' => 'Manicure & Spa'],
                ['img' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?auto=format&fit=crop&w=800&q=80', 'label' => 'Peinado & Estilo'],
            ],
            'pillars' => [
                ['icon' => '✂️', 'title' => 'Estilistas Expertos', 'desc' => 'Diagnóstico personalizado según tu tipo de rostro y cabello.'],
                ['icon' => '🌿', 'title' => 'Insumos Premium', 'desc' => 'Fórmulas de alta gama que protegen y nutren tu estilo.'],
                ['icon' => '⚡', 'title' => 'Agenda Sin Esperas', 'desc' => 'Elige tu horario y confirma directo por WhatsApp.'],
                ['icon' => '💎', 'title' => 'Garantía Total', 'desc' => 'Cuidamos cada detalle para que salgas encantada.'],
            ],
            'reviews' => [
                ['name' => 'Mariana V.', 'text' => 'Me encantó el trato y cómo quedó el matiz. Reservar desde la página fue súper rápido.'],
                ['name' => 'Karla G.', 'text' => 'El lugar está hermoso y súper puntual con mi cita. 100% recomendado.'],
                ['name' => 'Daniela R.', 'text' => 'Excelente servicio desde que escribí por WhatsApp hasta el resultado final.'],
            ],
        ],
        'comandas' => [
            'badge' => '🔥 Sabor Auténtico al Momento',
            'headline' => "Disfruta lo mejor de {$business->name}",
            'sub' => 'Ingredientes frescos, preparación al instante y menú digital interactivo para pedir en mesa o para llevar.',
            'cta' => 'Ver Menú y Ordenar',
            'catalog_title' => 'Menú Digital Interactivo',
            'form_title' => 'Levanta tu Pedido / Mesa',
            'hero_img' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=1200&q=80',
            'gallery' => [
                ['img' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?auto=format&fit=crop&w=800&q=80', 'label' => 'Especialidades de la Casa'],
                ['img' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80', 'label' => 'Cortes & Parrilla'],
                ['img' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=800&q=80', 'label' => 'Bebidas Frescas'],
            ],
            'pillars' => [
                ['icon' => '🔥', 'title' => 'Recién Preparado', 'desc' => 'Directo de cocina a tu mesa o paquete para llevar.'],
                ['icon' => '📱', 'title' => 'Menú QR en Vivo', 'desc' => 'Ordena sin esperar al mesero desde tu celular.'],
                ['icon' => '🚀', 'title' => 'Servicio Ágil', 'desc' => 'Comandas sincronizadas en cocina en tiempo real.'],
                ['icon' => '⭐', 'title' => 'Sabor Inigualable', 'desc' => 'Recetas de la casa con ingredientes seleccionados.'],
            ],
            'reviews' => [
                ['name' => 'Roberto M.', 'text' => 'Súper rápido el pedido desde el menú QR y todo llegó calientito.'],
                ['name' => 'Lucía P.', 'text' => 'Excelente sazón y porciones muy generosas. Volveremos seguro.'],
                ['name' => 'Héctor S.', 'text' => 'Muy práctico ordenar para pasar a recoger sin hacer fila.'],
            ],
        ],
        default => [
            'badge' => '🛍️ Calidad, Estilo & Envíos',
            'headline' => "Descubre lo nuevo en {$business->name}",
            'sub' => 'Catálogo actualizado en tiempo real, promociones exclusivas y atención personalizada al instante.',
            'cta' => 'Explorar Catálogo',
            'catalog_title' => 'Catálogo Destacado',
            'form_title' => 'Haz tu Pedido Ahora',
            'hero_img' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1200&q=80',
            'gallery' => [
                ['img' => 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?auto=format&fit=crop&w=800&q=80', 'label' => 'Nuevas Llegadas'],
                ['img' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=800&q=80', 'label' => 'Compra Fácil'],
                ['img' => 'https://images.unsplash.com/photo-1586880244406-556ebe35f282?auto=format&fit=crop&w=800&q=80', 'label' => 'Envíos Rápidos'],
            ],
            'pillars' => [
                ['icon' => '📦', 'title' => 'Stock Garantizado', 'desc' => 'Inventario sincronizado al momento en cada compra.'],
                ['icon' => '🚚', 'title' => 'Envío Express', 'desc' => 'Entrega local rápida o recolección en tienda.'],
                ['icon' => '💳', 'title' => 'Compra Segura', 'desc' => 'Confirmación inmediata y seguimiento por WhatsApp.'],
                ['icon' => '🏷️', 'title' => 'Promos Exclusivas', 'desc' => 'Descuentos especiales en nuestra tienda en línea.'],
            ],
            'reviews' => [
                ['name' => 'Alejandra C.', 'text' => 'Excelente calidad y me llegó el mismo día. Muy buena atención.'],
                ['name' => 'Diego L.', 'text' => 'Todo tal cual las fotos, súper fácil hacer el pedido desde el celular.'],
                ['name' => 'Patricia N.', 'text' => 'Me avisaron por WhatsApp en cuanto salió mi envío. Recomendadísimo.'],
            ],
        ],
    };
@endphp

@section('content')
<div class="min-h-screen bg-slate-950 text-slate-100 overflow-x-hidden">

    {{-- Sticky Glassmorphic Navbar --}}
    <nav class="sticky top-0 z-50 backdrop-blur-xl bg-slate-950/75 border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            <a href="#inicio" class="flex items-center gap-2.5 font-extrabold text-base sm:text-lg tracking-tight">
                <span class="w-8 h-8 rounded-xl brand-gradient flex items-center justify-center text-white text-sm shadow-lg">
                    {{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}
                </span>
                <span class="text-white">{{ $business->name }}</span>
            </a>

            <div class="hidden md:flex items-center gap-6 text-xs font-semibold text-slate-300">
                <a href="#experiencia" class="hover:text-white transition">Experiencia</a>
                <a href="#galeria" class="hover:text-white transition">Galería</a>
                <a href="#catalogo" class="hover:text-white transition">Catálogo</a>
                <a href="#opiniones" class="hover:text-white transition">Reseñas</a>
                <a href="#ubicacion" class="hover:text-white transition">Ubicación</a>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ $waOwnerLink }}" target="_blank"
                   class="px-4 py-2 rounded-xl text-xs font-bold text-white brand-gradient shadow-lg hover:opacity-95 transition">
                    💬 WhatsApp
                </a>
                <a href="{{ route('business.admin', ['slug' => $business->slug]) }}"
                   class="hidden sm:inline-flex px-3.5 py-2 rounded-xl text-xs font-semibold bg-white/10 hover:bg-white/15 text-slate-200 border border-white/10 transition">
                    Admin
                </a>
            </div>
        </div>
    </nav>

    {{-- Hero Section with Ambient Orbs & Glassmorphism --}}
    <section id="inicio" class="relative pt-14 pb-20 sm:pt-20 sm:pb-28 px-4 sm:px-6 overflow-hidden">
        <div class="pointer-events-none absolute -top-28 -left-24 w-96 h-96 rounded-full blur-3xl animate-glow" style="background: var(--bp);"></div>
        <div class="pointer-events-none absolute top-20 -right-24 w-96 h-96 rounded-full blur-3xl animate-glow" style="background: var(--bs); animation-delay: 1.5s;"></div>

        {{-- Decorative SVG Wave Grid --}}
        <svg class="pointer-events-none absolute inset-0 w-full h-full opacity-[0.06]" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
        </svg>

        <div class="relative max-w-7xl mx-auto grid lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-7 space-y-6">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold bg-white/10 border border-white/15 backdrop-blur-md text-white">
                    {{ $branchTheme['badge'] }}
                </span>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.08] text-white">
                    {{ $branchTheme['headline'] }}
                </h1>

                <p class="text-base sm:text-lg text-slate-300 max-w-xl leading-relaxed">
                    {{ $branchTheme['sub'] }}
                </p>

                <div class="flex flex-wrap items-center gap-3.5 pt-2">
                    <a href="#catalogo"
                       class="px-6 py-3.5 rounded-2xl brand-gradient text-white font-bold text-sm shadow-xl hover:scale-[1.02] transition transform">
                        {{ $branchTheme['cta'] }} →
                    </a>
                    <a href="{{ $waOwnerLink }}" target="_blank"
                       class="px-6 py-3.5 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/15 text-white font-semibold text-sm backdrop-blur-md transition">
                        Hablar por WhatsApp
                    </a>
                </div>

                <div class="pt-4 flex flex-wrap items-center gap-6 text-xs text-slate-300">
                    <div class="flex items-center gap-1.5">
                        <span class="text-amber-400">★★★★★</span>
                        <span class="font-bold text-white">4.9</span> Valoración local
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span>📍</span> {{ \Illuminate\Support\Str::limit($business->address, 48) }}
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5 relative">
                <div class="relative rounded-3xl overflow-hidden border border-white/15 shadow-2xl animate-float">
                    <img src="{{ $branchTheme['hero_img'] }}" alt="{{ $business->name }}"
                         class="w-full h-[380px] sm:h-[440px] object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/20 to-transparent"></div>
                    <div class="absolute bottom-5 left-5 right-5 p-4 rounded-2xl bg-slate-900/80 backdrop-blur-xl border border-white/15 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Atención inmediata</div>
                            <div class="text-sm font-extrabold text-white">{{ $business->name }}</div>
                        </div>
                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold text-white brand-gradient">
                            Abierto hoy
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if(session('landing_success'))
        <div class="max-w-5xl mx-auto px-4 mb-8">
            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-400/40 text-emerald-200 font-semibold text-sm flex items-center gap-2">
                <span>✅</span> {{ session('landing_success') }}
            </div>
        </div>
    @endif

    {{-- Value Pillars --}}
    <section id="experiencia" class="py-14 px-4 sm:px-6 border-y border-white/10 bg-slate-900/50">
        <div class="max-w-7xl mx-auto grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($branchTheme['pillars'] as $pillar)
                <div class="reveal p-6 rounded-2xl bg-white/[0.04] border border-white/10 hover:border-white/25 transition">
                    <div class="w-11 h-11 rounded-xl brand-gradient flex items-center justify-center text-xl mb-4 shadow-lg">
                        {{ $pillar['icon'] }}
                    </div>
                    <h3 class="font-bold text-white text-base">{{ $pillar['title'] }}</h3>
                    <p class="text-xs text-slate-400 mt-1.5 leading-relaxed">{{ $pillar['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Active Promotions Spotlight --}}
    @if($business->isFeatureEnabled('promociones') && $promotions->isNotEmpty())
        <section class="py-14 px-4 sm:px-6">
            <div class="max-w-7xl mx-auto">
                <div class="reveal rounded-3xl p-6 sm:p-8 brand-gradient shadow-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider bg-black/25 px-3 py-1 rounded-full text-white">🔥 Ofertas Especiales</span>
                            <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Promociones Vigentes</h2>
                        </div>
                        <a href="#catalogo" class="px-5 py-2.5 rounded-xl bg-white text-slate-900 font-bold text-xs shadow-md">Aprovechar ahora →</a>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4">
                        @foreach($promotions as $promo)
                            <div class="p-5 rounded-2xl bg-slate-950/75 backdrop-blur-md border border-white/15">
                                <div class="font-extrabold text-white text-base">{{ $promo->customer_name }}</div>
                                <p class="text-xs text-slate-300 mt-1.5">{{ $promo->notes }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Visual Gallery --}}
    <section id="galeria" class="py-16 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="reveal flex flex-wrap items-end justify-between gap-4 mb-8">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest brand-text-gradient">Inspiración & Calidad</span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-white mt-1">Conoce nuestro estilo</h2>
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                @foreach($branchTheme['gallery'] as $g)
                    <div class="reveal group relative rounded-3xl overflow-hidden border border-white/10 h-64 sm:h-72">
                        <img src="{{ $g['img'] }}" alt="{{ $g['label'] }}"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-transparent"></div>
                        <div class="absolute bottom-4 left-5 right-5 flex items-center justify-between">
                            <span class="font-bold text-white text-sm">{{ $g['label'] }}</span>
                            <span class="w-8 h-8 rounded-full brand-gradient flex items-center justify-center text-xs text-white">↗</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Interactive Catalog + Booking/Order Panel --}}
    <section id="catalogo" class="py-16 px-4 sm:px-6 bg-slate-900/60 border-y border-white/10">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-12 gap-10">
            <div class="lg:col-span-7 space-y-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest brand-text-gradient">Elige tu favorito</span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-white mt-1">{{ $branchTheme['catalog_title'] }}</h2>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($catalog as $item)
                        <div class="reveal group p-5 rounded-2xl bg-slate-950/80 border border-white/10 hover:border-white/30 transition flex flex-col justify-between cursor-pointer"
                             onclick="selectItem({{ $item->id }}, '{{ addslashes($item->name) }}'); trackLandingEvent('click_item', {item: '{{ addslashes($item->name) }}'})">
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-white/10 text-slate-300">{{ $item->category }}</span>
                                    @if($item->duration_minutes)
                                        <span class="text-xs text-slate-400">⏱ {{ $item->duration_minutes }} min</span>
                                    @endif
                                </div>
                                <h3 class="font-extrabold text-white text-base mt-3 group-hover:text-sky-300 transition">{{ $item->name }}</h3>
                            </div>
                            <div class="mt-5 pt-3 border-t border-white/10 flex items-center justify-between">
                                <span class="text-xl font-extrabold text-white">${{ number_format($item->price, 2) }}</span>
                                <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white brand-gradient group-hover:scale-105 transition">
                                    Seleccionar
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-5">
                <div id="reservar" class="sticky top-24 rounded-3xl bg-slate-950 border border-white/15 shadow-2xl p-6 sm:p-8 space-y-5">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider brand-text-gradient">Confirmación rápida</span>
                        <h3 class="text-xl font-extrabold text-white mt-1">{{ $branchTheme['form_title'] }}</h3>
                    </div>

                    <form action="{{ route('business.landing.action', ['slug' => $business->slug]) }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="catalog_item_id" id="selected_item_id">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Tu nombre *</label>
                            <input type="text" name="customer_name" required placeholder="Ej. Ana López"
                                class="w-full rounded-xl bg-slate-900 border border-white/15 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Tu WhatsApp</label>
                            <input type="text" name="customer_phone" placeholder="52155..."
                                class="w-full rounded-xl bg-slate-900 border border-white/15 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        </div>
                        @if($business->branch === 'estetica')
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Servicio elegido</label>
                                <input type="text" name="service_name" id="selected_service_name" value="{{ $catalog->first()?->name ?? 'Corte' }}"
                                    class="w-full rounded-xl bg-slate-900 border border-white/15 px-3.5 py-2.5 text-sm text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Fecha y hora preferida</label>
                                <input type="datetime-local" name="scheduled_at"
                                    class="w-full rounded-xl bg-slate-900 border border-white/15 px-3.5 py-2.5 text-sm text-white">
                            </div>
                        @else
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Producto / Platillo</label>
                                <input type="text" name="item_name" id="selected_service_name" value="{{ $catalog->first()?->name ?? 'Pedido' }}"
                                    class="w-full rounded-xl bg-slate-900 border border-white/15 px-3.5 py-2.5 text-sm text-white">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1">Cantidad</label>
                                    <input type="number" name="qty" value="1" min="1"
                                        class="w-full rounded-xl bg-slate-900 border border-white/15 px-3.5 py-2.5 text-sm text-white">
                                </div>
                                @if($business->branch === 'comandas')
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-300 mb-1">Mesa / Llevar</label>
                                        <input type="text" name="table_label" placeholder="Mesa 3"
                                            class="w-full rounded-xl bg-slate-900 border border-white/15 px-3.5 py-2.5 text-sm text-white">
                                    </div>
                                @endif
                            </div>
                        @endif
                        <button type="submit" class="w-full py-3.5 rounded-xl text-white font-bold text-sm brand-gradient shadow-lg hover:opacity-95 transition cursor-pointer">
                            Confirmar Solicitud →
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section id="opiniones" class="py-16 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto">
            <div class="reveal text-center max-w-2xl mx-auto mb-10">
                <span class="text-xs font-bold uppercase tracking-widest brand-text-gradient">Clientes Felices</span>
                <h2 class="text-2xl sm:text-4xl font-extrabold text-white mt-1">Lo que dicen de {{ $business->name }}</h2>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                @foreach($branchTheme['reviews'] as $rev)
                    <div class="reveal p-6 rounded-2xl bg-white/[0.04] border border-white/10 space-y-3">
                        <div class="text-amber-400 text-sm">★★★★★</div>
                        <p class="text-sm text-slate-300 leading-relaxed">"{{ $rev['text'] }}"</p>
                        <div class="text-xs font-bold text-white pt-1">— {{ $rev['name'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Location & Interactive Map --}}
    <section id="ubicacion" class="py-16 px-4 sm:px-6 bg-slate-900/50 border-t border-white/10">
        <div class="max-w-7xl mx-auto">
            <div class="reveal rounded-3xl bg-slate-950 border border-white/10 p-6 sm:p-8 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-widest brand-text-gradient">Visítanos</span>
                        <h2 class="text-2xl font-extrabold text-white mt-1">📍 Ubicación de {{ $business->name }}</h2>
                        <p class="text-sm text-slate-400 mt-1">{{ $business->address }}</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if($business->latitude && $business->longitude)
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $business->latitude }},{{ $business->longitude }}" target="_blank"
                               class="px-5 py-2.5 rounded-xl text-xs font-bold text-white brand-gradient shadow-md">
                                Cómo llegar en Maps →
                            </a>
                        @else
                            <a href="{{ $gmapsKit['maps_search_url'] ?? 'https://maps.google.com' }}" target="_blank"
                               class="px-5 py-2.5 rounded-xl text-xs font-bold text-white brand-gradient shadow-md">
                                Ver en Google Maps →
                            </a>
                        @endif
                    </div>
                </div>
                @if($business->latitude && $business->longitude)
                    <div id="landing-osm-map" class="w-full h-64 rounded-2xl border border-white/10 z-10"></div>
                @endif
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="py-8 px-4 border-t border-white/10 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
            <span>© {{ date('Y') }} {{ $business->name }}. Todos los derechos reservados.</span>
            <span>Impulsado por <a href="{{ route('home') }}" class="text-slate-300 font-semibold hover:underline">Aisy</a></span>
        </div>
    </footer>
</div>
@endsection

@push('scripts')
<script>
function selectItem(id, name) {
    document.getElementById('selected_item_id').value = id;
    const svc = document.getElementById('selected_service_name');
    if (svc) svc.value = name;
    document.getElementById('reservar')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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

// Scroll reveal animation
const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

@if($business->latitude && $business->longitude)
(function () {
    const map = L.map('landing-osm-map').setView([{{ $business->latitude }}, {{ $business->longitude }}], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    L.marker([{{ $business->latitude }}, {{ $business->longitude }}]).addTo(map).bindPopup("{{ addslashes($business->name) }}").openPopup();
})();
@endif
</script>
@endpush
