@extends('layouts.app')
@section('title', 'Atur Hak Akses')
@section('page_title', 'Atur Hak Akses')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-8 max-w-4xl mb-10">
    
    <div class="flex items-center gap-5 mb-10 pb-8 border-b border-slate-100">
        <div class="w-16 h-16 rounded-[1.25rem] bg-gradient-to-tr from-accent to-blue-400 flex items-center justify-center text-white font-extrabold text-2xl shadow-lg shadow-accent/30">
            {{ substr($user->name, 0, 1) }}
        </div>
        <div>
            <h3 class="text-2xl font-extrabold text-slate-800 tracking-tight">{{ $user->name }}</h3>
            <p class="text-sm font-medium text-slate-500 mt-1">NIK: {{ $user->nik }} <span class="mx-2 text-slate-300">•</span> Unit: <span class="text-slate-700 font-bold">{{ $user->unitKerja->nama_unit ?? '-' }}</span></p>
        </div>
    </div>

    <form action="/hak-akses/{{ $user->id }}" method="POST">
        @csrf 
        @method('PUT')
        
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <label class="block text-base font-extrabold text-slate-800">Daftar Modul Sistem</label>
                <p class="text-xs font-medium text-slate-500 mt-1">Klik kartu di bawah ini untuk memberikan atau mencabut akses fitur.</p>
            </div>
            <span class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg text-[0.7rem] font-bold border border-blue-100 uppercase tracking-widest inline-block text-center">Multi-Select</span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10">
            @foreach($moduls as $modul)
                @php 
                    $punyaAkses = $user->moduls->pluck('id')->contains($modul->id); 
                @endphp
                
                <label class="relative flex items-start gap-4 p-5 rounded-[1.25rem] border-2 cursor-pointer transition-all duration-300 hover:shadow-md group {{ $punyaAkses ? 'border-accent bg-accent/5' : 'border-slate-100 bg-slate-50 hover:border-slate-200' }}">
                    <div class="flex-shrink-0 mt-0.5">
                        <input type="checkbox" name="moduls[]" value="{{ $modul->id }}" 
                            {{ $punyaAkses ? 'checked' : '' }} 
                            class="peer w-5 h-5 text-accent bg-white border-slate-300 rounded focus:ring-accent focus:ring-offset-0 transition-colors cursor-pointer">
                    </div>
                    <div class="flex flex-col z-10">
                        <span class="text-sm font-extrabold {{ $punyaAkses ? 'text-accent' : 'text-slate-700' }}">{{ $modul->nama_modul }}</span>
                        <span class="text-xs font-semibold text-slate-400 mt-1"><i data-lucide="code" class="w-3 h-3 inline-block align-baseline mr-0.5"></i> {{ $modul->kode_modul }}</span>
                    </div>
                    
                    @if($punyaAkses)
                    <div class="absolute top-4 right-4 text-accent/10 pointer-events-none">
                        <i data-lucide="check-circle-2" class="w-12 h-12"></i>
                    </div>
                    @endif
                </label>
            @endforeach
        </div>

        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
            <a href="/hak-akses" class="px-6 py-3.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Batal</a>
            <button type="submit" class="bg-primary hover:bg-slate-800 text-white px-8 py-3.5 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Hak Akses
            </button>
        </div>
    </form>
</div>
@endsection