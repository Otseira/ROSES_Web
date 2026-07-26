@extends('layouts.app')
@section('title', 'Rekapitulasi Laporan')
@section('page_title', 'Rekap Absensi & Payroll')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">

    {{-- ===== HEADER (TIDAK DIUBAH) ===== --}}
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-10 gap-6">
        <!-- <div>
            <h3 class="text-2xl font-extrabold text-slate-800 tracking-tight">Cut-Off: {{
                \Carbon\Carbon::parse($startDate)->translatedFormat('d M') }} - {{
                \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y') }}</h3>
            <div class="flex flex-wrap gap-3 mt-3">
                <div class="flex items-center gap-2 bg-rose-50 border border-rose-100 px-4 py-2 rounded-xl">
                    <i data-lucide="trending-down" class="w-4 h-4 text-rose-500"></i>
                    <span class="text-xs font-bold text-rose-600">Denda: Rp {{ number_format($ratePotongan, 0, ',', '.')
                        }}/mnt</span>
                </div>
                <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-100 px-4 py-2 rounded-xl">
                    <i data-lucide="trending-up" class="w-4 h-4 text-emerald-500"></i>
                    <span class="text-xs font-bold text-emerald-600">Lembur: Rp {{ number_format($rateLembur, 0, ',',
                        '.') }}/jam</span>
                </div>
            </div>
        </div> -->

        <form method="GET" action="/laporan-payroll" class="flex gap-3 w-full xl:w-auto">
            <select name="bulan"
                class="bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 w-full xl:w-auto">
                @for($i = 1; $i <= 12; $i++) <option value="{{ $i }}" {{ $bulan==$i ? 'selected' : '' }}>{{
                    \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option>
                    @endfor
            </select>
            <select name="tahun"
                class="bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 w-full xl:w-auto">
                @for($i = date('Y') - 1; $i <= date('Y') + 1; $i++) <option value="{{ $i }}" {{ $tahun==$i ? 'selected'
                    : '' }}>{{ $i }}</option>
                    @endfor
            </select>
            <button type="submit"
                class="bg-primary hover:bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="search" class="w-4 h-4"></i>
            </button>
        </form>

        <div class="flex gap-2">
            <a href="/laporan-payroll/excel?bulan={{ $bulan }}&tahun={{ $tahun }}"
                class="bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 px-4 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center gap-2">
                <i data-lucide="sheet" class="w-4 h-4"></i> <span class="hidden sm:inline">Excel</span>
            </a>
            <a href="/laporan-payroll/pdf?bulan={{ $bulan }}&tahun={{ $tahun }}" target="_blank"
                class="bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white border border-rose-200 px-4 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4"></i> <span class="hidden sm:inline">Cetak PDF</span>
            </a>
            <a href="{{ route('laporan.lembur') }}"
                style="display:inline-block;padding:9px 16px;background:#166534;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px">
                📋 Rekap Lembur / On‑Call
            </a>
        </div>
    </div>

    {{-- ===== TABEL (KOLOM BARU) ===== --}}
    @php
    $coord = fn($v) => $v === null ? '-' : number_format((float)$v, 4, '.', '');
    $meter = fn($v) => $v === null ? '-' : number_format((float)$v, 0, ',', '.');

    // ✅ Jarak tampil bersih dengan satuan meter (urut: Masuk / Pulang)
    $radius = function ($m, $p) {
    $f = fn($v) => ($v === null || $v === '') ? null : number_format((float)$v, 0, ',', '.');
    $a = $f($m); $b = $f($p);
    if ($a !== null && $b !== null) return $a.' m / '.$b.' m';
    if ($a !== null) return $a.' m';
    if ($b !== null) return $b.' m';
    return '-';
    };
    @endphp

    <div class="mt-12">
        <h4 class="text-lg font-bold text-slate-800 mb-4">Detail Log Absensi Harian</h4>
        <div class="overflow-x-auto border rounded-xl">
            <table class="min-w-[1080px] w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left whitespace-nowrap">Nama</th>
                        <th class="px-4 py-3 whitespace-nowrap">Jam Masuk</th>
                        <th class="px-4 py-3 whitespace-nowrap">Jam Keluar</th>
                        <th class="px-4 py-3 whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 whitespace-nowrap">Radius (m)</th>
                        <th class="px-4 py-3 whitespace-nowrap">Latitude</th>
                        <th class="px-4 py-3 whitespace-nowrap">Longitude</th>
                        <th class="px-4 py-3 whitespace-nowrap">Foto Masuk</th>
                        <th class="px-4 py-3 whitespace-nowrap">Foto Keluar</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-sm">
                    @forelse($absensiDetail as $log)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-bold text-slate-800 whitespace-nowrap">{{ $log->roster?->user?->name
                            ?? '-' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">{{ $log->waktu_masuk ?
                            \Carbon\Carbon::parse($log->waktu_masuk)->format('H:i') : '-' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">{{ $log->waktu_pulang ?
                            \Carbon\Carbon::parse($log->waktu_pulang)->format('H:i') : '-' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">{{ $log->roster?->tanggal_dinas ?
                            \Carbon\Carbon::parse($log->roster->tanggal_dinas)->translatedFormat('d M Y') : '-' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap font-medium text-slate-700">
                            {{ $radius($log->jarak_masuk, $log->jarak_pulang) }}
                        </td>
                        <td class="px-4 py-3 text-xs whitespace-nowrap font-mono">
                            <div><span class="text-slate-400">M:</span> {{ $coord($log->latitude_masuk) }}</div>
                            <div><span class="text-slate-400">P:</span> {{ $coord($log->latitude_pulang) }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs whitespace-nowrap font-mono">
                            <div><span class="text-slate-400">M:</span> {{ $coord($log->longitude_masuk) }}</div>
                            <div><span class="text-slate-400">P:</span> {{ $coord($log->longitude_pulang) }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($log->foto_masuk)
                            <a href="{{ asset('storage/'.$log->foto_masuk) }}" target="_blank">
                                <img src="{{ asset('storage/'.$log->foto_masuk) }}"
                                    class="h-10 w-10 rounded-lg object-cover border border-slate-200 hover:scale-105 transition"
                                    title="Foto Masuk">
                            </a>
                            @else <span class="text-slate-300">-</span> @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($log->foto_pulang)
                            <a href="{{ asset('storage/'.$log->foto_pulang) }}" target="_blank">
                                <img src="{{ asset('storage/'.$log->foto_pulang) }}"
                                    class="h-10 w-10 rounded-lg object-cover border border-slate-200 hover:scale-105 transition"
                                    title="Foto Keluar">
                            </a>
                            @else <span class="text-slate-300">-</span> @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-slate-400">Belum ada data absensi untuk
                            periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection