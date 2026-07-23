@extends('layouts.app')
@section('title', 'Hak Akses')
@section('page_title', 'Pengaturan Hak Akses (ACL)')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 overflow-hidden">
    
    <div class="p-8 border-b border-slate-100/80 bg-white">
        <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Otorisasi Modul Sistem</h3>
        <p class="text-sm text-slate-500 font-medium mt-1">Pantau dan kelola akses fitur aplikasi untuk setiap pegawai terdaftar.</p>
    </div>

    @if(session('success'))
        <div class="mx-8 mt-6 bg-emerald-50 border border-emerald-100 text-emerald-600 px-4 py-3 rounded-xl text-sm flex gap-3 items-start">
            <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
            <span class="font-bold">{{ session('success') }}</span>
        </div>
    @endif

    <div class="overflow-x-auto mt-2">
        <table class="min-w-full divide-y divide-slate-100/80">
            <thead class="bg-slate-50/50">
                <tr>
                    <th class="px-8 py-5 text-left text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest w-1/3">Pegawai</th>
                    <th class="px-8 py-5 text-left text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest w-1/2">Modul yang Dimiliki</th>
                    <th class="px-8 py-5 text-right text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($users as $u)
                <tr class="hover:bg-slate-50/80 transition-colors duration-200 group">
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm group-hover:bg-accent/10 group-hover:text-accent transition-colors">
                                {{ substr($u->name, 0, 1) }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800">{{ $u->name }}</div>
                                <div class="text-xs font-semibold text-slate-400 mt-0.5">{{ $u->nik }} • {{ $u->unitKerja->nama_unit ?? '-' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-8 py-5">
                        <div class="flex flex-wrap gap-2">
                            @forelse($u->moduls as $modul)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[0.7rem] font-bold bg-slate-50 text-slate-600 border border-slate-200 group-hover:border-accent/30 group-hover:bg-accent/5 transition-colors">
                                    <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-accent"></i> {{ $modul->nama_modul }}
                                </span>
                            @empty
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-50 text-rose-600 border border-rose-100">
                                    <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Belum ada akses
                                </span>
                            @endforelse
                        </div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap text-right">
                        <a href="/hak-akses/{{ $u->id }}/edit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:border-accent hover:text-accent text-slate-600 rounded-xl text-sm font-bold shadow-sm transition-all">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4"></i> Atur Akses
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection