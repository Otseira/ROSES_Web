@extends('layouts.app')
@section('title', 'Live Monitoring Presensi')
@section('page_title', 'Live Monitoring')

@section('content')
<div class="space-y-6">

    {{-- HEADER BAR: Title, Live Clock, and Auto-Refresh --}}
    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h3 class="text-xl font-black text-slate-800">Live Monitoring Presensi</h3>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        LIVE AKTUAL
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-1">
                    Pantau kehadiran, keterlambatan, sesi dinas aktif di RS, dan panggilan on-call secara real-time.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Live Digital Clock --}}
                <div class="bg-slate-900 text-white px-4 py-2.5 rounded-2xl flex items-center gap-3 shadow-md">
                    <i data-lucide="clock" class="w-4 h-4 text-emerald-400"></i>
                    <div>
                        <div id="liveClock" class="font-mono font-black text-sm tracking-widest text-emerald-400 leading-none">--:--:-- WIB</div>
                        <div class="text-[10px] text-slate-400 mt-0.5 leading-none">{{ \Carbon\Carbon::parse($tanggalPilihan)->translatedFormat('l, d F Y') }}</div>
                    </div>
                </div>

                {{-- Auto Refresh Toggle & Button --}}
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-2xl p-1.5">
                    <button type="button" id="btnToggleAutoRefresh" onclick="toggleAutoRefresh()"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 bg-emerald-500 text-white shadow-sm">
                        <i data-lucide="radio" class="w-3.5 h-3.5"></i>
                        <span id="autoRefreshText">Auto (30s)</span>
                    </button>
                    <button type="button" onclick="location.reload()"
                        class="p-1.5 hover:bg-slate-200 text-slate-600 rounded-xl transition-all" title="Refresh Sekarang">
                        <i data-lucide="rotate-cw" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- FILTER FORM --}}
        <form method="GET" action="{{ route('monitoring.index') }}" class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            {{-- Tanggal --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Tanggal</label>
                <input type="date" name="tanggal" value="{{ $tanggalPilihan }}"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
            </div>

            {{-- Cari Nama / NIK --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Cari Pegawai</label>
                <input type="text" name="q" value="{{ $searchQuery }}" placeholder="Nama / NIK..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
            </div>

            {{-- Unit Kerja --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Unit Kerja</label>
                <select name="unit_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
                    <option value="">Semua Unit</option>
                    @foreach($units as $u)
                        <option value="{{ $u->id }}" @selected($unitFilter == $u->id)>{{ $u->nama_unit }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Shift --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Shift</label>
                <select name="shift_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
                    <option value="">Semua Shift</option>
                    @foreach($shifts as $s)
                        <option value="{{ $s->id }}" @selected($shiftFilter == $s->id)>{{ $s->nama_shift }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status Kehadiran --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Status Kehadiran</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
                    <option value="">Semua Status</option>
                    <option value="tepat_waktu" @selected($statusFilter == 'tepat_waktu')>Hadir Tepat Waktu</option>
                    <option value="terlambat" @selected($statusFilter == 'terlambat')>Hadir Terlambat</option>
                    <option value="sedang_dinas" @selected($statusFilter == 'sedang_dinas')>Sedang Bertugas (Di RS)</option>
                    <option value="sudah_pulang" @selected($statusFilter == 'sudah_pulang')>Sudah Pulang</option>
                    <option value="belum_hadir" @selected($statusFilter == 'belum_hadir')>Belum Hadir</option>
                    <option value="ganti_jam" @selected($statusFilter == 'ganti_jam')>Dispensasi Ganti Jam</option>
                </select>
            </div>

            {{-- Tombol Filter --}}
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <i data-lucide="filter" class="w-4 h-4"></i> Filter
                </button>
                <a href="{{ route('monitoring.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-bold transition-all flex items-center justify-center" title="Reset Filter">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    {{-- STATISTIK REAL-TIME CARDS (8 Metrik Lengkap) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
        {{-- Total Terjadwal --}}
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Terjadwal</span>
                <i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i>
            </div>
            <div class="text-2xl font-black text-slate-800 mt-2">{{ $totalJadwal }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Pegawai dinas</div>
        </div>

        {{-- Hadir Tepat Waktu --}}
        <div class="bg-white rounded-2xl p-4 border border-emerald-100 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Tepat Waktu</span>
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i>
            </div>
            <div class="text-2xl font-black text-emerald-600 mt-2">{{ $tepatCount }}</div>
            <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">{{ $totalJadwal > 0 ? round(($tepatCount/$totalJadwal)*100) : 0 }}% disiplin</div>
        </div>

        {{-- Terlambat --}}
        <div class="bg-white rounded-2xl p-4 border border-amber-100 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-600">Terlambat</span>
                <i data-lucide="clock-alert" class="w-4 h-4 text-amber-500"></i>
            </div>
            <div class="text-2xl font-black text-amber-600 mt-2">{{ $telatCount }}</div>
            <div class="text-[10px] text-amber-600 font-semibold mt-0.5">Lewat toleransi</div>
        </div>

        {{-- Sedang Bertugas (Aktif di RS) --}}
        <div class="bg-gradient-to-br from-sky-500 to-sky-600 text-white rounded-2xl p-4 shadow-md shadow-sky-500/20 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-sky-100">Sedang Di RS</span>
                <i data-lucide="activity" class="w-4 h-4 text-white"></i>
            </div>
            <div class="text-2xl font-black text-white mt-2">{{ $sedangDinasCount }}</div>
            <div class="text-[10px] text-sky-100 mt-0.5">Aktif di instalasi</div>
        </div>

        {{-- Sudah Pulang --}}
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600">Sudah Pulang</span>
                <i data-lucide="log-out" class="w-4 h-4 text-indigo-500"></i>
            </div>
            <div class="text-2xl font-black text-indigo-600 mt-2">{{ $sudahPulangCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Selesai tugas</div>
        </div>

        {{-- Belum Hadir --}}
        <div class="bg-white rounded-2xl p-4 border border-rose-100 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-500">Belum Hadir</span>
                <i data-lucide="user-x" class="w-4 h-4 text-rose-400"></i>
            </div>
            <div class="text-2xl font-black text-rose-500 mt-2">{{ $belumHadirCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Belum clock-in</div>
        </div>

        {{-- On-Call Darurat Aktif --}}
        <div class="bg-white rounded-2xl p-4 border border-purple-100 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-purple-600">On-Call Aktif</span>
                <i data-lucide="phone-call" class="w-4 h-4 text-purple-500"></i>
            </div>
            <div class="text-2xl font-black text-purple-600 mt-2">{{ count($onCallAktifList) }}</div>
            <div class="text-[10px] text-purple-600 font-semibold mt-0.5">Panggilan aktif</div>
        </div>

        {{-- Dispensasi Ganti Jam --}}
        <div class="bg-white rounded-2xl p-4 border border-violet-100 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-violet-600">Ganti Jam</span>
                <i data-lucide="hourglass" class="w-4 h-4 text-violet-500"></i>
            </div>
            <div class="text-2xl font-black text-violet-600 mt-2">{{ $gantiJamCount }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Kompensasi waktu</div>
        </div>
    </div>

    {{-- ALERT BANNER: ON-CALL DARURAT SEDANG BERJALAN --}}
    @if(count($onCallAktifList) > 0)
    <div class="bg-gradient-to-r from-purple-900 to-indigo-900 rounded-2xl p-5 text-white shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-purple-500/30 border border-purple-400/40 rounded-2xl flex items-center justify-center font-bold text-xl animate-pulse">
                🚨
            </div>
            <div>
                <h4 class="font-extrabold text-base text-purple-200">Perhatian: Sedang Berlangsung {{ count($onCallAktifList) }} Tugas On-Call Darurat</h4>
                <p class="text-xs text-purple-300 mt-0.5">Pegawai berikut sedang merespons panggilan darurat di rumah sakit saat ini:</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @foreach($onCallAktifList as $oc)
            <div class="bg-white/10 border border-white/20 rounded-xl px-3 py-1.5 text-xs flex items-center gap-2">
                <span class="font-bold text-white">{{ $oc->user?->name ?? 'Pegawai' }}</span>
                <span class="text-[10px] text-purple-300">({{ $oc->user?->unitKerja?->nama_unit ?? '-' }})</span>
                <span class="px-1.5 py-0.5 rounded bg-emerald-400/20 text-emerald-300 font-mono text-[10px]">
                    Mulai {{ optional($oc->waktu_mulai_lembur)->format('H:i') }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- TABEL LIVE MONITORING PRESENSI --}}
    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h4 class="text-lg font-black text-slate-800">Daftar Kehadiran Aktual</h4>
                <p class="text-xs text-slate-400 mt-0.5">Menampilkan {{ count($rows) }} pegawai terjadwal.</p>
            </div>
            <div class="text-xs text-slate-400 flex items-center gap-1.5 font-medium">
                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i>
                <span>Terverifikasi Geofence GPS & Face Selfie</span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                        <th class="px-4 py-3.5 w-12 text-center">No</th>
                        <th class="px-4 py-3.5">Pegawai</th>
                        <th class="px-4 py-3.5">Unit Kerja</th>
                        <th class="px-4 py-3.5">Jadwal Shift</th>
                        <th class="px-4 py-3.5">Absen Masuk</th>
                        <th class="px-4 py-3.5">Absen Pulang</th>
                        <th class="px-4 py-3.5 text-center">Durasi Kerja</th>
                        <th class="px-4 py-3.5 text-center">Status & Dispensasi</th>
                        <th class="px-4 py-3.5 text-center w-24">Detail / Foto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($rows as $index => $row)
                    <tr class="hover:bg-slate-50/60 transition-all">
                        {{-- No --}}
                        <td class="px-4 py-3.5 text-slate-400 text-center font-bold">{{ $index + 1 }}</td>

                        {{-- Pegawai --}}
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                @if($row['foto_profil'])
                                <img src="{{ $row['foto_profil'] }}" alt="{{ $row['nama'] }}" class="w-9 h-9 rounded-xl object-cover border border-slate-200">
                                @else
                                <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-black text-xs">
                                    {{ strtoupper(substr($row['nama'], 0, 1)) }}
                                </div>
                                @endif
                                <div>
                                    <span class="font-extrabold text-slate-800 block leading-tight">{{ $row['nama'] }}</span>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ $row['nik'] }}</span>
                                </div>
                            </div>
                        </td>

                        {{-- Unit Kerja --}}
                        <td class="px-4 py-3.5">
                            <span class="font-bold text-slate-700 block leading-tight">{{ $row['unit'] }}</span>
                            @if($row['jenis_lembur_unit'] === 'ganti_jam')
                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                Sistem Ganti Jam
                            </span>
                            @else
                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Lembur Dibayar
                            </span>
                            @endif
                        </td>

                        {{-- Shift & Jadwal --}}
                        <td class="px-4 py-3.5">
                            <span class="font-extrabold text-slate-800 block">{{ $row['shift_nama'] }}</span>
                            <span class="text-xs text-slate-500 font-mono">{{ $row['jam_masuk_jadwal'] }} - {{ $row['jam_pulang_jadwal'] }}</span>
                        </td>

                        {{-- Absen Masuk --}}
                        <td class="px-4 py-3.5">
                            @if($row['is_hadir'])
                                <div class="flex items-center gap-2">
                                    @if($row['foto_masuk_url'])
                                    <img src="{{ $row['foto_masuk_url'] }}" onclick="openImageModal('{{ $row['foto_masuk_url'] }}', 'Foto Masuk: {{ $row['nama'] }}')"
                                        class="w-8 h-8 rounded-lg object-cover cursor-pointer hover:opacity-80 transition-all border border-emerald-200 shadow-sm" title="Klik untuk perbesar">
                                    @endif
                                    <div>
                                        <span class="font-black text-slate-800 font-mono">{{ $row['waktu_masuk_fmt'] }}</span>
                                        <span class="block text-[10px] text-slate-400">Jarak: {{ $row['jarak_masuk'] }}</span>
                                    </div>
                                </div>
                            @else
                                <span class="text-slate-400 font-semibold text-xs">— Belum Absen —</span>
                            @endif
                        </td>

                        {{-- Absen Pulang --}}
                        <td class="px-4 py-3.5">
                            @if($row['is_sudah_pulang'])
                                <div class="flex items-center gap-2">
                                    @if($row['foto_pulang_url'])
                                    <img src="{{ $row['foto_pulang_url'] }}" onclick="openImageModal('{{ $row['foto_pulang_url'] }}', 'Foto Pulang: {{ $row['nama'] }}')"
                                        class="w-8 h-8 rounded-lg object-cover cursor-pointer hover:opacity-80 transition-all border border-indigo-200 shadow-sm" title="Klik untuk perbesar">
                                    @endif
                                    <div>
                                        <span class="font-black text-indigo-700 font-mono">{{ $row['waktu_pulang_fmt'] }}</span>
                                        <span class="block text-[10px] text-slate-400">Selesai</span>
                                    </div>
                                </div>
                            @elseif($row['is_sedang_dinas'])
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-black bg-sky-50 text-sky-700 border border-sky-200 animate-pulse">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                    Sedang Bertugas
                                </span>
                            @else
                                <span class="text-slate-400 font-semibold text-xs">—</span>
                            @endif
                        </td>

                        {{-- Durasi Kerja --}}
                        <td class="px-4 py-3.5 text-center font-mono font-bold text-slate-700">
                            {{ $row['durasi_kerja'] }}
                        </td>

                        {{-- Status & Dispensasi --}}
                        <td class="px-4 py-3.5 text-center">
                            @if(!$row['is_hadir'])
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-600 border border-rose-200">
                                    Belum Hadir
                                </span>
                            @elseif($row['is_terlambat'])
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    Terlambat {{ $row['menit_terlambat'] }} mnt
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Tepat Waktu
                                </span>
                            @endif

                            {{-- Badge Dispensasi Ganti Jam (jika ada) --}}
                            @if($row['ganti_jam'])
                            <span class="block mt-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-violet-100 text-violet-800 border border-violet-200"
                                title="{{ $row['ganti_jam']['keterangan'] }}">
                                ⏱️ {{ $row['ganti_jam']['tipe_label'] }} ({{ $row['ganti_jam']['durasi_menit'] }}m)
                            </span>
                            @endif
                        </td>

                        {{-- Detail / Modal --}}
                        <td class="px-4 py-3.5 text-center">
                            @if($row['lat_masuk'] && $row['lng_masuk'])
                            <a href="https://www.google.com/maps?q={{ $row['lat_masuk'] }},{{ $row['lng_masuk'] }}" target="_blank"
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-all" title="Buka Koordinat GPS">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-rose-500"></i> Peta
                            </a>
                            @else
                            <span class="text-slate-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-slate-400 font-bold">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i data-lucide="inbox" class="w-10 h-10 text-slate-300"></i>
                                <span>Tidak ada jadwal dinas atau kehadiran yang cocok dengan filter yang dipilih.</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL PREVIEW FOTO SELFIE / BUKTI --}}
<div id="imagePreviewModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h5 id="imageModalTitle" class="text-sm font-extrabold text-slate-800">Foto Bukti Presensi</h5>
            <button type="button" onclick="closeImageModal()" class="p-1 hover:bg-slate-100 text-slate-400 hover:text-slate-600 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="mt-4 flex justify-center">
            <img id="imageModalSrc" src="" alt="Bukti Presensi" class="rounded-2xl max-h-96 w-full object-cover border border-slate-200 shadow-md">
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" onclick="closeImageModal()" class="px-5 py-2 rounded-xl text-xs font-bold bg-slate-800 text-white hover:bg-slate-900 transition-all">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
    // 1. LIVE DIGITAL CLOCK
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const el = document.getElementById('liveClock');
        if (el) {
            el.innerText = `${hours}:${minutes}:${seconds} WIB`;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. AUTO-REFRESH TIMER (30 SECONDS)
    let autoRefreshActive = true;
    let refreshSeconds = 30;
    let countdownInterval;

    function startAutoRefresh() {
        countdownInterval = setInterval(() => {
            if (!autoRefreshActive) return;
            refreshSeconds--;
            const textEl = document.getElementById('autoRefreshText');
            if (textEl) {
                textEl.innerText = `Auto (${refreshSeconds}s)`;
            }
            if (refreshSeconds <= 0) {
                location.reload();
            }
        }, 1000);
    }

    function toggleAutoRefresh() {
        autoRefreshActive = !autoRefreshActive;
        const btn = document.getElementById('btnToggleAutoRefresh');
        const text = document.getElementById('autoRefreshText');
        if (autoRefreshActive) {
            refreshSeconds = 30;
            btn.className = "px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 bg-emerald-500 text-white shadow-sm";
            text.innerText = `Auto (30s)`;
        } else {
            btn.className = "px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 bg-slate-200 text-slate-600";
            text.innerText = "Auto (Off)";
        }
    }

    startAutoRefresh();

    // 3. IMAGE PREVIEW MODAL
    function openImageModal(url, title) {
        document.getElementById('imageModalSrc').src = url;
        document.getElementById('imageModalTitle').innerText = title;
        document.getElementById('imagePreviewModal').classList.remove('hidden');
    }

    function closeImageModal() {
        document.getElementById('imagePreviewModal').classList.add('hidden');
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
@endsection
