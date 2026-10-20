@extends('layouts.app')
@section('title', 'Rekap Lembur & Bank Jam')
@section('page_title', 'Rekap Lembur & Bank Jam')

@section('content')
@php
    $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $thNow = (int) date('Y');
@endphp

<div class="space-y-6">

    {{-- Header & Export Button --}}
    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-xl font-extrabold text-slate-800">Rekapitulasi Lembur & Bank Jam</h3>
                <p class="text-sm text-slate-500 mt-1">
                    Kelola pengajuan lembur berbayar (payroll) serta tabungan jam kerja kompensasi.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('laporan-payroll.lembur.excel', request()->all()) }}"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md transition-all active:scale-95 flex items-center gap-2">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Export Excel
                </a>
            </div>
        </div>

        {{-- Alerts --}}
        @if(session('success'))
        <div class="mt-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-emerald-700 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @if($errors->any())
        <div class="mt-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm font-bold text-rose-700 flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif

        {{-- Filter Form --}}
        <form method="GET" action="{{ route('laporan-payroll.lembur') }}" class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- Bulan --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Bulan</label>
                <select name="bulan" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
                    @for($m=1; $m<=12; $m++)
                        <option value="{{ $m }}" @selected($bulan == $m)>{{ $namaBulan[$m] }}</option>
                    @endfor
                </select>
            </div>

            {{-- Tahun --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Tahun</label>
                <select name="tahun" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
                    @for($y=$thNow+1; $y>=$thNow-3; $y--)
                        <option value="{{ $y }}" @selected($tahun == $y)>{{ $y }}</option>
                    @endfor
                </select>
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

            {{-- Status --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Status Validasi</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200">
                    <option value="">Semua Status</option>
                    <option value="Pending" @selected($statusFilter == 'Pending')>Pending</option>
                    <option value="Disetujui" @selected($statusFilter == 'Disetujui')>Disetujui</option>
                    <option value="Ditolak" @selected($statusFilter == 'Ditolak')>Ditolak</option>
                </select>
            </div>

            {{-- Tombol Filter --}}
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i> Filter
                </button>
                <a href="{{ route('laporan-payroll.lembur', ['tab' => $tab]) }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-bold transition-all flex items-center justify-center" title="Reset Filter">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>

        {{-- Navigation Tabs --}}
        <div class="mt-8 flex border-b border-slate-100 gap-2 overflow-x-auto">
            <a href="{{ route('laporan-payroll.lembur', array_merge(request()->all(), ['tab' => 'dibayar'])) }}"
                class="px-5 py-3 text-sm font-extrabold border-b-2 flex items-center gap-2 transition-all {{ $tab === 'dibayar' ? 'border-emerald-500 text-emerald-700 bg-emerald-50/30 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <i data-lucide="banknote" class="w-4 h-4"></i>
                <span>1. Lembur Berbayar (Payroll)</span>
                <span class="px-2 py-0.5 rounded-full text-xs {{ $tab === 'dibayar' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ count($rowsDibayar) }}
                </span>
            </a>

            <a href="{{ route('laporan-payroll.lembur', array_merge(request()->all(), ['tab' => 'bank_jam'])) }}"
                class="px-5 py-3 text-sm font-extrabold border-b-2 flex items-center gap-2 transition-all {{ $tab === 'bank_jam' ? 'border-indigo-500 text-indigo-700 bg-indigo-50/30 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <i data-lucide="wallet" class="w-4 h-4"></i>
                <span>2. Saldo Bank Jam Pegawai</span>
                <span class="px-2 py-0.5 rounded-full text-xs {{ $tab === 'bank_jam' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ count($rowsBankJam) }}
                </span>
            </a>

            <a href="{{ route('laporan-payroll.lembur', array_merge(request()->all(), ['tab' => 'pengambilan'])) }}"
                class="px-5 py-3 text-sm font-extrabold border-b-2 flex items-center gap-2 transition-all {{ $tab === 'pengambilan' ? 'border-amber-500 text-amber-700 bg-amber-50/30 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <i data-lucide="calendar-clock" class="w-4 h-4"></i>
                <span>3. Riwayat Pengambilan Jam</span>
                <span class="px-2 py-0.5 rounded-full text-xs {{ $tab === 'pengambilan' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ count($rowsPengambilan) }}
                </span>
            </a>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- TAB 1: LEMBUR BERBAYAR (PAYROLL) --}}
    {{-- ======================================================== --}}
    @if($tab === 'dibayar')
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Pengajuan Lembur</div>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ $statsDibayar->total_pengajuan ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Unit kerja kebijakan lembur dibayar</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Total Jam Disetujui (Payroll)</div>
            <div class="text-2xl font-black text-emerald-600 mt-1">{{ number_format($statsDibayar->total_jam_disetujui ?? 0, 2, ',', '.') }} Jam</div>
            <div class="text-[11px] text-slate-400 mt-1">Dasar pembayaran kompensasi uang</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-amber-500 uppercase tracking-wider">Menunggu Validasi Atasan</div>
            <div class="text-2xl font-black text-amber-500 mt-1">{{ $statsDibayar->jml_pending ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Belum diputuskan oleh atasan</div>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6">
        <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                        <th class="px-4 py-3.5 w-12 text-center">No</th>
                        <th class="px-4 py-3.5">Pegawai</th>
                        <th class="px-4 py-3.5">Unit Kerja</th>
                        <th class="px-4 py-3.5">Jenis</th>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">Jam Kerja</th>
                        <th class="px-4 py-3.5 text-center">Durasi</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5">Alasan</th>
                        <th class="px-4 py-3.5 text-center w-28">Aksi Web</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($rowsDibayar as $i => $r)
                    <tr class="hover:bg-slate-50/60 transition-all">
                        <td class="px-4 py-3.5 text-slate-400 text-center font-bold">{{ $i + 1 }}</td>
                        <td class="px-4 py-3.5">
                            <span class="font-extrabold text-slate-800 block">{{ $r['nama'] }}</span>
                            <span class="text-[11px] text-slate-400">{{ $r['nik'] }}</span>
                        </td>
                        <td class="px-4 py-3.5 font-semibold text-slate-600">{{ $r['unit'] }}</td>
                        <td class="px-4 py-3.5">
                            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold {{ $r['jenis'] === 'On-Call' ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-sky-50 text-sky-600 border border-sky-100' }}">
                                {{ $r['jenis'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 whitespace-nowrap">{{ $r['tanggal'] }}</td>
                        <td class="px-4 py-3.5 text-slate-600 whitespace-nowrap">{{ $r['jam_masuk'] }} - {{ $r['jam_keluar'] }}</td>
                        <td class="px-4 py-3.5 text-center font-black text-slate-800">{{ $r['durasi'] }}</td>
                        <td class="px-4 py-3.5 text-center">
                            @if($r['status'] === 'Disetujui')
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Disetujui</span>
                            @elseif($r['status'] === 'Ditolak')
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Ditolak</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 text-xs max-w-xs">{{ $r['alasan'] }}</td>
                        <td class="px-4 py-3.5 text-center">
                            @if($r['status'] === 'Pending')
                            <div class="flex items-center justify-center gap-1.5">
                                <form action="{{ route('laporan-payroll.lembur.validasi', $r['id']) }}" method="POST" onsubmit="return confirm('Setujui pengajuan lembur ini?');">
                                    @csrf
                                    <input type="hidden" name="status" value="Disetujui">
                                    <button type="submit" class="p-1.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 rounded-lg transition-all" title="Setujui">
                                        <i data-lucide="check" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                <button type="button" onclick="openRejectModal('lembur', '{{ $r['id'] }}', '{{ $r['nama'] }}')" class="p-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg transition-all" title="Tolak">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                            @else
                            <span class="text-[11px] text-slate-400">Selesai</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-slate-400 font-bold">
                            Tidak ada pengajuan lembur berbayar pada periode ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 2: SALDO BANK JAM PEGAWAI (KOMPENSASI JAM) --}}
    {{-- ======================================================== --}}
    @if($tab === 'bank_jam')
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Pegawai Sistem Ganti Jam</div>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ count($rowsBankJam) }} Pegawai</div>
            <div class="text-[11px] text-slate-400 mt-1">Pegawai unit non-bayar yang memiliki saldo jam kerja</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Total Saldo Jam Aktif (Akumulasi)</div>
            <div class="text-2xl font-black text-indigo-600 mt-1">{{ $totalSaldoJamSemua }} Jam</div>
            <div class="text-[11px] text-slate-400 mt-1">Total tabungan jam yang siap digunakan untuk kompensasi</div>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6">
        <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                        <th class="px-5 py-4 w-12 text-center">No</th>
                        <th class="px-5 py-4">Pegawai</th>
                        <th class="px-5 py-4">Unit Kerja</th>
                        <th class="px-5 py-4 text-center">Total Lembur Diperoleh (+)</th>
                        <th class="px-5 py-4 text-center">Total Jam Digunakan (-)</th>
                        <th class="px-5 py-4 text-center">Saldo Jam Tersedia (=)</th>
                        <th class="px-5 py-4 text-center">Status Rekening Jam</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($rowsBankJam as $i => $b)
                    <tr class="hover:bg-slate-50/60 transition-all">
                        <td class="px-5 py-4 text-slate-400 text-center font-bold">{{ $i + 1 }}</td>
                        <td class="px-5 py-4">
                            <span class="font-extrabold text-slate-800 block">{{ $b['nama'] }}</span>
                            <span class="text-[11px] text-slate-400">{{ $b['nik'] }}</span>
                        </td>
                        <td class="px-5 py-4 font-semibold text-slate-600">{{ $b['unit'] }}</td>
                        <td class="px-5 py-4 text-center font-bold text-emerald-600">
                            + {{ $b['jam_diperoleh_teks'] }}
                        </td>
                        <td class="px-5 py-4 text-center font-bold text-rose-500">
                            - {{ $b['jam_digunakan_teks'] }}
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black {{ $b['saldo_menit'] > 0 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-400' }}">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                {{ $b['saldo_tersedia_teks'] }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            @if($b['saldo_menit'] > 0)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Aktif
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-400">
                                    Saldo Kosong
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-slate-400 font-bold">
                            Belum ada pegawai pada unit kerja dengan kebijakan Ganti Jam Kerja.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 3: RIWAYAT PENGAMBILAN WAKTU LEMBUR --}}
    {{-- ======================================================== --}}
    @if($tab === 'pengambilan')
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Klaim Waktu</div>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ $statsPengambilan->total_pengajuan ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Pengajuan datang lambat / pulang cepat</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Total Durasi Disetujui</div>
            <div class="text-2xl font-black text-indigo-600 mt-1">
                {{ round(($statsPengambilan->total_menit_disetujui ?? 0) / 60, 1) }} Jam
            </div>
            <div class="text-[11px] text-slate-400 mt-1">{{ $statsPengambilan->total_menit_disetujui ?? 0 }} menit terpotong dari saldo</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <div class="text-xs font-bold text-amber-500 uppercase tracking-wider">Menunggu Validasi Atasan</div>
            <div class="text-2xl font-black text-amber-500 mt-1">{{ $statsPengambilan->jml_pending ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Klaim waktu yang perlu disetujui</div>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6">
        <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                        <th class="px-4 py-3.5 w-12 text-center">No</th>
                        <th class="px-4 py-3.5">Pegawai</th>
                        <th class="px-4 py-3.5">Unit Kerja</th>
                        <th class="px-4 py-3.5">Tanggal Dinas</th>
                        <th class="px-4 py-3.5">Jenis Kompensasi</th>
                        <th class="px-4 py-3.5 text-center">Durasi Ambil</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5">Alasan Pegawai</th>
                        <th class="px-4 py-3.5">Validator</th>
                        <th class="px-4 py-3.5 text-center w-28">Aksi Web</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($rowsPengambilan as $i => $p)
                    <tr class="hover:bg-slate-50/60 transition-all">
                        <td class="px-4 py-3.5 text-slate-400 text-center font-bold">{{ $i + 1 }}</td>
                        <td class="px-4 py-3.5">
                            <span class="font-extrabold text-slate-800 block">{{ $p['nama'] }}</span>
                            <span class="text-[11px] text-slate-400">{{ $p['nik'] }}</span>
                        </td>
                        <td class="px-4 py-3.5 font-semibold text-slate-600">{{ $p['unit'] }}</td>
                        <td class="px-4 py-3.5 font-bold text-slate-700 whitespace-nowrap">{{ $p['tanggal'] }}</td>
                        <td class="px-4 py-3.5">
                            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold {{ $p['tipe_raw'] === 'masuk_diperlambat' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                {{ $p['tipe_label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center font-black text-indigo-700">{{ $p['durasi_teks'] }}</td>
                        <td class="px-4 py-3.5 text-center">
                            @if($p['status'] === 'Disetujui')
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Disetujui</span>
                            @elseif($p['status'] === 'Ditolak')
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Ditolak</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 text-xs max-w-xs">{{ $p['keterangan'] }}</td>
                        <td class="px-4 py-3.5 text-xs text-slate-500">
                            {{ $p['validator_nama'] ?? '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if($p['status'] === 'Pending')
                            <div class="flex items-center justify-center gap-1.5">
                                <form action="{{ route('laporan-payroll.lembur.validasi-pengambilan', $p['id']) }}" method="POST" onsubmit="return confirm('Setujui pengambilan waktu lembur ini?');">
                                    @csrf
                                    <input type="hidden" name="status" value="Disetujui">
                                    <button type="submit" class="p-1.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 rounded-lg transition-all" title="Setujui">
                                        <i data-lucide="check" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                <button type="button" onclick="openRejectModal('pengambilan', '{{ $p['id'] }}', '{{ $p['nama'] }}')" class="p-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg transition-all" title="Tolak">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                            @else
                            <span class="text-[11px] text-slate-400">Selesai</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-slate-400 font-bold">
                            Tidak ada pengajuan pengambilan waktu lembur pada periode ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

{{-- MODAL TOLAK PENGAJUAN (WEB) --}}
<div id="modalReject" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center font-bold">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <h4 class="text-base font-extrabold text-slate-800">Tolak Pengajuan</h4>
                <p id="rejectPegawaiText" class="text-xs text-slate-400">Pegawai: -</p>
            </div>
        </div>

        <form id="formReject" method="POST" action="">
            @csrf
            <input type="hidden" name="status" value="Ditolak">

            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Alasan Penolakan <span class="text-rose-500">*</span></label>
                <textarea name="catatan" id="catatanReject" rows="3" required placeholder="Tuliskan alasan penolakan..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-rose-200 focus:border-rose-400"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-all">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition-all shadow-md">
                    Tolak Pengajuan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal(type, id, nama) {
        const modal = document.getElementById('modalReject');
        const form = document.getElementById('formReject');
        const pegawaiText = document.getElementById('rejectPegawaiText');
        const catatan = document.getElementById('catatanReject');

        catatan.value = '';
        pegawaiText.innerText = 'Pegawai: ' + nama;

        if (type === 'lembur') {
            form.action = '/laporan-payroll/lembur/validasi/' + id;
        } else {
            form.action = '/laporan-payroll/lembur/validasi-pengambilan/' + id;
        }

        modal.classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('modalReject').classList.add('hidden');
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
@endsection
