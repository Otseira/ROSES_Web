<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Laporan Rekap Absensi — {{ $periodLabel ?? now()->month($bulan)->translatedFormat('F Y') }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            background: #f1f5f9;
        }

        .page {
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            padding: 26px 30px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
            border-radius: 12px;
        }

        /* Toolbar — otomatis hilang saat dicetak */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
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
            transition: all 0.2s;
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
            padding-bottom: 12px;
            margin-bottom: 14px;
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
            padding: 5px 14px;
            border-radius: 16px;
            font-size: 10.5px;
        }

        .gen {
            color: #94a3b8;
            font-size: 9px;
            margin-top: 5px;
        }

        /* Kartu statistik global */
        .stats {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }

        .stat {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 12px;
            background: #f8fafc;
        }

        .stat .num {
            font-size: 16px;
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

        .blue .num {
            color: #2563eb;
        }

        .amber .num {
            color: #d97706;
        }

        /* Section per karyawan */
        .emp-section {
            margin-bottom: 22px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            page-break-inside: avoid;
        }

        .emp-header {
            background: #f1f5f9;
            border-bottom: 2px solid #14532d;
            padding: 7px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .emp-name {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
        }

        .emp-meta {
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
            margin-left: 6px;
        }

        .emp-badge {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 800;
        }

        /* Tabel data */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #14532d;
            color: #fff;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: .5px;
            padding: 6px 7px;
            text-align: left;
        }

        tbody td {
            padding: 4.5px 7px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9px;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr.row-empty {
            color: #94a3b8;
        }

        tbody tr.row-late {
            background: #fef2f2;
        }

        tfoot td {
            background: #f1f5f9;
            font-weight: 800;
            padding: 6px 7px;
            border-top: 2px solid #cbd5e1;
            font-size: 9.5px;
        }

        .muted {
            color: #94a3b8;
        }

        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 8px;
            font-size: 8px;
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

        /* Tanda tangan */
        .foot {
            display: flex;
            justify-content: space-between;
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .sig {
            text-align: center;
            font-size: 10px;
            color: #475569;
            width: 250px;
        }

        .sig .space {
            height: 55px;
        }

        .sig .name {
            font-weight: 700;
            color: #1e293b;
        }

        .note {
            margin-top: 14px;
            color: #94a3b8;
            font-size: 8.5px;
            border-top: 1px dashed #e2e8f0;
            padding-top: 8px;
        }

        @page {
            size: A4 landscape;
            margin: 8mm;
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
        <span class="hint">Pratinjau Laporan Rekap Absensi Penuh Sebulan — Klik tombol untuk mencetak atau simpan sebagai PDF.</span>
        <button class="btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="page">

        {{-- Kop Laporan --}}
        <div class="head">
            <div>
                <h1>RSKB ROPANASURI — LAPORAN REKAPITULASI ABSENSI & LEMBUR</h1>
                <p>Sistem Informasi Roster & Absensi (SIRO) • Unit: <b>{{ $unitNama ?? 'Semua Unit Kerja' }}</b></p>
            </div>
            <div class="right">
                <span class="period">{{ $periodLabel }}</span>
                <div class="gen">Dicetak: {{ now()->format('d M Y, H:i') }} WIB</div>
            </div>
        </div>

        {{-- Kartu Statistik Ringkasan Periode --}}
        <div class="stats">
            <div class="stat dark">
                <div class="num">{{ count($calendarData) }}</div>
                <div class="lbl">Total Karyawan</div>
            </div>
            <div class="stat green">
                <div class="num">{{ $grandTotalMasukDinas }}</div>
                <div class="lbl">Total Kehadiran Dinas (Hari)</div>
            </div>
            <div class="stat red">
                <div class="num">{{ $grandTotalTerlambat }} mnt</div>
                <div class="lbl">Akumulasi Keterlambatan</div>
            </div>
            <div class="stat blue">
                <div class="num">{{ round($grandTotalLembur / 60, 1) }} jam</div>
                <div class="lbl">Total Jam Lembur</div>
            </div>
            <div class="stat amber">
                <div class="num">{{ round($grandTotalOncall / 60, 1) }} jam</div>
                <div class="lbl">Total Jam On-Call</div>
            </div>
        </div>

        {{-- Daftar Per Karyawan (Seluruh Tanggal Ditampilkan) --}}
        @forelse($calendarData as $emp)
        <div class="emp-section">
            <div class="emp-header">
                <div>
                    <span class="emp-name">{{ $emp['nama'] }}</span>
                    <span class="emp-meta">NIK: {{ $emp['nik'] }} • Unit: {{ $emp['unit'] }}</span>
                </div>
                <div class="emp-badge">
                    Total Masuk Dinas: <strong>{{ $emp['total_masuk_dinas'] }} Hari</strong>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">Tanggal</th>
                        <th style="width: 9%;">Hari</th>
                        <th style="width: 7%;">Masuk</th>
                        <th style="width: 7%;">Keluar</th>
                        <th style="width: 9%;">Durasi</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 8%;">Telat</th>
                        <th style="width: 8%;">Jarak</th>
                        <th style="width: 10%;">Lembur (Mnt)</th>
                        <th style="width: 10%;">On-Call (Mnt)</th>
                        <th style="width: 10%; text-align: center;">Masuk Dinas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($emp['days'] as $day)
                    <tr class="{{ $day['is_hadir'] ? ($day['status'] == 'Terlambat' ? 'row-late' : '') : 'row-empty' }}">
                        <td><b>{{ $day['tanggal'] }}</b></td>
                        <td>{{ $day['hari'] }}</td>
                        <td>{{ $day['jam_masuk'] }}</td>
                        <td>{{ $day['jam_keluar'] }}</td>
                        <td>{{ $day['durasi'] }}</td>
                        <td>
                            @if($day['is_hadir'])
                            <span class="badge {{ $day['status'] == 'Tepat Waktu' ? 'ok' : ($day['status'] == 'Terlambat' ? 'late' : 'out') }}">
                                {{ $day['status'] }}
                            </span>
                            @else
                            <span class="muted">-</span>
                            @endif
                        </td>
                        <td>{{ is_numeric($day['menit_telat']) && $day['menit_telat'] > 0 ? $day['menit_telat'] . ' mnt' : '-' }}</td>
                        <td>{{ $day['jarak'] !== '-' ? $day['jarak'] : '-' }}</td>
                        <td>{{ $day['lembur_menit'] > 0 ? $day['lembur_menit'] . ' mnt' : '-' }}</td>
                        <td>{{ $day['oncall_menit'] > 0 ? $day['oncall_menit'] . ' mnt' : '-' }}</td>
                        <td style="text-align: center; font-weight: bold;">
                            @if($day['is_hadir'])
                            <span class="badge ok">1 (Hadir)</span>
                            @else
                            <span class="muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8">TOTAL KESELURUHAN ({{ $emp['nama'] }}):</td>
                        <td><b>{{ $emp['total_lembur_menit'] > 0 ? $emp['total_lembur_menit'] . ' mnt' : '-' }}</b></td>
                        <td><b>{{ $emp['total_oncall_menit'] > 0 ? $emp['total_oncall_menit'] . ' mnt' : '-' }}</b></td>
                        <td style="text-align: center; color: #15803d;"><b>{{ $emp['total_masuk_dinas'] }} Hari</b></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @empty
        <div style="text-align: center; padding: 40px; color: #94a3b8; border: 1px dashed #cbd5e1; border-radius: 10px;">
            Tidak ada data karyawan / absensi pada periode yang dipilih.
        </div>
        @endforelse

        {{-- Tanda Tangan Pengesahan --}}
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

        <p class="note">Dokumen resmi dicetak secara otomatis dari Sistem Informasi Roster & Absensi (SIRO) RSKB Ropanasuri. Data kehadiran bersifat mengikat sesuai log sistem pada tanggal pencetakan.</p>
    </div>

</body>

</html>