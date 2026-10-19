@extends('layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Ringkasan Dashboard')

@push('styles')
<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>
@endpush

@section('content')
{{-- Notifikasi Sukses / Pesan --}}
@if(session('success'))
<div class="bg-emerald-50/90 backdrop-blur-sm border border-emerald-200 text-emerald-800 px-5 py-3.5 rounded-2xl text-sm mb-6 flex items-center justify-between shadow-sm animate-fade-in">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/30">
            <i data-lucide="check" class="w-4 h-4"></i>
        </div>
        <span class="font-semibold">{{ session('success') }}</span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 text-lg leading-none">&times;</button>
</div>
@endif

@if(session('error'))
<div class="bg-rose-50/90 backdrop-blur-sm border border-rose-200 text-rose-800 px-5 py-3.5 rounded-2xl text-sm mb-6 flex items-center justify-between shadow-sm animate-fade-in">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-rose-500/30">
            <i data-lucide="alert-circle" class="w-4 h-4"></i>
        </div>
        <span class="font-semibold">{{ session('error') }}</span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800 text-lg leading-none">&times;</button>
</div>
@endif

{{-- ===== 1. HERO BANNER: SAMBUTAN & JAM DIGITAL REALTIME ===== --}}
<div class="relative bg-gradient-to-br from-slate-900 via-slate-800 to-primary rounded-3xl p-6 sm:p-8 md:p-10 shadow-xl shadow-slate-900/10 mb-8 overflow-hidden text-white border border-slate-700/40 group">
    {{-- Decorative Glow Circles --}}
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-accent/20 rounded-full blur-3xl pointer-events-none group-hover:scale-105 transition-transform duration-700"></div>
    <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/10 px-3 py-1 rounded-full text-xs font-semibold text-accent mb-3 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>SIRO • RSKB Ropanasuri</span>
            </div>
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight mb-2 text-white">
                {{ $salam }}, {{ $userLogin->name }}! 👋
            </h2>
            <p class="text-slate-300 text-xs sm:text-sm max-w-2xl leading-relaxed">
                Selamat bertugas di panel kendali presensi & roster. 
                Hak akses aktif: <span class="font-bold text-white uppercase bg-white/15 px-2 py-0.5 rounded-md border border-white/10 tracking-wider text-[11px]">{{ $userLogin->role }}</span>
                @if($userLogin->unitKerja)
                • Unit: <span class="font-bold text-emerald-300">{{ $userLogin->unitKerja->nama_unit }}</span>
                @endif
            </p>
        </div>

        {{-- Widget Jam Realtime & Tanggal --}}
        <div class="bg-white/10 backdrop-blur-md border border-white/15 rounded-2xl p-4 sm:p-5 flex flex-col justify-center min-w-[240px] shadow-lg shrink-0">
            <div class="flex items-center justify-between gap-3 text-slate-300 text-xs font-semibold uppercase tracking-wider mb-1.5">
                <span class="flex items-center gap-1.5 text-emerald-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    Live Clock
                </span>
                <span class="text-[10px] text-slate-400">WIB</span>
            </div>
            <div class="text-2xl sm:text-3xl font-black tracking-tight text-white font-mono" id="live-clock">
                --:--:--
            </div>
            <div class="text-xs text-slate-300 font-medium mt-1 flex items-center gap-1.5">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-accent shrink-0"></i>
                <span>{{ \Carbon\Carbon::parse($hariIni)->translatedFormat('l, d F Y') }}</span>
            </div>
        </div>
    </div>
</div>

{{-- ===== 2. ALERT LEMBUR MENUNGGU VALIDASI (Khusus Manajemen) ===== --}}
@if($canSeeManagement && $pendingLemburCount > 0)
<div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/90 rounded-2xl p-4 sm:p-5 mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm animate-fade-in">
    <div class="flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-md shadow-amber-500/20">
            <i data-lucide="bell-ring" class="w-5 h-5 animate-bounce"></i>
        </div>
        <div>
            <h4 class="font-bold text-slate-900 text-sm sm:text-base">Perlu Tindakan: Validasi Pengajuan Lembur</h4>
            <p class="text-xs text-slate-600 mt-0.5">
                Terdapat <strong class="text-amber-700 font-black">{{ $pendingLemburCount }}</strong> pengajuan lembur/on-call karyawan yang menunggu persetujuan Anda.
            </p>
        </div>
    </div>
    <a href="{{ route('laporan-payroll.lembur') }}" class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm transition-all hover:shadow hover:-translate-y-0.5 shrink-0">
        <span>Tinjau Sekarang</span>
        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
    </a>
