@extends('layouts.app')
@section('title', 'Tambah Pegawai')
@section('page_title', 'Registrasi Pegawai')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8 max-w-4xl mb-10">

    <div class="mb-8 pb-6 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center shadow-lg shadow-primary/20">
                <i data-lucide="user-plus" class="w-6 h-6"></i>
            </div>
            <div>
                <span class="inline-block bg-accent/10 text-accent text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md mb-1.5">
                    ✨ Form Tambah Data Baru
                </span>
                <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Data Pegawai</h3>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm font-semibold">
            <p>Mohon periksa kembali form inputan Anda. Terjadi beberapa kesalahan pengisian data.</p>
        </div>
    @endif

    <form action="/master-pegawai" method="POST" class="space-y-8">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">NIK Karyawan</label>
                <input type="text" name="nik" value="{{ old('nik') }}" required
                    class="w-full px-4 py-3 bg-slate-50 border @error('nik') border-rose-400 focus:ring-rose-200 focus:border-rose-500 @else border-slate-200 focus:ring-accent/20 focus:border-accent @enderror rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('nik') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Username Login</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="at-sign" class="w-5 h-5"></i>
                    </div>
                    <input type="text" name="username" value="{{ old('username') }}" required
                        class="w-full pl-11 pr-4 py-3 bg-slate-50 border @error('username') border-rose-400 focus:ring-rose-200 focus:border-rose-500 @else border-slate-200 focus:ring-accent/20 focus:border-accent @enderror rounded-xl focus:bg-white focus:ring-2 transition-all text-sm font-bold text-slate-800 outline-none"
                        placeholder="Tanpa spasi, misal: joko_susilo">
                </div>
                @error('username') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-3 bg-slate-50 border @error('name') border-rose-400 focus:ring-rose-200 focus:border-rose-500 @else border-slate-200 focus:ring-accent/20 focus:border-accent @enderror rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('name') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Email Utama</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-4 py-3 bg-slate-50 border @error('email') border-rose-400 focus:ring-rose-200 focus:border-rose-500 @else border-slate-200 focus:ring-accent/20 focus:border-accent @enderror rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('email') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Nomor WhatsApp</label>
                <input type="text" name="nomor_whatsapp" value="{{ old('nomor_whatsapp') }}"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all text-sm font-semibold text-slate-800"
                    placeholder="Contoh: 081234567890">
                @error('nomor_whatsapp') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Unit Kerja</label>
                <select name="unit_kerja_id" required
                    class="w-full px-4 py-3 bg-slate-50 border @error('unit_kerja_id') border-rose-400 focus:ring-rose-200 focus:border-rose-500 @else border-slate-200 focus:ring-accent/20 focus:border-accent @enderror rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-bold text-slate-700">
                    <option value="">-- Pilih Unit --</option>
                    @foreach($unitKerja as $unit)
                        <option value="{{ $unit->id }}" {{ old('unit_kerja_id') == $unit->id ? 'selected' : '' }}>
                            {{ $unit->nama_unit }}
                        </option>
                    @endforeach
                </select>
                @error('unit_kerja_id') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Hak Akses Sistem</label>
                <select name="role" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all text-sm font-bold text-slate-700">
                    <option value="staf" {{ old('role') == 'staf' ? 'selected' : '' }}>Staf / Karyawan Pelaksana (Akses Mobile Saja)</option>
                    <option value="kepala_unit" {{ old('role') == 'kepala_unit' ? 'selected' : '' }}>Kepala Unit Kerja (Akses Web Terkunci Unit)</option>
                    <option value="penanggung_jawab" {{ old('role') == 'penanggung_jawab' ? 'selected' : '' }}>Penanggung Jawab Shift (Akses Web Terkunci Unit)</option>
                    <option value="hrd" {{ old('role') == 'hrd' ? 'selected' : '' }}>HRD / Payroll (Akses Rekap Global)</option>
                    @if(auth()->user()->role === 'superadmin')
                        <option value="superadmin" {{ old('role') == 'superadmin' ? 'selected' : '' }}>Administrator (Akses Penuh Seluruh Sistem)</option>
                    @endif
                </select>
                @error('role') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Kata Sandi Awal</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-3 bg-slate-50 border @error('password') border-rose-400 focus:ring-rose-200 focus:border-rose-500 @else border-slate-200 focus:ring-accent/20 focus:border-accent @enderror rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('password') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
            <a href="/master-pegawai"
                class="px-6 py-3.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-5 transition-all">Batal</a>
            <button type="submit"
                class="bg-primary hover:bg-primary/90 text-white px-8 py-3.5 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg shadow-primary/20 transition-all">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Data Baru
            </button>
        </div>
    </form>
</div>
@endsection