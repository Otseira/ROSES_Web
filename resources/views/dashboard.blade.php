@extends('layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Ringkasan Dashboard')

@section('content')
@if(session('success'))
<div
    class="bg-emerald-50 border border-emerald-100 text-emerald-600 px-5 py-4 rounded-2xl text-sm mb-6 flex gap-3 items-center shadow-sm">
    <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
    <span class="font-bold">{{ session('success') }}</span>
</div>
@endif

{{-- ===== HEADER SAMBUTAN + TANGGAL (dipertahankan) ===== --}}
<div
    class="relative bg-gradient-to-r from-primary to-slate-800 rounded-[2rem] p-8 md:p-10 shadow-2xl shadow-slate-900/20 mb-8 overflow-hidden group">
    <div
        class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-gradient-to-br from-accent/40 to-transparent rounded-full blur-3xl transition-transform duration-700 group-hover:scale-110">
    </div>
    <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
            <h2 class="text-3xl font-extrabold text-white mb-2 tracking-tight">Selamat Datang, {{ auth()->user()->name
                }}! ✨</h2>
            <p class="text-slate-300 max-w-xl text-sm leading-relaxed">Panel kendali sistem absensi internal rumah
                sakit. Hak akses Anda saat ini dikonfigurasi sebagai <span class="font-black text-white uppercase">{{
                    auth()->user()->role }}</span>.</p>
        </div>
        <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl p-4 text-center min-w-[150px]">
            <p class="text-xs text-slate-300 font-semibold uppercase tracking-wider mb-1">Tanggal Hari Ini</p>
            <p class="text-white font-bold text-sm flex items-center justify-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-accent"></i> {{
                \Carbon\Carbon::parse($hariIni)->translatedFormat('d F Y') }}
            </p>
        </div>
    </div>
</div>

{{-- ===== PANTAUAN KEHADIRAN (diisi via AJAX; tersembunyi untuk staf) ===== --}}
<div id="kk-section" style="display:none" class="mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-extrabold text-slate-800 tracking-tight">Pantauan Aktual Kehadiran Staf</h3>
        <span id="kk-scope"
            class="text-xs font-bold text-slate-400 bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-full"></span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">

        {{-- Total Karyawan --}}
        <button type="button" data-kk="total"
            class="kk-card text-left bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition">
            <div class="w-12 h-12 bg-slate-50 text-slate-600 rounded-xl flex items-center justify-center shrink-0"><i
                    data-lucide="users" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-slate-800" id="kk-total">-</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Karyawan</p>
            </div>
        </button>

        {{-- Sudah Hadir --}}
        <button type="button" data-kk="hadir"
            class="kk-card text-left bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-xl flex items-center justify-center shrink-0">
                <i data-lucide="check-circle" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-emerald-600" id="kk-hadir">-</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sudah Hadir</p>
            </div>
        </button>

        {{-- Terlambat --}}
        <button type="button" data-kk="terlambat"
            class="kk-card text-left bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition">
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center shrink-0"><i
                    data-lucide="clock" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-amber-600" id="kk-terlambat">-</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Terlambat</p>
            </div>
        </button>

        {{-- Belum Absen --}}
        <button type="button" data-kk="belum"
            class="kk-card text-left bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition">
            <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center shrink-0"><i
                    data-lucide="user-x" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-rose-500" id="kk-belum">-</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Belum Absen</p>
            </div>
        </button>

        {{-- Tidak Dinas (info, tidak bisa diklik) --}}
        <div
            class="bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-xl flex items-center justify-center shrink-0"><i
                    data-lucide="moon" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-indigo-500" id="kk-libur">-</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tidak Dinas</p>
            </div>
        </div>

    </div>
    <p class="text-[11px] text-slate-400 mt-3">Klik kartu <span class="font-semibold">Total / Sudah Hadir / Terlambat /
            Belum Absen</span> untuk melihat daftar nama & unit karyawan.</p>
</div>

{{-- ===== MODAL DAFTAR KARYAWAN ===== --}}
<div id="kk-modal" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <h4 id="kk-modal-title" class="font-extrabold text-slate-800">Daftar Karyawan</h4>
            <button type="button" id="kk-modal-close"
                class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
        </div>
        <div id="kk-modal-body" class="px-5 py-4 overflow-y-auto text-sm">Memuat...</div>
    </div>
</div>

<script>
    (function () {
        const URL_RING = "{{ route('dashboard.ringkasanKaryawan') }}";
        const URL_LIST = "{{ route('dashboard.karyawanHariIni') }}";
        const section = document.getElementById('kk-section');
        if (!section) return;

        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        let punyaTerlambat = false;   // penanda apakah controller sudah mendukung kartu Terlambat

        // --- ambil ringkasan angka ---
        fetch(URL_RING, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => {
                if (!d || !d.can_see) return;          // staf: section tetap tersembunyi
                section.style.display = '';
                set('kk-scope', d.scope_label || '');
                set('kk-total', d.total);
                set('kk-hadir', d.hadir);
                set('kk-belum', d.belum);
                set('kk-libur', d.tidak_dinas);
                if (d.terlambat !== undefined) { punyaTerlambat = true; set('kk-terlambat', d.terlambat); }
            })
            .catch(() => { });

        // --- modal ---
        const modal = document.getElementById('kk-modal');
        const mBody = document.getElementById('kk-modal-body');
        const mTitle = document.getElementById('kk-modal-title');
        const openModal = () => { modal.style.display = 'flex'; };
        const closeModal = () => { modal.style.display = 'none'; };
        document.getElementById('kk-modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        const TITLES = { total: 'Total Karyawan', hadir: 'Sudah Hadir', terlambat: 'Terlambat', belum: 'Belum Absen' };

        document.querySelectorAll('.kk-card').forEach(btn => {
            btn.addEventListener('click', () => {
                const f = btn.getAttribute('data-kk');
                if (f === 'terlambat' && !punyaTerlambat) {
                    mTitle.textContent = 'Terlambat';
                    mBody.innerHTML = '<p class="text-slate-400 text-center py-6">Terapkan update controller (Bagian 2) untuk menampilkan daftar karyawan terlambat.</p>';
                    openModal();
                    return;
                }
                mTitle.textContent = TITLES[f] || 'Daftar Karyawan';
                mBody.innerHTML = '<p class="text-slate-400 text-center py-6">Memuat...</p>';
                openModal();

                fetch(URL_LIST + '?filter=' + encodeURIComponent(f), { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.rows || !d.rows.length) {
                            mBody.innerHTML = '<p class="text-slate-400 text-center py-6">Tidak ada data.</p>';
                            return;
                        }
                        let h = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs uppercase text-slate-400 border-b border-slate-100">'
                            + '<th class="py-2 pr-3">Nama</th><th class="py-2 pr-3">NIK</th><th class="py-2">Unit</th></tr></thead><tbody>';
                        d.rows.forEach(r => {
                            h += '<tr class="border-b border-slate-50">'
                                + '<td class="py-2 pr-3 font-semibold text-slate-800">' + (r.nama || '-') + '</td>'
                                + '<td class="py-2 pr-3 text-slate-500">' + (r.nik || '-') + '</td>'
                                + '<td class="py-2 text-slate-500">' + (r.unit || '-') + '</td></tr>';
                        });
                        h += '</tbody></table></div>';
                        mBody.innerHTML = h;
                        if (window.lucide) lucide.createIcons();
                    })
                    .catch(() => { mBody.innerHTML = '<p class="text-rose-500 text-center py-6">Gagal memuat data.</p>'; });
            });
        });
    })();
</script>
@endsection