</div>
@endif

{{-- ===== 3. TAMPILAN MANAJEMEN / PIMPINAN (Superadmin, HRD, Direktur, Manajer, Ka. Unit, PJ) ===== --}}
@if($canSeeManagement)
<div id="kk-section" class="space-y-8">
    {{-- Header Section Pantauan --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                <i data-lucide="layout-grid" class="w-5 h-5 text-accent"></i>
                Pantauan Aktual Kehadiran Hari Ini
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Ringkasan status kedatangan staf secara realtime pada unit kerja terkait.</p>
        </div>
        <div class="flex items-center gap-2.5 self-start sm:self-auto">
            <span id="kk-scope" class="text-xs font-bold text-slate-600 bg-white border border-slate-200/80 px-3.5 py-1.5 rounded-full shadow-sm">
                {{ $scopeLabel }}
            </span>
            <button type="button" id="btn-refresh-stats" title="Segarkan Data Realtime" class="p-2 rounded-xl bg-white border border-slate-200/80 text-slate-500 hover:text-accent hover:border-accent hover:bg-slate-50 transition-all shadow-sm">
                <i data-lucide="rotate-cw" class="w-4 h-4"></i>
            </button>
        </div>
    </div>

    {{-- 6 METRIC CARDS --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 sm:gap-5">
        {{-- Total Karyawan --}}
        <button type="button" data-kk="total" class="kk-card group text-left bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md hover:border-slate-300 hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 w-full">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Staf</span>
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-slate-200 transition-colors">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-black text-slate-900" id="kk-total">{{ $summary['total'] ?? 0 }}</p>
                <p class="text-[11px] text-slate-400 font-medium mt-1 flex items-center gap-1">
                    <span>Terdaftar di unit</span>
                    <i data-lucide="chevron-right" class="w-3 h-3 text-slate-400 group-hover:translate-x-0.5 transition-transform"></i>
                </p>
            </div>
        </button>

        {{-- Jadwal Dinas Hari Ini --}}
        <button type="button" data-kk="dinas" class="kk-card group text-left bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md hover:border-sky-300 hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 w-full">
                <span class="text-[11px] font-bold text-sky-600 uppercase tracking-wider">Jadwal Dinas</span>
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0 group-hover:bg-sky-100 transition-colors">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-black text-sky-700" id="kk-dinas">{{ $summary['dinas'] ?? 0 }}</p>
                <p class="text-[11px] text-sky-600 font-medium mt-1 flex items-center gap-1">
                    <span>Ada jadwal shift</span>
                    <i data-lucide="chevron-right" class="w-3 h-3 text-sky-400 group-hover:translate-x-0.5 transition-transform"></i>
                </p>
            </div>
        </button>

        {{-- Sudah Hadir --}}
        <button type="button" data-kk="hadir" class="kk-card group text-left bg-white rounded-2xl p-5 border border-emerald-100/90 shadow-sm hover:shadow-md hover:border-emerald-300 hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between relative overflow-hidden">
            <div class="flex items-center justify-between mb-3 w-full">
                <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider">Sudah Hadir</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 group-hover:bg-emerald-100 transition-colors">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <p class="text-3xl font-black text-emerald-600" id="kk-hadir">{{ $summary['hadir'] ?? 0 }}</p>
                    <span class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-1.5 py-0.5 rounded" id="kk-persen-label">
                        {{ $summary['persen'] ?? 0 }}%
                    </span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div id="kk-progress-bar" class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" style="width: {{ min(100, $summary['persen'] ?? 0) }}%"></div>
                </div>
            </div>
        </button>

        {{-- Terlambat --}}
        <button type="button" data-kk="terlambat" class="kk-card group text-left bg-white rounded-2xl p-5 border border-amber-100/90 shadow-sm hover:shadow-md hover:border-amber-300 hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 w-full">
                <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider">Terlambat</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 group-hover:bg-amber-100 transition-colors">
                    <i data-lucide="clock-alert" class="w-5 h-5"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-black text-amber-600" id="kk-terlambat">{{ $summary['terlambat'] ?? 0 }}</p>
                <p class="text-[11px] text-amber-700/80 font-medium mt-1 flex items-center gap-1">
                    <span>Masuk lewat jam</span>
                    <i data-lucide="chevron-right" class="w-3 h-3 text-amber-400 group-hover:translate-x-0.5 transition-transform"></i>
                </p>
            </div>
        </button>

        {{-- Belum Absen --}}
        <button type="button" data-kk="belum" class="kk-card group text-left bg-white rounded-2xl p-5 border border-rose-100/90 shadow-sm hover:shadow-md hover:border-rose-300 hover:-translate-y-1 transition-all duration-200 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 w-full">
                <span class="text-[11px] font-bold text-rose-500 uppercase tracking-wider">Belum Absen</span>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center shrink-0 group-hover:bg-rose-100 transition-colors">
                    <i data-lucide="user-x" class="w-5 h-5"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-black text-rose-500" id="kk-belum">{{ $summary['belum'] ?? 0 }}</p>
                <p class="text-[11px] text-rose-600/80 font-medium mt-1 flex items-center gap-1">
                    <span>Dinas belum clock-in</span>
                    <i data-lucide="chevron-right" class="w-3 h-3 text-rose-400 group-hover:translate-x-0.5 transition-transform"></i>
                </p>
            </div>
        </button>

        {{-- Tidak Dinas / Libur (Informasi) --}}
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 w-full">
                <span class="text-[11px] font-bold text-indigo-500 uppercase tracking-wider">Tidak Dinas</span>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center shrink-0">
                    <i data-lucide="moon" class="w-5 h-5"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-black text-indigo-600" id="kk-libur">{{ $summary['tidak_dinas'] ?? 0 }}</p>
                <p class="text-[11px] text-indigo-600/70 font-medium mt-1">Status Libur / Off</p>
            </div>
        </div>
    </div>

    {{-- KETERANGAN KLIK DETAIL --}}
    <p class="text-xs text-slate-400 flex items-center gap-1.5 -mt-3">
        <i data-lucide="info" class="w-3.5 h-3.5 text-slate-400"></i>
        <span>Klik kartu <strong class="text-slate-600">Total / Jadwal Dinas / Sudah Hadir / Terlambat / Belum Absen</strong> untuk melihat rincian nama karyawan & unit kerja.</span>
    </p>

    {{-- 2-COLUMN OPERATIONAL SECTION --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {{-- KOLOM KIRI (7/12): AKTIVITAS PRESENSI TERKINI HARI INI --}}
        <div class="lg:col-span-7 xl:col-span-8 bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <h4 class="font-extrabold text-slate-900 text-base">Aktivitas Presensi Terkini Hari Ini</h4>
                    </div>
                    <a href="{{ route('monitoring.index') }}" class="text-xs font-bold text-accent hover:text-blue-700 flex items-center gap-1 transition-colors">
                        <span>Layar Monitoring Lengkap</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                @if($recentAbsensi->isEmpty())
                <div class="text-center py-12 text-slate-400">
                    <div class="w-14 h-14 bg-slate-50 text-slate-300 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="clock" class="w-7 h-7"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-600">Belum ada absensi yang tercatat hari ini</p>
                    <p class="text-xs text-slate-400 mt-1">Data kedatangan karyawan akan otomatis tampil begitu staf melakukan clock-in.</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 uppercase font-semibold border-b border-slate-100 pb-2">
                                <th class="py-2 pr-3">Pegawai</th>
                                <th class="py-2 px-3">Unit & Shift</th>
                                <th class="py-2 px-3 text-center">Waktu Masuk</th>
                                <th class="py-2 pl-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($recentAbsensi as $item)
                            @php
                                $u = $item->user;
                                $shift = $item->roster?->shift?->nama_shift ?? '-';
                                $isTelat = ($item->menit_terlambat ?? 0) > 0;
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 pr-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-xs shrink-0 border border-slate-200">
                                            {{ substr($u->name ?? '?', 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-900 text-xs truncate max-w-[150px] sm:max-w-[200px]">{{ $u->name ?? '-' }}</p>
                                            <p class="text-[10px] text-slate-400">NIK: {{ $u->nik ?? '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <p class="font-medium text-slate-700 truncate max-w-[140px]">{{ $u->unitKerja?->nama_unit ?? '-' }}</p>
                                    <span class="inline-block bg-slate-100 text-slate-600 text-[10px] font-semibold px-2 py-0.5 rounded mt-0.5">
                                        {{ $shift }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center font-mono font-bold text-slate-800">
                                    {{ optional($item->waktu_masuk)->format('H:i') ?? '-' }} WIB
                                </td>
                                <td class="py-3 pl-3 text-right">
                                    @if($isTelat)
                                    <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200/80 px-2 py-0.5 rounded-full text-[10px] font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        Telat {{ $item->menit_terlambat }}m
                                    </span>
                                    @else
                                    <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200/80 px-2 py-0.5 rounded-full text-[10px] font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Tepat Waktu
                                    </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

            <div class="pt-4 border-t border-slate-100 mt-4 flex items-center justify-between text-xs text-slate-400">
                <span>Diperbarui secara otomatis</span>
                <a href="{{ route('monitoring.index') }}" class="font-semibold text-slate-600 hover:text-slate-900">
                    Buka Pantauan Lengkap &rarr;
                </a>
            </div>
        </div>

        {{-- KOLOM KANAN (5/12): PERSENTASE KEHADIRAN & PINTASAN AKSI --}}
        <div class="lg:col-span-5 xl:col-span-4 space-y-6">
            {{-- WIDGET TINGKAT KEHADIRAN HARI INI --}}
            <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-sm">
                <h4 class="font-extrabold text-slate-900 text-sm mb-4 flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-4 h-4 text-emerald-600"></i>
                    Tingkat Kehadiran Dinas
                </h4>

                @php
                    $dinasVal = $summary['dinas'] ?? 0;
                    $hadirVal = $summary['hadir'] ?? 0;
                    $persenVal = $summary['persen'] ?? 0;
                    $belumVal = $summary['belum'] ?? 0;
                    $telatVal = $summary['terlambat'] ?? 0;
                @endphp

                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-500">Target Dinas Hari Ini</span>
                    <span class="text-xs font-black text-slate-800">{{ $hadirVal }} / {{ $dinasVal }} Orang</span>
                </div>

                {{-- Stacked Progress Bar --}}
                <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden flex mb-4">
                    <div class="bg-emerald-500 h-full transition-all duration-500" style="width: {{ $dinasVal > 0 ? min(100, round(($hadirVal / $dinasVal) * 100)) : 0 }}%" title="Sudah Hadir"></div>
                    <div class="bg-rose-400 h-full transition-all duration-500" style="width: {{ $dinasVal > 0 ? min(100, round(($belumVal / $dinasVal) * 100)) : 0 }}%" title="Belum Absen"></div>
                </div>

                {{-- Breakdown Legend --}}
                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-center">
                    <div class="bg-emerald-50/60 rounded-xl p-2 border border-emerald-100">
                        <p class="text-[10px] uppercase font-bold text-emerald-700">Tepat Waktu</p>
                        <p class="text-base font-black text-emerald-700">{{ max(0, $hadirVal - $telatVal) }}</p>
                    </div>
                    <div class="bg-amber-50/60 rounded-xl p-2 border border-amber-100">
                        <p class="text-[10px] uppercase font-bold text-amber-700">Terlambat</p>
                        <p class="text-base font-black text-amber-700">{{ $telatVal }}</p>
                    </div>
                    <div class="bg-rose-50/60 rounded-xl p-2 border border-rose-100">
                        <p class="text-[10px] uppercase font-bold text-rose-600">Belum Masuk</p>
                        <p class="text-base font-black text-rose-600">{{ $belumVal }}</p>
                    </div>
                </div>
            </div>

            {{-- WIDGET PINTASAN AKSI CEPAT (QUICK ACCESS) --}}
            <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-sm">
                <h4 class="font-extrabold text-slate-900 text-sm mb-4 flex items-center gap-2">
                    <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                    Pintasan Cepat Operasional
                </h4>
                
                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('roster.index') }}" class="group p-3.5 rounded-2xl bg-slate-50 hover:bg-sky-50 border border-slate-100 hover:border-sky-200 transition-all flex flex-col justify-between">
                        <div class="w-8 h-8 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                            <i data-lucide="calendar-days" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-xs group-hover:text-sky-700 transition-colors">Roster Dinas</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Kelola jadwal staf</p>
                        </div>
                    </a>

                    <a href="{{ route('monitoring.index') }}" class="group p-3.5 rounded-2xl bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 transition-all flex flex-col justify-between">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                            <i data-lucide="monitor" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-xs group-hover:text-emerald-700 transition-colors">Live Monitoring</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Pantau absensi real-time</p>
                        </div>
                    </a>

                    <a href="{{ route('laporan-payroll.index') }}" class="group p-3.5 rounded-2xl bg-slate-50 hover:bg-indigo-50 border border-slate-100 hover:border-indigo-200 transition-all flex flex-col justify-between">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-xs group-hover:text-indigo-700 transition-colors">Rekap Absensi</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Cetak Excel & PDF</p>
                        </div>
                    </a>

                    <a href="{{ route('laporan-payroll.lembur') }}" class="group p-3.5 rounded-2xl bg-slate-50 hover:bg-amber-50 border border-slate-100 hover:border-amber-200 transition-all flex flex-col justify-between">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                            <i data-lucide="clock-3" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-xs group-hover:text-amber-700 transition-colors">Rekap Lembur</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Validasi jam lembur</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ===== 4. TAMPILAN JADWAL DINAS PRIBADI (Penting bagi Staf / Informasi Mandiri) ===== --}}
<div class="mt-8 @if($canSeeManagement) pt-8 border-t border-slate-200/80 @endif">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                <i data-lucide="user-check" class="w-5 h-5 text-accent"></i>
                Jadwal & Riwayat Presensi Saya
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Informasi penugasan shift dan akumulasi kehadiran pribadi Anda pada bulan berjalan.</p>
        </div>
        <span class="text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3 py-1 rounded-full">
            Bulan: {{ \Carbon\Carbon::parse($hariIni)->translatedFormat('F Y') }}
        </span>
    </div>

    {{-- Grid Info Pribadi: Shift Hari Ini & Statistik Saya --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        {{-- Kartu Shift Hari Ini --}}
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Shift Hari Ini</span>
                <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                </span>
            </div>
            @if($myJadwalHariIni)
                <div>
                    <h4 class="text-lg font-black text-slate-900">{{ $myJadwalHariIni->nama_shift ?? 'Shift Terjadwal' }}</h4>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">
                        Jam: {{ $myJadwalHariIni->jam_masuk ?? '-' }} - {{ $myJadwalHariIni->jam_pulang ?? '-' }} WIB
                    </p>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-400">Status Presensi:</span>
                    @if($myJadwalHariIni->logAbsensi && $myJadwalHariIni->logAbsensi->waktu_masuk)
                        <span class="font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                            Masuk {{ optional($myJadwalHariIni->logAbsensi->waktu_masuk)->format('H:i') }}
                        </span>
                    @else
                        <span class="font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200">
                            Belum Clock-In
                        </span>
                    @endif
                </div>
            @else
                <div>
                    <h4 class="text-base font-extrabold text-slate-700">Tidak Ada Jadwal / Off</h4>
                    <p class="text-xs text-slate-400 mt-0.5">Hari ini Anda tidak tercatat memiliki jadwal dinas aktif.</p>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400 font-medium">
                    Status: <span class="font-bold text-indigo-600">Libur / Lepas Dinas</span>
                </div>
            @endif
        </div>

        {{-- Kartu Shift Besok --}}
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Jadwal Besok</span>
                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="clock-forward" class="w-4 h-4"></i>
                </span>
            </div>
            @if($myJadwalBesok)
                <div>
                    <h4 class="text-lg font-black text-slate-900">{{ $myJadwalBesok->nama_shift ?? 'Shift Terjadwal' }}</h4>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">
                        Jam: {{ $myJadwalBesok->jam_masuk ?? '-' }} - {{ $myJadwalBesok->jam_pulang ?? '-' }} WIB
                    </p>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-500">
                    Bersiap untuk jadwal dinas besok hari.
                </div>
            @else
                <div>
                    <h4 class="text-base font-extrabold text-slate-700">Libur / Belum Dibuat</h4>
                    <p class="text-xs text-slate-400 mt-0.5">Tidak ada jadwal tercatat untuk besok.</p>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400 font-medium">
                    Status: <span class="font-bold text-slate-600">Off / Bebas Tugas</span>
                </div>
            @endif
        </div>

        {{-- Kartu Ringkasan Akumulasi Bulan Ini --}}
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Akumulasi Saya Bulan Ini</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="award" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-slate-50 rounded-xl p-2 border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-500 uppercase">Hadir</p>
                    <p class="text-base font-black text-emerald-600">{{ $myRekap['hadir'] }} <span class="text-[10px] font-normal text-slate-400">hr</span></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-2 border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-500 uppercase">Telat</p>
                    <p class="text-base font-black text-amber-600">{{ $myRekap['telat'] }} <span class="text-[10px] font-normal text-slate-400">mnt</span></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-2 border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-500 uppercase">Lembur</p>
                    <p class="text-base font-black text-sky-600">{{ round($myRekap['lembur'], 1) }} <span class="text-[10px] font-normal text-slate-400">jam</span></p>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-slate-100 text-[11px] text-slate-400 text-center">
                Data presensi pribadi terverifikasi
            </div>
        </div>
    </div>

    {{-- Riwayat Presensi Terakhir Saya (Tabel 5 Entri) --}}
    @if($myRecentAbsensi->isNotEmpty())
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm">
        <h4 class="font-extrabold text-slate-900 text-sm mb-3 flex items-center gap-2">
            <i data-lucide="history" class="w-4 h-4 text-slate-400"></i>
            Riwayat 5 Presensi Terakhir Saya
        </h4>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-slate-400 uppercase font-semibold border-b border-slate-100 pb-2">
                        <th class="py-2 pr-3">Tanggal</th>
                        <th class="py-2 px-3">Shift</th>
                        <th class="py-2 px-3 text-center">Masuk</th>
                        <th class="py-2 px-3 text-center">Pulang</th>
                        <th class="py-2 pl-3 text-right">Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($myRecentAbsensi as $abs)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="py-2.5 pr-3 font-semibold text-slate-800">
                            {{ optional($abs->tanggal_dinas ?? $abs->waktu_masuk)->translatedFormat('d M Y') }}
                        </td>
                        <td class="py-2.5 px-3 text-slate-600">
                            {{ $abs->roster?->shift?->nama_shift ?? '-' }}
                        </td>
                        <td class="py-2.5 px-3 text-center font-mono font-medium text-slate-700">
                            {{ optional($abs->waktu_masuk)->format('H:i') ?? '-' }}
                        </td>
                        <td class="py-2.5 px-3 text-center font-mono font-medium text-slate-700">
                            {{ optional($abs->waktu_pulang)->format('H:i') ?? '-' }}
                        </td>
                        <td class="py-2.5 pl-3 text-right">
                            @if(($abs->menit_terlambat ?? 0) > 0)
                            <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200/80 px-2 py-0.5 rounded-full text-[10px] font-bold">
                                Terlambat {{ $abs->menit_terlambat }}m
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200/80 px-2 py-0.5 rounded-full text-[10px] font-bold">
                                Tepat Waktu
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- ===== 5. MODAL INTERAKTIF DAFTAR KARYAWAN (Dengan Live Search) ===== --}}
<div id="kk-modal" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col border border-slate-100 overflow-hidden">
        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/60">
            <div>
                <h4 id="kk-modal-title" class="font-black text-slate-900 text-base">Daftar Karyawan</h4>
                <p id="kk-modal-subtitle" class="text-xs text-slate-400 mt-0.5">Memuat data...</p>
            </div>
            <button type="button" id="kk-modal-close" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center hover:bg-slate-100 transition-all text-xl leading-none">
                &times;
            </button>
        </div>

        {{-- Live Search Input --}}
        <div class="px-6 py-3 border-b border-slate-100 bg-white">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text" id="modal-search" placeholder="Cari nama karyawan, NIK, atau unit kerja..." class="w-full pl-10 pr-4 py-2 text-xs rounded-xl bg-slate-50 border border-slate-200/80 focus:bg-white focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent transition-all">
            </div>
        </div>

        {{-- Modal Body (Scrollable Table) --}}
        <div id="kk-modal-body" class="p-6 overflow-y-auto text-xs flex-1">
            <p class="text-slate-400 text-center py-8">Memuat data karyawan...</p>
        </div>

        {{-- Modal Footer --}}
        <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
            <span id="modal-counter">Menampilkan 0 karyawan</span>
            <button type="button" onclick="document.getElementById('kk-modal').style.display='none'" class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl transition-colors">
                Tutup
            </button>
        </div>
    </div>
</div>

{{-- ===== JAVASCRIPT LOGIC ===== --}}
<script>
    (function () {
        // --- 1. LIVE DIGITAL CLOCK ---
        function updateClock() {
            const clockEl = document.getElementById('live-clock');
            if (!clockEl) return;
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = `${h}:${m}:${s}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // --- 2. AJAX REFRESH & INTERAKTIF METRIK ---
        const URL_RING = "{{ route('dashboard.ringkasanKaryawan') }}";
        const URL_LIST = "{{ route('dashboard.karyawanHariIni') }}";
        const section = document.getElementById('kk-section');

        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

        function refreshStats() {
            const refreshBtn = document.getElementById('btn-refresh-stats');
            if (refreshBtn) refreshBtn.classList.add('animate-spin');

            fetch(URL_RING, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    if (refreshBtn) refreshBtn.classList.remove('animate-spin');
                    if (!d || !d.can_see) return;
                    if (section) section.style.display = '';
                    set('kk-scope', d.scope_label || 'Seluruh Unit');
                    set('kk-total', d.total);
                    set('kk-dinas', d.dinas ?? 0);
                    set('kk-hadir', d.hadir);
                    set('kk-terlambat', d.terlambat ?? 0);
                    set('kk-belum', d.belum);
                    set('kk-libur', d.tidak_dinas);

                    if (d.persen !== undefined) {
                        set('kk-persen-label', d.persen + '%');
                        const pBar = document.getElementById('kk-progress-bar');
                        if (pBar) pBar.style.width = Math.min(100, d.persen) + '%';
                    }
                })
                .catch(() => {
                    if (refreshBtn) refreshBtn.classList.remove('animate-spin');
                });
        }

        const btnRefresh = document.getElementById('btn-refresh-stats');
        if (btnRefresh) {
            btnRefresh.addEventListener('click', refreshStats);
        }

        // --- 3. MODAL DAFTAR KARYAWAN DENGAN LIVE SEARCH ---
        const modal = document.getElementById('kk-modal');
        const mBody = document.getElementById('kk-modal-body');
        const mTitle = document.getElementById('kk-modal-title');
        const mSub = document.getElementById('kk-modal-subtitle');
        const mCounter = document.getElementById('modal-counter');
        const searchInput = document.getElementById('modal-search');
        let currentRows = [];

        const openModal = () => { if (modal) modal.style.display = 'flex'; };
        const closeModal = () => { if (modal) modal.style.display = 'none'; };

        const closeBtn = document.getElementById('kk-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        const TITLES = {
            total: { title: 'Total Seluruh Karyawan', sub: 'Daftar seluruh pegawai dalam cakupan unit kerja Anda' },
            dinas: { title: 'Karyawan Berdinas Hari Ini', sub: 'Daftar pegawai yang memiliki jadwal dinas aktif hari ini' },
            hadir: { title: 'Karyawan Sudah Hadir', sub: 'Daftar staf yang telah berhasil melakukan absensi masuk' },
            terlambat: { title: 'Karyawan Terlambat Masuk', sub: 'Daftar staf yang masuk melewati jadwal shift' },
            belum: { title: 'Karyawan Belum Absen', sub: 'Daftar staf terjadwal dinas yang belum melakukan clock-in' }
        };

        function renderRows(rows) {
            if (!mBody) return;
            if (!rows || !rows.length) {
                mBody.innerHTML = '<div class="text-center py-8 text-slate-400"><p class="font-semibold">Tidak ada karyawan yang sesuai dengan kriteria.</p></div>';
                if (mCounter) mCounter.textContent = 'Menampilkan 0 karyawan';
                return;
            }

            if (mCounter) mCounter.textContent = `Menampilkan ${rows.length} karyawan`;

            let h = '<div class="overflow-x-auto"><table class="w-full text-left"><thead>'
                + '<tr class="text-slate-400 uppercase font-semibold text-[10px] border-b border-slate-100 pb-2">'
                + '<th class="py-2 pr-3">Karyawan</th>'
                + '<th class="py-2 px-3">Unit Kerja</th>'
                + '<th class="py-2 px-3">Shift</th>'
                + '<th class="py-2 px-3 text-center">Masuk</th>'
                + '<th class="py-2 pl-3 text-right">Status</th>'
                + '</tr></thead><tbody class="divide-y divide-slate-50">';

            rows.forEach(r => {
                let badgeStatus = '';
                if (r.status === 'Tepat Waktu') {
                    badgeStatus = '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full font-bold text-[10px]">Tepat Waktu</span>';
                } else if (r.status === 'Terlambat' || r.menit_telat > 0) {
                    badgeStatus = `<span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full font-bold text-[10px]">Telat ${r.menit_telat || 0}m</span>`;
                } else if (r.status === 'Belum Absen') {
                    badgeStatus = '<span class="bg-rose-50 text-rose-600 border border-rose-200 px-2 py-0.5 rounded-full font-bold text-[10px]">Belum Absen</span>';
                } else {
                    badgeStatus = `<span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-bold text-[10px]">${r.status || '-'}</span>`;
                }

                h += '<tr class="hover:bg-slate-50/70 transition-colors">'
                    + '<td class="py-2.5 pr-3">'
                    + '<p class="font-bold text-slate-800">' + (r.nama || '-') + '</p>'
                    + '<p class="text-[10px] text-slate-400">NIK: ' + (r.nik || '-') + '</p>'
                    + '</td>'
                    + '<td class="py-2.5 px-3 text-slate-600">' + (r.unit || '-') + '</td>'
                    + '<td class="py-2.5 px-3 text-slate-600"><span class="bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded text-[10px] font-semibold">' + (r.shift || '-') + '</span></td>'
                    + '<td class="py-2.5 px-3 text-center font-mono font-bold text-slate-700">' + (r.jam_masuk || '-') + '</td>'
                    + '<td class="py-2.5 pl-3 text-right">' + badgeStatus + '</td>'
                    + '</tr>';
            });

            h += '</tbody></table></div>';
            mBody.innerHTML = h;
            if (window.lucide) lucide.createIcons();
        }

        // Live Search Input Handler
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const kw = this.value.toLowerCase().trim();
                if (!kw) {
                    renderRows(currentRows);
                    return;
                }
                const filtered = currentRows.filter(r => {
                    const nama = (r.nama || '').toLowerCase();
                    const nik = (r.nik || '').toLowerCase();
                    const unit = (r.unit || '').toLowerCase();
                    const shift = (r.shift || '').toLowerCase();
                    return nama.includes(kw) || nik.includes(kw) || unit.includes(kw) || shift.includes(kw);
                });
                renderRows(filtered);
            });
        }

        // Card Click Listener
        document.querySelectorAll('.kk-card').forEach(btn => {
            btn.addEventListener('click', () => {
                const f = btn.getAttribute('data-kk');
                const info = TITLES[f] || { title: 'Daftar Karyawan', sub: 'Informasi status kehadiran' };

                if (mTitle) mTitle.textContent = info.title;
                if (mSub) mSub.textContent = info.sub;
                if (searchInput) searchInput.value = '';
                if (mBody) mBody.innerHTML = '<div class="text-center py-10 text-slate-400"><div class="w-8 h-8 rounded-full border-2 border-accent border-t-transparent animate-spin mx-auto mb-2"></div><p class="font-medium text-xs">Mengambil data dari server...</p></div>';
                
                openModal();

                fetch(URL_LIST + '?filter=' + encodeURIComponent(f), { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(d => {
                        currentRows = d.rows || [];
                        renderRows(currentRows);
                    })
                    .catch(() => {
                        if (mBody) mBody.innerHTML = '<p class="text-rose-500 text-center py-8 font-semibold">Gagal memuat data karyawan. Silakan coba lagi.</p>';
                    });
            });
        });
    })();
</script>
@endsection