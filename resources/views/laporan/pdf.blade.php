<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Laporan Rekap Absensi — {{ now()->month($bulan)->translatedFormat('F Y') }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            background: #f1f5f9;
        }

        .page {
            max-width: 1150px;
            margin: 20px auto;
            background: #fff;
            padding: 30px 34px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .10);
            border-radius: 12px;
        }

        /* Toolbar — otomatis hilang saat dicetak */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1150px;
            margin: 16px auto 0;
            padding: 0 8px;
        }

        .toolbar .hint {
            color: #64748b;
            font-size: 11px;
        }

        .toolbar .btn {
            background: #14532d;
            color: #fff;
            border: none;
            padding: 10px 24px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
        }

        .toolbar .btn:hover {
            background: #0f3d21;
        }

        /* Kop laporan */
        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #14532d;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }

        .head h1 {
            margin: 0;
            font-size: 16px;
            color: #14532d;
            letter-spacing: .3px;
        }

        .head p {
            margin: 3px 0 0;
            color: #64748b;
            font-size: 10px;
        }

        .head .right {
            text-align: right;
        }

        .period {
            display: inline-block;
            background: #14532d;
            color: #fff;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
        }

        .gen {
            color: #94a3b8;
            font-size: 9px;
            margin-top: 5px;
        }

        /* Kartu statistik */
        .stats {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }

        .stat {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .stat .num {
            font-size: 18px;
            font-weight: 800;
        }

        .stat .lbl {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #64748b;
            font-weight: 700;
        }

        .dark .num {
            color: #14532d;
        }

        .green .num {
            color: #16a34a;
        }

        .red .num {
            color: #dc2626;
        }

        .amber .num {
            color: #d97706;
        }

        .blue .num {
            color: #2563eb;
        }

        /* Tabel */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #14532d;
            color: #fff;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: .6px;
            padding: 8px;
            text-align: left;
        }

        thead th:first-child {
            border-radius: 8px 0 0 8px;
        }

        thead th:last-child {
            border-radius: 0 8px 8px 0;
        }

        tbody td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr {
            page-break-inside: avoid;
        }

        .muted {
            color: #94a3b8;
        }

        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.ok {
            background: #dcfce7;
            color: #15803d;
        }

        .badge.late {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge.out {
            background: #fef3c7;
            color: #b45309;
        }

        .chip {
            display: inline-block;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            padding: 1px 7px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 700;
            margin: 1px 2px 1px 0;
        }

        /* Tanda tangan */
        .foot {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .sig {
            text-align: center;
            font-size: 10px;
            color: #475569;
            width: 260px;
        }

        .sig .space {
            height: 62px;
        }

        .sig .name {
            font-weight: 700;
            color: #1e293b;
        }

        .note {
            margin-top: 16px;
            color: #94a3b8;
            font-size: 9px;
            border-top: 1px dashed #e2e8f0;
            padding-top: 8px;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                margin: 0;
                padding: 0;
                max-width: 100%;
                box-shadow: none;
                border-radius: 0;
            }

            .toolbar {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="toolbar">
        <span class="hint">Pratinjau laporan — klik tombol untuk mencetak / menyimpan sebagai PDF.</span>
        <button class="btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    </div>

    @php
    $total = $logs->count();
    $tepat = $logs->where('status_kehadiran', 'Tepat Waktu')->count();
    $telat = $logs->where('status_kehadiran', 'Terlambat')->count();
    $luar = $logs->where('status_kehadiran', 'Luar Jadwal')->count();
    $menitLate = (int) $logs->sum('menit_terlambat');
    $jamLembur = round($lemburs->flatten()->sum('total_jam_lembur'), 1);
    @endphp

    <div class="page">

        {{-- Kop --}}
        <div class="head">
            <div>
                <h1>RSKB ROPANASURI — LAPORAN REKAP ABSENSI & LEMBUR</h1>
                <p>Sistem Informasi Absensi (SIRO) • absensi.ropanasuri.com</p>
            </div>
            <div class="right">
                <span class="period">{{ now()->month($bulan)->translatedFormat('F Y') }}</span>
                <div class="gen">Dicetak: {{ now()->format('d M Y, H:i') }} WIB</div>
            </div>
        </div>

        {{-- Statistik --}}
        <div class="stats">
            <div class="stat dark">
                <div class="num">{{ $total }}</div>
                <div class="lbl">Total Absensi</div>
            </div>
            <div class="stat green">
                <div class="num">{{ $tepat }}</div>
                <div class="lbl">Tepat Waktu</div>
            </div>
            <div class="stat red">
                <div class="num">{{ $telat }}</div>
                <div class="lbl">Terlambat ({{ $menitLate }} mnt)</div>
            </div>
            <div class="stat amber">
                <div class="num">{{ $luar }}</div>
                <div class="lbl">Luar Jadwal</div>
            </div>
            <div class="stat blue">
                <div class="num">{{ number_format($jamLembur, 1) }}</div>
                <div class="lbl">Jam Lembur</div>
            </div>
        </div>

        {{-- Tabel --}}
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Unit Kerja</th>
                    <th>Tanggal</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Durasi</th>
                    <th>Status</th>
                    <th>Telat</th>
                    <th>Jarak ke Pusat</th>
                    <th>Lembur / On-Call</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                @php
                $nama = $log->user?->name ?? $log->roster?->user?->name ?? '-';
                $unitNama = $log->user?->unitKerja?->nama_unit ?? $log->roster?->user?->unitKerja?->nama_unit ?? '-';
                $key = ($log->user_id ?? $log->roster?->user_id) . '|' . optional($log->waktu_masuk)->toDateString();
                $items = $lemburs->get($key);
                @endphp
                <tr>
                    <td><b>{{ $nama }}</b></td>
                    <td>{{ $unitNama }}</td>
                    <td>{{ optional($log->waktu_masuk)->format('d M Y') ?? '-' }}</td>
                    <td>{{ optional($log->waktu_masuk)->format('H:i') ?? '-' }}</td>
                    <td>{{ optional($log->waktu_pulang)->format('H:i') ?? '<span class=muted>-</span>' }}</td>
                    <td>{{ $log->durasi_kerja ?? '<span class=muted>-</span>' }}</td>
                    <td>
                        <span
                            class="badge {{ $log->status_kehadiran == 'Tepat Waktu' ? 'ok' : ($log->status_kehadiran == 'Terlambat' ? 'late' : 'out') }}">
                            {{ $log->status_kehadiran }}
                        </span>
                    </td>
                    <td>{{ ($log->menit_terlambat ?? 0) > 0 ? $log->menit_terlambat . ' mnt' : '—' }}</td>
                    <td>{{ $log->jarak !== null ? $log->jarak . ' m' : '-' }}</td>
                    <td>
                        @if($items && $items->isNotEmpty())
                        @foreach($items as $l)
                        <span class="chip">{{ $l->jenis_lembur }} • {{ number_format($l->total_jam_lembur ?? 0, 1) }}
                            jam • {{ $l->status_validasi }}</span>
                        @endforeach
                        @else <span class="muted">—</span> @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center; padding:20px;" class="muted">Tidak ada data absensi pada
                        periode ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Tanda tangan --}}
        <div class="foot">
            <div class="sig">
                Disiapkan oleh,
                <div class="space"></div>
                <span class="name">( .......................................... )</span><br>
                HRD / Administrasi
            </div>
            <div class="sig">
                Mengetahui,
                <div class="space"></div>
                <span class="name">( .......................................... )</span><br>
                Direktur RSKB Ropanasuri
            </div>
        </div>

        <p class="note">Dokumen ini dibuat otomatis oleh SIRO — Sistem Informasi Absensi Ropanasuri. Data bersifat final
            sesuai log perangkat pada tanggal pencetakan.</p>
    </div>

</body>

</html>