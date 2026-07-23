@extends('layouts.app')
@section('title', 'Tambah Shift')
@section('page_title', 'Master Shift')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8 max-w-2xl mb-10">
    
    <div class="mb-8 pb-6 border-b border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-emerald-500 text-white rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
            <i data-lucide="clock" class="w-6 h-6"></i>
        </div>
        <div>
            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Tambah Shift Baru</h3>
            <p class="text-sm font-medium text-slate-500 mt-1">Buat aturan jam kerja operasional.</p>
        </div>
    </div>

    <form action="/master-shift" method="POST" class="space-y-6">
        @csrf
        
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Nama Shift</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i data-lucide="tag" class="w-5 h-5"></i></div>
                <input type="text" name="nama_shift" required class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-bold text-slate-800 outline-none" placeholder="Contoh: Shift Pagi">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Jam Masuk</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i data-lucide="log-in" class="w-5 h-5"></i></div>
                    <input type="time" name="jam_masuk" required class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-bold text-slate-800 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Jam Pulang</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i data-lucide="log-out" class="w-5 h-5"></i></div>
                    <input type="time" name="jam_pulang" required class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-bold text-slate-800 outline-none">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Toleransi Keterlambatan (Menit)</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i data-lucide="timer" class="w-5 h-5"></i></div>
                <input type="number" name="toleransi_terlambat_menit" value="15" required min="0" class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-bold text-slate-800 outline-none">
            </div>
            <p class="text-xs font-medium text-slate-400 mt-2"><i data-lucide="info" class="w-3 h-3 inline"></i> Isi 0 jika karyawan tidak diberikan toleransi keterlambatan.</p>
        </div>

        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
            <a href="/master-shift" class="px-6 py-3.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Batal</a>
            <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white px-8 py-3.5 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/20 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Shift
            </button>
        </div>
    </form>
</div>
@endsection