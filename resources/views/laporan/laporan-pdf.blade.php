@php
$coord = fn($v) => $v === null ? '-' : number_format((float)$v, 4, '.', '');
$meter = fn($v) => $v === null ? '-' : number_format((float)$v, 0, ',', '.');
$jam = fn($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i') : '-';
$tgl = fn($t) => $t ? \Carbon\Carbon::parse($t)->format('d/m/Y') : '-';

// ✅ Jarak bersih bersatuan meter
$radius = function ($m, $p) {
$f = fn($v) => ($v === null || $v === '') ? null : number_format((float)$v, 0, ',', '.');
$a = $f($m); $b = $f($p);
if ($a !== null && $b !== null) return $a.' m / '.$b.' m';
if ($a !== null) return $a.' m';
if ($b !== null) return $b.' m';
return '-';
};
@endphp
<!DOCTYPE html>
<html>

<head>
    <title>Rekap Absensi</title>
    <style>
        @page {
            margin: 1cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }

        h2 {
            text-align: center;
            margin: 0;
            font-size: 15px;
        }

        .per {
            text-align: center;
            color: #555;
            margin: 4px 0 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 7px 6px;
            text-align: center;
            vertical-align: top;
        }

        th {
            background-color: #f2f2f2;
            text-transform: uppercase;
            font-size: 10px;
        }

        td.l {
            text-align: left;
        }

        .sign {
            margin-top: 40px;
            float: right;
            text-align: center;
        }
    </style>
</head>

<body>
    <h2>REKAPITULASI ABSENSI</h2>
    <p class="per">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M') }} - {{
        \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width:20%">Nama</th>
                <th style="width:10%">Jam Masuk</th>
                <th style="width:10%">Jam Keluar</th>
                <th style="width:13%">Tanggal</th>
                <th style="width:13%">Radius (m)</th>
                <th style="width:17%">Latitude</th>
                <th style="width:17%">Longitude</th>
            </tr>
        </thead>
        <tbody>
            @forelse($absensiDetail ?? [] as $log)
            <tr>
                <td class="l">{{ $log->roster?->user?->name ?? '-' }}</td>
                <td>{{ $jam($log->waktu_masuk) }}</td>
                <td>{{ $jam($log->waktu_pulang) }}</td>
                <td>{{ $tgl($log->roster?->tanggal_dinas) }}</td>
                <td>{{ $radius($log->jarak_masuk, $log->jarak_pulang) }}</td>
                <td>M: {{ $coord($log->latitude_masuk) }}<br>P: {{ $coord($log->latitude_pulang) }}</td>
                <td>M: {{ $coord($log->longitude_masuk) }}<br>P: {{ $coord($log->longitude_pulang) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7">Tidak ada data.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <p>Padang, {{ date('d F Y') }}</p>
        <br><br><br>
        <p>(______________________)</p>
        <p>Penanggung Jawab / HRD</p>
    </div>
</body>

</html>