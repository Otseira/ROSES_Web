<!-- resources/views/pegawai/create.blade.php -->
@extends('layouts.app')
@section('title', 'Tambah Pegawai')
@section('page_title', 'Registrasi Pegawai')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8 max-w-4xl mb-10">

    <div class="mb-8 pb-6 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div
                class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center shadow-lg shadow-primary/20">
                <i data-lucide="user-plus" class="w-6 h-6"></i>
            </div>
            <div>
                <span
                    class="inline-block bg-accent/10 text-accent text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md mb-1.5">
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
                <input type="text" name="nik" value="{{ old('nik') }}"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('nik') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Username Login</label>
                <input type="text" name="username" value="{{ old('username') }}" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 transition-all text-sm font-bold text-slate-800 outline-none">
                @error('username') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('name') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Email Utama</label>
                <input type="email" name="email" value="{{ old('email') }}"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('email') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Nomor WhatsApp</label>
                <input type="text" name="nomor_whatsapp" value="{{ old('nomor_whatsapp') }}"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all text-sm font-semibold text-slate-800">
                @error('nomor_whatsapp') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message
                    }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Unit Kerja Utama (Domisili)</label>
                <select name="unit_kerja_id" id="unit_kerja_id" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-bold text-slate-700">
                    <option value="">-- Pilih Unit --</option>
                    @foreach($unitKerja as $unit)
                    <option value="{{ $unit->id }}" {{ old('unit_kerja_id')==$unit->id ? 'selected' : '' }}>
                        {{ $unit->nama_unit }}
                    </option>
                    @endforeach
                </select>
                @error('unit_kerja_id') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message
                    }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Hak Akses Sistem</label>
                <select name="role" id="role_select" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-accent/20 focus:border-accent outline-none transition-all text-sm font-bold text-slate-700">
                    <option value="staf" {{ old('role')=='staf' ? 'selected' : '' }}>Staf / Karyawan Pelaksana (Akses
                        Mobile Saja)</option>
                    <option value="kepala_unit" {{ old('role')=='kepala_unit' ? 'selected' : '' }}>Kepala Unit Kerja
                    </option>
                    <option value="penanggung_jawab" {{ old('role')=='penanggung_jawab' ? 'selected' : '' }}>Penanggung
                        Jawab Shift</option>
                    <option value="manajer" {{ old('role')=='manajer' ? 'selected' : '' }}>Manajer (Multi-Unit)</option>
                    <option value="direktur" {{ old('role')=='direktur' ? 'selected' : '' }}>Direktur (Akses Global)
                    </option>
                    <option value="hrd" {{ old('role')=='hrd' ? 'selected' : '' }}>HRD / Payroll</option>
                    @if(auth()->user()->role === 'superadmin')
                    <option value="superadmin" {{ old('role')=='superadmin' ? 'selected' : '' }}>Administrator</option>
                    @endif
                </select>
                @error('role') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Kata Sandi Awal</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 outline-none transition-all text-sm font-semibold text-slate-800">
                @error('password') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- BAGIAN BARU: Unit yang Dikelola (Muncul jika role = Kepala Unit, PJ, atau Manajer) -->
        <div id="unit_kelola_container"
            class="hidden md:col-span-2 bg-blue-50/50 border border-blue-100 rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-8 h-8 bg-blue-500 text-white rounded-lg flex items-center justify-center">
                    <i data-lucide="building-2" class="w-4 h-4"></i>
                </div>
                <div>
                    <h4 class="font-bold text-blue-900 text-sm">Unit Kerja yang Dikelola</h4>
                    <p class="text-xs text-blue-600 font-medium">Pilih satu atau lebih unit yang akan diawasi oleh
                        pegawai ini.</p>
                </div>
            </div>

            <div
                class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-60 overflow-y-auto p-2 bg-white rounded-xl border border-blue-200">
                @foreach($unitKerja as $unit)
                <label
                    class="flex items-center gap-2 p-3 bg-slate-50 hover:bg-blue-50 rounded-lg cursor-pointer transition-all border border-transparent hover:border-blue-200">
                    <input type="checkbox" name="manages_units[]" value="{{ $unit->id }}"
                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" {{
                        (is_array(old('manages_units')) && in_array($unit->id, old('manages_units'))) ? 'checked' : ''
                    }}>
                    <span class="text-sm font-semibold text-slate-700">{{ $unit->nama_unit }}</span>
                </label>
                @endforeach
            </div>
            @error('manages_units') <span class="text-xs text-rose-500 font-semibold mt-2 block">{{ $message }}</span>
            @enderror
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('role_select');
        const unitKelolaContainer = document.getElementById('unit_kelola_container');

        // Role yang memicu munculnya opsi unit kelola
        const rolesWithUnitManagement = ['kepala_unit', 'penanggung_jawab', 'manajer'];

        function toggleUnitKelola() {
            if (rolesWithUnitManagement.includes(roleSelect.value)) {
                unitKelolaContainer.classList.remove('hidden');
            } else {
                unitKelolaContainer.classList.add('hidden');
                // Uncheck semua saat disembunyikan agar tidak terkirim
                unitKelolaContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            }
        }

        roleSelect.addEventListener('change', toggleUnitKelola);

        // Jalankan saat halaman pertama kali dimuat (misal jika ada error validasi dan role sudah terpilih)
        toggleUnitKelola();

        // Inisialisasi ikon Lucide
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
@endsection