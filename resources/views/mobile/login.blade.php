@extends('mobile.layout')

@section('title', 'Login - ROSES Mobile')

@section('content')
@php
    $logo = $pengaturan?->logo ? asset('storage/' . $pengaturan->logo) : null;
    $namaInstansi = $pengaturan?->nama_instansi ?: 'RSI Ropanasuri';
    $tagline = $pengaturan?->tagline ?: 'Sistem Informasi Absensi & Operasional';
@endphp

<div class="flex-1 flex flex-col justify-between p-5 sm:p-6 relative overflow-hidden" 
     x-data="{ 
        portal: 'mobile',
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

    <!-- Header & Branding (Safe Area Top Aware) -->
    <div class="pt-safe text-center relative z-10">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-3xl bg-white shadow-xl shadow-primary-500/10 border border-slate-100 p-3 mb-4 mx-auto">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $namaInstansi }}" class="w-full h-full object-contain">
            @else
                <div class="w-full h-full rounded-2xl bg-gradient-to-tr from-primary-500 to-emerald-400 flex items-center justify-center text-white">
                    <i data-lucide="activity" class="w-8 h-8 sm:w-10 sm:h-10"></i>
                </div>
            @endif
        </div>

        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-tight">{{ $namaInstansi }}</h1>
        <p class="text-[11px] sm:text-xs font-semibold text-emerald-600 mt-0.5 uppercase tracking-wider">ROSES Mobile Web</p>
        <p class="text-[11px] sm:text-xs text-slate-500 mt-1 max-w-xs mx-auto leading-relaxed">{{ $tagline }}</p>
    </div>

    <!-- Form Section -->
    <div class="my-auto py-4 relative z-10">
        <!-- Error Alert -->
        @if($errors->any())
        <div class="mb-4 p-3.5 rounded-2xl bg-rose-50 border border-rose-200/80 flex items-start gap-2.5 text-rose-700 animate-pulse">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 mt-0.5 text-rose-500"></i>
            <div class="text-xs font-medium leading-relaxed">
                {{ $errors->first() }}
            </div>
        </div>
        @endif

        <form x-ref="form" method="POST" :action="portal === 'web' ? '{{ route('login.post', [], false) }}' : '{{ route('app.login.post', [], false) }}'" @submit.prevent="submitForm" class="space-y-3.5">
            @csrf

            <!-- ============================================================== -->
            <!-- PILIHAN PROSES / PORTAL: ROSES MOBILE vs PORTAL WEB -->
            <!-- ============================================================== -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Pilih Portal Masuk</label>
                <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1 rounded-2xl border border-slate-200/70">
                    <button type="button" 
                            @click="portal = 'mobile'" 
                            :class="portal === 'mobile' ? 'bg-gradient-to-r from-emerald-600 to-teal-500 text-white font-bold shadow-xs' : 'text-slate-500 font-medium hover:text-slate-800'"
                            class="py-2 px-3 rounded-xl text-xs flex items-center justify-center gap-1.5 transition-all">
                        <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                        <span>ROSES Mobile</span>
                    </button>

                    <button type="button" 
                            @click="portal = 'web'" 
                            :class="portal === 'web' ? 'bg-white text-slate-900 font-bold shadow-xs' : 'text-slate-500 font-medium hover:text-slate-800'"
                            class="py-2 px-3 rounded-xl text-xs flex items-center justify-center gap-1.5 transition-all">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
                        <span>Portal Web</span>
                    </button>
                </div>
                <input type="hidden" name="target_portal" :value="portal">
            </div>

            <!-- Input Username / NIK -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">NIK atau Username</label>
                <div class="relative flex items-center">
                    <div class="absolute left-3.5 pointer-events-none text-slate-400">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </div>
                    <input type="text" 
                           name="login" 
                           x-model="login"
                           required 
                           autofocus
                           autocomplete="username"
                           placeholder="Masukkan NIK atau Username"
                           class="w-full pl-10 pr-4 py-3 rounded-2xl bg-white border border-slate-200 text-slate-900 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all shadow-xs">
                </div>
            </div>

            <!-- Input Password -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Kata Sandi</label>
                <div class="relative flex items-center">
                    <div class="absolute left-3.5 pointer-events-none text-slate-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </div>
                    <input :type="showPassword ? 'text' : 'password'" 
                           name="password" 
                           x-model="password"
                           required
                           autocomplete="current-password"
                           placeholder="Masukkan kata sandi Anda"
                           class="w-full pl-10 pr-10 py-3 rounded-2xl bg-white border border-slate-200 text-slate-900 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all shadow-xs">
                    <button type="button" 
                            @click="showPassword = !showPassword"
                            tabindex="-1"
                            class="absolute right-3.5 text-slate-400 hover:text-slate-600 transition-colors">
                        <i :data-lucide="showPassword ? 'eye-off' : 'eye'" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Tombol Masuk Dinamis -->
            <button type="submit" 
                    :disabled="loading"
                    :class="portal === 'mobile' 
                        ? 'bg-gradient-to-r from-primary-600 to-emerald-500 hover:from-primary-700 hover:to-emerald-600 shadow-primary-500/30' 
                        : 'bg-slate-900 hover:bg-slate-800 shadow-slate-900/20'"
                    class="w-full mt-3 py-3.5 px-6 rounded-2xl text-white font-bold text-xs sm:text-sm tracking-wide shadow-lg flex items-center justify-center gap-2 transition-all active:scale-[0.98] disabled:opacity-75 disabled:pointer-events-none">
                <template x-if="!loading">
                    <div class="flex items-center gap-2">
                        <span x-text="portal === 'mobile' ? 'Masuk ke ROSES Mobile' : 'Masuk ke Dashboard Web'"></span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </div>
                </template>
                <template x-if="loading">
                    <div class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Memverifikasi...</span>
                    </div>
                </template>
            </button>
        </form>

        <!-- Petunjuk iPhone PWA -->
        <div class="mt-5 p-3 rounded-2xl bg-emerald-50/80 border border-emerald-100 flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-white text-emerald-600 flex items-center justify-center shadow-xs shrink-0">
                <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
            </div>
            <p class="text-[10px] sm:text-[11px] text-emerald-800 leading-tight">
                <strong>Pengguna iPhone:</strong> Buka di Safari, ketuk tombol <em>Share</em> lalu pilih <strong>"Tambah ke Layar Utama"</strong>.
            </p>
        </div>
    </div>

    <!-- Footer (Safe Area Bottom Aware) -->
    <div class="text-center pt-2.5 pb-safe relative z-10 border-t border-slate-100 text-[10px] text-slate-400 leading-tight">
        <p>&copy; {{ date('Y') }} <strong class="text-slate-600">IT RSKB Ropanasuri</strong></p>
        <p class="text-[9px] text-slate-400 mt-0.5">(Luthfi Ariesto Prayoga &amp; Teddi Takejo Saogok)</p>
    </div>
</div>
@endsection
