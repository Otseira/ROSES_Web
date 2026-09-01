<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - SIM Absensi RS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#0f172a', secondary: '#334155', accent: '#0ea5e9' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-slate-50 font-sans antialiased text-slate-800">

    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: true }">

        <aside :class="sidebarOpen ? 'w-72' : 'w-20'"
            class="bg-primary text-slate-300 transition-all duration-300 flex flex-col shadow-2xl z-20 relative">

            @php
            $pengaturanGlobal = \App\Models\PengaturanAplikasi::first();
            // ✅ BARU: ambil role user sekali agar lebih rapi
            $userRole = auth()->user()->role;
            @endphp

            <div class="h-20 flex items-center justify-center border-b border-slate-800/50 px-4">
                <a href="/dashboard" class="flex items-center gap-3 w-full"
                    :class="sidebarOpen ? 'justify-start' : 'justify-center'">
                    @if($pengaturanGlobal && $pengaturanGlobal->logo)
                    <img src="{{ asset('storage/' . $pengaturanGlobal->logo) }}" alt="Logo RS"
                        class="h-10 w-auto object-contain shrink-0 drop-shadow-md bg-white/10 p-1 rounded-lg">
                    @else
                    <div
                        class="bg-gradient-to-tr from-accent to-blue-400 p-2 rounded-xl text-white shadow-lg shadow-accent/30 shrink-0">
                        <i data-lucide="activity" class="w-6 h-6"></i>
                    </div>
                    @endif

                    <span x-show="sidebarOpen"
                        class="text-sm font-extrabold text-white tracking-wide truncate leading-tight whitespace-normal">
                        {{ $pengaturanGlobal->nama_instansi ?? 'SIM Absensi Ropanasuri' }}
                    </span>
                </a>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <div x-show="sidebarOpen"
                    class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4 mt-2 px-3">Menu Utama
                </div>

                <a href="/dashboard"
                    class="flex items-center gap-4 px-3 py-3 rounded-xl transition-all duration-200 {{ request()->is('dashboard') ? 'bg-accent/10 text-accent font-medium' : 'hover:bg-slate-800 hover:text-white' }}">
                    <i data-lucide="layout-dashboard"
                        class="w-5 h-5 {{ request()->is('dashboard') ? 'text-accent' : 'text-slate-400' }}"></i>
                    <span x-show="sidebarOpen">Dashboard</span>
                </a>

                @if(in_array($userRole, ['superadmin', 'hrd']))
                <div x-show="sidebarOpen"
                    class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4 mt-6 px-3">Master Data
                </div>
                <a href="/master-pegawai"
                    class="flex items-center gap-4 px-3 py-3 rounded-xl transition-all duration-200 {{ request()->is('master-pegawai*') ? 'bg-accent/10 text-accent font-medium' : 'hover:bg-slate-800 hover:text-white' }}">
                    <i data-lucide="users"
                        class="w-5 h-5 {{ request()->is('master-pegawai*') ? 'text-accent' : 'text-slate-400' }}"></i>
                    <span x-show="sidebarOpen">Data Pegawai</span>
                </a>
                <a href="/master-shift"
                    class="flex items-center gap-4 px-3 py-3 rounded-xl transition-all duration-200 {{ request()->is('master-shift*') ? 'bg-accent/10 text-accent font-medium' : 'hover:bg-slate-800 hover:text-white' }}">
                    <i data-lucide="clock"
                        class="w-5 h-5 {{ request()->is('master-shift*') ? 'text-accent' : 'text-slate-400' }}"></i>
                    <span x-show="sidebarOpen">Master Shift</span>
                </a>
                @endif

                {{-- ✅ PERUBAHAN 1: Tambahkan 'manajer' ke section Operasional & Laporan --}}
                @if(in_array($userRole, ['superadmin', 'hrd', 'manajer', 'kepala_unit', 'penanggung_jawab']))
                <div x-show="sidebarOpen"
                    class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4 mt-6 px-3">Operasional &
                    Laporan</div>

                {{-- ✅ PERUBAHAN 2: Tambahkan 'manajer' juga ke link Roster Jadwal --}}
                @if(in_array($userRole, ['superadmin', 'hrd', 'manajer', 'kepala_unit', 'penanggung_jawab']))
                <a href="/roster"
                    class="flex items-center gap-4 px-3 py-3 rounded-xl transition-all duration-200 {{ request()->is('roster*') ? 'bg-accent/10 text-accent font-medium' : 'hover:bg-slate-800 hover:text-white' }}">
                    <i data-lucide="calendar-days"
                        class="w-5 h-5 {{ request()->is('roster*') ? 'text-accent' : 'text-slate-400' }}"></i>
                    <span x-show="sidebarOpen">Roster Jadwal</span>
                </a>
                @endif

                <a href="/laporan-payroll"
                    class="flex items-center gap-4 px-3 py-3 rounded-xl transition-all duration-200 {{ request()->is('laporan-payroll*') ? 'bg-accent/10 text-accent font-medium' : 'hover:bg-slate-800 hover:text-white' }}">
                    <i data-lucide="calculator"
                        class="w-5 h-5 {{ request()->is('laporan-payroll*') ? 'text-accent' : 'text-slate-400' }}"></i>
                    <span x-show="sidebarOpen">Rekap Laporan</span>
                </a>
                @endif

                @if($userRole === 'superadmin')
                <div x-show="sidebarOpen"
                    class="text-xs font-semibold text-rose-400 uppercase tracking-wider mb-4 mt-6 px-3">Super Admin
                </div>
                <a href="/pengaturan"
                    class="flex items-center gap-4 px-3 py-3 rounded-xl transition-all duration-200 {{ request()->is('pengaturan*') ? 'bg-rose-500/10 text-rose-400 font-medium' : 'hover:bg-slate-800 hover:text-white' }}">
                    <i data-lucide="settings"
                        class="w-5 h-5 {{ request()->is('pengaturan*') ? 'text-rose-400' : 'text-slate-400' }}"></i>
                    <span x-show="sidebarOpen">Pengaturan Sistem</span>
                </a>
                @endif
            </nav>

        </aside>

        <main class="flex-1 flex flex-col overflow-hidden relative">

            <header
                class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-8 z-40 sticky top-0">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="p-2 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <h1 class="text-xl font-bold text-slate-800 hidden md:block">@yield('page_title')</h1>
                </div>

                <div class="flex items-center gap-6">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-slate-700">{{ auth()->user()->name }}</p>
                        <p class="text-xs font-medium text-slate-400">{{ auth()->user()->unitKerja->nama_unit ?? 'Unit
                            Belum Diatur' }}</p>
                    </div>

                    <div class="relative" x-data="{ userMenuOpen: false }">
                        <button @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2 p-1 rounded-full hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2">
                            <div
                                class="w-10 h-10 rounded-full bg-gradient-to-tr from-accent to-blue-300 flex items-center justify-center text-white font-bold shadow-md">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                        </button>

                        <div x-show="userMenuOpen" @click.outside="userMenuOpen = false"
                            x-transition.opacity.duration.200ms
                            class="absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-2 z-50">

                            <div class="px-4 py-2 border-b border-slate-100 mb-1">
                                <p class="text-sm text-slate-500">Masuk sebagai</p>
                                <p class="text-sm font-bold text-slate-800 truncate">{{ $userRole }}</p>
                            </div>

                            <a href="/profil/ubah-password"
                                class="w-full text-left flex items-center gap-3 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-accent transition-colors">
                                <i data-lucide="key-round" class="w-4 h-4"></i> Ganti Password
                            </a>

                            <a href="#"
                                onclick="event.preventDefault(); document.getElementById('form-logout-rahasia').submit();"
                                class="w-full text-left flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
                            </a>
                        </div>
                    </div>

                    <form id="form-logout-rahasia" action="/logout" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </header>

            <div class="flex-1 overflow-x-hidden overflow-y-auto p-8 bg-slate-50">
                @yield('content')
            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>