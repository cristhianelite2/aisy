@extends('layouts.app')

@section('title', "Creando {$business->name} — Aisy")

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-sky-600">Aprovisionamiento en vivo</span>
                <h1 class="text-2xl font-extrabold text-slate-900 mt-1">Preparando {{ $business->name }}</h1>
                <p class="text-sm text-slate-500 mt-1">URL asignada: <span class="font-mono text-slate-800">{{ url("/{$business->slug}") }}</span></p>
            </div>
            <div id="spinner" class="w-8 h-8 border-4 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div id="steps-list" class="space-y-3 mb-8">
            <div class="text-sm text-slate-500">Conectando flujo paso a paso...</div>
        </div>

        <div id="done-box" class="hidden space-y-6 pt-6 border-t border-slate-200">
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm font-medium">
                ✅ ¡Listo! Todas las capas de tu negocio están activas y sincronizadas.
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <a href="{{ route('business.landing', ['slug' => $business->slug]) }}"
                   class="flex items-center justify-center py-3 px-4 rounded-xl text-white font-bold text-sm shadow-sm transition"
                   style="background: {{ $business->primary_color }}">
                    🌐 Abrir mi Landing Page
                </a>
                <a href="{{ route('business.admin', ['slug' => $business->slug]) }}"
                   class="flex items-center justify-center py-3 px-4 rounded-xl bg-slate-900 text-white font-bold text-sm shadow-sm hover:bg-slate-800 transition">
                    ⚙️ Ir a mi Panel Admin
                </a>
            </div>

            <div class="p-5 rounded-xl bg-slate-50 border border-slate-200 text-center">
                <h3 class="font-bold text-sm text-slate-900 mb-1">Conecta tu WhatsApp en 1 paso</h3>
                <p class="text-xs text-slate-500 mb-3">Escanea este QR con tu WhatsApp para hablar directo con tu Asistente IA.</p>
                <div id="wa-qr-box" class="flex justify-center">
                    <button type="button" onclick="loadQr()" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold cursor-pointer">
                        Generar QR de WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const stepsEl = document.getElementById('steps-list');
const doneBox = document.getElementById('done-box');
const spinner = document.getElementById('spinner');
let firstEvent = true;

function appendStep(step) {
    if (firstEvent) {
        stepsEl.innerHTML = '';
        firstEvent = false;
    }
    const row = document.createElement('div');
    row.className = 'flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200';
    row.innerHTML = `
        <span class="w-6 h-6 rounded-full bg-emerald-500 text-white text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">✓</span>
        <div>
            <div class="font-bold text-sm text-slate-900">Paso ${step.step}: ${step.title}</div>
            <div class="text-xs text-slate-600 mt-0.5">${step.detail}</div>
        </div>
    `;
    stepsEl.appendChild(row);
}

const es = new EventSource("{{ route('onboarding.stream', ['slug' => $business->slug]) }}");
es.addEventListener('step', (e) => {
    appendStep(JSON.parse(e.data));
});
es.addEventListener('complete', () => {
    es.close();
    spinner.classList.add('hidden');
    doneBox.classList.remove('hidden');
    loadQr();
});
es.onerror = () => {
    es.close();
    spinner.classList.add('hidden');
    doneBox.classList.remove('hidden');
};

async function loadQr() {
    const box = document.getElementById('wa-qr-box');
    box.innerHTML = '<span class="text-xs text-slate-500">Obteniendo QR de WhatsApp...</span>';
    try {
        const res = await fetch("{{ route('business.admin.wa_qr', ['slug' => $business->slug]) }}");
        const data = await res.json();
        if (data.state === 'open') {
            box.innerHTML = `<span class="text-xs font-bold text-emerald-700">✅ WhatsApp conectado (${data.connectedPhone || 'activo'})</span>`;
        } else if (data.qrcode) {
            box.innerHTML = `<img src="${data.qrcode}" alt="WhatsApp QR" class="w-48 h-48 rounded-xl border border-slate-200">`;
        } else {
            box.innerHTML = `<span class="text-xs text-amber-700">Gateway WhatsApp en espera (${data.state || 'offline'}). Puedes escanearlo desde el Panel Admin.</span>`;
        }
    } catch {
        box.innerHTML = '<span class="text-xs text-slate-500">Disponible en el Panel Admin.</span>';
    }
}
</script>
@endpush
