@extends('layouts.app')
@section('title', 'Edit Unit Kerja')
@section('page_title', 'Master Unit Kerja')

@section('content')
<div class="max-w-xl bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8">

    <div class="flex items-center gap-4 pb-6 border-b border-slate-100">
        <div class="w-12 h-12 bg-amber-500 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/20">
            <i data-lucide="edit-3" class="w-6 h-6"></i>
        </div>
        <div>
            <h3 class="text-lg font-extrabold text-slate-800">Edit Unit Kerja</h3>
            <p class="text-sm text-slate-500">Perbarui informasi unit kerja: <span class="font-bold text-slate-700">{{ $unit->nama_unit }}</span></p>
        </div>
    </div>

    @if($errors->any())
    <div class="mt-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm font-bold text-rose-700 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
        <span>{{ $errors->first() }}</span>
    </div>
    @endif

    <form method="POST" action="{{ route('master-unit.update', $unit->id) }}" class="mt-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">
                Nama Unit Kerja <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="nama_unit" value="{{ old('nama_unit', $unit->nama_unit) }}" required
                placeholder="Contoh: IGD, Rawat Inap, ICU, Farmasi"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all">
            <p class="text-[11px] text-slate-400 mt-1.5">Nama unit kerja harus unik dan jelas.</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Deskripsi / Keterangan</label>
            <textarea name="deskripsi" rows="3"
                placeholder="Contoh: Instalasi Gawat Darurat dan penanganan medis darurat 24 jam"
                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all">{{ old('deskripsi', $unit->deskripsi) }}</textarea>
            <p class="text-[11px] text-slate-400 mt-1.5">Opsional. Penjelasan singkat mengenai fungsi atau lokasi unit.</p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="{{ route('master-unit.index') }}"
                class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 border border-slate-200 hover:bg-slate-50 transition-all">
                Batal
            </a>
            <button type="submit"
                class="px-6 py-2.5 rounded-xl text-sm font-extrabold bg-emerald-500 hover:bg-emerald-600 text-white shadow-md transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i> Perbarui Unit Kerja
            </button>
        </div>
    </form>
</div>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection

