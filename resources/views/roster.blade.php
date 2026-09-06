@extends('layouts.app')
@section('title', 'Matriks Roster')
@section('page_title', 'Roster Jadwal Operasional')

@section('content')
@php
$palette = [
'bg-sky-100 text-sky-700',
'bg-amber-100 text-amber-700',
'bg-violet-100 text-violet-700',
'bg-emerald-100 text-emerald-700',
'bg-rose-100 text-rose-700',
'bg-indigo-100 text-indigo-700',
'bg-teal-100 text-teal-700',
'bg-orange-100 text-orange-700',
];
$shiftStyle = [];
foreach ($shifts as $i => $s) {
$shiftStyle[$s->id] = $palette[$i % count($palette)];
}
$shiftMap = ['' => ['label' => 'Libur', 'cls' => 'bg-slate-100 text-slate-400']];
foreach ($shifts as $s) {
$shiftMap[(string) $s->id] = [
'label' => \Illuminate\Support\Str::limit($s->nama_shift, 10),
'full' => $s->nama_shift,
'cls' => $shiftStyle[$s->id],
'jam' => substr((string) $s->jam_masuk, 0, 5) . '–' . substr((string) $s->jam_pulang, 0, 5),
];
}
@endphp

<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">

    {{-- ===== HEADER PERIODE ===== --}}
    <div
        class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-5 bg-slate-50/50 p-4 md:p-6 rounded-[1.5rem] border border-slate-100">
        <div>
            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Periode: {{
                \Carbon\Carbon::createFromDate($tahun, $bulan)->translatedFormat('F Y') }}</h3>
            <p class="text-sm text-slate-500 mt-1 font-medium">Mode cepat aktif: pilih shift di palet, lalu klik /
                tahan-geser pada matriks untuk mengecat jadwal.</p>
        </div>

        <form method="GET" action="/roster" class="flex flex-wrap gap-3">
            <select name="bulan"
                class="bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 shadow-sm appearance-none outline-none cursor-pointer">
                @for($i = 1; $i <= 12; $i++) <option value="{{ $i }}" {{ $bulan==$i ? 'selected' : '' }}>{{
                    \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option>
                    @endfor
            </select>
            <select name="tahun"
                class="bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 shadow-sm appearance-none outline-none cursor-pointer">
                @for($i = date('Y') - 1; $i <= date('Y') + 1; $i++) <option value="{{ $i }}" {{ $tahun==$i ? 'selected'
                    : '' }}>{{ $i }}</option>
                    @endfor
            </select>
            <button type="submit"
                class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-md transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="filter" class="w-4 h-4"></i> Terapkan
            </button>
        </form>
    </div>

    {{-- ===== TOOLBAR PALET SHIFT & ALAT BANTU ===== --}}
    <div class="mb-4 p-4 rounded-2xl border border-slate-200 bg-slate-50 flex flex-wrap items-center gap-3">
        <span class="text-xs font-extrabold uppercase tracking-widest text-slate-500 mr-1">Palet Shift:</span>

        {{-- Chip Libur --}}
        <button type="button" data-shift=""
            class="shift-chip px-4 py-2 rounded-xl text-xs font-extrabold border border-slate-200 bg-slate-100 text-slate-400 transition-all active:scale-95">
            Libur / Kosong
        </button>

        {{-- Chip setiap shift: NAMA + JAM DINAS --}}
        @foreach($shifts as $s)
        <button type="button" data-shift="{{ $s->id }}"
            class="shift-chip px-4 py-2 rounded-xl text-xs font-extrabold border border-slate-200 {{ $shiftStyle[$s->id] }} transition-all active:scale-95 text-center">
            {{ $s->nama_shift }}
            <span class="block text-[9px] font-bold opacity-70 mt-0.5">
                {{ substr((string) $s->jam_masuk, 0, 5) }}–{{ substr((string) $s->jam_pulang, 0, 5) }}
            </span>
        </button>
        @endforeach

        <div class="lg:ml-auto flex flex-wrap gap-2">
            <button type="button" id="btnCustomShift"
                class="px-4 py-2 rounded-xl text-xs font-extrabold bg-gradient-to-r from-purple-600 to-pink-600 text-white hover:from-purple-700 hover:to-pink-700 transition-all active:scale-95 flex items-center gap-1">
                <i data-lucide="edit" class="w-3.5 h-3.5"></i> Shift Custom
            </button>
            <button type="button" id="btnCopyPrev"
                class="px-4 py-2 rounded-xl text-xs font-extrabold bg-slate-800 text-white hover:bg-slate-900 transition-all active:scale-95 flex items-center gap-1">
                <i data-lucide="copy" class="w-3.5 h-3.5"></i> Salin Bulan Lalu
            </button>
            <button type="button" id="btnClear"
                class="px-4 py-2 rounded-xl text-xs font-extrabold bg-white text-rose-600 border border-rose-200 hover:bg-rose-50 transition-all active:scale-95 flex items-center gap-1">
                <i data-lucide="eraser" class="w-3.5 h-3.5"></i> Bersihkan Semua
            </button>
        </div>
    </div>

    <p class="text-[11px] text-slate-400 font-medium mb-3">
        💡 <b>Klik / tahan & geser</b> pada sel = cat jadwal • <b>Klik nama pegawai</b> = isi penuh 1 baris • <b>Klik
            nomor tanggal</b> = isi penuh 1 kolom • <b>Shift Custom</b> = jam manual di luar palet
    </p>
    <div id="shift-info"
        class="hidden mb-3 px-4 py-3 rounded-xl bg-sky-50 border border-sky-200 text-xs font-semibold text-sky-800">
    </div>

    {{-- ===== TOOLBAR NAVIGASI SIMPLE ===== --}}
    <div class="mb-4 flex flex-col gap-3">
        {{-- Tab per Unit --}}
        <div class="flex flex-wrap items-center gap-2" id="unitTabs">
            <span class="text-xs font-extrabold uppercase tracking-widest text-slate-500 mr-1">Unit:</span>
            <button type="button" data-unit="all"
                class="unit-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 bg-slate-800 text-white">
                Semua Unit
            </button>
            @foreach($stafGrouped as $namaUnit => $groupStaf)
            <button type="button" data-unit="{{ $namaUnit }}"
                class="unit-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">
                {{ $namaUnit }} <span class="opacity-60">({{ $groupStaf->count() }})</span>
            </button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <input type="text" id="cariPegawai" placeholder="🔍 Cari nama pegawai..."
                class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-accent/30 w-64">

            <div class="flex flex-wrap items-center gap-1" id="weekNav">
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-500 mr-1">Tanggal:</span>
            </div>
        </div>
    </div>

    {{-- ===== MATRIKS ROSTER ===== --}}
    <form action="{{ url('/roster/bulk-store') }}" method="POST" id="rosterForm">
        @csrf
        <div class="overflow-auto max-h-[72vh] border border-slate-100 rounded-2xl">
            <table class="min-w-full border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th
                            class="sticky top-0 left-0 z-40 bg-slate-900 text-white px-6 py-4 text-left text-xs font-bold uppercase tracking-widest w-64 shadow-[4px_4px_15px_-3px_rgba(0,0,0,0.3)]">
                            Nama Pegawai
                        </th>
                        @for($i = 1; $i <= $jumlahHari; $i++) @php $dateStr=$tahun . '-' . str_pad($bulan, 2, '0' ,
                            STR_PAD_LEFT) . '-' . str_pad($i, 2, '0' , STR_PAD_LEFT);
                            $carbonTgl=\Carbon\Carbon::createFromDate($tahun, $bulan, $i); $libur=$liburMap[$dateStr] ??
                            null; $isWeekend=$carbonTgl->isWeekend();

                            if ($libur && $libur['jenis'] === 'nasional') {
                            $headBg = 'bg-rose-600'; $labelTxt = 'LIBUR'; $labelCls = 'text-rose-100';
                            $tooltip = '🔴 LIBUR NASIONAL: ' . $libur['nama'];
                            } elseif ($libur && $libur['jenis'] === 'cuti_bersama') {
                            $headBg = 'bg-purple-600'; $labelTxt = 'CUTI'; $labelCls = 'text-purple-100';
                            $tooltip = '🟣 CUTI BERSAMA: ' . $libur['nama'];
                            } elseif ($isWeekend) {
                            $headBg = 'bg-amber-500'; $labelTxt = $carbonTgl->isoWeekday() === 6 ? 'SAB' : 'MIN';
                            $labelCls = 'text-amber-100';
                            $tooltip = '🟠 Akhir pekan';
                            } else {
                            $headBg = 'bg-slate-800'; $labelTxt = null; $labelCls = '';
                            $tooltip = 'Hari kerja';
                            }
                            @endphp
                            <th data-date="{{ $dateStr }}" data-day="{{ $i }}"
                                class="col-head sticky top-0 z-30 {{ $headBg }} hover:brightness-125 text-white px-2 py-3 text-center text-xs font-bold uppercase tracking-widest min-w-[86px] border-l border-slate-700/50 cursor-pointer transition-all"
                                title="{{ $tooltip }} — klik untuk isi penuh kolom {{ $i }}">
                                {{ $i }}
                                @if($labelTxt)
                                <span class="block text-[8px] font-black {{ $labelCls }} mt-0.5 leading-tight">{{
                                    $labelTxt }}</span>
                                @endif
                            </th>
                            @endfor
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse($stafGrouped as $namaUnit => $groupStaf)
                    {{-- Baris Header Kelompok Unit --}}
                    <tr class="unit-head" data-unit="{{ $namaUnit }}">
                        <td colspan="{{ $jumlahHari + 1 }}"
                            class="bg-slate-800 text-white px-6 py-2.5 text-xs font-extrabold uppercase tracking-widest border-y border-slate-700">
                            {{ $namaUnit }}
                            <span class="ml-2 bg-white/10 px-2 py-0.5 rounded-full text-[10px] font-bold">
                                {{ $groupStaf->count() }} pegawai
                            </span>
                        </td>
                    </tr>

                    {{-- Baris Staf --}}
                    @foreach($groupStaf as $pegawai)
                    <tr class="staff-row" data-unit="{{ $namaUnit }}" data-name="{{ mb_strtolower($pegawai->name) }}">
                        <td data-user="{{ $pegawai->id }}"
                            class="row-head sticky left-0 z-20 bg-white hover:bg-slate-100 px-6 py-2 whitespace-nowrap text-sm font-extrabold text-slate-800 border-b border-r border-slate-100 shadow-[4px_0_15px_-3px_rgba(0,0,0,0.05)] cursor-pointer transition-colors"
                            title="Klik untuk isi penuh baris {{ $pegawai->name }}">
                            {{ $pegawai->name }}
                            <span class="block text-[10px] font-bold text-slate-400">{{ $namaUnit }}</span>
                        </td>
                        @for($i = 1; $i <= $jumlahHari; $i++) @php $tanggalSekarang=$tahun . '-' . str_pad($bulan,
                            2, '0' , STR_PAD_LEFT) . '-' . str_pad($i, 2, '0' , STR_PAD_LEFT); $rosterHariIni=$pegawai->
                            rosters->first(fn ($r) => \Carbon\Carbon::parse($r->tanggal_dinas)->format('Y-m-d') ===
                            $tanggalSekarang);
                            $isCustom = $rosterHariIni && $rosterHariIni->custom_jam_masuk;
                            $nilaiAwal = ($rosterHariIni && $rosterHariIni->shift_id) ? $rosterHariIni->shift_id : '';

                            $displayText = $isCustom
                            ? substr((string) $rosterHariIni->custom_jam_masuk, 0, 5) . '–' . substr((string)
                            $rosterHariIni->custom_jam_pulang, 0, 5)
                            : ($nilaiAwal && $rosterHariIni && $rosterHariIni->shift
                            ? substr((string) $rosterHariIni->shift->jam_masuk, 0, 5) . '–' . substr((string)
                            $rosterHariIni->shift->jam_pulang, 0, 5)
                            : '—');

                            $bgClass = $isCustom
                            ? 'bg-purple-100 text-purple-700'
                            : ($nilaiAwal ? ($shiftStyle[$nilaiAwal] ?? 'bg-slate-100 text-slate-600') : 'bg-slate-100
                            text-slate-400');

                            $tooltipCell = $pegawai->name . ' — ' . $tanggalSekarang
                            . ($isCustom
                            ? ' • ' . ($rosterHariIni->custom_nama_shift ?? 'Custom')
                            : ($rosterHariIni && $rosterHariIni->shift ? ' • ' . $rosterHariIni->shift->nama_shift :
                            ''));
                            @endphp
                            <td class="p-0 border-b border-l border-slate-100" data-day="{{ $i }}">
                                @if($isCustom)
                                <input type="hidden" name="roster[{{ $pegawai->id }}][{{ $tanggalSekarang }}][shift_id]"
                                    value="">
                                <input type="hidden"
                                    name="roster[{{ $pegawai->id }}][{{ $tanggalSekarang }}][custom_jam_masuk]"
                                    value="{{ $rosterHariIni->custom_jam_masuk }}">
                                <input type="hidden"
                                    name="roster[{{ $pegawai->id }}][{{ $tanggalSekarang }}][custom_jam_pulang]"
                                    value="{{ $rosterHariIni->custom_jam_pulang }}">
                                <input type="hidden"
                                    name="roster[{{ $pegawai->id }}][{{ $tanggalSekarang }}][custom_nama_shift]"
                                    value="{{ $rosterHariIni->custom_nama_shift }}">
                                @else
                                <input type="hidden" name="roster[{{ $pegawai->id }}][{{ $tanggalSekarang }}]"
                                    value="{{ $nilaiAwal }}">
                                @endif

                                <div class="roster-cell h-11 flex items-center justify-center text-[9px] font-extrabold cursor-pointer select-none {{ $bgClass }}"
                                    data-shift="{{ $nilaiAwal }}" data-user="{{ $pegawai->id }}"
                                    data-date="{{ $tanggalSekarang }}" @if($isCustom) data-custom="1" @endif
                                    title="{{ $tooltipCell }}">
                                    {{ $displayText }}
                                </div>
                            </td>
                            @endfor
                    </tr>
                    @endforeach
                    @empty
                    <tr>
                        <td colspan="{{ $jumlahHari + 1 }}"
                            class="px-6 py-10 text-center text-sm font-bold text-slate-400">
                            Tidak ada unit yang Anda kelola.<br>
                            <span class="text-xs font-medium">Silakan centang "Unit yang Dikelola" pada menu Hak Akses
                                terlebih dahulu.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-4 mb-3 text-[10px] font-bold text-slate-500">
            <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-slate-800"></span> Hari
                Kerja</span>
            <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-amber-500"></span> Sabtu /
                Minggu</span>
            <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-rose-600"></span> Libur
                Nasional</span>
            <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-purple-600"></span> Cuti
                Bersama</span>
            <span class="flex items-center gap-1.5"><span
                    class="w-3.5 h-3.5 rounded bg-purple-100 border border-purple-300"></span> Shift Custom</span>
        </div>

        <div class="mt-8 flex justify-end">
            <button type="submit"
                class="bg-accent hover:bg-sky-500 text-white px-8 py-3.5 rounded-xl text-sm font-extrabold shadow-lg shadow-accent/30 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="cloud-upload" class="w-5 h-5"></i> Simpan & Publikasikan
            </button>
        </div>
    </form>
</div>

{{-- ===== MODAL SHIFT CUSTOM ===== --}}
<div id="customShiftModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-extrabold text-slate-800 mb-2">🎨 Shift Custom</h3>
        <p class="text-xs text-slate-500 mb-4">Untuk jadwal khusus di luar palet shift standar (mis. perawat OK, jadwal
            berubah mendadak). Berlaku global untuk semua unit.</p>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Nama Shift (opsional)</label>
                <input type="text" id="customNamaShift" placeholder="Contoh: Jaga Siang Khusus"
                    class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-accent/30">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Jam Masuk <span
                            class="text-rose-500">*</span></label>
                    <input type="time" id="customJamMasuk"
                        class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-accent/30">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Jam Pulang <span
                            class="text-rose-500">*</span></label>
                    <input type="time" id="customJamPulang"
                        class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-accent/30">
                </div>
            </div>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="button" id="btnCancelCustom"
                class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-all">
                Batal
            </button>
            <button type="button" id="btnSaveCustom"
                class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold bg-gradient-to-r from-purple-600 to-pink-600 text-white hover:from-purple-700 hover:to-pink-700 transition-all">
                Aktifkan & Cat
            </button>
        </div>
    </div>
</div>

{{-- Penampung data untuk JavaScript --}}
<div id="roster-data" class="hidden" data-bulan="{{ $bulan }}" data-tahun="{{ $tahun }}" data-days="{{ $jumlahHari }}"
    data-copy-url="{{ url('/roster/copy-previous') }}" data-shift-map='@json($shiftMap)'></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        // ---------- Baca data dari atribut HTML ----------
        const dataEl = document.getElementById('roster-data');
        const SHIFT_MAP = JSON.parse(dataEl.dataset.shiftMap);
        const BULAN = parseInt(dataEl.dataset.bulan, 10);
        const TAHUN = parseInt(dataEl.dataset.tahun, 10);
        const COPY_URL = dataEl.dataset.copyUrl;
        const TOTAL_DAYS = parseInt(dataEl.dataset.days || '31', 10);

        let activeShift = '';
        let painting = false;
        let customActive = false;
        let customData = null;

        const chips = document.querySelectorAll('.shift-chip');
        const infoBox = document.getElementById('shift-info');
        const btnCustom = document.getElementById('btnCustomShift');

        // Kumpulan semua class warna sel (untuk reset)
        const ALL_CELL_CLASSES = ['bg-purple-100', 'text-purple-700'];
        Object.values(SHIFT_MAP).forEach(function (m) {
            m.cls.split(' ').forEach(function (c) { ALL_CELL_CLASSES.push(c); });
        });

        // ---------- Fungsi ganti shift aktif ----------
        function setActiveShift(value) {
            activeShift = value;
            chips.forEach(function (c) {
                c.classList.remove('ring-4', 'ring-slate-400/60', 'scale-105');
                if (c.dataset.shift === value) c.classList.add('ring-4', 'ring-slate-400/60', 'scale-105');
            });

            if (infoBox) {
                const key = (value === '' || value === null || value === undefined) ? '' : String(value);
                const info = SHIFT_MAP[key];
                if (key === '' || !info) {
                    infoBox.classList.add('hidden');
                } else {
                    infoBox.classList.remove('hidden');
                    infoBox.innerHTML = '🕐 Shift: <b>' + info.full + '</b> &nbsp;•&nbsp; Jam Dinas: <b>' + (info.jam || '-') + '</b>';
                }
            }
        }

        // ---------- Reset mode custom ----------
        function resetCustom() {
            customActive = false;
            customData = null;
            btnCustom.classList.remove('ring-4', 'ring-purple-400/60', 'scale-105');
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                resetCustom();
                setActiveShift(chip.dataset.shift);
            });
        });

        if (chips.length > 0) { setActiveShift(chips[0].dataset.shift); } else { setActiveShift(''); }

        // ---------- Helper: kelola hidden input per sel ----------
        function addHidden(parent, name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            parent.appendChild(input);
        }

        function setCellInputs(cell, mode, payload) {
            const parent = cell.parentElement;
            const userId = cell.dataset.user;
            const date = cell.dataset.date;

            parent.querySelectorAll('input[type="hidden"]').forEach(function (i) { i.remove(); });

            if (mode === 'custom') {
                addHidden(parent, 'roster[' + userId + '][' + date + '][shift_id]', '');
                addHidden(parent, 'roster[' + userId + '][' + date + '][custom_jam_masuk]', payload.jam_masuk);
                addHidden(parent, 'roster[' + userId + '][' + date + '][custom_jam_pulang]', payload.jam_pulang);
                addHidden(parent, 'roster[' + userId + '][' + date + '][custom_nama_shift]', payload.nama);
            } else {
                addHidden(parent, 'roster[' + userId + '][' + date + ']', payload.shiftId);
            }
        }

        // ---------- Fungsi mengecat sel (support shift palet & custom) ----------
        function styleCell(cell, shiftId) {
            // Mode CUSTOM aktif
            if (customActive && customData) {
                ALL_CELL_CLASSES.forEach(function (c) { cell.classList.remove(c); });
                cell.classList.add('bg-purple-100', 'text-purple-700');
                cell.dataset.shift = '';
                cell.dataset.custom = '1';
                cell.textContent = customData.jam_masuk + '–' + customData.jam_pulang;
                setCellInputs(cell, 'custom', customData);
                return;
            }

            // Mode NORMAL (palet shift)
            const key = (shiftId === '' || shiftId === null || shiftId === undefined) ? '' : String(shiftId);
            const info = SHIFT_MAP[key] || SHIFT_MAP[''];

            ALL_CELL_CLASSES.forEach(function (c) { cell.classList.remove(c); });
            info.cls.split(' ').forEach(function (c) { cell.classList.add(c); });

            cell.dataset.shift = key;
            delete cell.dataset.custom;
            cell.textContent = key === '' ? '—' : (info.jam || info.label);

            setCellInputs(cell, 'normal', { shiftId: key });
        }

        // ---------- Interaksi klik & drag (paint) ----------
        document.addEventListener('mousedown', function (e) {
            const cell = e.target.closest('.roster-cell');
            if (cell) {
                e.preventDefault();
                painting = true;
                styleCell(cell, activeShift);
            }
        });
        document.addEventListener('mouseover', function (e) {
            if (!painting) return;
            const cell = e.target.closest('.roster-cell');
            if (cell) styleCell(cell, activeShift);
        });
        window.addEventListener('mouseup', function () { painting = false; });

        // ---------- Klik nama pegawai = isi 1 baris ----------
        document.querySelectorAll('.row-head').forEach(function (head) {
            head.addEventListener('click', function () {
                document.querySelectorAll('.roster-cell[data-user="' + head.dataset.user + '"]')
                    .forEach(function (cell) { styleCell(cell, activeShift); });
            });
        });

        // ---------- Klik nomor tanggal = isi 1 kolom ----------
        document.querySelectorAll('.col-head').forEach(function (head) {
            head.addEventListener('click', function () {
                document.querySelectorAll('.roster-cell[data-date="' + head.dataset.date + '"]')
                    .forEach(function (cell) { styleCell(cell, activeShift); });
            });
        });

        // ---------- Bersihkan semua ----------
        document.getElementById('btnClear').addEventListener('click', async function () {
            const conf = await Swal.fire({
                icon: 'warning',
                title: 'Bersihkan Semua?',
                text: 'Seluruh jadwal pada matriks ini akan diubah menjadi Libur (belum disimpan ke database).',
                showCancelButton: true,
                confirmButtonText: 'Ya, Bersihkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#EF4444'
            });
            if (!conf.isConfirmed) return;
            resetCustom();
            document.querySelectorAll('.roster-cell').forEach(function (cell) { styleCell(cell, ''); });
        });

        // ---------- Salin bulan lalu ----------
        document.getElementById('btnCopyPrev').addEventListener('click', async function () {
            const conf = await Swal.fire({
                icon: 'question',
                title: 'Salin Bulan Lalu?',
                text: 'Jadwal periode sebelumnya akan disalin ke periode ini (tanggal yang sama), termasuk shift custom. Jadwal yang sudah ada akan ditimpa.',
                showCancelButton: true,
                confirmButtonText: 'Ya, Salin',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#3B82F6'
            });
            if (!conf.isConfirmed) return;

            Swal.fire({ title: 'Menyalin...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            try {
                const response = await fetch(COPY_URL, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ bulan: BULAN, tahun: TAHUN })
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: result.message, confirmButtonColor: '#3B82F6' })
                        .then(function () { window.location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: result.message || 'Terjadi kesalahan.', confirmButtonColor: '#EF4444' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Kesalahan Jaringan', text: 'Tidak dapat terhubung ke server.', confirmButtonColor: '#EF4444' });
            }
        });

        // ---------- ✅ SHIFT CUSTOM: modal ----------
        const customModal = document.getElementById('customShiftModal');
        const btnCancelCustom = document.getElementById('btnCancelCustom');
        const btnSaveCustom = document.getElementById('btnSaveCustom');
        const customNama = document.getElementById('customNamaShift');
        const customMasuk = document.getElementById('customJamMasuk');
        const customPulang = document.getElementById('customJamPulang');

        btnCustom.addEventListener('click', function () {
            customModal.classList.remove('hidden');
            customModal.classList.add('flex');
            customNama.value = '';
            customMasuk.value = '';
            customPulang.value = '';
        });

        btnCancelCustom.addEventListener('click', function () {
            customModal.classList.add('hidden');
            customModal.classList.remove('flex');
        });

        btnSaveCustom.addEventListener('click', function () {
            if (!customMasuk.value || !customPulang.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jam wajib diisi',
                    text: 'Jam masuk dan jam pulang harus diisi.',
                    confirmButtonColor: '#3B82F6'
                });
                return;
            }

            customData = {
                nama: customNama.value.trim() || 'Custom',
                jam_masuk: customMasuk.value,
                jam_pulang: customPulang.value,
            };
            customActive = true;

            // Highlight tombol custom, matikan highlight chip palet
            chips.forEach(function (c) { c.classList.remove('ring-4', 'ring-slate-400/60', 'scale-105'); });
            btnCustom.classList.add('ring-4', 'ring-purple-400/60', 'scale-105');

            if (infoBox) {
                infoBox.classList.remove('hidden');
                infoBox.innerHTML = '🎨 <b>Shift Custom Aktif:</b> ' + customData.nama +
                    ' &nbsp;•&nbsp; Jam: <b>' + customData.jam_masuk + '–' + customData.jam_pulang + '</b>' +
                    ' &nbsp;— klik / geser sel untuk mengecat.';
            }

            customModal.classList.add('hidden');
            customModal.classList.remove('flex');
        });

        // ---------- Submit form (simpan) ----------
        const form = document.getElementById('rosterForm');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Menyimpan Jadwal...',
                    text: 'Sistem sedang memproses matriks roster ke database.',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        Swal.fire({ icon: 'success', title: 'Tersimpan!', text: result.message, confirmButtonColor: '#3B82F6' })
                            .then(function () { window.location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', text: result.message, confirmButtonColor: '#EF4444' });
                    }
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Kesalahan Jaringan', text: 'Tidak dapat terhubung ke server.', confirmButtonColor: '#EF4444' });
                }
            });
        }

        // ==========================================================
        // ✅ LOGIKA NAVIGASI SIMPLE (Tab Unit, Cari Nama, Per Minggu)
        // ==========================================================
        const unitTabs = document.querySelectorAll('.unit-tab');
        const unitHeads = document.querySelectorAll('tr.unit-head');
        const staffRows = document.querySelectorAll('tr.staff-row');
        const dayEls = document.querySelectorAll('[data-day]');
        const weekNav = document.getElementById('weekNav');
        const searchBox = document.getElementById('cariPegawai');

        let activeUnit = 'all';
        let activeWeek = 'all';
        let query = '';

        (function buildWeeks() {
            const mk = (val, label) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.dataset.week = val;
                b.textContent = label;
                b.className = 'week-btn px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 ' +
                    (val === 'all' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-100');
                return b;
            };
            weekNav.appendChild(mk('all', 'Semua'));
            for (let s = 1; s <= TOTAL_DAYS; s += 7) {
                const e = Math.min(s + 6, TOTAL_DAYS);
                weekNav.appendChild(mk(s + '-' + e, s + '–' + e));
            }
        })();

        function applyViewFilters() {
            unitHeads.forEach(function (tr) {
                const unitMatch = (activeUnit === 'all' || tr.dataset.unit === activeUnit);
                tr.style.display = query ? 'none' : (unitMatch ? '' : 'none');
            });

            staffRows.forEach(function (tr) {
                const okUnit = (activeUnit === 'all' || tr.dataset.unit === activeUnit);
                const okName = !query || (tr.dataset.name || '').includes(query);
                tr.style.display = (okUnit && okName) ? '' : 'none';
            });

            let lo = 1, hi = TOTAL_DAYS;
            if (activeWeek !== 'all') {
                const p = activeWeek.split('-');
                lo = parseInt(p[0], 10);
                hi = parseInt(p[1], 10);
            }

            dayEls.forEach(function (el) {
                const d = parseInt(el.dataset.day, 10);
                el.style.display = (activeWeek === 'all' || (d >= lo && d <= hi)) ? '' : 'none';
            });
        }

        unitTabs.forEach(function (b) {
            b.addEventListener('click', function () {
                activeUnit = b.dataset.unit;
                unitTabs.forEach(function (x) {
                    x.className = 'unit-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 ' +
                        (x.dataset.unit === activeUnit ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-100');
                });
                applyViewFilters();
            });
        });

        weekNav.addEventListener('click', function (e) {
            const b = e.target.closest('.week-btn');
            if (!b) return;
            activeWeek = b.dataset.week;
            weekNav.querySelectorAll('.week-btn').forEach(function (x) {
                x.className = 'week-btn px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200 ' +
                    (x.dataset.week === activeWeek ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-100');
            });
            applyViewFilters();
        });

        if (searchBox) {
            searchBox.addEventListener('input', function () {
                query = searchBox.value.trim().toLowerCase();
                applyViewFilters();
            });
        }

    });
</script>
@endsection