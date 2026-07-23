@extends('layouts.app')
@section('title', 'Ubah Kata Sandi')
@section('page_title', 'Keamanan Akun')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8 max-w-2xl mx-auto mb-10 mt-4">

    <div class="mb-8 pb-6 border-b border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-gradient-to-tr from-accent to-blue-500 text-white rounded-xl flex items-center justify-center shadow-lg shadow-accent/20">
            <i data-lucide="shield-check" class="w-6 h-6"></i>
        </div>
        <div>
            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Ubah Kata Sandi</h3>
            <p class="text-sm font-medium text-slate-500 mt-1">Pastikan akun Anda menggunakan kata sandi yang kuat dan unik.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="bg-rose-50 border border-rose-100 text-rose-600 px-5 py-4 rounded-xl text-sm mb-8 flex gap-3 items-start">
            <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 mt-0.5"></i>
            <ul class="list-disc pl-5 font-medium space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="/profil/ubah-password" method="POST" class="space-y-6">
        @csrf
        
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Kata Sandi Saat Ini</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="lock" class="w-5 h-5"></i>
                </div>
                <input type="password" name="password_lama" required class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-bold text-slate-800 placeholder-slate-400 outline-none" placeholder="Masukkan kata sandi lama Anda">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 space-y-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Kata Sandi Baru</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="key" class="w-5 h-5"></i>
                    </div>
                    <input type="password" name="password_baru" required class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-bold text-slate-800 placeholder-slate-400 outline-none" placeholder="Minimal 6 karakter">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Konfirmasi Kata Sandi Baru</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                    </div>
                    <input type="password" name="password_baru_confirmation" required class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent transition-all text-sm font-bold text-slate-800 placeholder-slate-400 outline-none" placeholder="Ketik ulang kata sandi baru">
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
            <button type="submit" class="bg-primary hover:bg-slate-800 text-white px-8 py-3.5 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>
@endsection