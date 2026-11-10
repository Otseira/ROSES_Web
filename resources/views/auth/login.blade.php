<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Login - ROSES RS</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { 
                        primary: '#0f172a', 
                        accent: '#0ea5e9',
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-slate-50 font-sans antialiased flex items-center justify-center min-h-[100dvh] p-4 sm:p-6 relative overflow-x-hidden selection:bg-emerald-500 selection:text-white"
      x-data="{ 
          portal: (/iPhone|iPad|iPod|Android|Mobile/i.test(navigator.userAgent) || window.innerWidth < 640) ? 'mobile' : 'web',
          showPassword: false 
      }">

    <!-- Background Glow Accents -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-accent/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md bg-white/90 backdrop-blur-xl p-6 sm:p-8 rounded-3xl shadow-2xl border border-white relative z-10 transition-all">
        @php
            $pengaturanGlobal = \App\Models\PengaturanAplikasi::first();
            $logo = $pengaturanGlobal?->logo ? asset('storage/' . $pengaturanGlobal->logo) : null;
            $namaInstansi = $pengaturanGlobal?->nama_instansi ?: 'SIM Absensi RS';
        @endphp

        <!-- Logo & Branding -->
        <div class="flex justify-center mb-4">
            @if($logo)
                <img src="{{ $logo }}" alt="Logo" class="h-16 sm:h-20 w-auto object-contain drop-shadow-md">
            @else
                <div class="w-16 h-16 bg-gradient-to-tr from-accent to-blue-500 rounded-2xl flex items-center justify-center shadow-lg shadow-accent/30 text-white">
                    <i data-lucide="activity" class="w-8 h-8"></i>
                </div>
            @endif
        </div>

        <div class="text-center mb-6">
            <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight mb-1">
                {{ $namaInstansi }}
            </h2>
            <p class="text-xs sm:text-sm font-medium text-slate-500">
                Sistem Informasi Presensi & Manajemen Operasional
            </p>
        </div>

        <!-- Alert Error -->
        @if ($errors->any())
        <div class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-2xl text-xs sm:text-sm mb-5 flex gap-3 items-start animate-shake">
            <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-500 mt-0.5"></i>
            <span class="font-medium leading-relaxed">{{ $errors->first() }}</span>
        </div>
        @endif

        @if(session('info'))
        <div class="bg-blue-50 border border-blue-100 text-blue-700 px-4 py-3 rounded-2xl text-xs sm:text-sm mb-5 flex gap-3 items-start">
            <i data-lucide="info" class="w-5 h-5 shrink-0 text-blue-500 mt-0.5"></i>
            <span class="font-medium leading-relaxed">{{ session('info') }}</span>
        </div>
        @endif

        <form action="/login" method="POST" class="space-y-4">
            @csrf

            <!-- ============================================================== -->
            <!-- PILIHAN PROSES / PORTAL: ROSES WEB vs ROSES MOBILE (iPHONE) -->
            <!-- ============================================================== -->
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
                    Pilih Portal Masuk
                </label>
                
                <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1.5 rounded-2xl border border-slate-200/60">
                    <!-- Opsi 1: Portal Web Dashboard -->
                    <button type="button" 
                            @click="portal = 'web'" 
                            :class="portal === 'web' 
                                ? 'bg-white text-slate-900 shadow-sm border border-slate-200/80 font-bold' 
                                : 'text-slate-500 font-medium hover:text-slate-800 hover:bg-slate-200/50'"
                            class="py-2.5 px-3 rounded-xl text-xs flex flex-col items-center justify-center gap-1 transition-all">
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="layout-dashboard" class="w-4 h-4" :class="portal === 'web' ? 'text-accent' : 'text-slate-400'"></i>
                            <span>ROSES Web</span>
                        </div>
                        <span class="text-[10px] text-slate-400 font-normal">Manajemen & PC</span>
                    </button>

                    <!-- Opsi 2: ROSES Mobile (iPhone / Karyawan) -->
                    <button type="button" 
                            @click="portal = 'mobile'" 
                            :class="portal === 'mobile' 
                                ? 'bg-gradient-to-r from-emerald-600 to-teal-500 text-white shadow-md font-bold' 
                                : 'text-slate-500 font-medium hover:text-slate-800 hover:bg-slate-200/50'"
                            class="py-2.5 px-3 rounded-xl text-xs flex flex-col items-center justify-center gap-1 transition-all">
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="smartphone" class="w-4 h-4" :class="portal === 'mobile' ? 'text-white' : 'text-slate-400'"></i>
                            <span>ROSES Mobile</span>
                        </div>
                        <span class="text-[10px] font-normal" :class="portal === 'mobile' ? 'text-emerald-100' : 'text-slate-400'">Khusus iPhone</span>
                    </button>
                </div>

                <input type="hidden" name="target_portal" :value="portal">

                <!-- Penjelasan Singkat Mode Terpilih -->
                <div class="mt-2 text-[11px] px-1 text-slate-500 flex items-center gap-1.5">
                    <template x-if="portal === 'web'">
                        <span class="text-slate-500 flex items-center gap-1">
                            <i data-lucide="info" class="w-3.5 h-3.5 text-accent shrink-0"></i>
                            <span><strong>Mode Web:</strong> Akses data shift, jadwal dinas, dan rekapitulasi payroll.</span>
                        </span>
                    </template>
                    <template x-if="portal === 'mobile'">
                        <span class="text-emerald-700 flex items-center gap-1">
                            <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600 shrink-0"></i>
                            <span><strong>Mode Mobile:</strong> Presensi selfie kamera, verifikasi GPS radius RS, dan lembur.</span>
                        </span>
                    </template>
                </div>
            </div>

            <!-- Input Username / NIK -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Username atau NIK</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </div>
                    <input type="text" name="login" required
                        class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-xs sm:text-sm font-semibold text-slate-800 placeholder-slate-400"
                        placeholder="Masukkan NIK atau Username" value="{{ old('login') }}">
                </div>
            </div>

            <!-- Input Kata Sandi -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kata Sandi</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </div>
                    <input :type="showPassword ? 'text' : 'password'" name="password" required
                        class="w-full pl-10 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-xs sm:text-sm font-semibold text-slate-800 placeholder-slate-400"
                        placeholder="••••••••">
                    <button type="button" @click="showPassword = !showPassword" tabindex="-1" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                        <i :data-lucide="showPassword ? 'eye-off' : 'eye'" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Tombol Submit Dinamis Sesuai Portal -->
            <button type="submit"
                :class="portal === 'mobile' 
                    ? 'bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-700 hover:to-teal-600 shadow-emerald-500/30' 
                    : 'bg-primary hover:bg-slate-800 shadow-primary/20'"
                class="w-full text-white font-bold py-3.5 px-4 rounded-2xl shadow-lg transition-all flex justify-center items-center gap-2 mt-5 text-sm active:scale-[0.98]">
                <template x-if="portal === 'web'">
                    <div class="flex items-center gap-2">
                        <span>Masuk ke ROSES Web</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </div>
                </template>
                <template x-if="portal === 'mobile'">
                    <div class="flex items-center gap-2">
                        <span>Masuk ke ROSES Mobile</span>
                        <i data-lucide="smartphone" class="w-4 h-4"></i>
                    </div>
                </template>
            </button>
        </form>

        <!-- Informasi Khusus Android vs iPhone -->
        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
            <span>📱 <strong>iPhone:</strong> ROSES Web</span>
            <span>🤖 <strong>Android:</strong> ROSES Mobile APK</span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) lucide.createIcons();
        });
        document.addEventListener('alpine:initialized', () => {
            if (window.lucide) setTimeout(() => lucide.createIcons(), 50);
        });
    </script>
</body>

</html>
