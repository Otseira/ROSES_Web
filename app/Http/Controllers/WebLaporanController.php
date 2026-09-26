<?php

namespace App\Http\Controllers;

use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\MasterUnitKerja;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RekapAbsensiPerKaryawanExport;

class WebLaporanController extends Controller
{
    public function index(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat laporan unit tersebut.');
        }

        // === RENTANG TANGGAL (custom atau default) ===
        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);

        [$logs, $lemburs] = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        // Statistik ringkasan
        $stats = [
            'total'      => $logs->count(),
            'tepat'      => $logs->where('status_kehadiran', 'Tepat Waktu')->count(),
            'terlambat'  => $logs->where('status_kehadiran', 'Terlambat')->count(),
            'luar'       => $logs->where('status_kehadiran', 'Luar Jadwal')->count(),
            'menit_late' => (int) $logs->sum('menit_terlambat'),
            'jam_lembur' => round($lemburs->flatten()->sum('total_jam_lembur'), 1),
        ];

        // Dropdown unit
        if ($allowedUnitIds !== null) {
            $units = MasterUnitKerja::whereIn('id', $allowedUnitIds)
                ->orderBy('nama_unit', 'asc')->get();
        } else {
            $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        }

        // Kelompokkan per unit
        $logsGrouped = $logs->groupBy(function ($log) {
            $user = $log->user ?? $log->roster?->user;
            return $user?->unitKerja?->nama_unit ?? 'Tanpa Unit';
        })->sortKeys();

        // Untuk kompatibilitas view PDF (label periode)
        $bulan = Carbon::parse($startDate)->month;
        $tahun = Carbon::parse($startDate)->year;

        return view('laporan.laporan', compact(
            'unit',
            'logs',
            'lemburs',
            'stats',
            'units',
            'logsGrouped',
            'tglMulai',
            'tglSelesai',
            'startDate',
            'endDate',
            'bulan',
            'tahun'
        ));
    }

    /**
     * ✅ EXPORT EXCEL — SpreadsheetML (Excel 2003 XML):
     *    layout presisi, multi-sheet per karyawan, tanpa extension zip.
     */
    public function exportExcel(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk mengekspor laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);
        [$logs, $lemburs]      = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        $periodLabel = Carbon::parse($startDate)->translatedFormat('d M Y')
            . ' s/d ' . Carbon::parse($endDate)->translatedFormat('d M Y');

        // ===== Kelompokkan per karyawan =====
        $grouped = $logs->groupBy(function ($log) {
            $user = $log->user ?? $log->roster?->user;
            return $user?->id ?? ('log-' . $log->id);
        });

        $sheetsData = [];
        foreach ($grouped as $userId => $groupLogs) {
            $first = $groupLogs->first();
            $user  = $first->user ?? $first->roster?->user;

            $totalLemburMenit = 0;
            $totalOncallMenit = 0;
            $totalTerlambatMenit = 0;
            $terlambat6_10 = 0;
            $terlambat11_15 = 0;
            $terlambat16_20 = 0;
            $terlambat21plus = 0;

            $rows = [];
            foreach ($groupLogs as $log) {
                $key   = ($log->user_id ?? $log->roster?->user_id) . '|' . optional($log->waktu_masuk)->toDateString();
                $items = $lemburs->get($key);

                $mLembur = 0;
                $mOncall = 0;
                if ($items) {
                    foreach ($items as $l) {
                        $mnt  = (float) ($l->total_jam_lembur ?? 0) * 60;
                        $norm = str_contains(strtolower(str_replace(['-', ' ', '_'], '', $l->jenis_lembur ?? '')), 'oncall');
                        $norm ? $mOncall += $mnt : $mLembur += $mnt;
                    }
                }

                $totalLemburMenit += $mLembur;
                $totalOncallMenit += $mOncall;

                $mntTerlambat = (int) ($log->menit_terlambat ?? 0);
                $totalTerlambatMenit += $mntTerlambat;

                if ($mntTerlambat >= 6 && $mntTerlambat <= 10)        $terlambat6_10  += $mntTerlambat;
                elseif ($mntTerlambat >= 11 && $mntTerlambat <= 15)   $terlambat11_15 += $mntTerlambat;
                elseif ($mntTerlambat >= 16 && $mntTerlambat <= 20)   $terlambat16_20 += $mntTerlambat;
                elseif ($mntTerlambat > 20)                           $terlambat21plus += $mntTerlambat;

                $rows[] = [
                    optional($log->waktu_masuk)->format('d/m/Y') ?? '-',
                    optional($log->waktu_masuk)->format('H:i') ?? '-',
                    optional($log->waktu_pulang)->format('H:i') ?? '-',
                    $log->durasi_kerja ?? '-',
                    $log->status_kehadiran ?? '-',
                    $mntTerlambat,
                    $log->jarak ?? '-',
                    (int) round($mLembur),
                    (int) round($mOncall),
                ];
            }

            $potongan6_10  = (int) round($terlambat6_10 * 0.25);
            $potongan11_15 = (int) round($terlambat11_15 * 0.50);
            $potongan16_20 = $terlambat16_20;
            $totalPotongan = $potongan6_10 + $potongan11_15 + $potongan16_20 + $terlambat21plus;

            $sheetsData[] = [
                'nama' => $user?->name ?? 'Tanpa Nama',
                'unit' => $user?->unitKerja?->nama_unit ?? '-',
                'rows' => $rows,
                'summary' => [
                    'total_lembur_menit'    => (int) round($totalLemburMenit),
                    'total_oncall_menit'    => (int) round($totalOncallMenit),
                    'total_terlambat_menit' => $totalTerlambatMenit,
                    'terlambat_6_10'        => $terlambat6_10,
                    'terlambat_11_15'       => $terlambat11_15,
                    'terlambat_16_20'       => $terlambat16_20,
                    'terlambat_21plus'      => $terlambat21plus,
                    'potongan_6_10'         => $potongan6_10,
                    'potongan_11_15'        => $potongan11_15,
                    'potongan_16_20'        => $potongan16_20,
                    'total_potongan'        => $totalPotongan,
                ],
            ];
        }

        usort($sheetsData, fn($a, $b) => strcasecmp($a['nama'], $b['nama']));

        $filename = 'rekap-absensi-'
            . Carbon::parse($startDate)->format('Y-m-d') . '-'
            . Carbon::parse($endDate)->format('Y-m-d') . '.xls';

        $xml = $this->buildSpreadsheetML($sheetsData, $periodLabel);

        return response($xml, 200, [
            'Content-Type'        => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /** Escape aman untuk XML. */
    private function xmlEsc($v): string
    {
        return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * ✅ Bangun file Excel 2003 XML (SpreadsheetML):
     *    multi-sheet, merge presisi, warna & border konsisten di semua pembaca.
     */
    private function buildSpreadsheetML(array $sheetsData, string $periodLabel): string
    {
        $borders = '<Borders>'
            . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>';

        $x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $x .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $x .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

        // ===== DEFINISI STYLE =====
        $x .= '<Styles>' . "\n";
        $x .= '<Style ss:ID="Default"><Alignment ss:Vertical="Center"/></Style>' . "\n";
        $x .= '<Style ss:ID="title"><Font ss:Bold="1" ss:Size="14" ss:Color="#FFFFFF"/><Interior ss:Color="#1B5E20" ss:Pattern="Solid"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>' . "\n";
        $x .= '<Style ss:ID="ident"><Font ss:Bold="1"/><Interior ss:Color="#E8F5E9" ss:Pattern="Solid"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="head"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#4CAF50" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="data"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="dataz"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#F1F8E9" ss:Pattern="Solid"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="ringb"><Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/><Interior ss:Color="#EF6C00" ss:Pattern="Solid"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>' . "\n";
        $x .= '<Style ss:ID="ring"><Font ss:Bold="1"/><Interior ss:Color="#FFF3E0" ss:Pattern="Solid"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="brkb"><Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/><Interior ss:Color="#C62828" ss:Pattern="Solid"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>' . "\n";
        $x .= '<Style ss:ID="brkh"><Font ss:Bold="1"/><Interior ss:Color="#FFCDD2" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="brk1"><Interior ss:Color="#FFF8E1" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="brk2"><Interior ss:Color="#FFECB3" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="brk3"><Interior ss:Color="#FFCCBC" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="brk4"><Interior ss:Color="#FFAB91" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="tot"><Font ss:Bold="1" ss:Size="11" ss:Color="#FFFFFF"/><Interior ss:Color="#B71C1C" ss:Pattern="Solid"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '<Style ss:ID="totv"><Font ss:Bold="1" ss:Size="11" ss:Color="#FFFFFF"/><Interior ss:Color="#B71C1C" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . $borders . '</Style>' . "\n";
        $x .= '</Styles>' . "\n";

        $cell = function (string $style, $val, string $type = 'String', int $merge = 0): string {
            return '<Cell ss:StyleID="' . $style . '"' . ($merge ? ' ss:MergeAcross="' . $merge . '"' : '')
                . '><Data ss:Type="' . $type . '">' . $this->xmlEsc($val) . '</Data></Cell>';
        };

        $usedNames = [];

        foreach ($sheetsData as $meta) {
            // Nama sheet unik & aman
            $clean = preg_replace('/[\\\\\/\?\*\[\]:]+/', '-', trim($meta['nama']));
            $clean = substr($clean, 0, 28) ?: 'Karyawan';
            $name  = $clean;
            $i = 1;
            while (in_array($name, $usedNames)) {
                $name = substr($clean, 0, 28 - strlen('(' . $i . ')')) . ' (' . $i . ')';
                $i++;
            }
            $usedNames[] = $name;

            $x .= '<Worksheet ss:Name="' . $this->xmlEsc($name) . '"><Table>' . "\n";
            foreach ([170, 150, 90, 110, 110, 110, 80, 100, 100] as $w) {
                $x .= '<Column ss:Width="' . $w . '"/>' . "\n";
            }

            // Baris judul
            $x .= '<Row ss:Height="26">' . $cell('title', 'REKAP ABSENSI KARYAWAN', 'String', 8) . '</Row>' . "\n";

            // Identitas
            foreach ([['Nama', $meta['nama']], ['Unit Kerja', $meta['unit']], ['Periode', $periodLabel]] as [$l, $v]) {
                $x .= '<Row>' . $cell('ident', $l) . $cell('ident', $v, 'String', 7) . '</Row>' . "\n";
            }
            $x .= '<Row/>' . "\n";

            // Header tabel
            $heads = ['Tanggal', 'Jam Masuk', 'Jam Keluar', 'Durasi', 'Status', 'Terlambat (mnt)', 'Jarak (m)', 'Lembur (mnt)', 'On-Call (mnt)'];
            $x .= '<Row ss:Height="20">';
            foreach ($heads as $h) $x .= $cell('head', $h);
            $x .= '</Row>' . "\n";

            // Data harian (zebra)
            $i = 0;
            foreach ($meta['rows'] as $r) {
                $style = ($i % 2 === 1) ? 'dataz' : 'data';
                $x .= '<Row>';
                foreach ($r as $val) {
                    $x .= $cell($style, $val, is_numeric($val) ? 'Number' : 'String');
                }
                $x .= '</Row>' . "\n";
                $i++;
            }
            $x .= '<Row/>' . "\n";

            // Ringkasan
            $s = $meta['summary'] ?? [];
            $x .= '<Row ss:Height="20">' . $cell('ringb', 'RINGKASAN TOTAL', 'String', 8) . '</Row>' . "\n";
            $x .= '<Row>' . $cell('ring', 'Total Lembur') . $cell('ring', ($s['total_lembur_menit'] ?? 0) . ' menit (' . round(($s['total_lembur_menit'] ?? 0) / 60, 2) . ' jam)', 'String', 7) . '</Row>' . "\n";
            $x .= '<Row>' . $cell('ring', 'Total On-Call') . $cell('ring', ($s['total_oncall_menit'] ?? 0) . ' menit (' . round(($s['total_oncall_menit'] ?? 0) / 60, 2) . ' jam)', 'String', 7) . '</Row>' . "\n";
            $x .= '<Row>' . $cell('ring', 'Total Keterlambatan') . $cell('ring', ($s['total_terlambat_menit'] ?? 0) . ' menit', 'String', 7) . '</Row>' . "\n";
            $x .= '<Row/>' . "\n";

            // Breakdown
            $x .= '<Row ss:Height="20">' . $cell('brkb', 'BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)', 'String', 3) . '</Row>' . "\n";
            $x .= '<Row>' . $cell('brkh', 'Kategori') . $cell('brkh', 'Total Menit') . $cell('brkh', 'Persentase') . $cell('brkh', 'Potongan (menit)') . '</Row>' . "\n";
            $x .= '<Row>' . $cell('brk1', 'Terlambat 6 - 10 menit')  . $cell('brk1', $s['terlambat_6_10'] ?? 0, 'Number')  . $cell('brk1', '25%')  . $cell('brk1', $s['potongan_6_10'] ?? 0, 'Number')  . '</Row>' . "\n";
            $x .= '<Row>' . $cell('brk2', 'Terlambat 11 - 15 menit') . $cell('brk2', $s['terlambat_11_15'] ?? 0, 'Number') . $cell('brk2', '50%')  . $cell('brk2', $s['potongan_11_15'] ?? 0, 'Number') . '</Row>' . "\n";
            $x .= '<Row>' . $cell('brk3', 'Terlambat 16 - 20 menit') . $cell('brk3', $s['terlambat_16_20'] ?? 0, 'Number') . $cell('brk3', '100%') . $cell('brk3', $s['potongan_16_20'] ?? 0, 'Number') . '</Row>' . "\n";
            if (($s['terlambat_21plus'] ?? 0) > 0) {
                $x .= '<Row>' . $cell('brk4', 'Terlambat > 20 menit') . $cell('brk4', $s['terlambat_21plus'], 'Number') . $cell('brk4', '(Tindak Lanjut)') . $cell('brk4', $s['terlambat_21plus'], 'Number') . '</Row>' . "\n";
            }
            $x .= '<Row/>' . "\n";

            // Total potongan
            $x .= '<Row ss:Height="20">' . $cell('tot', 'TOTAL POTONGAN KETERLAMBATAN', 'String', 2) . $cell('totv', ($s['total_potongan'] ?? 0) . ' menit') . '</Row>' . "\n";

            $x .= '</Table></Worksheet>' . "\n";
        }

        $x .= '</Workbook>';

        return $x;
    }

    public function exportPdf(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat PDF laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);
        [$logs, $lemburs] = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        $bulan = Carbon::parse($startDate)->month;
        $tahun = Carbon::parse($startDate)->year;

        return view('laporan.pdf', compact(
            'bulan',
            'tahun',
            'logs',
            'lemburs',
            'tglMulai',
            'tglSelesai',
            'startDate',
            'endDate'
        ));
    }

    /**
     * ✅ Rentang tanggal: pakai input user; jika kosong → 1 bulan berjalan s/d hari ini.
     */
    private function resolveDateRange(?string $tglMulai, ?string $tglSelesai): array
    {
        if ($tglMulai && $tglSelesai) {
            return [
                Carbon::parse($tglMulai)->startOfDay()->toDateTimeString(),
                Carbon::parse($tglSelesai)->endOfDay()->toDateTimeString(),
            ];
        }

        return [
            now()->startOfMonth()->toDateTimeString(),
            now()->endOfDay()->toDateTimeString(),
        ];
    }

    private function getAllowedUnitIds($user): ?array
    {
        if ($user->hasGlobalAccess()) {
            return null;
        }
        $ids = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
        if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $ids)) {
            $ids[] = $user->unit_kerja_id;
        }
        return $ids;
    }

    private function buildData(string $startDate, string $endDate, $unit = null, ?array $allowedUnitIds = null)
    {
        $pengaturan = PengaturanAplikasi::first();
        $lat = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lng = $pengaturan ? (float) $pengaturan->longitude : 0;

        $logs = LogAbsensi::with(['user.unitKerja', 'roster.user.unitKerja', 'roster.shift'])
            ->whereBetween('waktu_masuk', [$startDate, $endDate])
            ->when($unit, function ($q) use ($unit) {
                $q->where(function ($q2) use ($unit) {
                    $q2->whereHas('roster.user', fn($u) => $u->where('unit_kerja_id', $unit))
                        ->orWhereHas('user', fn($u) => $u->where('unit_kerja_id', $unit));
                });
            })
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->where(function ($q2) use ($allowedUnitIds) {
                    $q2->whereHas('roster.user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds))
                        ->orWhereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
                });
            })
            ->orderBy('waktu_masuk')
            ->get()
            ->map(function ($log) use ($lat, $lng) {
                // Hitung jarak
                $log->jarak_masuk = is_numeric($log->latitude_masuk)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_masuk, (float) $log->longitude_masuk))
                    : null;
                $log->jarak_pulang = is_numeric($log->latitude_pulang)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_pulang, (float) $log->longitude_pulang))
                    : null;
                $log->jarak = $log->jarak_masuk ?? $log->jarak_pulang;

                // ✅ HITUNG ULANG STATUS berdasarkan roster (real-time)
                $this->recalculateStatusForReport($log);

                // Hitung durasi kerja
                if ($log->waktu_masuk && $log->waktu_pulang) {
                    $m = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
                    $log->durasi_kerja = intdiv($m, 60) . 'j ' . ($m % 60) . 'm';
                } else {
                    $log->durasi_kerja = null;
                }

                return $log;
            });

        $lemburs = LogLembur::with('user')
            ->where('status_validasi', 'Disetujui')   // ✅ filter utama
            ->whereBetween('waktu_mulai_lembur', [$startDate, $endDate])
            ->when($unit, fn($q) => $q->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unit)))
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
            })
            ->orderBy('waktu_mulai_lembur')
            ->get()
            ->groupBy(fn($l) => $l->user_id . '|' . $l->waktu_mulai_lembur->toDateString());

        return [$logs, $lemburs];
    }

    /**
     * ✅ Hitung ulang status untuk laporan (real-time, tidak mengubah database).
     * Jika ada roster → hitung keterlambatan berdasarkan shift.
     * Jika tidak ada roster → "Tanpa Jadwal" atau "Luar Jadwal".
     */
    private function recalculateStatusForReport($log): void
    {
        $roster = $log->roster;

        if (!$roster || !$log->waktu_masuk) {
            $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
                ? 'Luar Jadwal'
                : 'Tanpa Jadwal';
            $log->menit_terlambat = 0;
            return;
        }

        // ✅ Ambil jam dari custom atau shift
        $jamMasuk = $roster->custom_jam_masuk ?? ($roster->shift ? (string) $roster->shift->jam_masuk : null);
        $toleransi = $roster->shift ? (int) ($roster->shift->toleransi_terlambat_menit ?? 5) : 5;

        if (!$jamMasuk) {
            $log->status_kehadiran = 'Tanpa Jadwal';
            $log->menit_terlambat = 0;
            return;
        }

        // ✅ FIX: tanggal_dinas bisa berupa objek Carbon → format dulu ke Y-m-d
        $tanggalStr = \Carbon\Carbon::parse($roster->tanggal_dinas)->format('Y-m-d');
        $expected   = \Carbon\Carbon::parse($tanggalStr . ' ' . $jamMasuk);

        // Shift malam (overnight)
        $jamPulang = $roster->custom_jam_pulang ?? ($roster->shift ? (string) $roster->shift->jam_pulang : null);
        if ($jamPulang && $jamMasuk && $jamPulang < $jamMasuk && $log->waktu_masuk->hour < 12) {
            $expected->subDay();
        }

        $selisih   = (int) floor($expected->diffInMinutes($log->waktu_masuk, false));

        $log->menit_terlambat = ($selisih > $toleransi) ? (int) $selisih : 0;

        $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
            ? 'Luar Jadwal'
            : ($log->menit_terlambat > 0 ? 'Terlambat' : 'Tepat Waktu');
    }

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
