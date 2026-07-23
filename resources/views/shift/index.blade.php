@extends('layouts.app')

@section('title', 'Master Shift')
@section('page_title', 'Pengaturan Master Shift')

@section('content')
<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-bold text-gray-800">Daftar Shift Operasional</h3>
        <a href="/master-shift/create" class="bg-slate-800 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-slate-700 transition-colors">
            + Tambah Shift
        </a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 mb-6 rounded text-emerald-700 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded text-red-700 text-sm font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="overflow-x-auto border border-gray-200 rounded-md">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Shift</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jam Masuk</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Jam Pulang</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Toleransi</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($shifts as $s)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-slate-800">{{ $s->nama_shift }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-mono text-emerald-600 font-semibold">{{ \Carbon\Carbon::parse($s->jam_masuk)->format('H:i') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-mono text-rose-600 font-semibold">{{ \Carbon\Carbon::parse($s->jam_pulang)->format('H:i') }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-600">{{ $s->toleransi_terlambat_menit }} Menit</td>
                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end gap-3">
                            <a href="/master-shift/{{ $s->id }}/edit" class="text-blue-600 hover:text-blue-900">Ubah</a>
                            <form action="/master-shift/{{ $s->id }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus shift ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection