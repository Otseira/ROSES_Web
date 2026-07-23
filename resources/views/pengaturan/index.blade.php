@extends('layouts.app')
@section('title', 'Pengaturan Sistem')
@section('page_title', 'Pengaturan Global Aplikasi')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8 max-w-5xl mb-10">

    <div class="mb-8 pb-6 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-rose-500 text-white rounded-xl flex items-center justify-center shadow-lg shadow-rose-500/20">
                <i data-lucide="settings-2" class="w-6 h-6"></i>
            </div>
            <div>
                <span class="inline-block bg-rose-100 text-rose-700 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md mb-1.5 border border-rose-200">
                    👑 Zona Super Admin
                </span>
                <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Variabel Lingkungan</h3>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 px-5 py-4 rounded-xl text-sm mb-8 flex gap-3 items-start">
            <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
            <span class="font-bold">{{ session('success') }}</span>
        </div>
    @endif

    <form action="/pengaturan/update" method="POST" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-1">
                <label class="block text-sm font-bold text-slate-700 mb-3">Logo Rumah Sakit</label>
                <div class="border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center hover:bg-slate-50 transition-colors">
                    @if($pengaturan->logo)
                        <img src="{{ asset('storage/' . $pengaturan->logo) }}" alt="Logo Instansi" class="h-32 object-contain mx-auto mb-4 drop-shadow-md">
                    @else
                        <div class="h-32 bg-slate-100 rounded-xl flex items-center justify-center mb-4">
                            <i data-lucide="image" class="w-10 h-10 text-slate-300"></i>
                        </div>
                    @endif
                    
                    <input type="file" name="logo" id="logo" accept="image/*" class="hidden">
                    <label for="logo" class="cursor-pointer bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-xs font-bold hover:border-accent hover:text-accent transition-colors inline-block">
                        <i data-lucide="upload" class="w-3 h-3 inline mr-1"></i> Unggah Logo Baru
                    </label>
                    <p class="text-[10px] text-slate-400 mt-2 font-medium">Format: JPG, PNG (Maks 2MB)</p>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Nama Instansi / Rumah Sakit</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i data-lucide="building-2" class="w-5 h-5"></i></div>
                        <input type="text" name="nama_instansi" value="{{ $pengaturan->nama_instansi }}" required class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 outline-none font-bold text-slate-800">
                    </div>
                </div>

                <div class="bg-blue-50/50 p-5 rounded-2xl border border-blue-100">
                    <h4 class="text-sm font-bold text-blue-800 mb-4 flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4"></i> Konfigurasi GPS (Geofencing)</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Titik Latitude</label>
                            <input type="text" name="latitude" value="{{ $pengaturan->latitude }}" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none text-sm font-mono text-slate-700">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Titik Longitude</label>
                            <input type="text" name="longitude" value="{{ $pengaturan->longitude }}" required class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none text-sm font-mono text-slate-700">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Radius Absensi (Dalam Meter)</label>
                        <div class="flex items-center gap-3">
                            <input type="number" name="radius_meter" value="{{ $pengaturan->radius_meter }}" required class="w-32 px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none text-sm font-bold text-slate-700">
                            <span class="text-xs font-medium text-slate-500">Jarak maksimal pegawai diizinkan menekan tombol absen di aplikasi mobile.</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
            <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white px-8 py-3.5 rounded-xl text-sm font-bold shadow-lg shadow-rose-500/20 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Pengaturan Global
            </button>
        </div>
    </form>
</div>
@endsection