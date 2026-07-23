@extends('layouts.app')
@section('title', 'Dashboard')
@section('page_title', 'Ringkasan Dashboard')

@section('content')
@if(session('success'))
    <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 px-5 py-4 rounded-2xl text-sm mb-6 flex gap-3 items-center shadow-sm">
        <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
        <span class="font-bold">{{ session('success') }}</span>
    </div>
@endif

<div class="relative bg-gradient-to-r from-primary to-slate-800 rounded-[2rem] p-8 md:p-10 shadow-2xl shadow-slate-900/20 mb-8 overflow-hidden group">
    <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-gradient-to-br from-accent/40 to-transparent rounded-full blur-3xl transition-transform duration-700 group-hover:scale-110"></div>
    <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
            <h2 class="text-3xl font-extrabold text-white mb-2 tracking-tight">Selamat Datang, {{ auth()->user()->name }}! ✨</h2>
            <p class="text-slate-300 max-w-xl text-sm leading-relaxed">Panel kendali sistem absensi internal rumah sakit. Hak akses Anda saat ini dikonfigurasi sebagai <span class="font-black text-white uppercase">{{ auth()->user()->role }}</span>.</p>
        </div>
        <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl p-4 text-center min-w-[150px]">
            <p class="text-xs text-slate-300 font-semibold uppercase tracking-wider mb-1">Tanggal Hari Ini</p>
            <p class="text-white font-bold text-sm flex items-center justify-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-accent"></i> {{ \Carbon\Carbon::parse($hariIni)->translatedFormat('d F Y') }}
            </p>
        </div>
    </div>
</div>

<div class="mb-6">
    <h3 class="text-lg font-extrabold text-slate-800 tracking-tight mb-4">Pantauan Aktual Kehadiran Staf</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <div class="bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-slate-50 text-slate-600 rounded-xl flex items-center justify-center shrink-0"><i data-lucide="users" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-slate-800">{{ $totalJadwal }}</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Wajib Hadir</p>
            </div>
        </div>

        <div class="bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-xl flex items-center justify-center shrink-0"><i data-lucide="check-circle" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-emerald-600">{{ $hadir }}</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sudah Datang</p>
            </div>
        </div>

        <div class="bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center shrink-0"><i data-lucide="clock" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-amber-600">{{ $terlambat }}</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Terlambat</p>
            </div>
        </div>

        <div class="bg-white rounded-[1.5rem] p-5 shadow-[0_8px_30px_rgb(0,0,0,0.02)] border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center shrink-0"><i data-lucide="user-x" class="w-6 h-6"></i></div>
            <div>
                <p class="text-2xl font-black text-rose-500">{{ $belumHadir }}</p>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Belum Absen</p>
            </div>
        </div>

    </div>
</div>
@endsection