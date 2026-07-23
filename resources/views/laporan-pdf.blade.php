<!DOCTYPE html>
<html>
<head>
    <title>Rekap Absensi & Payroll</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2 style="text-align: center;">REKAPITULASI ABSENSI DAN PAYROLL</h2>
    <p style="text-align: center;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>NIK</th>
                <th>Nama</th>
                <th>Hadir</th>
                <th>Terlambat</th>
                <th>Potongan</th>
                <th>Insentif</th>
                <th>Netto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>{{ $user->nik }}</td>
                <td style="text-align: left;">{{ $user->nama }}</td>
                <td>{{ $user->kehadiran }}</td>
                <td>{{ $user->menit_terlambat }} mnt</td>
                <td>Rp {{ number_format($user->potongan, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($user->insentif_lembur, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($user->netto, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 50px; float: right;">
        <p>Padang, {{ date('d F Y') }}</p>
        <br><br><br>
        <p>(______________________)</p>
        <p>Penanggung Jawab / HRD</p>
    </div>
</body>
</html>