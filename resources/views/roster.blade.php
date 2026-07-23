@extends('layouts.app')
@section('title', 'Matriks Roster')
@section('page_title', 'Roster Jadwal Operasional')

@section('content')
<div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100/60 p-6 md:p-8">
    
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-5 bg-slate-50/50 p-4 md:p-6 rounded-[1.5rem] border border-slate-100">
        <div>
            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Periode: {{ \Carbon\Carbon::createFromDate($tahun, $bulan)->translatedFormat('F Y') }}</h3>
            <p class="text-sm text-slate-500 mt-1 font-medium">Susun jadwal massal. Sistem akan mengunci nama staf di sebelah kiri.</p>
        </div>
        
        <form method="GET" action="/roster" class="flex flex-wrap gap-3">
            <select name="bulan" class="bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 shadow-sm appearance-none outline-none cursor-pointer">
                @for($i = 1; $i <= 12; $i++)
                    <option value="{{ $i }}" {{ $bulan == $i ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($i)->translatedFormat('F') }}</option>
                @endfor
            </select>
            <select name="tahun" class="bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-accent/20 focus:border-accent px-5 py-3 shadow-sm appearance-none outline-none cursor-pointer">
                @for($i = date('Y') - 1; $i <= date('Y') + 1; $i++)
                    <option value="{{ $i }}" {{ $tahun == $i ? 'selected' : '' }}>{{ $i }}</option>
                @endfor
            </select>
            <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-md transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="filter" class="w-4 h-4"></i> Terapkan
            </button>
        </form>
    </div>

    <form action="{{ url('/roster/bulk-store') }}" method="POST" id="rosterForm">
        @csrf
        <div class="overflow-x-auto border border-slate-100 rounded-2xl pb-4">
            <table class="min-w-full relative border-collapse">
                <thead class="bg-slate-800 text-white">
                    <tr>
                        <th class="sticky left-0 bg-slate-900 px-6 py-4 text-left text-xs font-bold uppercase tracking-widest z-20 w-64 shadow-[4px_0_15px_-3px_rgba(0,0,0,0.3)]">
                            Nama Pegawai
                        </th>
                        @for($i = 1; $i <= $jumlahHari; $i++)
                            <th class="px-2 py-4 text-center text-xs font-bold uppercase tracking-widest min-w-[110px] border-l border-slate-700/50">
                                {{ $i }}
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @foreach($staf as $pegawai)
                    <tr class="hover:bg-slate-50/80 transition-colors group border-b border-slate-100">
                        <td class="sticky left-0 bg-white group-hover:bg-slate-50 px-6 py-3 whitespace-nowrap text-sm font-extrabold text-slate-800 z-10 border-r border-slate-100 shadow-[4px_0_15px_-3px_rgba(0,0,0,0.05)] transition-colors">
                            {{ $pegawai->name }}
                        </td>
                        @for($i = 1; $i <= $jumlahHari; $i++)
                            @php
                                $tanggalSekarang = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                                $rosterHariIni = $pegawai->rosters->firstWhere('tanggal_dinas', $tanggalSekarang);
                            @endphp
                            <td class="px-1 py-1 border-l border-slate-100">
                                <select name="roster[{{ $pegawai->id }}][{{ $tanggalSekarang }}]" 
                                        class="w-full text-xs font-bold text-slate-600 bg-transparent hover:bg-slate-100 focus:bg-white border-2 border-transparent focus:border-accent focus:ring-0 rounded-lg py-2.5 px-1 text-center transition-all appearance-none cursor-pointer outline-none">
                                    <option value="" class="text-slate-400 font-medium">Libur</option>
                                    @foreach($shifts as $shift)
                                        <option value="{{ $shift->id }}" {{ ($rosterHariIni && $rosterHariIni->shift_id == $shift->id) ? 'selected' : '' }}>
                                            {{ $shift->nama_shift }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        @endfor
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('rosterForm');

    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault(); 

            Swal.fire({
                title: 'Menyimpan Jadwal...',
                text: 'Sistem sedang memproses matriks roster ke database.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            // --- LETAK FILE HEADERS DI SINI ---
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                // TAMBAHKAN BARIS INI:
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            // ----------------------------------
            body: formData
        });

                const result = await response.json();

                if (response.ok && result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Tersimpan!',
                        text: result.message,
                        confirmButtonColor: '#3B82F6'
                    }).then(() => {
                        window.location.reload(); // Segarkan halaman agar matriks terbaru muncul
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan',
                        text: result.message,
                        confirmButtonColor: '#EF4444'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Jaringan',
                    text: 'Tidak dapat terhubung ke server.',
                    confirmButtonColor: '#EF4444'
                });
            }
        });
    }
});
</script>

        <div class="mt-8 flex justify-end">
            <button type="submit" class="bg-accent hover:bg-sky-500 text-white px-8 py-3.5 rounded-xl text-sm font-extrabold shadow-lg shadow-accent/30 transition-all active:scale-95 flex items-center gap-2">
                <i data-lucide="cloud-upload" class="w-5 h-5"></i> Simpan & Publikasikan
            </button>
        </div>
    </form>
</div>
@endsection