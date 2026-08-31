@extends('layouts.app')
@section('title', 'Rekap Laporan')
@section('page_title', 'Rekap Absensi')

@section('content')
<div class="space-y-6 mb-10">

    {{-- ===== KARTU STATISTIK (TETAP GLOBAL, TIDAK DIKELOMPOKKAN) ===== --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
        <div class="bg-white rounded-2xl border border-slate-100/60 shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-slate-100 text-slate-600 rounded-xl flex items-center justify-center"><i
                        data-lucide="users" class="w-5 h-5"></i></div>
                <div>
                    <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-400">Total Absensi</p>
                    <p class="text-xl font-extrabold text-slate-800">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100/60 shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center"><i
                        data-lucide="check-circle" class="w-5 h-5"></i></div>
                <div>
                    <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-400">Tepat Waktu</p>
                    <p class="text-xl font-extrabold text-emerald-600">{{ $stats['tepat'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100/60 shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center"><i
                        data-lucide="alarm-clock" class="w-5 h-5"></i></div>
                <div>
                    <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-400">Terlambat</p>
                    <p class="text-xl font-extrabold text-rose-600">{{ $stats['terlambat'] }} <span
                            class="text-xs font-bold text-slate-400">({{ $stats['menit_late'] }} mnt)</span></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100/60 shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center"><i
                        data-lucide="swap-horizontal-circle" class="w-5 h-5"></i></div>
                <div>
                    <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-400">Luar Jadwal</p>
                    <p class="text-xl font-extrabold text-amber-600">{{ $stats['luar'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100/60 shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center"><i
                        data-lucide="timer" class="w-5 h-5"></i></div>
                <div>
                    <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-400">Jam Lembur</p>
                    <p class="text-xl font-extrabold text-blue-600">{{ number_format($stats['jam_lembur'], 1) }} <span
                            class="text-xs font-bold text-slate-400">jam</span></p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== FILTER & EXPORT (TETAP SAMA) ===== --}}
    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
            <form method="GET" action="/laporan-payroll" class="flex flex-wrap items-center gap-3">
                <select name="bulan"
                    class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none">
                    @for ($m = 1; $m <= 12; $m++) <option value="{{ $m }}" {{ $bulan==$m ? 'selected' : '' }}>{{
                        now()->month($m)->translatedFormat('F') }}</option>
                        @endfor
                </select>
                <select name="tahun"
                    class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none">
                    @for ($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ $tahun==$y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
                <select name="unit"
                    class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none max-w-[220px]">
                    <option value="">🏢 Semua Unit</option>
                    @foreach($units as $u)
                    <option value="{{ $u->id }}" {{ $unit==$u->id ? 'selected' : '' }}>{{ $u->nama_unit }}</option>
                    @endforeach
                </select>
                <button type="submit"
                    class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl transition-all">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
            </form>

            <div class="flex flex-wrap gap-3">
                <a href="/laporan-payroll/excel?bulan={{ $bulan }}&tahun={{ $tahun }}&unit={{ $unit }}"
                    class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all">
                    <i data-lucide="sheet" class="w-4 h-4"></i> Excel
                </a>
                <a href="/laporan-payroll/pdf?bulan={{ $bulan }}&tahun={{ $tahun }}&unit={{ $unit }}" target="_blank"
                    class="bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all">
                    <i data-lucide="file-text" class="w-4 h-4"></i> Cetak PDF
                </a>
            </div>
        </div>

        <h4 class="font-extrabold text-slate-800 mb-6">
            Detail Log Absensi — {{ now()->month($bulan)->translatedFormat('F Y') }}
            @if($unit) <span class="text-primary">• {{ $units->firstWhere('id', $unit)?->nama_unit }}</span> @endif
        </h4>

        {{-- ===== BARU: LOOP PER KELOMPOK UNIT ===== --}}
        @forelse($logsGrouped as $namaUnit => $groupLogs)
        {{-- Header Unit --}}
        <div class="mb-3 mt-8 first:mt-0">
            <div class="bg-slate-800 text-white px-6 py-3 rounded-t-2xl flex items-center justify-between">
                <h5 class="text-sm font-extrabold uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="building-2" class="w-4 h-4"></i>
                    {{ $namaUnit }}
                </h5>
                <span class="bg-white/10 px-3 py-1 rounded-full text-xs font-bold">
                    {{ $groupLogs->count() }} absensi
                </span>
            </div>
        </div>

        {{-- Tabel per Unit --}}
        <div class="overflow-x-auto rounded-b-2xl border border-slate-100">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                        <th class="px-5 py-4">Nama</th>
                        <th class="px-5 py-4">Unit Kerja</th>
                        <th class="px-5 py-4">Tanggal</th>
                        <th class="px-5 py-4">Masuk</th>
                        <th class="px-5 py-4">Keluar</th>
                        <th class="px-5 py-4">Durasi</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4">Telat</th>
                        <th class="px-5 py-4">Jarak</th>
                        <th class="px-5 py-4">Foto</th>
                        <th class="px-5 py-4">Lembur (Menit)</th>
                        <th class="px-5 py-4">On-Call (Menit)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($groupLogs as $log)
                    @php
                    $nama = $log->user?->name ?? $log->roster?->user?->name ?? '-';
                    $unitNama = $log->user?->unitKerja?->nama_unit ?? $log->roster?->user?->unitKerja?->nama_unit ??
                    '-';
                    $key = ($log->user_id ?? $log->roster?->user_id) . '|' .
                    optional($log->waktu_masuk)->toDateString();
                    $items = $lemburs->get($key);
                    $mLembur = 0;
                    $mOncall = 0;
                    if ($items) {
                    foreach ($items as $l) {
                    $mnt = (float) ($l->total_jam_lembur ?? 0) * 60;
                    $norm = str_contains(strtolower(str_replace(['-', ' ', '_'], '', $l->jenis_lembur ?? '')),
                    'oncall');
                    $norm ? $mOncall += $mnt : $mLembur += $mnt;
                    }
                    }
                    $mLembur = (int) round($mLembur);
                    $mOncall = (int) round($mOncall);
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition-all">
                        <td class="px-5 py-4 text-sm font-bold text-slate-800 whitespace-nowrap">{{ $nama }}</td>
                        <td class="px-5 py-4 text-xs font-semibold text-slate-600">{{ $unitNama }}</td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap">{{
                            optional($log->waktu_masuk)->format('d M Y') ?? '-' }}</td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-600">{{
                            optional($log->waktu_masuk)->format('H:i') ?? '-' }}</td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-600">{{
                            optional($log->waktu_pulang)->format('H:i') ?? '-' }}</td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap">{{
                            $log->durasi_kerja ?? '-' }}</td>

                        <td class="px-5 py-4 whitespace-nowrap">
                            <span
                                class="px-2.5 py-1 rounded-full text-[10px] font-bold
                                    {{ $log->status_kehadiran == 'Tepat Waktu' ? 'bg-emerald-50 text-emerald-600' : ($log->status_kehadiran == 'Terlambat' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600') }}">
                                {{ $log->status_kehadiran }}
                            </span>
                        </td>

                        <td
                            class="px-5 py-4 text-sm font-semibold {{ ($log->menit_terlambat ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                            {{ ($log->menit_terlambat ?? 0) > 0 ? $log->menit_terlambat . ' mnt' : '—' }}
                        </td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap">{{ $log->jarak !==
                            null ? $log->jarak . ' m' : '-' }}</td>

                        <td class="px-5 py-4">
                            <div class="flex gap-1.5">
                                @if($log->foto_masuk)
                                <a href="{{ url('/storage/' . $log->foto_masuk) }}" target="_blank" title="Foto Masuk">
                                    <img src="{{ url('/storage/' . $log->foto_masuk) }}"
                                        class="w-9 h-9 rounded-md object-cover border border-emerald-200">
                                </a>
                                @endif
                                @if($log->foto_pulang)
                                <a href="{{ url('/storage/' . $log->foto_pulang) }}" target="_blank"
                                    title="Foto Keluar">
                                    <img src="{{ url('/storage/' . $log->foto_pulang) }}"
                                        class="w-9 h-9 rounded-md object-cover border border-orange-200">
                                </a>
                                @endif
                                @if(!$log->foto_masuk && !$log->foto_pulang) <span
                                    class="text-slate-400 text-sm">—</span> @endif
                            </div>
                        </td>
                        <td
                            class="px-5 py-4 text-sm font-bold {{ $mLembur > 0 ? 'text-blue-700' : 'text-slate-400' }} whitespace-nowrap">
                            {{ $mLembur > 0 ? $mLembur . ' mnt' : '—' }}
                        </td>
                        <td
                            class="px-5 py-4 text-sm font-bold {{ $mOncall > 0 ? 'text-indigo-700' : 'text-slate-400' }} whitespace-nowrap">
                            {{ $mOncall > 0 ? $mOncall . ' mnt' : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="px-5 py-12 text-center text-sm text-slate-400 font-semibold">Belum ada
                            data absensi pada unit ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @empty
        <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center">
            <p class="text-sm text-slate-400 font-semibold">Belum ada data absensi pada periode ini.</p>
        </div>
        @endforelse
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection