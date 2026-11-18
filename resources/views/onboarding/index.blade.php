@extends('layouts.app')

@section('title', 'Aisy — Crea tu negocio en 1 minuto')

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

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Dirección del local</label>
                    <input type="text" name="address" value="{{ old('address') }}" placeholder="Ej. Av. Insurgentes Sur 800, CDMX"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
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
