@extends('layouts.app')
@section('title', 'Rekapitulasi Laporan')
@section('page_title', 'Rekap Absensi & Payroll')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">

    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-10 gap-6">
        <div>
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
        </div>

        <form method="GET" action="/laporan-payroll" class="flex gap-3 w-full xl:w-auto">
            <select name="bulan"
                class="bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 w-full xl:w-auto">
                @for($i = 1; $i <= 12; $i++) <option value="{{ $i }}" {{ $bulan==$i ? 'selected' : '' }}>{{
                    \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option> @endfor
            </select>
            <select name="tahun"
                class="bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 w-full xl:w-auto">
                @for($i = date('Y') - 1; $i <= date('Y') + 1; $i++) <option value="{{ $i }}" {{ $tahun==$i ? 'selected'
                    : '' }}>{{ $i }}</option> @endfor
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
        </div>
    </div>

    <div class="mt-12">
    <h4 class="text-lg font-bold text-slate-800 mb-4">Detail Log Absensi Harian</h4>
    <div class="overflow-x-auto border rounded-xl">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500">
                <tr>
                    <th class="px-4 py-3">Pegawai</th>
                    <th class="px-4 py-3">Masuk (Jarak)</th>
                    <th class="px-4 py-3">Pulang (Jarak)</th>
                    <th class="px-4 py-3">Terlambat</th>
                    <th class="px-4 py-3">Bukti Foto</th>
                </tr>
            </thead>
            <tbody class="divide-y text-sm">
                @foreach($absensiDetail as $log)
                <tr>
                    <td class="px-4 py-3 font-bold">{{ $log->roster->user->name }}</td>
                    <td class="px-4 py-3">
                        {{ $log->waktu_masuk ? \Carbon\Carbon::parse($log->waktu_masuk)->format('H:i') : '-' }} 
                        <span class="text-[10px] text-slate-400">({{ $log->jarak_masuk }}m)</span>
                    </td>
                    <td class="px-4 py-3">
                        {{ $log->waktu_pulang ? \Carbon\Carbon::parse($log->waktu_pulang)->format('H:i') : '-' }}
                        <span class="text-[10px] text-slate-400">({{ $log->jarak_pulang ?? '-' }}m)</span>
                    </td>
                    <td class="px-4 py-3 text-rose-600">{{ $log->menit_terlambat }} mnt</td>
                    <td class="px-4 py-3 flex gap-2">
                        @if($log->foto_masuk) <a href="{{ asset('storage/'.$log->foto_masuk) }}" target="_blank" class="text-blue-500">Masuk</a> @endif
                        @if($log->foto_pulang) <a href="{{ asset('storage/'.$log->foto_pulang) }}" target="_blank" class="text-orange-500">Pulang</a> @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</div>
@endsection