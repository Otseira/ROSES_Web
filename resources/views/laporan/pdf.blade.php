<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Rekap Absensi & Lembur</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        h2 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        p.sub {
            margin: 0 0 12px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.5px;
        }

        .chip {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 1px 4px;
            border-radius: 4px;
            font-size: 8px;
        }

        .section {
            font-size: 11px;
            font-weight: bold;
            margin: 10px 0 4px;
        }
    </style>
</head>

<body>
    <h2>Rekap Absensi & Payroll — RSKB Ropanasuri</h2>
    <p class="sub">Periode: {{ now()->month($bulan)->translatedFormat('F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Tanggal</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Jarak (M / P)</th>
                <th>Foto Masuk</th>
                <th>Foto Keluar</th>
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
                <td>
                    M: {{ $log->jarak_masuk !== null ? $log->jarak_masuk . ' m' : '-' }}<br>
                    P: {{ $log->jarak_pulang !== null ? $log->jarak_pulang . ' m' : '-' }}
                </td>
                <td>{{ $log->foto_masuk ? url('/storage/' . $log->foto_masuk) : '-' }}</td>
                <td>{{ $log->foto_pulang ? url('/storage/' . $log->foto_pulang) : '-' }}</td>
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
                <td colspan="8" style="text-align:center;">Tidak ada data absensi.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section">Rekap Lembur / On-Call</div>
    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Jenis</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Total Jam</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lemburs->flatten() as $l)
            <tr>
                <td><b>{{ $l->user?->name ?? '-' }}</b></td>
                <td>{{ $l->jenis_lembur }}</td>
                <td>{{ optional($l->waktu_mulai_lembur)->format('d/m/Y H:i') }}</td>
                <td>{{ optional($l->waktu_selesai_lembur)->format('d/m/Y H:i') ?? '-' }}</td>
                <td>{{ number_format($l->total_jam_lembur ?? 0, 1) }}</td>
                <td>{{ $l->status_validasi }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;">Tidak ada lembur/on-call.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>