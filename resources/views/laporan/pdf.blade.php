<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Rekap Absensi</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #1e293b;
        }

        h2 {
            font-size: 15px;
            margin: 0 0 2px;
        }

        p.sub {
            margin: 0 0 14px;
            color: #64748b;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }

        .chip {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 9px;
        }

        .toolbar {
            margin-bottom: 12px;
        }

        .toolbar button {
            padding: 8px 18px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            background: #0f172a;
            color: #fff;
            border: none;
            border-radius: 8px;
        }

        @media print {
            .toolbar {
                display: none;
            }
        }

        @page {
            size: A4 landscape;
            margin: 1cm;
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨️ Cetak / Simpan sebagai PDF</button>
    </div>

    <h2>Rekap Absensi & Payroll — RSKB Ropanasuri</h2>
    <p class="sub">Periode: {{ now()->month($bulan)->translatedFormat('F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Tanggal</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Jarak ke Pusat</th>
                <th>Lembur / On-Call</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            @php
            $nama = $log->user?->name ?? $log->roster?->user?->name ?? '-';
            $key = ($log->user_id ?? $log->roster?->user_id) . '|' . optional($log->waktu_masuk)->toDateString();
            $items = $lemburs->get($key);
            @endphp
            <tr>
                <td><b>{{ $nama }}</b></td>
                <td>{{ optional($log->waktu_masuk)->format('d/m/Y') ?? '-' }}</td>
                <td>{{ optional($log->waktu_masuk)->format('H:i') ?? '-' }}</td>
                <td>{{ optional($log->waktu_pulang)->format('H:i') ?? '-' }}</td>
                <td>{{ $log->jarak !== null ? $log->jarak . ' m' : '-' }}</td>
                <td>
                    @if($items && $items->isNotEmpty())
                    @foreach($items as $l)
                    <span class="chip">{{ $l->jenis_lembur }} • {{ number_format($l->total_jam_lembur ?? 0, 1) }} jam •
                        {{ $l->status_validasi }}</span><br>
                    @endforeach
                    @else - @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;">Tidak ada data absensi pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        // Otomatis buka dialog print → pilih "Save as PDF"
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>

</html>