<table>
    <thead>
        <tr>
            <th>Nama</th>
            <th>Unit Kerja</th>
            <th>Tanggal</th>
            <th>Jam Masuk</th>
            <th>Jam Keluar</th>
            <th>Durasi</th>
            <th>Status</th>
            <th>Telat (mnt)</th>
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
            <td>{{ optional($log->waktu_masuk)->format('d/m/Y') ?? '-' }}</td>
            <td>{{ optional($log->waktu_masuk)->format('H:i') ?? '-' }}</td>
            <td>{{ optional($log->waktu_pulang)->format('H:i') ?? '-' }}</td>
            <td>{{ $log->durasi_kerja ?? '-' }}</td>
            <td>{{ $log->status_kehadiran }}</td>
            <td>{{ $log->menit_terlambat ?? 0 }}</td>
            <td>{{ $log->jarak !== null ? $log->jarak . ' m' : '-' }}</td>
            <td>
                @if($items && $items->isNotEmpty())
                @foreach($items as $l)
                <span class="chip">{{ $l->jenis_lembur }} • {{ number_format($l->total_jam_lembur ?? 0, 1) }} jam • {{
                    $l->status_validasi }}</span><br>
                @endforeach
                @else - @endif
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="10" style="text-align:center;">Tidak ada data absensi.</td>
        </tr>
        @endforelse
    </tbody>
</table>