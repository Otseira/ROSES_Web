@extends('layouts.app')
@section('title', 'Rekapitulasi Laporan Lembur/On Call')
@section('page_title', 'Rekap Lembur/On Call')

@php
    $namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $thNow = (int) date('Y');
    $badge = [
        'Pending'   => '#f59e0b',
        'Disetujui' => '#16a34a',
        'Ditolak'   => '#dc2626',
    ];
@endphp

<div style="max-width:1200px;margin:0 auto;padding:16px;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;color:#1f2937">

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <h2 style="margin:0;font-size:20px;font-weight:700">Rekap Lembur / On‑Call</h2>
        <span style="font-size:12px;color:#6b7280">
            @if(in_array($role, ['kepala_unit','penanggung_jawab']))
                🔒 Ditampilkan hanya untuk unit Anda
            @else
                Seluruh unit kerja
            @endif
        </span>
    </div>

    {{-- Kartu ringkasan --}}
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
        <div style="flex:1;min-width:150px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px">
            <div style="font-size:12px;color:#6b7280">Total Pengajuan</div>
            <div style="font-size:22px;font-weight:700">{{ $totalPengajuan }}</div>
        </div>
        <div style="flex:1;min-width:150px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px">
            <div style="font-size:12px;color:#6b7280">Total Jam (Disetujui)</div>
            <div style="font-size:22px;font-weight:700;color:#16a34a">{{ number_format($totalJamDisetujui,2,',','.') }} jam</div>
        </div>
        <div style="flex:1;min-width:150px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px">
            <div style="font-size:12px;color:#6b7280">Menunggu Validasi</div>
            <div style="font-size:22px;font-weight:700;color:#f59e0b">{{ $jmlPending }}</div>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('laporan.lembur') }}"
          style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div>
            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px">Bulan</label>
            <select name="bulan" style="padding:8px;border:1px solid #d1d5db;border-radius:8px">
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}" @selected($bulan==$m)>{{ $namaBulan[$m] }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px">Tahun</label>
            <select name="tahun" style="padding:8px;border:1px solid #d1d5db;border-radius:8px">
                @for($y=$thNow+1;$y>=$thNow-3;$y--)
                    <option value="{{ $y }}" @selected($tahun==$y)>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px">Status</label>
            <select name="status" style="padding:8px;border:1px solid #d1d5db;border-radius:8px">
                <option value="">Semua</option>
                <option value="Pending"   @selected($statusFilter=='Pending')>Pending</option>
                <option value="Disetujui" @selected($statusFilter=='Disetujui')>Disetujui</option>
                <option value="Ditolak"   @selected($statusFilter=='Ditolak')>Ditolak</option>
            </select>
        </div>
        <button type="submit" style="padding:9px 18px;background:#166534;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer">Terapkan</button>
    </form>

    {{-- Tabel --}}
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:920px">
                <thead>
                    <tr style="background:#f3f4f6;text-align:left">
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">#</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Pegawai</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Unit</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Jenis</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Tanggal</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Jam Masuk</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Jam Keluar</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Durasi</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Jarak dari RS</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Status</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb">Alasan / Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $i => $r)
                    <tr style="border-bottom:1px solid #f1f5f9">
                        <td style="padding:10px">{{ $i+1 }}</td>
                        <td style="padding:10px">
                            <div style="font-weight:600">{{ $r['nama'] }}</div>
                            <div style="font-size:11px;color:#6b7280">{{ $r['nik'] }}</div>
                        </td>
                        <td style="padding:10px">{{ $r['unit'] }}</td>
                        <td style="padding:10px">{{ $r['jenis'] }}</td>
                        <td style="padding:10px">{{ $r['tanggal'] }}</td>
                        <td style="padding:10px">{{ $r['jam_masuk'] }}</td>
                        <td style="padding:10px">{{ $r['jam_keluar'] }}</td>
                        <td style="padding:10px;font-weight:600">{{ $r['durasi'] }}</td>
                        <td style="padding:10px">{{ $r['jarak'] }}</td>
                        <td style="padding:10px">
                            @php $bg = $badge[$r['status']] ?? '#6b7280'; @endphp
                            <span style="display:inline-block;padding:3px 10px;border-radius:999px;color:#fff;font-size:11px;font-weight:600;background:{{ $bg }}">{{ $r['status'] }}</span>
                        </td>
                        <td style="padding:10px;color:#374151">{{ $r['alasan'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" style="padding:24px;text-align:center;color:#6b7280">Tidak ada data lembur / on‑call pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection