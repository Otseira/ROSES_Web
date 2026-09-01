@extends('layouts.app')
@section('title', 'Master Shift')
@section('page_title', 'Master Shift')

@section('content')
<div class="max-w-xl bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8">

    <div class="flex items-center gap-4 pb-6 border-b border-slate-100">
        <div class="w-12 h-12 bg-emerald-500 text-white rounded-2xl flex items-center justify-center">
            <i data-lucide="clock" class="w-6 h-6"></i>
        </div>
        <div>
            <h3 class="text-lg font-extrabold text-slate-800">Tambah Shift Baru</h3>
            <p class="text-sm text-slate-500">Buat aturan jam kerja operasional.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="mt-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm font-bold text-rose-700">
        ⚠️ {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('master-shift.store') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Nama Shift</label>
            <input type="text" name="nama_shift" value="{{ old('nama_shift') }}" required
                placeholder="Contoh: RANAP - Pagi"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
        </div>

        {{-- ✅ BARU: Unit Kerja --}}
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Unit Kerja</label>
            <select name="unit_kerja_id"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 cursor-pointer">
                <option value="">— Umum / Semua Unit —</option>
                @foreach($units as $u)
                <option value="{{ $u->id }}" {{ old('unit_kerja_id')==$u->id ? 'selected' : '' }}>
                    {{ $u->nama_unit }} • {{ $u->deskripsi }}
                </option>
                @endforeach
            </select>
            <p class="text-[11px] text-slate-400 mt-1.5">Shift hanya akan muncul di palet roster unit yang dipilih.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Jam Masuk</label>
                <input type="time" name="jam_masuk" value="{{ old('jam_masuk') }}" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Jam Pulang</label>
                <input type="time" name="jam_pulang" value="{{ old('jam_pulang') }}" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Toleransi Keterlambatan (Menit)</label>
            <input type="number" name="toleransi_terlambat_menit" value="{{ old('toleransi_terlambat_menit', 5) }}"
                min="0"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
            <p class="text-[11px] text-slate-400 mt-1.5">Isi 0 jika karyawan tidak diberikan toleransi keterlambatan.
            </p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="{{ route('master-shift.index') }}"
                class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 border border-slate-200 hover:bg-slate-50 transition-all">Batal</a>
            <button type="submit"
                class="px-6 py-2.5 rounded-xl text-sm font-extrabold bg-emerald-500 hover:bg-emerald-600 text-white shadow-md transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Shift
            </button>
        </div>
    </form>
</div>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection