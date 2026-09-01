@extends('layouts.app')
@section('title', 'Master Shift')
@section('page_title', 'Master Shift')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h3 class="text-xl font-extrabold text-slate-800">Daftar Shift Operasional</h3>
            <p class="text-sm text-slate-500 mt-1">Kelola aturan jam kerja per unit kerja.</p>
        </div>
        <a href="{{ route('master-shift.create') }}"
            class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md transition-all active:scale-95 flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Shift
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm font-bold text-emerald-700">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-sm font-bold text-rose-700">
        ⚠️ {{ $errors->first() }}
    </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-slate-100">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-[0.7rem] font-black uppercase tracking-widest">
                    <th class="px-5 py-4">Nama Shift</th>
                    <th class="px-5 py-4">Unit Kerja</th>
                    <th class="px-5 py-4">Jam Masuk</th>
                    <th class="px-5 py-4">Jam Pulang</th>
                    <th class="px-5 py-4">Toleransi</th>
                    <th class="px-5 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($shifts as $shift)
                <tr class="hover:bg-slate-50/60 transition-all">
                    <td class="px-5 py-4 text-sm font-bold text-slate-800 whitespace-nowrap">{{ $shift->nama_shift }}
                    </td>
                    <td class="px-5 py-4">
                        @if($shift->unitKerja)
                        <span
                            class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-50 text-sky-600 border border-sky-100">
                            🏢 {{ $shift->unitKerja->nama_unit }}
                        </span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">{{ $shift->unitKerja->deskripsi }}</span>
                        @else
                        <span
                            class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-400">
                            Umum / Semua Unit
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-sm font-semibold text-slate-600">{{ substr((string) $shift->jam_masuk, 0,
                        5) }}</td>
                    <td class="px-5 py-4 text-sm font-semibold text-slate-600">{{ substr((string) $shift->jam_pulang, 0,
                        5) }}</td>
                    <td class="px-5 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap">{{
                        $shift->toleransi_terlambat_menit }} mnt</td>
                    <td class="px-5 py-4">
                        <div class="flex justify-center gap-2">
                            <a href="{{ route('master-shift.edit', $shift->id) }}"
                                class="p-2 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-all"
                                title="Edit">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <form action="{{ route('master-shift.destroy', $shift->id) }}" method="POST"
                                onsubmit="return confirm('Yakin ingin menghapus shift ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-all"
                                    title="Hapus">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm font-bold text-slate-400">Belum ada data
                        shift.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection