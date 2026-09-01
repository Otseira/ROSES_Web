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
'cls' => $shiftStyle[$s->id],
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

        {{-- Chip setiap shift --}}
        @foreach($shifts as $s)
        <button type="button" data-shift="{{ $s->id }}"
            class="shift-chip px-4 py-2 rounded-xl text-xs font-extrabold border border-slate-200 {{ $shiftStyle[$s->id] }} transition-all active:scale-95">
            {{ $s->nama_shift }}
        </button>
        @endforeach

        <div class="lg:ml-auto flex flex-wrap gap-2">
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
            nomor tanggal</b> = isi penuh 1 kolom
    </p>

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
                        @for($i = 1; $i <= $jumlahHari; $i++) <th
                            data-date="{{ $tahun }}-{{ str_pad($bulan, 2, '0', STR_PAD_LEFT) }}-{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}"
                            class="col-head sticky top-0 z-30 bg-slate-800 hover:bg-slate-600 text-white px-2 py-4 text-center text-xs font-bold uppercase tracking-widest min-w-[86px] border-l border-slate-700/50 cursor-pointer transition-colors"
                            title="Klik untuk isi penuh kolom tanggal {{ $i }}">
                            {{ $i }}
                            </th>
                            @endfor
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @forelse($stafGrouped as $namaUnit => $groupStaf)
                    {{-- ===== Baris Header Kelompok Unit ===== --}}
                    <tr>
                        <td colspan="{{ $jumlahHari + 1 }}"
                            class="bg-slate-800 text-white px-6 py-2.5 text-xs font-extrabold uppercase tracking-widest border-y border-slate-700">
                            {{ $namaUnit }}
                            <span class="ml-2 bg-white/10 px-2 py-0.5 rounded-full text-[10px] font-bold">{{
                                $groupStaf->count() }} pegawai</span>
                        </td>
                    </tr>

                    {{-- ===== Baris Staf dalam Unit Tersebut ===== --}}
                    @foreach($groupStaf as $pegawai)
                    <tr>
                        <td data-user="{{ $pegawai->id }}"
                            class="row-head sticky left-0 z-20 bg-white hover:bg-slate-100 px-6 py-2 whitespace-nowrap text-sm font-extrabold text-slate-800 border-b border-r border-slate-100 shadow-[4px_0_15px_-3px_rgba(0,0,0,0.05)] cursor-pointer transition-colors"
                            title="Klik untuk isi penuh baris {{ $pegawai->name }}">
                            {{ $pegawai->name }}
                            {{-- BARU: nama unit tampil kecil di bawah nama pegawai --}}
                            <span class="block text-[10px] font-bold text-slate-400">{{ $namaUnit }}</span>
                        </td>
                        @for($i = 1; $i <= $jumlahHari; $i++) @php $tanggalSekarang=$tahun . '-' . str_pad($bulan,
                            2, '0' , STR_PAD_LEFT) . '-' . str_pad($i, 2, '0' , STR_PAD_LEFT); $rosterHariIni=$pegawai->
                            rosters->firstWhere('tanggal_dinas', $tanggalSekarang);
                            $nilaiAwal = $rosterHariIni ? $rosterHariIni->shift_id : '';
                            @endphp
                            <td class="p-0 border-b border-l border-slate-100">
                                <input type="hidden" name="roster[{{ $pegawai->id }}][{{ $tanggalSekarang }}]"
                                    value="{{ $nilaiAwal }}">
                                <div class="roster-cell h-11 flex items-center justify-center text-[10px] font-extrabold cursor-pointer select-none {{ $nilaiAwal ? ($shiftStyle[$nilaiAwal] ?? 'bg-slate-100 text-slate-600') : 'bg-slate-100 text-slate-400' }}"
                                    data-shift="{{ $nilaiAwal }}" data-user="{{ $pegawai->id }}"
                                    data-date="{{ $tanggalSekarang }}"
                                    title="{{ $pegawai->name }} — {{ $tanggalSekarang }}">
                                    {{ $nilaiAwal ?
                                    \Illuminate\Support\Str::limit(optional($rosterHariIni->shift)->nama_shift, 10) :
                                    '—' }}
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
                    @endempty
                    <tr>
                        <td colspan="{{ $jumlahHari + 1 }}"
                            class="px-6 py-10 text-center text-sm font-bold text-slate-400">
                            Tidak ada staf pada unit kerja Anda.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8 flex justify-end">
            <button type="submit"
                class="bg-accent hover:bg-sky-500 text-white px-8 py-3.5 rounded-xl text-sm font-extrabold shadow-lg shadow-accent/30 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="cloud-upload" class="w-5 h-5"></i> Simpan & Publikasikan
            </button>
        </div>
    </form>
</div>

{{-- Penampung data untuk JavaScript (semua output Blade dipindah ke sini) --}}
<div id="roster-data" class="hidden" data-bulan="{{ $bulan }}" data-tahun="{{ $tahun }}"
    data-copy-url="{{ url('/roster/copy-previous') }}" data-shift-map='@json($shiftMap)'></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        // ---------- Baca data dari atribut HTML (JavaScript murni, tanpa Blade) ----------
        const dataEl = document.getElementById('roster-data');
        const SHIFT_MAP = JSON.parse(dataEl.dataset.shiftMap);
        const BULAN = parseInt(dataEl.dataset.bulan, 10);
        const TAHUN = parseInt(dataEl.dataset.tahun, 10);
        const COPY_URL = dataEl.dataset.copyUrl;

        let activeShift = '';
        let painting = false;

        // ---------- Palet Shift ----------
        const chips = document.querySelectorAll('.shift-chip');
        function setActiveShift(value) {
            activeShift = value;
            chips.forEach(function (c) {
                c.classList.remove('ring-4', 'ring-slate-400/60', 'scale-105');
                if (c.dataset.shift === value) c.classList.add('ring-4', 'ring-slate-400/60', 'scale-105');
            });
        }
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () { setActiveShift(chip.dataset.shift); });
        });
        if (chips.length > 1) { setActiveShift(chips[1].dataset.shift); } else { setActiveShift(''); }

        // ---------- Fungsi mengecat sel ----------
        function styleCell(cell, shiftId) {
            const key = (shiftId === '' || shiftId === null || shiftId === undefined) ? '' : String(shiftId);
            const info = SHIFT_MAP[key] || SHIFT_MAP[''];

            Object.values(SHIFT_MAP).forEach(function (m) { m.cls.split(' ').forEach(function (c) { cell.classList.remove(c); }); });
            info.cls.split(' ').forEach(function (c) { cell.classList.add(c); });

            cell.dataset.shift = key;
            cell.textContent = key === '' ? '—' : info.label;

            const input = cell.parentElement.querySelector('input[type="hidden"]');
            if (input) input.value = key;
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
            document.querySelectorAll('.roster-cell').forEach(function (cell) { styleCell(cell, ''); });
        });

        // ---------- Salin bulan lalu ----------
        document.getElementById('btnCopyPrev').addEventListener('click', async function () {
            const conf = await Swal.fire({
                icon: 'question',
                title: 'Salin Bulan Lalu?',
                text: 'Jadwal periode sebelumnya akan disalin ke periode ini (tanggal yang sama). Jadwal yang sudah ada akan ditimpa.',
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
    });
</script>
@endsection