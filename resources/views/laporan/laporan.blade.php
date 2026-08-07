@extends('layouts.app')
@section('title', 'Rekap Laporan')
@section('page_title', 'Rekap Absensi')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8 mb-10">

    {{-- Filter & Tombol Export --}}
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
        <form method="GET" action="/laporan-payroll" class="flex items-center gap-3">
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
            <button type="submit"
                class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl transition-all">
                <i data-lucide="search" class="w-4 h-4"></i>
            </button>
        </form>

        <div class="flex flex-wrap gap-3">
            <a href="/laporan-payroll/excel?bulan={{ $bulan }}&tahun={{ $tahun }}"
                class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all">
                <i data-lucide="sheet" class="w-4 h-4"></i> Excel
            </a>
            <a href="/laporan-payroll/pdf?bulan={{ $bulan }}&tahun={{ $tahun }}"
                class="bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all">
                <i data-lucide="file-text" class="w-4 h-4"></i> Cetak PDF
            </a>
        </div>
    </div>

    {{-- ===== TABEL ABSENSI ===== --}}
    <h4 class="font-extrabold text-slate-800 mb-4">Detail Log Absensi Harian — {{
        now()->month($bulan)->translatedFormat('F Y') }}</h4>
    <div class="overflow-x-auto rounded-2xl border border-slate-100 mb-10">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                    <th class="px-6 py-4">Nama</th>
                    <th class="px-6 py-4">Tanggal</th>
                    <th class="px-6 py-4">Jam Masuk</th>
                    <th class="px-6 py-4">Jam Keluar</th>
                    <th class="px-6 py-4">Jarak Absen (M / P)</th>
                    <th class="px-6 py-4">Foto Masuk</th>
                    <th class="px-6 py-4">Foto Keluar</th>
                    <th class="px-6 py-4">Lembur / On-Call</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                @php
                $nama = $log->user?->name ?? $log->roster?->user?->name ?? '-';
                $key = ($log->user_id ?? $log->roster?->user_id) . '|' . optional($log->waktu_masuk)->toDateString();
                $items = $lemburs->get($key);
                @endphp
                <tr class="hover:bg-slate-50/60 transition-all">
                    <td class="px-6 py-4 text-sm font-bold text-slate-800">{{ $nama }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">{{ optional($log->waktu_masuk)->format('d
                        M Y') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">{{
                        optional($log->waktu_masuk)->format('H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">{{
                        optional($log->waktu_pulang)->format('H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">
                        M: {{ $log->jarak_masuk !== null ? $log->jarak_masuk . ' m' : '-' }}<br>
                        P: {{ $log->jarak_pulang !== null ? $log->jarak_pulang . ' m' : '-' }}
                    </td>
                    <td class="px-6 py-4">
                        @if($log->foto_masuk)
                        <a href="{{ url('/storage/' . $log->foto_masuk) }}" target="_blank">
                            <img src="{{ url('/storage/' . $log->foto_masuk) }}"
                                class="w-12 h-12 rounded-lg object-cover border border-slate-200 hover:opacity-80">
                        </a>
                        @else <span class="text-slate-400 text-sm">—</span> @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($log->foto_pulang)
                        <a href="{{ url('/storage/' . $log->foto_pulang) }}" target="_blank">
                            <img src="{{ url('/storage/' . $log->foto_pulang) }}"
                                class="w-12 h-12 rounded-lg object-cover border border-slate-200 hover:opacity-80">
                        </a>
                        @else <span class="text-slate-400 text-sm">—</span> @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($items && $items->isNotEmpty())
                        @foreach($items as $l)
                        <span
                            class="inline-block px-2.5 py-1 mb-1 mr-1 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg text-[11px] font-bold">
                            ⏱ {{ $l->jenis_lembur }} • {{ number_format($l->total_jam_lembur ?? 0, 1) }} jam • {{
                            $l->status_validasi }}
                        </span>
                        @endforeach
                        @else
                        <span class="text-slate-400 text-sm">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-400 font-semibold">Belum ada data
                        absensi pada periode ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ===== TABEL LEMBUR / ON-CALL ===== --}}
    <h4 class="font-extrabold text-slate-800 mb-4">Rekap Lembur / On-Call — {{ now()->month($bulan)->translatedFormat('F
        Y') }}</h4>
    <div class="overflow-x-auto rounded-2xl border border-slate-100">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                    <th class="px-6 py-4">Nama</th>
                    <th class="px-6 py-4">Jenis</th>
                    <th class="px-6 py-4">Mulai</th>
                    <th class="px-6 py-4">Selesai</th>
                    <th class="px-6 py-4">Total Jam</th>
                    <th class="px-6 py-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($lemburs->flatten() as $l)
                <tr class="hover:bg-slate-50/60 transition-all">
                    <td class="px-6 py-4 text-sm font-bold text-slate-800">{{ $l->user?->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">{{ $l->jenis_lembur }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">{{
                        optional($l->waktu_mulai_lembur)->format('d M Y H:i') }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">{{
                        optional($l->waktu_selesai_lembur)->format('d M Y H:i') ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-600">{{ number_format($l->total_jam_lembur ??
                        0, 1) }} jam</td>
                    <td class="px-6 py-4">
                        <span
                            class="px-3 py-1 rounded-full text-[11px] font-bold
                            {{ $l->status_validasi == 'Disetujui' ? 'bg-emerald-50 text-emerald-600' : ($l->status_validasi == 'Ditolak' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600') }}">
                            {{ $l->status_validasi }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400 font-semibold">Tidak ada
                        lembur/on-call pada periode ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection