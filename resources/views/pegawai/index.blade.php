@extends('layouts.app')
@section('title', 'Master Pegawai')
@section('page_title', 'Manajemen Data Pegawai')

@section('content')
@php
$roleLabels = [
'staf' => 'Staf',
'kepala_unit' => 'Kepala Unit',
'penanggung_jawab' => 'Penanggung Jawab',
'manajer' => 'Manajer',
'direktur' => 'Direktur',
'hrd' => 'HRD',
'superadmin' => 'Super Admin',
];
$roleColors = [
'staf' => 'bg-slate-100 text-slate-600 border-slate-200/50',
'kepala_unit' => 'bg-teal-50 text-teal-600 border-teal-100/50',
'penanggung_jawab' => 'bg-cyan-50 text-cyan-600 border-cyan-100/50',
'manajer' => 'bg-blue-50 text-blue-600 border-blue-100/50',
'direktur' => 'bg-indigo-50 text-indigo-600 border-indigo-100/50',
'hrd' => 'bg-purple-50 text-purple-600 border-purple-100/50',
'superadmin' => 'bg-rose-50 text-rose-600 border-rose-100/50',
];
@endphp

<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 overflow-hidden">

    {{-- Header --}}
    <div
        class="p-8 border-b border-slate-100/80 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white">
        <div>
            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Daftar Tenaga Medis & Staf</h3>
            <p class="text-sm text-slate-500 font-medium mt-1">Total {{ $pegawai->count() }} pegawai terdaftar. Kelola
                data personal dan otorisasi sistem.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="/master-pegawai/import"
                class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/20 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="upload" class="w-4 h-4"></i> Import CSV
            </a>
            <a href="/master-pegawai/create"
                class="bg-primary hover:bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Pegawai
            </a>
        </div>
    </div>

    {{-- Flash Success --}}
    @if (session('success'))
    <div
        class="mx-8 mt-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-semibold flex items-center gap-2">
        <i data-lucide="check-circle" class="w-5 h-5"></i>
        {{ session('success') }}
    </div>
    @endif

    {{-- Error Import --}}
    @if (session('import_errors') && count(session('import_errors')) > 0)
    <div class="mx-8 mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
        <div class="flex items-center gap-2 mb-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600"></i>
            <span class="font-bold text-amber-900 text-sm">Beberapa data gagal diimport:</span>
        </div>
        <ul class="list-disc list-inside text-sm text-amber-800 space-y-1 max-h-40 overflow-y-auto">
            @foreach (session('import_errors') as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Tabel --}}
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
                        Hak Akses</th>
                    <th
                        class="px-8 py-5 text-left text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">
                        Unit yang Dikelola</th>
                    <th
                        class="px-8 py-5 text-left text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">
                        Kontak</th>
                    <th
                        class="px-8 py-5 text-right text-[0.7rem] font-extrabold text-slate-400 uppercase tracking-widest">
                        Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($pegawai as $p)
                <tr class="hover:bg-slate-50/80 transition-colors duration-200 group">
                    {{-- Karyawan --}}
                    <td class="px-8 py-5 whitespace-nowrap">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm group-hover:bg-accent/10 group-hover:text-accent transition-colors">
                                {{ strtoupper(substr($p->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-800">{{ $p->name }}</div>
                                <div class="text-xs font-semibold text-slate-400 mt-0.5">
                                    <span class="text-slate-500"> {{ $p->username }}</span>
                                    @if($p->nik)
                                    · NIK {{ $p->nik }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>

                    {{-- Unit Kerja --}}
                    <td class="px-8 py-5 whitespace-nowrap">
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 border border-blue-100/50">
                            <i data-lucide="building-2" class="w-3 h-3"></i>
                            {{ $p->unitKerja?->nama_unit ?? 'Belum Diatur' }}
                        </span>
                    </td>

                    {{-- ✅ Hak Akses --}}
                    <td class="px-8 py-5 whitespace-nowrap">
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $roleColors[$p->role] ?? 'bg-slate-100 text-slate-600 border-slate-200/50' }}">
                            {{ $roleLabels[$p->role] ?? ucfirst($p->role) }}
                        </span>
                    </td>

                    {{-- ✅ Unit yang Dikelola --}}
                    <td class="px-8 py-5">
                        @forelse($p->managesUnits as $unit)
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 mb-1 mr-1 bg-indigo-50 text-indigo-600 border border-indigo-100/50 rounded-full text-[0.65rem] font-bold">
                            <i data-lucide="briefcase" class="w-3 h-3"></i>
                            {{ $unit->nama_unit }}
                        </span>
                        @empty
                        <span class="text-xs text-slate-400 font-medium">—</span>
                        @endforelse
                    </td>

                    {{-- Kontak --}}
                    <td class="px-8 py-5 whitespace-nowrap text-xs font-medium text-slate-500">
                        <div class="flex flex-col gap-1">
                            <span class="inline-flex items-center gap-1.5">
                                <i data-lucide="phone" class="w-3 h-3"></i>
                                {{ $p->nomor_whatsapp ?? '—' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <i data-lucide="mail" class="w-3 h-3"></i>
                                {{ $p->email ?? '—' }}
                            </span>
                        </div>
                    </td>

                    {{-- Aksi --}}
                    <td class="px-8 py-5 whitespace-nowrap text-right text-sm">
                        <div class="inline-flex gap-1">
                            <a href="/master-pegawai/{{ $p->id }}/edit"
                                class="p-2 rounded-lg text-slate-400 hover:text-amber-500 hover:bg-amber-50 transition-colors inline-block"
                                title="Edit">
                                <i data-lucide="pencil" class="w-5 h-5"></i>
                            </a>
                            <form action="/master-pegawai/{{ $p->id }}" method="POST"
                                onsubmit="return confirm('Yakin ingin menghapus pegawai ini?');" class="inline-block">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="p-2 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors"
                                    title="Hapus">
                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-8 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center">
                                <i data-lucide="users" class="w-8 h-8 text-slate-400"></i>
                            </div>
                            <p class="text-sm font-bold text-slate-600">Belum ada data pegawai</p>
                            <p class="text-xs text-slate-400">Tambahkan pegawai baru atau import via CSV</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
@endsection