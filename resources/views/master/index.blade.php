@extends('layouts.app')

@section('title', 'Cuenta Maestra — Aisy')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
    <header class="flex flex-wrap items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Panel Super-Administrador</span>
            <h1 class="text-2xl font-extrabold text-slate-900 mt-1">Cuenta Maestra — Todos los Negocios y Actividades</h1>
            <p class="text-xs text-slate-500 mt-1">
                Acceso Odoo ERP local: <code class="font-mono text-slate-800">{{ $odooUrl }}</code> · Usuario: <code class="font-mono text-slate-800">maestro@aisy.ceballosleon.com</code> / <code class="font-mono text-slate-800">AisyMaster2026!</code>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ $odooUrl }}" target="_blank" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                Abrir Odoo Maestro →
            </a>
            <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold">
                ← Inicio
            </a>
        </div>
    </header>

    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-bold text-slate-900 mb-4">🏢 Negocios Registrados ({{ $businesses->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-200 text-slate-500 uppercase">
                    <tr>
                        <th class="py-2.5 pr-4">Negocio</th>
                        <th class="py-2.5 pr-4">Giro</th>
                        <th class="py-2.5 pr-4">Propietario</th>
                        <th class="py-2.5 pr-4">Ref. Compañía</th>
                        <th class="py-2.5 pr-4">Operaciones</th>
                        <th class="py-2.5 pr-4">Actividades</th>
                        <th class="py-2.5">Accesos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($businesses as $b)
                        <tr>
                            <td class="py-3 pr-4 font-bold text-slate-900">{{ $b->name }}</td>
                            <td class="py-3 pr-4 capitalize">{{ $b->branch }}</td>
                            <td class="py-3 pr-4">{{ $b->owner_name }} ({{ $b->owner_phone }})</td>
                            <td class="py-3 pr-4 font-mono">#{{ $b->odoo_company_id }}</td>
                            <td class="py-3 pr-4">{{ $b->operations_count }}</td>
                            <td class="py-3 pr-4">{{ $b->activities_count }}</td>
                            <td class="py-3 flex gap-2">
                                <a href="{{ route('business.landing', ['slug' => $b->slug]) }}" target="_blank" class="text-sky-600 font-semibold hover:underline">Sitio</a>
                                <a href="{{ route('business.admin', ['slug' => $b->slug]) }}" class="text-slate-700 font-semibold hover:underline">Panel</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-bold text-slate-900 mb-4">📜 Registro Global de Actividades</h2>
        <div class="space-y-2 max-h-96 overflow-y-auto">
            @foreach($recentActivities as $act)
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs flex items-center justify-between gap-4">
                    <div>
                        <span class="font-bold text-slate-900">[{{ $act->business?->name ?? 'Negocio' }}]</span>
                        <span class="font-semibold text-slate-800">{{ $act->title }}</span>
                        <div class="text-slate-500 mt-0.5">{{ $act->details }}</div>
                    </div>
                    <span class="text-[11px] text-slate-400 shrink-0">{{ $act->created_at->format('d/m H:i') }}</span>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
