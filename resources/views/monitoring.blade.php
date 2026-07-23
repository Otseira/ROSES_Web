@extends('layouts.app')
@section('title', 'Live Monitoring')
@section('page_title', 'Pantauan Aktual Hari Ini')

@section('content')
<div class="mb-8">
    <div class="inline-flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl shadow-sm">
        <i data-lucide="calendar-clock" class="w-5 h-5 text-accent"></i> 
        <span class="text-sm font-bold text-slate-700">{{ \Carbon\Carbon::parse($hariIni)->translatedFormat('l, d F Y') }}</span>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <div class="bg-white rounded-[1.5rem] p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 flex flex-col justify-center">
        <div class="flex justify-between items-start mb-4">
            <div class="w-12 h-12 bg-slate-50 text-slate-600 rounded-xl flex items-center justify-center"><i data-lucide="users" class="w-6 h-6"></i></div>
            <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-wider">Total</span>
        </div>
        <p class="text-4xl font-black text-slate-800">{{ $totalJadwal }}</p>
    </div>

    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-[1.5rem] p-6 shadow-lg shadow-emerald-500/30 flex flex-col justify-center text-white">
        <div class="flex justify-between items-start mb-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"><i data-lucide="check-circle" class="w-6 h-6"></i></div>
            <span class="bg-white/20 text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-wider">Hadir</span>
        </div>
        <p class="text-4xl font-black">{{ $hadir }}</p>
    </div>

    <div class="bg-gradient-to-br from-amber-400 to-amber-500 rounded-[1.5rem] p-6 shadow-lg shadow-amber-500/30 flex flex-col justify-center text-white">
        <div class="flex justify-between items-start mb-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"><i data-lucide="clock-3" class="w-6 h-6"></i></div>
            <span class="bg-white/20 text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-wider">Telat</span>
        </div>
        <p class="text-4xl font-black">{{ $terlambat }}</p>
    </div>

    <div class="bg-white rounded-[1.5rem] p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-rose-100 flex flex-col justify-center">
        <div class="flex justify-between items-start mb-4">
            <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center"><i data-lucide="user-x" class="w-6 h-6"></i></div>
            <span class="bg-rose-50 text-rose-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-wider">Belum</span>
        </div>
        <p class="text-4xl font-black text-rose-500">{{ $belumHadir }}</p>
    </div>
</div>
@endsection