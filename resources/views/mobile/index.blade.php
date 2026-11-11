@extends('mobile.layout')

@section('title', 'ROSES Mobile - ' . ($pengaturan?->nama_instansi ?: 'RSI Ropanasuri'))

@section('content')
@php
    $logo = $pengaturan?->logo ? '/storage/' . $pengaturan->logo : null;
    $namaInstansi = $pengaturan?->nama_instansi ?: 'RSI Ropanasuri';
    $userFoto = $user->foto_profil ? '/storage/' . $user->foto_profil : null;
    $canValidasi = $user->canValidasi();
    $isGantiJam = $user->isGantiJam();
@endphp

<div x-data="siroMobileApp()" x-init="initApp()" class="flex-1 flex flex-col min-h-dvh relative pb-bottom-nav select-none">

    <!-- Top App Bar (Header Tetap, Safe Area Top Aware untuk Notch / Dynamic Island iPhone) -->
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-100 px-4 pt-safe pb-3 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 p-1.5 flex items-center justify-center shrink-0">
                @if($logo)
                    <img src="{{ $logo }}" alt="Logo RS" class="w-full h-full object-contain">
                @else
                    <i data-lucide="activity" class="w-5 h-5 text-primary-600"></i>
                @endif
            </div>
            <div>
                <h1 class="text-sm font-black text-slate-900 leading-tight">{{ $namaInstansi }}</h1>
                <p class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    ROSES Mobile Web
                </p>
            </div>
        </div>

        <button @click="switchTab('profil')" class="relative shrink-0">
            <template x-if="profilePhotoUrl">
                <img :src="profilePhotoUrl" alt="{{ $user->name }}" class="w-9 h-9 rounded-full object-cover border-2 border-emerald-500/50 shadow-xs">
            </template>
            <template x-if="!profilePhotoUrl">
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-primary-600 to-emerald-400 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
            </template>
        </button>
    </header>

    <!-- =================================================================== -->
    <!-- TAB 1: BERANDA (HOME) -->
    <!-- =================================================================== -->
    <div x-show="currentTab === 'beranda'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="p-4 space-y-4">
        
        <!-- Kartu Sapaan & Jam Digital Real-time -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-primary-600 to-teal-700 text-white p-5 shadow-lg shadow-emerald-500/20">
            <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white/10 blur-xl"></div>
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-100/80" x-text="greetingText">Selamat Datang</span>
                    <h2 class="text-lg font-black tracking-tight truncate max-w-[200px]">{{ $user->name }}</h2>
                    <p class="text-xs text-emerald-100/90 mt-0.5 flex items-center gap-1.5">
                        <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                        <span>{{ $user->unitKerja?->nama_unit ?? 'Unit Kerja' }}</span>
                    </p>
                </div>
                <div class="text-right">
                    <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-md text-[10px] font-semibold text-emerald-100">
                        <i data-lucide="clock" class="w-3 h-3"></i>
                        <span>WIB</span>
                    </div>
                </div>
            </div>

            <!-- Ticker Jam Digital -->
            <div class="mt-4 pt-3 border-t border-white/15 flex items-baseline justify-between">
                <div>
                    <div class="text-3xl font-black tracking-tight font-mono" x-text="liveTime">00:00:00</div>
                    <div class="text-[11px] font-medium text-emerald-100/90" x-text="liveDate">Memuat tanggal...</div>
                </div>
                <div class="text-right">
                    <span class="text-[11px] text-emerald-200 block">Jadwal Shift:</span>
                    <span class="text-xs font-bold text-white uppercase" x-text="todayData.shift_nama || 'Memuat...'">-</span>
                </div>
            </div>
        </div>

        <!-- Banner Peringatan On-Call Aktif (Bila Sedang On-Call) -->
        <template x-if="todayData.on_call_aktif">
            <div class="p-4 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-md flex items-center justify-between animate-pulse">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-xs font-extrabold uppercase tracking-wide">ON-CALL SEDANG BERJALAN</div>
                        <div class="text-[11px] text-amber-100" x-text="'Mulai: ' + todayData.on_call_aktif.waktu_mulai"></div>
                    </div>
                </div>
                <button @click="openOnCallClockOut()" class="px-3.5 py-2 rounded-xl bg-white text-orange-600 font-bold text-xs shadow-sm hover:bg-orange-50 active:scale-95 transition-all">
                    Akhiri On-Call
                </button>
            </div>
        </template>

        <!-- Kartu Status Presensi Hari Ini -->
        <div class="bg-white rounded-3xl p-4 border border-slate-100 shadow-sm space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <i data-lucide="calendar-check-2" class="w-4 h-4 text-primary-600"></i>
                    <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Status Presensi Hari Ini</span>
                </div>
                <template x-if="todayData.status_kehadiran">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                          :class="{
                              'bg-emerald-100 text-emerald-700': todayData.status_kehadiran === 'Tepat Waktu',
                              'bg-rose-100 text-rose-700': todayData.status_kehadiran === 'Terlambat',
                              'bg-slate-100 text-slate-600': todayData.status_kehadiran === 'Libur' || todayData.status_kehadiran === 'Tanpa Jadwal'
                          }"
                          x-text="todayData.status_kehadiran">
                    </span>
                </template>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <!-- Box Masuk -->
                <div class="p-3.5 rounded-2xl border"
                     :class="todayData.is_masuk_done ? 'bg-emerald-50/50 border-emerald-200' : 'bg-slate-50 border-slate-200/80'">
                    <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                        <span class="font-semibold">Absen Masuk</span>
                        <i data-lucide="log-in" class="w-3.5 h-3.5" :class="todayData.is_masuk_done ? 'text-emerald-600' : 'text-slate-400'"></i>
                    </div>
                    <div class="text-lg font-black" :class="todayData.is_masuk_done ? 'text-emerald-700' : 'text-slate-400'"
                         x-text="todayData.waktu_masuk ? todayData.waktu_masuk + ' WIB' : '--:--'">--:--</div>
                    <div class="text-[10px] text-slate-500 mt-1" x-text="'Jadwal: ' + (todayData.jam_masuk_jadwal || '-')"></div>
                </div>

                <!-- Box Pulang -->
                <div class="p-3.5 rounded-2xl border"
                     :class="todayData.is_pulang_done ? 'bg-emerald-50/50 border-emerald-200' : 'bg-slate-50 border-slate-200/80'">
                    <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                        <span class="font-semibold">Absen Pulang</span>
                        <i data-lucide="log-out" class="w-3.5 h-3.5" :class="todayData.is_pulang_done ? 'text-emerald-600' : 'text-slate-400'"></i>
                    </div>
                    <div class="text-lg font-black" :class="todayData.is_pulang_done ? 'text-emerald-700' : 'text-slate-400'"
                         x-text="todayData.waktu_pulang ? todayData.waktu_pulang + ' WIB' : '--:--'">--:--</div>
                    <div class="text-[10px] text-slate-500 mt-1" x-text="'Jadwal: ' + (todayData.jam_pulang_jadwal || '-')"></div>
                </div>
            </div>
        </div>

        <!-- Kartu Bank Jam Lembur (Bila Unit Kerja Berjenis Ganti Jam) -->
        <template x-if="todayData.bank_jam">
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/80 rounded-3xl p-4 shadow-xs flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center shadow-sm shrink-0">
                        <i data-lucide="hourglass" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">Saldo Bank Jam Lembur</span>
                        <div class="text-base font-black text-slate-900 leading-tight" x-text="todayData.bank_jam.saldo_jam_teks">0 Jam 0 Menit</div>
                        <span class="text-[10px] text-slate-500">Kompensasi waktu istirahat / cuti</span>
                    </div>
                </div>
                <button @click="switchTab('lembur'); subTabLembur = 1" class="px-3 py-1.5 rounded-xl bg-white text-amber-700 font-bold text-xs border border-amber-200 shadow-xs hover:bg-amber-50 active:scale-95 transition-all shrink-0">
                    Klaim Jam
                </button>
            </div>
        </template>

        <!-- Tombol Aksi Utama Presensi (Grid 2 Kolom) -->
        <div class="grid grid-cols-2 gap-3 pt-1">
            <!-- Tombol Absen Masuk -->
            <button @click="openAbsensi('masuk')"
                    :disabled="todayData.is_masuk_done && !todayData.is_pulang_done"
                    class="py-4 px-4 rounded-3xl bg-gradient-to-br from-emerald-500 to-primary-600 hover:from-emerald-600 hover:to-primary-700 active:scale-[0.98] text-white shadow-lg shadow-emerald-500/25 flex flex-col items-center justify-center gap-2 transition-all disabled:opacity-50 disabled:pointer-events-none">
                <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center">
                    <i data-lucide="camera" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-black uppercase tracking-wider">Absen Masuk</span>
                <span class="text-[10px] text-emerald-100 font-medium">Selfie & Lokasi RS</span>
            </button>

            <!-- Tombol Absen Pulang -->
            <button @click="openAbsensi('pulang')"
                    :disabled="!todayData.is_masuk_done || todayData.is_pulang_done"
                    class="py-4 px-4 rounded-3xl bg-gradient-to-br from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 active:scale-[0.98] text-white shadow-lg shadow-rose-500/25 flex flex-col items-center justify-center gap-2 transition-all disabled:opacity-50 disabled:pointer-events-none">
                <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center">
                    <i data-lucide="log-out" class="w-6 h-6"></i>
                </div>
                <span class="text-xs font-black uppercase tracking-wider">Absen Pulang</span>
                <span class="text-[10px] text-rose-100 font-medium">Selfie & Lokasi RS</span>
            </button>
        </div>

        <!-- Tombol Tambahan: On-Call & Kompensasi -->
        <div class="grid grid-cols-2 gap-3">
            <button @click="openOnCallModal()" class="py-3 px-3.5 rounded-2xl bg-white border border-slate-200/80 hover:bg-slate-50 active:scale-95 text-slate-700 flex items-center gap-3 transition-all shadow-xs">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <i data-lucide="phone-call" class="w-4 h-4"></i>
                </div>
                <div class="text-left truncate">
                    <div class="text-xs font-bold truncate">On-Call Darurat</div>
                    <div class="text-[10px] text-slate-400">Panggilan tugas RS</div>
                </div>
            </button>

            <button @click="switchTab('jadwal')" class="py-3 px-3.5 rounded-2xl bg-white border border-slate-200/80 hover:bg-slate-50 active:scale-95 text-slate-700 flex items-center gap-3 transition-all shadow-xs">
                <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                </div>
                <div class="text-left truncate">
                    <div class="text-xs font-bold truncate">Jadwal Roster</div>
                    <div class="text-[10px] text-slate-400">Lihat kalender dinas</div>
                </div>
            </button>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- TAB 2: JADWAL DINAS (ROSTER MATRIX) -->
    <!-- =================================================================== -->
    <div x-show="currentTab === 'jadwal'" x-transition class="p-4 space-y-4">
        <div class="flex items-center justify-between bg-white p-3.5 rounded-2xl border border-slate-100 shadow-xs">
            <button @click="changeMonth(-1)" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-slate-200">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
            <div class="text-center">
                <h3 class="text-sm font-bold text-slate-800" x-text="monthNames[rosterMonth - 1] + ' ' + rosterYear"></h3>
                <span class="text-[10px] text-slate-400">Kalender Shift Pribadi</span>
            </div>
            <button @click="changeMonth(1)" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-slate-200">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Legend Indikator Shift -->
        <div class="flex flex-wrap items-center gap-2 text-[10px] font-semibold text-slate-500 bg-white p-3 rounded-2xl border border-slate-100">
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span> Tepat</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span> Terlambat</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500"></span> Lembur</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-purple-500"></span> Shift 2</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-slate-300"></span> Libur</span>
        </div>

        <!-- Grid Kalender Shift 31 Hari -->
        <div class="bg-white rounded-3xl p-3 border border-slate-100 shadow-xs">
            <template x-if="loadingRoster">
                <div class="py-12 text-center text-xs text-slate-400 flex flex-col items-center gap-2">
                    <svg class="animate-spin h-6 w-6 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Memuat Roster...</span>
                </div>
            </template>

            <template x-if="!loadingRoster && rosterDays.length > 0">
                <div class="grid grid-cols-7 gap-1 sm:gap-1.5 text-center">
                    <template x-for="(day, dayIdx) in ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']" :key="day">
                        <div class="text-[10px] font-bold pb-1.5" :class="dayIdx === 0 ? 'text-rose-500' : 'text-slate-400'" x-text="day"></div>
                    </template>

                    <!-- Sel kosong sebelum tanggal 1 agar tanggal jatuh tepat pada harinya -->
                    <template x-for="blankIdx in rosterLeadingBlanks" :key="'blank-' + blankIdx">
                        <div class="min-h-[48px] sm:min-h-[52px]"></div>
                    </template>

                    <template x-for="item in rosterDays" :key="item.tanggal">
                        <div @click="selectedRosterDay = item; showRosterDetailModal = true"
                             class="p-1 rounded-xl border flex flex-col items-center justify-between min-h-[48px] sm:min-h-[52px] cursor-pointer active:scale-90 transition-all"
                             :class="[
                                 item.is_libur
                                     ? 'bg-slate-50 border-slate-200/60 text-slate-400'
                                     : (item.absen_masuk && item.terlambat_menit === 0
                                         ? 'bg-emerald-50/70 border-emerald-300 text-emerald-800'
                                         : (item.absen_masuk && item.terlambat_menit > 0
                                             ? 'bg-rose-50/70 border-rose-300 text-rose-800'
                                             : 'bg-white border-slate-200 text-slate-700')),
                                 isTodayRoster(item.tanggal) ? 'ring-2 ring-primary-500 ring-offset-1' : ''
                             ]">
                            <span class="text-[11px] font-black" :class="isSundayRoster(item.tanggal) && !item.absen_masuk ? 'text-rose-500' : ''" x-text="item.tanggal"></span>
                            <span class="text-[9px] font-bold truncate max-w-[38px] sm:max-w-[44px]" x-text="item.is_libur ? 'OFF' : (item.nama_shift || '-')"></span>
                            <div class="flex items-center gap-0.5 mt-0.5">
                                <template x-if="item.absen_masuk">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="item.terlambat_menit > 0 ? 'bg-rose-500' : 'bg-emerald-500'"></span>
                                </template>
                                <template x-if="item.lembur_masuk">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                </template>
                                <template x-if="item.sesi2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- TAB 3: LEMBUR & BANK JAM -->
    <!-- =================================================================== -->
    <div x-show="currentTab === 'lembur'" x-transition class="p-4 space-y-4">
        <!-- Segmented Tab Header -->
        <div class="flex bg-slate-200/80 p-1 rounded-2xl">
            <button @click="subTabLembur = 0" 
                    :class="subTabLembur === 0 ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600'" 
                    class="flex-1 py-2 rounded-xl text-xs font-bold transition-all">
                Lembur & On-Call
            </button>
            <button @click="subTabLembur = 1" 
                    :class="subTabLembur === 1 ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600'" 
                    class="flex-1 py-2 rounded-xl text-xs font-bold transition-all">
                Bank Jam Lembur
            </button>
        </div>

        <!-- Sub-tab 0: Lembur & On-Call -->
        <div x-show="subTabLembur === 0" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <button @click="openEkstensiModal()" class="p-4 rounded-3xl bg-gradient-to-br from-indigo-500 to-primary-600 text-white shadow-md flex flex-col items-center text-center gap-2 active:scale-95 transition-all">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-bold">Ajukan Ekstensi</span>
                    <span class="text-[10px] text-indigo-100">Lanjutan dinas shift</span>
                </button>

                <button @click="openOnCallModal()" class="p-4 rounded-3xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white shadow-md flex flex-col items-center text-center gap-2 active:scale-95 transition-all">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center">
                        <i data-lucide="phone-incoming" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-bold">Mulai On-Call</span>
                    <span class="text-[10px] text-cyan-100">Panggilan darurat RS</span>
                </button>
            </div>

            <!-- Daftar Riwayat Lembur -->
            <div class="bg-white rounded-3xl p-4 border border-slate-100 shadow-xs space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Riwayat Pengajuan Lembur</h3>
                    <button @click="fetchLemburData()" class="text-primary-600 hover:text-primary-700 text-xs font-bold flex items-center gap-1">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    </button>
                </div>

                <template x-if="loadingLembur">
                    <div class="py-8 text-center text-xs text-slate-400">Memuat riwayat lembur...</div>
                </template>

                <template x-if="!loadingLembur && riwayatLembur.length === 0">
                    <div class="py-8 text-center text-xs text-slate-400">Belum ada riwayat pengajuan lembur.</div>
                </template>

                <template x-for="item in riwayatLembur" :key="item.id">
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/70 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900" x-text="item.jenis_lembur || 'Lembur'"></span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                  :class="{
                                      'bg-amber-100 text-amber-700': item.status_validasi === 'Pending',
                                      'bg-emerald-100 text-emerald-700': item.status_validasi === 'Disetujui',
                                      'bg-rose-100 text-rose-700': item.status_validasi === 'Ditolak'
                                  }"
                                  x-text="item.status_validasi">
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-600 leading-snug" x-text="item.alasan_lembur || 'Tanpa keterangan'"></div>
                        <div class="text-[10px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-200/50">
                            <span x-text="item.tanggal_lembur"></span>
                            <span x-text="(item.waktu_mulai || '') + ' - ' + (item.waktu_selesai || 'Aktif')"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Sub-tab 1: Bank Jam Lembur -->
        <div x-show="subTabLembur === 1" class="space-y-4">
            <!-- Kartu Saldo Bank Jam Detail -->
            <div class="rounded-3xl bg-gradient-to-br from-amber-500 to-orange-600 text-white p-5 shadow-lg shadow-amber-500/20 space-y-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-100">Saldo Bank Jam Anda</span>
                <div class="text-3xl font-black font-mono" x-text="todayData.bank_jam ? todayData.bank_jam.saldo_jam_teks : '0 Jam 0 Menit'">0 Jam 0 Menit</div>
                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-white/20 text-xs">
                    <div>
                        <span class="text-amber-100 text-[10px] block">Total Diperoleh</span>
                        <span class="font-bold font-mono" x-text="todayData.bank_jam ? todayData.bank_jam.total_lembur_disetujui_teks : '0 Jam'"></span>
                    </div>
                    <div>
                        <span class="text-amber-100 text-[10px] block">Telah Digunakan</span>
                        <span class="font-bold font-mono" x-text="todayData.bank_jam ? todayData.bank_jam.total_pengambilan_teks : '0 Jam'"></span>
                    </div>
                </div>
            </div>

            <!-- Tombol Ajukan Ambil Saldo Jam -->
            <button @click="openAmbilJamModal()" class="w-full py-3.5 px-4 rounded-2xl bg-white border border-amber-300 text-amber-800 font-bold text-xs flex items-center justify-center gap-2 shadow-xs active:scale-98">
                <i data-lucide="calendar-plus" class="w-4 h-4 text-amber-600"></i>
                <span>Ajukan Pengambilan Saldo Jam (Time-Off)</span>
            </button>

            <!-- Riwayat Pengambilan Jam -->
            <div class="bg-white rounded-3xl p-4 border border-slate-100 shadow-xs space-y-3">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide pb-2 border-b border-slate-100">Riwayat Pengambilan Saldo Jam</h3>
                <template x-if="riwayatAmbilJam.length === 0">
                    <div class="py-8 text-center text-xs text-slate-400">Belum ada riwayat pengambilan saldo jam.</div>
                </template>
                <template x-for="item in riwayatAmbilJam" :key="item.id">
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/70 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900" x-text="item.durasi_teks || (item.durasi_menit + ' menit')"></span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                  :class="{
                                      'bg-amber-100 text-amber-700': item.status_validasi === 'Pending',
                                      'bg-emerald-100 text-emerald-700': item.status_validasi === 'Disetujui',
                                      'bg-rose-100 text-rose-700': item.status_validasi === 'Ditolak'
                                  }"
                                  x-text="item.status_validasi">
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-600" x-text="item.alasan"></div>
                        <div class="text-[10px] text-slate-400 pt-1 border-t border-slate-200/50" x-text="'Rencana: ' + item.tanggal_rencana"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- TAB 4: VALIDASI (KHUSUS ATASAN / canValidasi) -->
    <!-- =================================================================== -->
    @if($canValidasi)
    <div x-show="currentTab === 'validasi'" x-transition class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wide">Persetujuan & Validasi</h2>
            <button @click="fetchValidasiList()" class="text-primary-600 text-xs font-bold flex items-center gap-1">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                <span>Refresh</span>
            </button>
        </div>

        <div class="flex bg-slate-200/80 p-1 rounded-2xl text-xs font-bold">
            <button @click="tabValidasi = 'lembur'" :class="tabValidasi === 'lembur' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600'" class="flex-1 py-2 rounded-xl transition-all">
                Lembur & On-Call
            </button>
            <button @click="tabValidasi = 'ambil'" :class="tabValidasi === 'ambil' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600'" class="flex-1 py-2 rounded-xl transition-all">
                Ambil Jam
            </button>
        </div>

        <!-- List Validasi Lembur & On-Call -->
        <div x-show="tabValidasi === 'lembur'" class="space-y-3">
            <template x-if="loadingValidasi">
                <div class="py-12 text-center text-xs text-slate-400">Memuat data permohonan...</div>
            </template>

            <template x-if="!loadingValidasi && validasiLemburList.length === 0">
                <div class="p-8 text-center bg-white rounded-3xl border border-slate-100 text-xs text-slate-400">
                    <i data-lucide="check-circle" class="w-8 h-8 text-emerald-500 mx-auto mb-2 opacity-60"></i>
                    Semua permohonan lembur telah diproses.
                </div>
            </template>

            <template x-for="item in validasiLemburList" :key="item.id">
                <div class="p-4 rounded-3xl bg-white border border-slate-100 shadow-xs space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-black text-slate-900" x-text="item.nama_pegawai"></div>
                            <div class="text-[10px] text-slate-500" x-text="(item.unit_kerja || '') + ' • ' + (item.jenis_lembur || '')"></div>
                        </div>
                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-700" x-text="item.status_validasi"></span>
                    </div>

                    <div class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-2xl border border-slate-100" x-text="item.alasan_lembur || 'Tanpa keterangan'"></div>

                    <div class="text-[11px] text-slate-500 flex items-center justify-between">
                        <span x-text="item.tanggal_lembur"></span>
                        <span class="font-bold text-slate-800" x-text="(item.waktu_mulai || '') + ' - ' + (item.waktu_selesai || '')"></span>
                    </div>

                    <!-- Tombol Aksi Verifikasi -->
                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
                        <button @click="tolakLembur(item.id)" class="py-2.5 px-3 rounded-2xl bg-rose-50 text-rose-700 font-bold text-xs hover:bg-rose-100 active:scale-95 transition-all">
                            Tolak
                        </button>
                        <button @click="setujuiLembur(item.id)" class="py-2.5 px-3 rounded-2xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 active:scale-95 transition-all shadow-xs">
                            Setujui
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- List Validasi Pengambilan Saldo Jam -->
        <div x-show="tabValidasi === 'ambil'" class="space-y-3">
            <template x-if="validasiAmbilList.length === 0">
                <div class="p-8 text-center bg-white rounded-3xl border border-slate-100 text-xs text-slate-400">
                    <i data-lucide="check-circle" class="w-8 h-8 text-emerald-500 mx-auto mb-2 opacity-60"></i>
                    Tidak ada permohonan ambil jam yang menunggu persetujuan.
                </div>
            </template>

            <template x-for="item in validasiAmbilList" :key="item.id">
                <div class="p-4 rounded-3xl bg-white border border-slate-100 shadow-xs space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-black text-slate-900" x-text="item.nama_pegawai"></div>
                            <div class="text-[10px] text-slate-500" x-text="'Durasi: ' + (item.durasi_teks || (item.durasi_menit + ' menit'))"></div>
                        </div>
                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-700" x-text="item.status_validasi"></span>
                    </div>

                    <div class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-2xl" x-text="item.alasan"></div>
                    <div class="text-[11px] text-slate-500" x-text="'Rencana tanggal: ' + item.tanggal_rencana"></div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
                        <button @click="tolakAmbilJam(item.id)" class="py-2.5 px-3 rounded-2xl bg-rose-50 text-rose-700 font-bold text-xs hover:bg-rose-100 active:scale-95 transition-all">
                            Tolak
                        </button>
                        <button @click="setujuiAmbilJam(item.id)" class="py-2.5 px-3 rounded-2xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 active:scale-95 transition-all shadow-xs">
                            Setujui
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
    @endif

    <!-- =================================================================== -->
    <!-- TAB 5: PROFIL PENGGUNA -->
    <!-- =================================================================== -->
    <div x-show="currentTab === 'profil'" x-transition class="p-4 space-y-4">
        <!-- Header Profil -->
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-xs text-center relative overflow-hidden">
            <div class="relative w-24 h-24 mx-auto mb-3">
                <template x-if="profilePhotoUrl">
                    <img :src="profilePhotoUrl" alt="{{ $user->name }}" class="w-24 h-24 rounded-full object-cover border-4 border-emerald-500/30 shadow-md">
                </template>
                <template x-if="!profilePhotoUrl">
                    <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-primary-600 to-emerald-400 text-white font-black text-2xl flex items-center justify-center shadow-md">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                </template>
                <button @click="openUploadFotoModal()" class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center shadow-md hover:bg-primary-700 active:scale-95">
                    <i data-lucide="camera" class="w-4 h-4"></i>
                </button>
            </div>

            <h2 class="text-base font-black text-slate-900 leading-tight">{{ $user->name }}</h2>
            <p class="text-xs text-slate-500 font-mono mt-0.5">NIK: {{ $user->nik }}</p>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold mt-2">
                <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                <span class="uppercase tracking-wider">{{ $user->role }}</span>
            </div>
        </div>

        <!-- Menu Opsi Pengguna -->
        <div class="bg-white rounded-3xl border border-slate-100 shadow-xs divide-y divide-slate-100 overflow-hidden text-xs">
            <button @click="showUbahPasswordModal = true" class="w-full p-4 flex items-center justify-between hover:bg-slate-50 transition-colors text-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
                        <i data-lucide="key-round" class="w-4 h-4"></i>
                    </div>
                    <span class="font-bold">Ubah Kata Sandi</span>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
            </button>

            @if(in_array($user->role, ['superadmin', 'hrd', 'direktur', 'manajer', 'kepala_unit']))
            <a href="{{ route('dashboard', [], false) }}" class="w-full p-4 flex items-center justify-between hover:bg-slate-50 transition-colors text-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    </div>
                    <span class="font-bold">Buka Dashboard Web Lengkap</span>
                </div>
                <i data-lucide="external-link" class="w-4 h-4 text-slate-400"></i>
            </a>
            @endif

            <form method="POST" action="{{ route('app.logout', [], false) }}" class="w-full">
                @csrf
                <button type="submit" class="w-full p-4 flex items-center justify-between hover:bg-rose-50 transition-colors text-rose-600 font-bold">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                        </div>
                        <span>Keluar dari Aplikasi</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-rose-400"></i>
                </button>
            </form>
        </div>

        <div class="text-center pt-2 text-[11px] text-slate-400 leading-relaxed">
            <div>ROSES Mobile Web v1.0 • {{ $namaInstansi }}</div>
            <div class="text-[10px] mt-1">
                &copy; {{ date('Y') }} <strong class="text-slate-600">IT RSKB Ropanasuri</strong><br>
                (Luthfi Ariesto Prayoga &amp; Teddi Takejo Saogok)
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- FLOATING BOTTOM NAVIGATION BAR (IDENTIK FLUTTER SIROM, SAFE AREA IPHONE) -->
    <!-- =================================================================== -->
    <nav class="fixed inset-x-0 bottom-nav-safe max-w-[430px] mx-auto z-40 px-3.5 pointer-events-none">
        <div class="bg-white/95 backdrop-blur-xl border border-slate-200/80 rounded-full px-2 py-1.5 shadow-2xl flex items-center justify-around pointer-events-auto">
            
            <!-- Tab Beranda -->
            <button @click="switchTab('beranda')" class="flex-1 py-1.5 flex flex-col items-center gap-0.5 relative transition-all"
                    :class="currentTab === 'beranda' ? 'text-primary-600 font-bold' : 'text-slate-400 hover:text-slate-600'">
                <i data-lucide="home" class="w-5 h-5"></i>
                <span class="text-[10px]">Beranda</span>
                <span x-show="currentTab === 'beranda'" class="w-1 h-1 rounded-full bg-primary-600 absolute bottom-0"></span>
            </button>

            <!-- Tab Jadwal -->
            <button @click="switchTab('jadwal')" class="flex-1 py-1.5 flex flex-col items-center gap-0.5 relative transition-all"
                    :class="currentTab === 'jadwal' ? 'text-primary-600 font-bold' : 'text-slate-400 hover:text-slate-600'">
                <i data-lucide="calendar" class="w-5 h-5"></i>
                <span class="text-[10px]">Jadwal</span>
                <span x-show="currentTab === 'jadwal'" class="w-1 h-1 rounded-full bg-primary-600 absolute bottom-0"></span>
            </button>

            <!-- Tab Lembur -->
            <button @click="switchTab('lembur')" class="flex-1 py-1.5 flex flex-col items-center gap-0.5 relative transition-all"
                    :class="currentTab === 'lembur' ? 'text-primary-600 font-bold' : 'text-slate-400 hover:text-slate-600'">
                <i data-lucide="clock" class="w-5 h-5"></i>
                <span class="text-[10px]">Lembur</span>
                <span x-show="currentTab === 'lembur'" class="w-1 h-1 rounded-full bg-primary-600 absolute bottom-0"></span>
            </button>

            <!-- Tab Validasi (Hanya bila canValidasi) -->
            @if($canValidasi)
            <button @click="switchTab('validasi')" class="flex-1 py-1.5 flex flex-col items-center gap-0.5 relative transition-all"
                    :class="currentTab === 'validasi' ? 'text-primary-600 font-bold' : 'text-slate-400 hover:text-slate-600'">
                <i data-lucide="check-check" class="w-5 h-5"></i>
                <span class="text-[10px]">Validasi</span>
                <span x-show="currentTab === 'validasi'" class="w-1 h-1 rounded-full bg-primary-600 absolute bottom-0"></span>
            </button>
            @endif

            <!-- Tab Profil -->
            <button @click="switchTab('profil')" class="flex-1 py-1.5 flex flex-col items-center gap-0.5 relative transition-all"
                    :class="currentTab === 'profil' ? 'text-primary-600 font-bold' : 'text-slate-400 hover:text-slate-600'">
                <i data-lucide="user" class="w-5 h-5"></i>
                <span class="text-[10px]">Profil</span>
                <span x-show="currentTab === 'profil'" class="w-1 h-1 rounded-full bg-primary-600 absolute bottom-0"></span>
            </button>
        </div>
    </nav>

    <!-- =================================================================== -->
    <!-- MODAL 1: KAMERA & PRESENSI (MASUK / PULANG) -->
    <!-- =================================================================== -->
    <div x-show="showAbsenModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/80 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200" x-transition:leave="transition ease-in duration-150">
        
        <div class="bg-white rounded-t-[32px] sm:rounded-3xl w-full max-w-md sm:max-w-sm overflow-hidden shadow-2xl flex flex-col max-h-[92dvh] pb-safe sm:pb-0">
            <!-- Header Modal -->
            <div class="p-4 border-b border-slate-100 flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-sm font-black text-slate-900" x-text="absenType === 'masuk' ? 'Presensi Masuk Kerja' : 'Presensi Pulang Kerja'"></h3>
                    <p class="text-[11px] text-slate-500">Ambil foto selfie di area rumah sakit</p>
                </div>
                <button @click="closeAbsenModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Body: Viewfinder Kamera & GPS Status -->
            <div class="p-4 space-y-3 overflow-y-auto">
                <!-- Status GPS & Geofencing Pill (4 State + Tombol Refresh GPS) -->
                <div class="p-3 rounded-2xl flex items-start justify-between gap-2 text-xs border transition-colors"
                     :class="{
                         'bg-sky-50 text-sky-900 border-sky-200': gpsStatus.state === 'loading',
                         'bg-emerald-50 text-emerald-900 border-emerald-200': gpsStatus.state === 'valid',
                         'bg-amber-50 text-amber-900 border-amber-200': gpsStatus.state === 'warning',
                         'bg-rose-50 text-rose-900 border-rose-200': gpsStatus.state === 'error'
                     }">
                    <div class="flex items-start gap-2.5 min-w-0">
                        <span x-show="gpsStatus.state === 'loading'" class="shrink-0 mt-0.5 text-sky-600">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </span>
                        <span x-show="gpsStatus.state === 'valid'" class="shrink-0 mt-0.5 text-emerald-600">
                            <i data-lucide="map-pin" class="w-4 h-4"></i>
                        </span>
                        <span x-show="gpsStatus.state === 'warning' || gpsStatus.state === 'error'" class="shrink-0 mt-0.5"
                              :class="gpsStatus.state === 'warning' ? 'text-amber-600' : 'text-rose-600'">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i>
                        </span>
                        <div class="leading-tight min-w-0">
                            <div class="font-bold" x-text="gpsStatus.title">Mencari Lokasi GPS...</div>
                            <div class="text-[11px] opacity-85 mt-0.5 break-words" x-text="gpsStatus.message"></div>
                        </div>
                    </div>

                    <button type="button" @click="startGpsWatch(true)"
                            class="shrink-0 px-2.5 py-1.5 rounded-xl bg-white/80 hover:bg-white text-[10px] font-bold border border-black/10 shadow-2xs active:scale-95 transition-all">
                        Refresh GPS
                    </button>
                </div>

                <!-- Kamera Viewfinder Container (Rasio dinamis mengikuti orientasi kamera agar tanpa bar hitam & tanpa crop zoom) -->
                <div class="relative w-full max-w-[340px] mx-auto max-h-[48dvh] bg-slate-950 rounded-3xl overflow-hidden shadow-inner flex items-center justify-center"
                     :style="`aspect-ratio: ${cameraAspectRatio};`">
                    
                    <!-- Video Stream WebRTC (Gerak kanan = kanan, tanpa crop) -->
                    <video x-ref="videoElement" @loadedmetadata="updateCameraAspectRatio($event)" autoplay playsinline muted style="transform: scaleX(-1); -webkit-transform: scaleX(-1);" class="w-full h-full object-cover" x-show="cameraStreamActive && !capturedPhotoBlob"></video>

                    <!-- Preview Foto yang Sudah Dijepret -->
                    <img x-show="capturedPhotoBlob" :src="capturedPhotoBlob" class="w-full h-full object-cover" alt="Selfie Preview">

                    <!-- Panduan Oval Wajah (Proporsional terhadap bingkai kamera) -->
                    <div x-show="cameraStreamActive && !capturedPhotoBlob" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                        <div class="h-[72%] aspect-[3/4] max-w-[68%] border-2 border-dashed border-emerald-400/80 rounded-[50%] flex items-end justify-center pb-2.5">
                            <span class="text-[10px] font-bold text-white/85 bg-slate-950/50 px-2.5 py-0.5 rounded-full">Posisikan Wajah</span>
                        </div>
                    </div>

                    <!-- Placeholder Jika Kamera Belum Aktif atau Native Mode -->
                    <div x-show="!cameraStreamActive && !capturedPhotoBlob" class="text-center p-4 text-white space-y-2.5">
                        <i data-lucide="camera" class="w-9 h-9 mx-auto text-slate-400"></i>
                        <div class="text-xs font-semibold text-slate-200" x-text="cameraStatusMessage">Menghubungkan Kamera...</div>
                        <label class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold cursor-pointer active:scale-95 transition-all">
                            <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                            <span>Buka Kamera iPhone</span>
                            <input type="file" accept="image/*" capture="user" class="hidden" @change="handleNativeCameraCapture($event)">
                        </label>
                    </div>

                    <!-- Info ukuran file terkompresi -->
                    <div x-show="capturedPhotoBlob && capturedPhotoSizeLabel" class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-slate-950/60 text-white text-[10px] font-semibold backdrop-blur-xs" x-text="capturedPhotoSizeLabel"></div>

                    <!-- Canvas Rahasia untuk Snapshot & Kompresi Foto -->
                    <canvas x-ref="canvasElement" class="hidden"></canvas>
                </div>

                <!-- Fallback Input Kamera Native iPhone -->
                <div class="text-center">
                    <label class="cursor-pointer text-xs font-bold text-primary-600 hover:text-primary-700 inline-flex items-center justify-center gap-1.5 py-1">
                        <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                        <span>Gunakan Kamera Native iPhone</span>
                        <input type="file" accept="image/*" capture="user" class="hidden" @change="handleNativeCameraCapture($event)">
                    </label>
                </div>
            </div>

            <!-- Footer: Tombol Aksi Jepret & Kirim -->
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center gap-2 shrink-0">
                <template x-if="!capturedPhotoBlob">
                    <button @click="snapPhoto()" :disabled="!cameraStreamActive || processingPhoto" class="w-full py-3 rounded-2xl bg-primary-600 hover:bg-primary-700 active:scale-95 text-white font-bold text-xs flex items-center justify-center gap-2 transition-all disabled:opacity-50 shadow-md">
                        <i data-lucide="camera" class="w-4 h-4"></i>
                        <span x-text="processingPhoto ? 'Memproses Foto...' : 'Ambil Foto Selfie'"></span>
                    </button>
                </template>

                <template x-if="capturedPhotoBlob">
                    <div class="flex items-center gap-2 w-full">
                        <button @click="retakePhoto()" :disabled="submittingAbsen" class="py-3 px-4 rounded-2xl bg-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-300 active:scale-95 transition-all disabled:opacity-50">
                            Foto Ulang
                        </button>
                        <button @click="submitAbsensi()" :disabled="submittingAbsen || processingPhoto || !capturedPhotoFile || gpsStatus.state !== 'valid'" class="flex-1 py-3 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold text-xs hover:from-emerald-700 hover:to-teal-700 active:scale-95 transition-all shadow-md flex items-center justify-center gap-2 disabled:opacity-50">
                            <span x-text="submittingAbsen ? 'Mengirim Presensi...' : (processingPhoto ? 'Mengompres Foto...' : 'Kirim Presensi Sekarang')"></span>
                            <i data-lucide="send" class="w-4 h-4"></i>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL 2: DETAIL JADWAL DINAS TANGGAL -->
    <!-- =================================================================== -->
    <div x-show="showRosterDetailModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/70 backdrop-blur-xs">
        <div class="bg-white rounded-t-[32px] sm:rounded-3xl w-full max-w-md sm:max-w-sm p-5 space-y-4 shadow-2xl pb-safe sm:pb-5">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900" x-text="formatRosterDateTitle(selectedRosterDay?.tanggal)"></h3>
                <button @click="showRosterDetailModal = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedRosterDay">
                <div class="space-y-3 text-xs">
                    <div class="p-3 rounded-2xl bg-slate-50 flex items-center justify-between">
                        <span class="text-slate-500 font-medium">Shift Kerja</span>
                        <span class="font-bold text-slate-900" x-text="selectedRosterDay.nama_shift || 'Libur / Off'"></span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="p-2.5 rounded-xl border border-slate-100">
                            <span class="text-[10px] text-slate-400 block">Jadwal Masuk</span>
                            <span class="font-bold text-slate-800" x-text="selectedRosterDay.jam_masuk || '-'"></span>
                        </div>
                        <div class="p-2.5 rounded-xl border border-slate-100">
                            <span class="text-[10px] text-slate-400 block">Jadwal Pulang</span>
                            <span class="font-bold text-slate-800" x-text="selectedRosterDay.jam_keluar || '-'"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-800">
                            <span class="text-[10px] opacity-75 block">Presensi Masuk</span>
                            <span class="font-bold" x-text="selectedRosterDay.absen_masuk || 'Belum'"></span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-800">
                            <span class="text-[10px] opacity-75 block">Presensi Pulang</span>
                            <span class="font-bold" x-text="selectedRosterDay.absen_pulang || 'Belum'"></span>
                        </div>
                    </div>

                    <template x-if="selectedRosterDay.sesi2">
                        <div class="p-3 rounded-2xl bg-purple-50 border border-purple-100 space-y-2">
                            <div class="flex items-center justify-between text-purple-900">
                                <span class="font-bold">Shift 2: <span x-text="selectedRosterDay.sesi2.nama_shift || '-'"></span></span>
                                <span class="text-[11px] font-semibold" x-text="(selectedRosterDay.sesi2.jam_masuk || '-') + ' - ' + (selectedRosterDay.sesi2.jam_keluar || '-')"></span>
                            </div>
                            <div class="text-[11px] text-purple-700 flex items-center justify-between">
                                <span x-text="'Masuk: ' + (selectedRosterDay.sesi2.absen_masuk || 'Belum')"></span>
                                <span x-text="'Pulang: ' + (selectedRosterDay.sesi2.absen_pulang || 'Belum')"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL 3: AJUKAN EKSTENSI LEMBUR -->
    <!-- =================================================================== -->
    <div x-show="showEkstensiModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/70 backdrop-blur-xs">
        <div class="bg-white rounded-t-[32px] sm:rounded-3xl w-full max-w-md sm:max-w-sm p-5 space-y-4 shadow-2xl pb-safe sm:pb-5">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900">Ajukan Lembur Ekstensi</h3>
                <button @click="showEkstensiModal = false; stopGpsWatch()" class="text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="px-3 py-2 rounded-xl text-[11px] flex items-center justify-between border"
                     :class="gpsStatus.state === 'valid' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200'">
                    <span class="truncate pr-2" x-text="gpsStatus.message"></span>
                    <button type="button" @click="startGpsWatch(true)" class="font-bold text-primary-600 shrink-0">Refresh</button>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Durasi Lembur (Menit)</label>
                    <select x-model="formEkstensi.durasi_menit" class="w-full p-3 rounded-2xl border border-slate-200 bg-white font-medium">
                        <option value="60">60 Menit (1 Jam)</option>
                        <option value="120">120 Menit (2 Jam)</option>
                        <option value="180">180 Menit (3 Jam)</option>
                        <option value="240">240 Menit (4 Jam)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Keterangan / Tugas Lembur</label>
                    <textarea x-model="formEkstensi.keterangan" rows="3" placeholder="Jelaskan alasan dan tugas lembur lanjutan..." class="w-full p-3 rounded-2xl border border-slate-200 font-medium"></textarea>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Foto Bukti Tugas Lembur</label>
                    <input type="file" accept="image/*" capture="user" @change="handleEkstensiFoto($event)" class="w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700">
                </div>

                <button @click="submitEkstensi()" :disabled="submittingEkstensi" class="w-full py-3.5 rounded-2xl bg-primary-600 text-white font-bold hover:bg-primary-700 active:scale-95 transition-all shadow-md">
                    <span x-text="submittingEkstensi ? 'Mengirimkan...' : 'Kirim Pengajuan Ekstensi'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL 4: ON-CALL DARURAT (MASUK / KELUAR) -->
    <!-- =================================================================== -->
    <div x-show="showOnCallModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/70 backdrop-blur-xs">
        <div class="bg-white rounded-t-[32px] sm:rounded-3xl w-full max-w-md sm:max-w-sm p-5 space-y-4 shadow-2xl pb-safe sm:pb-5">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900" x-text="isOnCallClockOut ? 'Akhiri Sesi On-Call' : 'Mulai On-Call Darurat'"></h3>
                <button @click="showOnCallModal = false; stopGpsWatch()" class="text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="px-3 py-2 rounded-xl text-[11px] flex items-center justify-between border"
                     :class="gpsStatus.state === 'valid' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200'">
                    <span class="truncate pr-2" x-text="gpsStatus.message"></span>
                    <button type="button" @click="startGpsWatch(true)" class="font-bold text-primary-600 shrink-0">Refresh</button>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Keterangan Panggilan Darurat</label>
                    <textarea x-model="formOnCall.keterangan" rows="3" placeholder="Contoh: Operasi Cito, IGD overload, dll..." class="w-full p-3 rounded-2xl border border-slate-200 font-medium"></textarea>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Foto Selfie Kehadiran On-Call</label>
                    <input type="file" accept="image/*" capture="user" @change="handleOnCallFoto($event)" class="w-full text-xs text-slate-500 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700">
                </div>

                <button @click="submitOnCall()" :disabled="submittingOnCall" class="w-full py-3.5 rounded-2xl bg-blue-600 text-white font-bold hover:bg-blue-700 active:scale-95 transition-all shadow-md">
                    <span x-text="submittingOnCall ? 'Mengirim Data...' : (isOnCallClockOut ? 'Akhiri Sesi On-Call Sekarang' : 'Mulai Sesi On-Call Sekarang')"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL 5: AMBIL SALDO JAM (TIME-OFF IN LIEU) -->
    <!-- =================================================================== -->
    <div x-show="showAmbilJamModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/70 backdrop-blur-xs">
        <div class="bg-white rounded-t-[32px] sm:rounded-3xl w-full max-w-md sm:max-w-sm p-5 space-y-4 shadow-2xl pb-safe sm:pb-5">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900">Ajukan Pengambilan Saldo Jam</h3>
                <button @click="showAmbilJamModal = false" class="text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Tanggal Rencana Ambil Jam</label>
                    <input type="date" x-model="formAmbilJam.tanggal_rencana" class="w-full p-3 rounded-2xl border border-slate-200 font-medium">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Durasi yang Diambil (Menit)</label>
                    <input type="number" step="30" min="30" x-model="formAmbilJam.durasi_menit" placeholder="Contoh: 60, 120, 240" class="w-full p-3 rounded-2xl border border-slate-200 font-medium">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Alasan Pengambilan Jam</label>
                    <textarea x-model="formAmbilJam.alasan" rows="3" placeholder="Jelaskan keperluan izin / pulang cepat..." class="w-full p-3 rounded-2xl border border-slate-200 font-medium"></textarea>
                </div>

                <button @click="submitAmbilJam()" :disabled="submittingAmbilJam" class="w-full py-3.5 rounded-2xl bg-amber-600 text-white font-bold hover:bg-amber-700 active:scale-95 transition-all shadow-md">
                    <span x-text="submittingAmbilJam ? 'Memproses...' : 'Kirim Permohonan Ambil Jam'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL 6: UBAH KATA SANDI -->
    <!-- =================================================================== -->
    <div x-show="showUbahPasswordModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/70 backdrop-blur-xs">
        <div class="bg-white rounded-t-[32px] sm:rounded-3xl w-full max-w-md sm:max-w-sm p-5 space-y-4 shadow-2xl pb-safe sm:pb-5">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900">Ubah Kata Sandi</h3>
                <button @click="showUbahPasswordModal = false" class="text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kata Sandi Saat Ini</label>
                    <input type="password" x-model="formPassword.current_password" class="w-full p-3 rounded-2xl border border-slate-200 font-medium">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Kata Sandi Baru</label>
                    <input type="password" x-model="formPassword.new_password" class="w-full p-3 rounded-2xl border border-slate-200 font-medium">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" x-model="formPassword.new_password_confirmation" class="w-full p-3 rounded-2xl border border-slate-200 font-medium">
                </div>

                <button @click="submitUbahPassword()" class="w-full py-3.5 rounded-2xl bg-primary-600 text-white font-bold hover:bg-primary-700 active:scale-95 transition-all shadow-md">
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL 7: UBAH FOTO PROFIL -->
    <!-- =================================================================== -->
    <div x-show="showUploadFotoModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/70 backdrop-blur-xs">
        <div class="bg-white rounded-t-[32px] sm:rounded-3xl w-full max-w-md sm:max-w-sm p-5 space-y-4 shadow-2xl pb-safe sm:pb-5">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900">Ubah Foto Profil</h3>
                <button @click="showUploadFotoModal = false" class="text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="text-center" x-show="fotoProfilPreview">
                    <img :src="fotoProfilPreview" class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-emerald-200 shadow-sm" alt="Preview Foto Profil">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Pilih Foto atau Ambil Selfie</label>
                    <input type="file" accept="image/*" @change="handleUploadFotoSelect($event)" class="w-full text-xs text-slate-500 file:mr-2 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700">
                </div>

                <button @click="submitUploadFoto()" :disabled="submittingFotoProfil || !fotoProfilFile" class="w-full py-3.5 rounded-2xl bg-primary-600 text-white font-bold hover:bg-primary-700 active:scale-95 transition-all shadow-md disabled:opacity-50">
                    <span x-text="submittingFotoProfil ? 'Mengunggah Foto...' : 'Simpan Foto Profil'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Global -->
    <div x-show="toast.show" x-cloak x-transition class="fixed top-5 inset-x-4 max-w-xs mx-auto z-50 p-3.5 rounded-2xl text-white text-xs font-bold shadow-xl flex items-center gap-2.5"
         :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'">
        <span x-show="toast.type === 'success'" class="shrink-0">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
        </span>
        <span x-show="toast.type !== 'success'" class="shrink-0">
            <i data-lucide="alert-circle" class="w-4 h-4"></i>
        </span>
        <span x-text="toast.message" class="leading-tight"></span>
    </div>
