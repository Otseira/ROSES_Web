@extends('layouts.app')
@section('title', 'Master Pegawai')
@section('page_title', 'Manajemen Data Pegawai')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 overflow-hidden">

    <div
        class="p-8 border-b border-slate-100/80 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white">
        <div>
            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Daftar Tenaga Medis & Staf</h3>
            <p class="text-sm text-slate-500 font-medium mt-1">Kelola data personal dan otorisasi sistem.</p>
        </div>
        <a href="/master-pegawai/create"
            class="bg-primary hover:bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 transition-all active:scale-95 flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Pegawai
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100/80">
            <thead class="bg-slate-50/50">
                <tr>
                    <th
                        class="px-8 py-5 text-left text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">
                        Karyawan</th>
                    <th
                        class="px-8 py-5 text-left text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">
                        Unit Kerja</th>
                    <th
                        class="px-8 py-5 text-left text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">
                        Kontak</th>
                    <th
                        class="px-8 py-5 text-right text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">
                        Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($pegawai as $p)
                <tr class="hover:bg-slate-50/80 transition-colors duration-200 group">
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm group-hover:bg-accent/10 group-hover:text-accent transition-colors">
                                {{ substr($p->name, 0, 1) }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800">{{ $p->name }}</div>
                                <div class="text-xs font-semibold text-slate-400 mt-0.5">{{ $p->nik }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap">
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 border border-blue-100/50">
                            {{ $p->unitKerja->nama_unit ?? 'Belum Diatur' }}
                        </span>
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap text-sm font-medium text-slate-500">
                        {{ $p->nomor_whatsapp ?? '-' }}
                    </td>
                    <td class="px-8 py-5 whitespace-nowrap text-right text-sm">
                        <a href="/master-pegawai/{{ $p->id }}/edit"
                            class="p-2 rounded-lg text-slate-400 hover:text-amber-500 hover:bg-amber-50 transition-colors inline-block">
                            <i data-lucide="pencil" class="w-5 h-5"></i>
                        </a>
                        <form action="/master-pegawai/{{ $p->id }}" method="POST"
                            onsubmit="return confirm('Yakin ingin menghapus pegawai ini?');">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="p-2 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection