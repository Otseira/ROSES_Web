@extends('layouts.app')
@section('title', 'Master Unit Kerja')
@section('page_title', 'Master Unit Kerja')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">

    {{-- Header & Action --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h3 class="text-xl font-extrabold text-slate-800">Daftar Unit Kerja</h3>
            <p class="text-sm text-slate-500 mt-1">Kelola departemen, instalasi, dan kebijakan lembur unit operasional rumah sakit.</p>
        </div>
        <a href="{{ route('master-unit.create') }}"
            class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md transition-all active:scale-95 flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Unit Kerja
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div class="mb-5 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-emerald-700 flex items-center gap-2">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if($errors->any())
    <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm font-bold text-rose-700 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
        <span>{{ $errors->first() }}</span>
    </div>
    @endif

    {{-- Search Bar --}}
    <div class="mb-6">
        <form method="GET" action="{{ route('master-unit.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Cari nama unit kerja atau deskripsi..."
                    class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all">
            </div>
            <div class="flex gap-2">
                <button type="submit"
                    class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-bold transition-all flex items-center gap-2">
                    <i data-lucide="filter" class="w-4 h-4"></i> Cari
                </button>
                @if(request('search'))
                <a href="{{ route('master-unit.index') }}"
                    class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm font-bold transition-all flex items-center gap-1">
                    <i data-lucide="x" class="w-4 h-4"></i> Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-100">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                    <th class="px-5 py-4 w-16 text-center">No</th>
                    <th class="px-5 py-4">Nama Unit Kerja</th>
                    <th class="px-5 py-4">Deskripsi / Keterangan</th>
                    <th class="px-5 py-4 text-center">Kebijakan Lembur</th>
                    <th class="px-5 py-4 text-center">Jumlah Pegawai</th>
                    <th class="px-5 py-4 text-center w-28">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($units as $index => $unit)
                <tr class="hover:bg-slate-50/60 transition-all">
                    <td class="px-5 py-4 text-sm font-bold text-slate-400 text-center">
                        {{ $units->firstItem() + $index }}
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black text-xs">
                                <i data-lucide="building-2" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <span class="text-sm font-extrabold text-slate-800 block">{{ $unit->nama_unit }}</span>
                                <span class="text-[11px] text-slate-400">ID: #{{ $unit->id }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <span class="text-sm font-medium text-slate-600">
                            {{ $unit->deskripsi ?: '— Tidak ada keterangan —' }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-center">
                        @if(($unit->jenis_lembur ?? 'dibayar') === 'ganti_jam')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i>
                            Ganti Jam Kerja
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-600"></i>
                            Lembur Dibayar
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-center">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $unit->users_count > 0 ? 'bg-sky-50 text-sky-700 border border-sky-100' : 'bg-slate-100 text-slate-400' }}">
                            <i data-lucide="users" class="w-3.5 h-3.5"></i>
                            {{ $unit->users_count }} Pegawai
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex justify-center gap-2">
                            <a href="{{ route('master-unit.edit', $unit->id) }}"
                                class="p-2 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-all"
                                title="Edit Unit">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <form action="{{ route('master-unit.destroy', $unit->id) }}" method="POST"
                                onsubmit="return confirm('Apakah Anda yakin ingin menghapus unit kerja \'{{ $unit->nama_unit }}\'?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-all"
                                    title="Hapus Unit">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm font-bold text-slate-400">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <i data-lucide="inbox" class="w-8 h-8 text-slate-300"></i>
                            <span>Belum ada data unit kerja yang ditemukan.</span>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($units->hasPages())
    <div class="mt-6">
        {{ $units->links() }}
    </div>
    @endif

</div>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection
