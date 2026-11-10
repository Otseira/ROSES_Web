@extends('mobile.layout')

@section('title', 'Login - SIRO Mobile')

@section('content')
@php
    $logo = $pengaturan?->logo ? asset('storage/' . $pengaturan->logo) : null;
    $namaInstansi = $pengaturan?->nama_instansi ?: 'RSI Ropanasuri';
    $tagline = $pengaturan?->tagline ?: 'Sistem Informasi Absensi & Operasional';
@endphp

<div class="flex-1 flex flex-col justify-between p-6 relative overflow-hidden" 
     x-data="{ 
        login: '{{ old('login') }}', 
        password: '', 
        showPassword: false, 
        loading: false,
        submitForm() {
            if (!this.login || !this.password) return;
            this.loading = true;
            this.$refs.form.submit();
        }
     }">

    <!-- Background Accent Decoration (Emerald Wave) -->
    <div class="absolute -top-32 -right-32 w-80 h-80 rounded-full bg-gradient-to-br from-primary-400/20 to-primary-600/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-gradient-to-tr from-emerald-500/15 to-teal-400/10 blur-2xl pointer-events-none"></div>

    <!-- Header & Branding -->
    <div class="pt-8 text-center relative z-10">
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-white shadow-xl shadow-primary-500/10 border border-slate-100 p-3 mb-5 mx-auto">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $namaInstansi }}" class="w-full h-full object-contain">
            @else
                <div class="w-full h-full rounded-2xl bg-gradient-to-tr from-primary-500 to-emerald-400 flex items-center justify-center text-white">
                    <i data-lucide="activity" class="w-10 h-10"></i>
                </div>
            @endif
        </div>

        <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $namaInstansi }}</h1>
        <p class="text-xs font-medium text-emerald-600 mt-1 uppercase tracking-wider font-semibold">SIRO Mobile Web</p>
        <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">{{ $tagline }}</p>
    </div>

    <!-- Form Section -->
    <div class="my-auto py-6 relative z-10">
        <!-- Error Alert -->
        @if($errors->any())
        <div class="mb-5 p-4 rounded-2xl bg-rose-50 border border-rose-200/80 flex items-start gap-3 text-rose-700 animate-pulse">
            <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 mt-0.5 text-rose-500"></i>
            <div class="text-xs font-medium leading-relaxed">
                {{ $errors->first() }}
            </div>
        </div>
        @endif

        <form x-ref="form" method="POST" action="{{ route('app.login.post') }}" @submit.prevent="submitForm" class="space-y-4">
            @csrf

            <!-- Input Username / NIK -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">NIK atau Username</label>
                <div class="relative flex items-center">
                    <div class="absolute left-4 pointer-events-none text-slate-400">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <input type="text" 
                           name="login" 
                           x-model="login"
                           required 
                           autofocus
                           autocomplete="username"
                           placeholder="Masukkan NIK atau Username"
                           class="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-white border border-slate-200 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all shadow-sm">
                </div>
            </div>

            <!-- Input Password -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Kata Sandi</label>
                <div class="relative flex items-center">
                    <div class="absolute left-4 pointer-events-none text-slate-400">
                        <i data-lucide="lock" class="w-5 h-5"></i>
                    </div>
                    <input :type="showPassword ? 'text' : 'password'" 
                           name="password" 
                           x-model="password"
                           required
                           autocomplete="current-password"
                           placeholder="Masukkan kata sandi Anda"
                           class="w-full pl-12 pr-12 py-3.5 rounded-2xl bg-white border border-slate-200 text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all shadow-sm">
                    <button type="button" 
                            @click="showPassword = !showPassword"
                            tabindex="-1"
                            class="absolute right-4 text-slate-400 hover:text-slate-600 transition-colors">
                        <i :data-lucide="showPassword ? 'eye-off' : 'eye'" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <!-- Tombol Masuk -->
            <button type="submit" 
                    :disabled="loading"
                    class="w-full mt-4 py-4 px-6 rounded-2xl bg-gradient-to-r from-primary-600 to-emerald-500 hover:from-primary-700 hover:to-emerald-600 active:scale-[0.98] text-white font-bold text-sm tracking-wide shadow-lg shadow-primary-500/30 flex items-center justify-center gap-2 transition-all disabled:opacity-75 disabled:pointer-events-none">
                <template x-if="!loading">
                    <div class="flex items-center gap-2">
                        <span>Masuk ke SIRO Mobile</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </div>
                </template>
                <template x-if="loading">
                    <div class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Memverifikasi...</span>
                    </div>
                </template>
            </button>
        </form>

        <!-- Petunjuk iPhone PWA -->
        <div class="mt-8 p-3.5 rounded-2xl bg-emerald-50/80 border border-emerald-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-white text-emerald-600 flex items-center justify-center shadow-xs shrink-0">
                <i data-lucide="smartphone" class="w-4 h-4"></i>
            </div>
            <p class="text-[11px] text-emerald-800 leading-tight">
                <strong>Pengguna iPhone:</strong> Buka di Safari, ketuk tombol <em>Bagikan (Share)</em> lalu pilih <strong>"Tambah ke Layar Utama"</strong>.
            </p>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center pt-4 pb-2 relative z-10 border-t border-slate-100">
        <p class="text-[11px] text-slate-400">&copy; {{ date('Y') }} {{ $namaInstansi }}. All rights reserved.</p>
        <a href="{{ route('login') }}" class="inline-block mt-1 text-[11px] text-slate-500 hover:text-primary-600 font-medium">
            Masuk ke Portal Dashboard Web &rarr;
        </a>
    </div>
</div>
@endsection
