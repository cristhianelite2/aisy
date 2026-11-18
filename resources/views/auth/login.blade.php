@extends('layouts.app')

@section('title', 'Iniciar sesión — Aisy')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="text-center">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-800">
                🔐 Acceso Seguro Aisy
            </span>
            <h1 class="text-2xl font-extrabold text-slate-900 mt-3">Inicia sesión en tu panel</h1>
            <p class="text-xs text-slate-500 mt-1">Administra tus negocios, módulos, Odoo y Asistente WhatsApp.</p>
        </div>

        @if($errors->any())
            <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login.submit') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Correo electrónico</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="demo@aisy.ceballosleon.com"
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••"
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
            </div>
            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-slate-300">
                Recordar mi sesión
            </label>
            <button type="submit" class="w-full py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm cursor-pointer">
                Entrar al Panel →
            </button>
        </form>

        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1">
            <div class="font-bold text-slate-800">Cuenta Demo (4 negocios precargados):</div>
            <div>Correo: <code class="font-mono text-slate-900">demo@aisy.ceballosleon.com</code></div>
            <div>Clave: <code class="font-mono text-slate-900">Aisy2026!</code></div>
        </div>

        <div class="text-center">
            <a href="{{ route('home') }}" class="text-xs font-semibold text-sky-600 hover:underline">← Volver al inicio / Crear negocio</a>
        </div>
    </div>
</div>
@endsection