</div>
@endsection

@push('scripts')
<script>
function siroMobileApp() {
    return {
        currentTab: 'beranda',
        subTabLembur: 0,
        tabValidasi: 'lembur',

        // Live Clock State
        liveTime: '00:00:00',
        liveDate: '',
        greetingText: 'Selamat Datang',

        // Data State + Lazy-loading flags
        todayData: {},
        rosterDays: [],
        rosterLeadingBlanks: [],
        rosterMonth: new Date().getMonth() + 1,
        rosterYear: new Date().getFullYear(),
        loadingRoster: false,
        rosterLoaded: false,
        selectedRosterDay: null,
        showRosterDetailModal: false,

        // Lembur State
        riwayatLembur: [],
        riwayatAmbilJam: [],
        loadingLembur: false,
        lemburLoaded: false,
        showEkstensiModal: false,
        formEkstensi: { durasi_menit: 60, keterangan: '', foto: null },
        submittingEkstensi: false,

        showOnCallModal: false,
        isOnCallClockOut: false,
        formOnCall: { keterangan: '', foto: null },
        submittingOnCall: false,

        showAmbilJamModal: false,
        formAmbilJam: { tanggal_rencana: '', durasi_menit: 60, alasan: '' },
        submittingAmbilJam: false,

        // Validasi Atasan State
        validasiLemburList: [],
        validasiAmbilList: [],
        loadingValidasi: false,
        validasiLoaded: false,

        // Profil & Password State
        profilePhotoUrl: @json($userFoto),
        showUbahPasswordModal: false,
        showUploadFotoModal: false,
        fotoProfilPreview: null,
        fotoProfilFile: null,
        submittingFotoProfil: false,
        formPassword: { current_password: '', new_password: '', new_password_confirmation: '' },

        // Kamera & Absensi State
        showAbsenModal: false,
        absenType: 'masuk',
        cameraStream: null,
        cameraStreamActive: false,
        cameraAspectRatio: '3 / 4',
        cameraStatusMessage: 'Menghubungkan Kamera...',
        processingPhoto: false,
        capturedPhotoBlob: null,
        capturedPhotoFile: null,
        capturedPhotoSizeLabel: '',
        submittingAbsen: false,

        // GPS State (Adaptive iPhone Safari)
        gpsCoords: { latitude: 0, longitude: 0, accuracy: 0, updatedAt: 0 },
        gpsStatus: { state: 'loading', title: 'Mencari Lokasi GPS...', message: 'Mengambil koordinat...', in_radius: false },
        gpsWatchId: null,
        gpsFallbackTimer: null,
        gpsFallbackTriggered: false,
        lastLocationCheckAt: 0,

        // Toast
        toast: { show: false, message: '', type: 'success' },

        monthNames: [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ],

        initApp() {
            this.startLiveClock();
            this.fetchTodayStatus();
            this.prewarmGpsIfPermitted();

            // Refresh CSRF & status saat PWA/Safari dibuka kembali dari background
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.refreshCsrfToken();
                    if (this.currentTab === 'beranda') {
                        this.fetchTodayStatus();
                    }
                }
            });
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) {
                    this.refreshCsrfToken();
                    this.fetchTodayStatus();
                }
            });
        },

        switchTab(tab) {
            this.currentTab = tab;
            if (tab === 'jadwal' && !this.rosterLoaded) {
                this.fetchRoster();
            } else if (tab === 'lembur' && !this.lemburLoaded) {
                this.fetchLemburData();
            } else if (tab === 'validasi' && !this.validasiLoaded) {
                @if($canValidasi)
                this.fetchValidasiList();
                @endif
            }
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        startLiveClock() {
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const tick = () => {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                this.liveTime = `${h}:${m}:${s}`;
                this.liveDate = `${days[now.getDay()]}, ${now.getDate()} ${this.monthNames[now.getMonth()]} ${now.getFullYear()}`;

                const hour = now.getHours();
                if (hour >= 4 && hour < 11) this.greetingText = 'Selamat Pagi';
                else if (hour >= 11 && hour < 15) this.greetingText = 'Selamat Siang';
                else if (hour >= 15 && hour < 18) this.greetingText = 'Selamat Sore';
                else this.greetingText = 'Selamat Malam';
            };
            tick();
            setInterval(tick, 1000);
        },

        showToast(msg, type = 'success') {
            this.toast.message = msg;
            this.toast.type = type;
            this.toast.show = true;
            setTimeout(() => this.toast.show = false, 4000);
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        updateCsrfMeta(token) {
            if (!token) return;
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', token);
        },

        async refreshCsrfToken() {
            try {
                const res = await fetch('{{ route('app.api.csrf-token', [], false) }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                if (res.ok) {
                    const json = await res.json();
                    if (json.csrf_token) this.updateCsrfMeta(json.csrf_token);
                }
            } catch (_) {}
        },

        async safeFetch(url, options = {}, timeoutMs = 25000, retriedCsrf = false) {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), timeoutMs);
            const meta = document.querySelector('meta[name="csrf-token"]');
            const token = meta ? meta.getAttribute('content') : '';

            const headers = Object.assign(
                {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                },
                options.headers || {}
            );

            try {
                const res = await fetch(url, Object.assign({}, options, {
                    headers,
                    signal: controller.signal,
                    credentials: 'same-origin'
                }));
                clearTimeout(timer);

                // Otomatis refresh CSRF token jika PWA Safari bangun dari tidur (419)
                if (res.status === 419 && !retriedCsrf) {
                    await this.refreshCsrfToken();
                    return this.safeFetch(url, options, timeoutMs, true);
                }

                if (res.status === 401) {
                    this.showToast('Sesi login telah berakhir. Silakan masuk ulang.', 'error');
                    setTimeout(() => { window.location.href = '{{ route('app.login', [], false) }}'; }, 1200);
                    return { success: false, message: 'Sesi berakhir. Mengalihkan ke login...' };
                }

                if (res.status === 413) {
                    return { success: false, message: 'Ukuran foto terlalu besar. Silakan foto ulang.' };
                }

                const text = await res.text();
                let json = null;
                try {
                    json = text ? JSON.parse(text) : {};
                } catch (_) {
                    return {
                        success: false,
                        message: `Server bermasalah (HTTP ${res.status}). Silakan coba beberapa saat lagi.`
                    };
                }

                if (!res.ok && json && json.success === undefined) {
                    let msg = json.message || `Terjadi kesalahan (HTTP ${res.status}).`;
                    if (json.errors) {
                        const firstKey = Object.keys(json.errors)[0];
                        if (firstKey && json.errors[firstKey]?.[0]) {
                            msg = json.errors[firstKey][0];
                        }
                    }
                    return { success: false, message: msg, data: json.data };
                }

                return json;
            } catch (err) {
                clearTimeout(timer);
                if (err.name === 'AbortError') {
                    return {
                        success: false,
                        message: 'Koneksi lambat / waktu habis. Pastikan sinyal Wi-Fi atau seluler stabil lalu coba lagi.'
                    };
                }
                return {
                    success: false,
                    message: 'Koneksi ke server terganggu. Periksa jaringan internet Anda.'
                };
            }
        },

        async fetchTodayStatus() {
            const json = await this.safeFetch('{{ route('app.api.today-status', [], false) }}', { cache: 'no-store' }, 15000);
            if (json && json.csrf_token) this.updateCsrfMeta(json.csrf_token);
            if (json && json.success) {
                this.todayData = json.data;
                if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
            }
        },

        async fetchRoster() {
            this.loadingRoster = true;
            const firstDayIdx = new Date(this.rosterYear, this.rosterMonth - 1, 1).getDay();
            this.rosterLeadingBlanks = Array.from({ length: firstDayIdx }, (_, i) => i);

            const json = await this.safeFetch(`{{ route('app.api.jadwal-dinas', [], false) }}?bulan=${this.rosterMonth}&tahun=${this.rosterYear}`);
            if (json && json.success) {
                const serverStartIdx = json.data.hari_awal_minggu;
                if (typeof serverStartIdx === 'number') {
                    this.rosterLeadingBlanks = Array.from({ length: serverStartIdx }, (_, i) => i);
                }
                this.rosterDays = json.data.hari || [];
                this.rosterLoaded = true;
            }
            this.loadingRoster = false;
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        isTodayRoster(day) {
            const now = new Date();
            return now.getFullYear() === this.rosterYear && (now.getMonth() + 1) === this.rosterMonth && now.getDate() === day;
        },

        isSundayRoster(day) {
            if (!day) return false;
            return new Date(this.rosterYear, this.rosterMonth - 1, day).getDay() === 0;
        },

        formatRosterDateTitle(day) {
            if (!day) return 'Detail Jadwal';
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const d = new Date(this.rosterYear, this.rosterMonth - 1, day);
            return `${days[d.getDay()]}, ${day} ${this.monthNames[this.rosterMonth - 1]} ${this.rosterYear}`;
        },

        changeMonth(delta) {
            this.rosterMonth += delta;
            if (this.rosterMonth > 12) {
                this.rosterMonth = 1;
                this.rosterYear += 1;
            } else if (this.rosterMonth < 1) {
                this.rosterMonth = 12;
                this.rosterYear -= 1;
            }
            this.fetchRoster();
        },

        async fetchLemburData() {
            this.loadingLembur = true;
            const json = await this.safeFetch('{{ route('app.api.lembur-data', [], false) }}');
            if (json && json.success) {
                this.riwayatLembur = json.data.riwayat_lembur || [];
                this.riwayatAmbilJam = json.data.riwayat_ambil_jam || [];
                this.lemburLoaded = true;
            }
            this.loadingLembur = false;
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        async fetchValidasiList() {
            this.loadingValidasi = true;
            const json = await this.safeFetch('{{ route('app.api.validasi-list', [], false) }}');
            if (json && json.success) {
                this.validasiLemburList = json.data.lembur || [];
                this.validasiAmbilList = json.data.ambil_jam || [];
                this.validasiLoaded = true;
            }
            this.loadingValidasi = false;
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        // ===================================================================
        // GPS ADAPTIF KHUSUS IPHONE (HIGH ACCURACY + INDOOR WIFI FALLBACK)
        // ===================================================================
        async prewarmGpsIfPermitted() {
            if (!navigator.geolocation || !navigator.permissions || !navigator.permissions.query) return;
            try {
                const perm = await navigator.permissions.query({ name: 'geolocation' });
                if (perm.state === 'granted') {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            if (pos.coords.latitude !== 0 || pos.coords.longitude !== 0) {
                                this.gpsCoords = {
                                    latitude: pos.coords.latitude,
                                    longitude: pos.coords.longitude,
                                    accuracy: pos.coords.accuracy,
                                    updatedAt: Date.now()
                                };
                            }
                        },
                        () => {},
                        { enableHighAccuracy: false, maximumAge: 60000, timeout: 5000 }
                    );
                }
            } catch (_) {}
        },

        stopGpsWatch() {
            if (this.gpsWatchId !== null && navigator.geolocation) {
                navigator.geolocation.clearWatch(this.gpsWatchId);
                this.gpsWatchId = null;
            }
            if (this.gpsFallbackTimer) {
                clearTimeout(this.gpsFallbackTimer);
                this.gpsFallbackTimer = null;
            }
        },

        startGpsWatch(forceRefresh = false) {
            if (!navigator.geolocation) {
                this.gpsStatus = {
                    state: 'error',
                    title: 'GPS Tidak Didukung',
                    message: 'Browser ini tidak mendukung fitur lokasi GPS.',
                    in_radius: false
                };
                return;
            }

            this.stopGpsWatch();
            this.gpsFallbackTriggered = false;

            if (forceRefresh) {
                this.gpsCoords = { latitude: 0, longitude: 0, accuracy: 0, updatedAt: 0 };
            }

            // Jika ada cache GPS segar (< 15 detik & akurasi bagus), langsung cek jarak sambil tetap menyempurnakan GPS
            const ageMs = Date.now() - (this.gpsCoords.updatedAt || 0);
            if (!forceRefresh && ageMs < 15000 && (this.gpsCoords.latitude !== 0 || this.gpsCoords.longitude !== 0)) {
                this.verifyLocationWithBackend(this.gpsCoords);
            } else {
                this.gpsStatus = {
                    state: 'loading',
                    title: 'Mencari Lokasi iPhone...',
                    message: 'Mengambil koordinat GPS / Wi-Fi sekitar RS...',
                    in_radius: false
                };
            }

            // Tahap 1: Watch High Accuracy (berjalan terus menyempurnakan sinyal)
            this.gpsWatchId = navigator.geolocation.watchPosition(
                (pos) => this.handleGpsPosition(pos),
                (err) => this.handleGpsError(err),
                { enableHighAccuracy: true, maximumAge: 15000, timeout: 15000 }
            );

            // Tahap 2: Fallback Indoor (jika dalam 3 detik satelit GPS belum lock, ambil via Wi-Fi/Seluler iPhone)
            this.gpsFallbackTimer = setTimeout(() => {
                if ((Date.now() - (this.gpsCoords.updatedAt || 0)) > 15000 || this.gpsCoords.latitude === 0) {
                    this.runStandardAccuracyFallback();
                }
            }, 3000);
        },

        runStandardAccuracyFallback() {
            if (this.gpsFallbackTriggered || !navigator.geolocation) return;
            this.gpsFallbackTriggered = true;

            navigator.geolocation.getCurrentPosition(
                (pos) => this.handleGpsPosition(pos),
                (err) => {
                    if (this.gpsCoords.latitude === 0 && this.gpsCoords.longitude === 0) {
                        this.handleGpsError(err, true);
                    }
                },
                { enableHighAccuracy: false, maximumAge: 60000, timeout: 10000 }
            );
        },

        handleGpsPosition(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = pos.coords.accuracy || 50;

            if (lat === 0 && lng === 0) return;

            if (this.gpsFallbackTimer) {
                clearTimeout(this.gpsFallbackTimer);
                this.gpsFallbackTimer = null;
            }

            // Jangan timpa sinyal akurat baru (<15 detik) dengan sinyal jauh lebih buruk
            const prevAge = Date.now() - (this.gpsCoords.updatedAt || 0);
            if (prevAge < 15000 && this.gpsCoords.accuracy > 0 && acc > this.gpsCoords.accuracy * 1.5 && this.gpsCoords.accuracy <= 60) {
                return;
            }

            this.gpsCoords = {
                latitude: lat,
                longitude: lng,
                accuracy: acc,
                updatedAt: Date.now()
            };

            // Throttle cek jarak ke backend agar tidak membanjiri server
            const now = Date.now();
            if (now - this.lastLocationCheckAt >= 1500 || this.gpsStatus.state === 'loading') {
                this.lastLocationCheckAt = now;
                this.verifyLocationWithBackend(this.gpsCoords);
            }
        },

        handleGpsError(err, isFromFallback = false) {
            if (err.code === 1) {
                this.stopGpsWatch();
                this.gpsStatus = {
                    state: 'error',
                    title: 'Izin Lokasi Ditolak',
                    message: 'Di iPhone: buka Pengaturan > Privasi & Keamanan > Layanan Lokasi > Situs Web Safari > pilih "Saat Menggunakan App" & nyalakan Lokasi Tepat.',
                    in_radius: false
                };
                return;
            }

            // Timeout (3) atau Unavailable (2) saat high-accuracy -> langsung coba mode standar Wi-Fi/Seluler
            if (!isFromFallback && !this.gpsFallbackTriggered) {
                this.runStandardAccuracyFallback();
                return;
            }

            if (this.gpsCoords.latitude === 0 && this.gpsCoords.longitude === 0) {
                this.gpsStatus = {
                    state: 'error',
                    title: 'Sinyal GPS Lemah',
                    message: 'Lokasi belum terbaca di dalam ruangan. Mendekatlah ke jendela/pintu lalu ketuk "Refresh GPS".',
                    in_radius: false
                };
            }
        },

        async verifyLocationWithBackend(coords) {
            const json = await this.safeFetch('{{ route('app.api.check-location', [], false) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                    accuracy: coords.accuracy
                })
            }, 10000);

            if (!json) return;

            if (json.accuracy_low) {
                this.gpsStatus = {
                    state: 'warning',
                    title: 'Akurasi GPS Rendah',
                    message: json.message,
                    in_radius: false
                };
            } else if (json.in_radius) {
                this.gpsStatus = {
                    state: 'valid',
                    title: 'Lokasi Valid (Dalam Radius RS)',
                    message: json.message,
                    in_radius: true
                };
            } else if (json.success === false) {
                this.gpsStatus = {
                    state: 'error',
                    title: 'Gagal Memeriksa Lokasi',
                    message: json.message || 'Coba ketuk Refresh GPS.',
                    in_radius: false
                };
            } else {
                this.gpsStatus = {
                    state: 'warning',
                    title: 'Di Luar Radius RS',
                    message: json.message,
                    in_radius: false
                };
            }
        },

        // ===================================================================
        // KOMPRESI GAMBAR OTOMATIS (UBAH FOTO 10MB IPHONE JADI ~80KB JPEG)
        // ===================================================================
        async compressCanvasToJpeg(canvas, quality = 0.72) {
            const dataUrl = canvas.toDataURL('image/jpeg', quality);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
            const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
            const sizeKb = Math.max(1, Math.round(file.size / 1024));
            return { dataUrl, file, sizeKb, width: canvas.width, height: canvas.height };
        },

        async compressImageFile(file, maxDim = 720, quality = 0.72) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                const objectUrl = URL.createObjectURL(file);

                img.onload = async () => {
                    try {
                        let w = img.naturalWidth || img.width;
                        let h = img.naturalHeight || img.height;
                        if (w > maxDim || h > maxDim) {
                            if (w >= h) {
                                h = Math.round((h * maxDim) / w);
                                w = maxDim;
                            } else {
                                w = Math.round((w * maxDim) / h);
                                h = maxDim;
                            }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);

                        const compressed = await this.compressCanvasToJpeg(canvas, quality);
                        URL.revokeObjectURL(objectUrl);
                        resolve(compressed);
                    } catch (e) {
                        URL.revokeObjectURL(objectUrl);
                        reject(e);
                    }
                };

                img.onerror = () => {
                    URL.revokeObjectURL(objectUrl);
                    reject(new Error('Format gambar tidak dapat diproses browser.'));
                };

                img.src = objectUrl;
            });
        },

        // ===================================================================
        // ALUR KAMERA & PRESENSI
        // ===================================================================
        openAbsensi(type) {
            this.absenType = type;
            this.capturedPhotoBlob = null;
            this.capturedPhotoFile = null;
            this.capturedPhotoSizeLabel = '';
            this.cameraAspectRatio = '3 / 4';
            this.processingPhoto = false;
            this.showAbsenModal = true;

            this.refreshCsrfToken();
            this.startGpsWatch(false);
            this.initCameraStream();
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        closeAbsenModal() {
            this.stopCameraStream();
            this.stopGpsWatch();
            this.showAbsenModal = false;
        },

        updateCameraAspectRatio(event) {
            const video = event?.target || this.$refs.videoElement;
            if (video && video.videoWidth && video.videoHeight) {
                this.cameraAspectRatio = `${video.videoWidth} / ${video.videoHeight}`;
            }
        },

        async initCameraStream() {
            this.cameraStreamActive = false;
            this.cameraStatusMessage = 'Menghubungkan Kamera...';
            try {
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    const isMobileDevice = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
                    const isPortrait = window.innerHeight >= window.innerWidth;

                    const preferredVideo = (isMobileDevice && isPortrait)
                        ? {
                            facingMode: 'user',
                            aspectRatio: { ideal: 0.75 },
                            width: { ideal: 1080 },
                            height: { ideal: 1440 }
                        }
                        : {
                            // Pada webcam laptop/desktop, gunakan mode wide native 1280x720 agar tidak ter-crop ke 640x480
                            facingMode: 'user',
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        };

                    try {
                        this.cameraStream = await navigator.mediaDevices.getUserMedia({
                            video: preferredVideo,
                            audio: false
                        });
                    } catch (_) {
                        this.cameraStream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'user' },
                            audio: false
                        });
                    }

                    // Setel zoom kamera ke nilai minimum (paling lebar) bila perangkat mendukung kontrol zoom
                    const track = this.cameraStream.getVideoTracks()[0];
                    if (track && typeof track.getCapabilities === 'function') {
                        const caps = track.getCapabilities();
                        if (caps && caps.zoom && typeof caps.zoom.min === 'number') {
                            track.applyConstraints({ advanced: [{ zoom: caps.zoom.min }] }).catch(() => {});
                        }
                    }

                    if (this.$refs.videoElement) {
                        this.$refs.videoElement.srcObject = this.cameraStream;
                        await this.$refs.videoElement.play().catch(() => {});
                        this.updateCameraAspectRatio();
                        this.cameraStreamActive = true;
                    }
                } else {
                    this.cameraStatusMessage = 'Gunakan tombol Kamera iPhone di bawah.';
                }
            } catch (err) {
                console.warn('WebRTC Camera gagal atau diblokir:', err);
                this.cameraStreamActive = false;
                this.cameraStatusMessage = 'Ketuk tombol di bawah untuk membuka Kamera iPhone.';
            }
        },

        stopCameraStream() {
            if (this.cameraStream) {
                this.cameraStream.getTracks().forEach(track => track.stop());
                this.cameraStream = null;
            }
            this.cameraStreamActive = false;
        },

        async snapPhoto() {
            const video = this.$refs.videoElement;
            const canvas = this.$refs.canvasElement;
            if (!video || !canvas || !video.videoWidth) {
                this.showToast('Kamera sedang bersiap, coba tekan lagi.', 'error');
                return;
            }

            this.processingPhoto = true;
            try {
                const maxDim = 720;
                let w = video.videoWidth;
                let h = video.videoHeight;
                if (w > maxDim || h > maxDim) {
                    if (w >= h) {
                        h = Math.round((h * maxDim) / w);
                        w = maxDim;
                    } else {
                        w = Math.round((w * maxDim) / h);
                        h = maxDim;
                    }
                }
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                // Sesuaikan hasil jepretan dengan orientasi preview kamera (kanan = kanan, kiri = kiri)
                ctx.save();
                ctx.translate(w, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0, w, h);
                ctx.restore();

                const result = await this.compressCanvasToJpeg(canvas, 0.72);
                this.capturedPhotoBlob = result.dataUrl;
                this.capturedPhotoFile = result.file;
                this.capturedPhotoSizeLabel = `${result.sizeKb} KB`;
                this.stopCameraStream();
            } catch (e) {
                this.showToast('Gagal mengambil foto kamera.', 'error');
            } finally {
                this.processingPhoto = false;
                if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
            }
        },

        retakePhoto() {
            this.capturedPhotoBlob = null;
            this.capturedPhotoFile = null;
            this.capturedPhotoSizeLabel = '';
            this.initCameraStream();
        },

        async handleNativeCameraCapture(e) {
            const rawFile = e.target.files[0];
            e.target.value = '';
            if (!rawFile) return;

            this.processingPhoto = true;
            try {
                const result = await this.compressImageFile(rawFile, 720, 0.72);
                if (result.width && result.height) {
                    this.cameraAspectRatio = `${result.width} / ${result.height}`;
                }
                this.capturedPhotoBlob = result.dataUrl;
                this.capturedPhotoFile = result.file;
                this.capturedPhotoSizeLabel = `${result.sizeKb} KB`;
                this.stopCameraStream();
            } catch (_) {
                // Fallback bila browser tidak bisa decode lewat canvas (misal codec tertentu)
                if (rawFile.size <= 4 * 1024 * 1024) {
                    this.capturedPhotoFile = rawFile;
                    this.capturedPhotoSizeLabel = `${Math.round(rawFile.size / 1024)} KB`;
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        this.capturedPhotoBlob = ev.target.result;
                        this.stopCameraStream();
                    };
                    reader.readAsDataURL(rawFile);
                } else {
                    this.showToast('Ukuran foto terlalu besar. Gunakan resolusi standar kamera.', 'error');
                }
            } finally {
                this.processingPhoto = false;
                if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
            }
        },

        async submitAbsensi() {
            if (!this.capturedPhotoFile) {
                this.showToast('Silakan ambil foto selfie terlebih dahulu.', 'error');
                return;
            }
            if (this.gpsStatus.state !== 'valid') {
                this.showToast(this.gpsStatus.message || 'Pastikan lokasi GPS valid di dalam radius RS.', 'error');
                return;
            }

            this.submittingAbsen = true;
            const formData = new FormData();
            formData.append('latitude', this.gpsCoords.latitude);
            formData.append('longitude', this.gpsCoords.longitude);
            formData.append('accuracy', Math.round(this.gpsCoords.accuracy || 15));
            formData.append('foto', this.capturedPhotoFile);

            const url = this.absenType === 'masuk'
                ? '{{ route('app.api.absensi.masuk', [], false) }}'
                : '{{ route('app.api.absensi.pulang', [], false) }}';

            const json = await this.safeFetch(url, { method: 'POST', body: formData }, 25000);
            this.submittingAbsen = false;

            if (json && json.success) {
                this.showToast(json.message || 'Presensi berhasil dicatat!');
                this.closeAbsenModal();
                this.rosterLoaded = false;
                this.fetchTodayStatus();
            } else {
                this.showToast((json && json.message) || 'Gagal mengirim presensi.', 'error');
            }
        },

        // ===================================================================
        // LEMBUR, ON-CALL & AMBIL JAM ACTION
        // ===================================================================
        openEkstensiModal() {
            this.formEkstensi = { durasi_menit: 60, keterangan: '', foto: null };
            this.showEkstensiModal = true;
            this.startGpsWatch(false);
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        async handleEkstensiFoto(e) {
            const rawFile = e.target.files[0];
            if (!rawFile) return;
            try {
                const compressed = await this.compressImageFile(rawFile, 720, 0.72);
                this.formEkstensi.foto = compressed.file;
            } catch (_) {
                this.formEkstensi.foto = rawFile;
            }
        },

        async submitEkstensi() {
            if (!this.formEkstensi.keterangan || !this.formEkstensi.foto) {
                this.showToast('Keterangan dan foto bukti wajib diisi.', 'error');
                return;
            }
            if (this.gpsCoords.latitude === 0 && this.gpsCoords.longitude === 0) {
                this.showToast('Menunggu koordinat GPS iPhone. Pastikan izin lokasi aktif.', 'error');
                this.startGpsWatch(true);
                return;
            }

            this.submittingEkstensi = true;
            const formData = new FormData();
            formData.append('durasi_menit', this.formEkstensi.durasi_menit);
            formData.append('keterangan', this.formEkstensi.keterangan);
            formData.append('foto_masuk', this.formEkstensi.foto);
            formData.append('latitude', this.gpsCoords.latitude);
            formData.append('longitude', this.gpsCoords.longitude);
            formData.append('accuracy', Math.round(this.gpsCoords.accuracy || 15));

            const json = await this.safeFetch('{{ route('app.api.lembur.ekstensi', [], false) }}', {
                method: 'POST',
                body: formData
            }, 25000);
            this.submittingEkstensi = false;

            if (json && json.success) {
                this.showToast('Pengajuan lembur ekstensi berhasil dikirim!');
                this.showEkstensiModal = false;
                this.stopGpsWatch();
                this.fetchLemburData();
            } else {
                this.showToast((json && json.message) || 'Gagal mengajukan lembur.', 'error');
            }
        },

        openOnCallModal() {
            this.isOnCallClockOut = false;
            this.formOnCall = { keterangan: '', foto: null };
            this.showOnCallModal = true;
            this.startGpsWatch(false);
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        openOnCallClockOut() {
            this.isOnCallClockOut = true;
            this.formOnCall = { keterangan: 'Selesai tugas On-Call', foto: null };
            this.showOnCallModal = true;
            this.startGpsWatch(false);
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        async handleOnCallFoto(e) {
            const rawFile = e.target.files[0];
            if (!rawFile) return;
            try {
                const compressed = await this.compressImageFile(rawFile, 720, 0.72);
                this.formOnCall.foto = compressed.file;
            } catch (_) {
                this.formOnCall.foto = rawFile;
            }
        },

        async submitOnCall() {
            if (!this.formOnCall.keterangan || !this.formOnCall.foto) {
                this.showToast('Keterangan dan foto bukti wajib diisi.', 'error');
                return;
            }
            if (this.gpsCoords.latitude === 0 && this.gpsCoords.longitude === 0) {
                this.showToast('Menunggu koordinat GPS iPhone. Pastikan izin lokasi aktif.', 'error');
                this.startGpsWatch(true);
                return;
            }

            this.submittingOnCall = true;
            const formData = new FormData();
            formData.append('keterangan', this.formOnCall.keterangan);
            formData.append('latitude', this.gpsCoords.latitude);
            formData.append('longitude', this.gpsCoords.longitude);
            formData.append('accuracy', Math.round(this.gpsCoords.accuracy || 15));

            const url = this.isOnCallClockOut
                ? '{{ route('app.api.lembur.oncall-keluar', [], false) }}'
                : '{{ route('app.api.lembur.oncall-masuk', [], false) }}';

            if (this.isOnCallClockOut) {
                formData.append('foto_keluar', this.formOnCall.foto);
            } else {
                formData.append('foto_masuk', this.formOnCall.foto);
            }

            const json = await this.safeFetch(url, { method: 'POST', body: formData }, 25000);
            this.submittingOnCall = false;

            if (json && json.success) {
                this.showToast(json.message || 'Sesi On-Call berhasil diproses!');
                this.showOnCallModal = false;
                this.stopGpsWatch();
                this.fetchTodayStatus();
                this.fetchLemburData();
            } else {
                this.showToast((json && json.message) || 'Gagal memproses On-Call.', 'error');
            }
        },

        openAmbilJamModal() {
            this.formAmbilJam = { tanggal_rencana: '', durasi_menit: 60, alasan: '' };
            this.showAmbilJamModal = true;
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        async submitAmbilJam() {
            if (!this.formAmbilJam.tanggal_rencana || !this.formAmbilJam.alasan) {
                this.showToast('Tanggal dan alasan permohonan wajib diisi.', 'error');
                return;
            }

            this.submittingAmbilJam = true;
            const json = await this.safeFetch('{{ route('app.api.lembur.ambil-jam', [], false) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.formAmbilJam)
            });
            this.submittingAmbilJam = false;

            if (json && json.success) {
                this.showToast('Permohonan ambil saldo jam berhasil dikirim!');
                this.showAmbilJamModal = false;
                this.fetchLemburData();
            } else {
                this.showToast((json && json.message) || 'Gagal mengajukan ambil jam.', 'error');
            }
        },

        // ===================================================================
        // VALIDASI ATASAN ACTION
        // ===================================================================
        async setujuiLembur(id) {
            const url = '{{ route('app.api.validasi.proses', ['id' => '__ID__'], false) }}'.replace('__ID__', id);
            const json = await this.safeFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi: 'setujui' })
            });
            if (json && json.success) {
                this.showToast('Lembur berhasil disetujui!');
                this.fetchValidasiList();
            } else {
                this.showToast((json && json.message) || 'Gagal memproses persetujuan.', 'error');
            }
        },

        async tolakLembur(id) {
            const catatan = prompt('Masukkan alasan penolakan:');
            if (!catatan) return;

            const url = '{{ route('app.api.validasi.proses', ['id' => '__ID__'], false) }}'.replace('__ID__', id);
            const json = await this.safeFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi: 'tolak', catatan: catatan })
            });
            if (json && json.success) {
                this.showToast('Lembur telah ditolak.');
                this.fetchValidasiList();
            } else {
                this.showToast((json && json.message) || 'Gagal memproses penolakan.', 'error');
            }
        },

        async setujuiAmbilJam(id) {
            const url = '{{ route('app.api.validasi.ambil-jam', ['id' => '__ID__'], false) }}'.replace('__ID__', id);
            const json = await this.safeFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi: 'setujui' })
            });
            if (json && json.success) {
                this.showToast('Pengambilan jam disetujui!');
                this.fetchValidasiList();
            } else {
                this.showToast((json && json.message) || 'Gagal memproses persetujuan.', 'error');
            }
        },

        async tolakAmbilJam(id) {
            const catatan = prompt('Masukkan alasan penolakan:');
            if (!catatan) return;

            const url = '{{ route('app.api.validasi.ambil-jam', ['id' => '__ID__'], false) }}'.replace('__ID__', id);
            const json = await this.safeFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aksi: 'tolak', catatan: catatan })
            });
            if (json && json.success) {
                this.showToast('Pengambilan jam ditolak.');
                this.fetchValidasiList();
            } else {
                this.showToast((json && json.message) || 'Gagal memproses penolakan.', 'error');
            }
        },

        // ===================================================================
        // PROFIL: UPLOAD FOTO & UBAH PASSWORD
        // ===================================================================
        openUploadFotoModal() {
            this.fotoProfilPreview = null;
            this.fotoProfilFile = null;
            this.showUploadFotoModal = true;
            if (window.lucide) setTimeout(() => lucide.createIcons(), 30);
        },

        async handleUploadFotoSelect(e) {
            const rawFile = e.target.files[0];
            e.target.value = '';
            if (!rawFile) return;
            try {
                const compressed = await this.compressImageFile(rawFile, 600, 0.75);
                this.fotoProfilPreview = compressed.dataUrl;
                this.fotoProfilFile = compressed.file;
            } catch (_) {
                this.fotoProfilFile = rawFile;
                const reader = new FileReader();
                reader.onload = (ev) => { this.fotoProfilPreview = ev.target.result; };
                reader.readAsDataURL(rawFile);
            }
        },

        async submitUploadFoto() {
            if (!this.fotoProfilFile) {
                this.showToast('Silakan pilih foto terlebih dahulu.', 'error');
                return;
            }
            this.submittingFotoProfil = true;
            const formData = new FormData();
            formData.append('foto', this.fotoProfilFile);

            const json = await this.safeFetch('{{ route('app.api.profil.foto', [], false) }}', {
                method: 'POST',
                body: formData
            }, 25000);
            this.submittingFotoProfil = false;

            if (json && json.success) {
                if (json.data?.foto_profil) {
                    this.profilePhotoUrl = '/storage/' + json.data.foto_profil + '?v=' + Date.now();
                }
                this.showToast('Foto profil berhasil diperbarui!');
                this.showUploadFotoModal = false;
            } else {
                this.showToast((json && json.message) || 'Gagal mengunggah foto profil.', 'error');
            }
        },

        async submitUbahPassword() {
            if (!this.formPassword.current_password || !this.formPassword.new_password) {
                this.showToast('Lengkapi semua kolom kata sandi.', 'error');
                return;
            }
            if (this.formPassword.new_password !== this.formPassword.new_password_confirmation) {
                this.showToast('Konfirmasi kata sandi baru tidak cocok.', 'error');
                return;
            }

            const json = await this.safeFetch('{{ route('app.api.profil.password', [], false) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.formPassword)
            });

            if (json && json.success) {
                this.showToast('Kata sandi berhasil diperbarui!');
                this.showUbahPasswordModal = false;
                this.formPassword = { current_password: '', new_password: '', new_password_confirmation: '' };
            } else {
                this.showToast((json && json.message) || 'Gagal mengubah kata sandi.', 'error');
            }
        }
    };
}
</script>
@endpush
