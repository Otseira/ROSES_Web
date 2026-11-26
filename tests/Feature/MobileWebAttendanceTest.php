<?php

namespace Tests\Feature;

use App\Models\JadwalRoster;
use App\Models\LogAbsensi;
use App\Models\MasterShift;
use App\Models\MasterUnitKerja;
use App\Models\PengaturanAplikasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileWebAttendanceTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->buildSqliteSchema();

        $unit = MasterUnitKerja::create([
            'nama_unit'    => 'IGD',
            'jenis_lembur' => 'dibayar',
        ]);

        PengaturanAplikasi::create([
            'nama_instansi' => 'RSKB Ropanasuri',
            'latitude'      => '-0.947110',
            'longitude'     => '100.417180',
            'radius_meter'  => 100,
        ]);

        $this->user = User::create([
            'name'          => 'Staf Uji iPhone',
            'username'      => 'staf_iphone',
            'nik'           => '19990001',
            'email'         => 'staf@ropanasuri.test',
            'unit_kerja_id' => $unit->id,
            'role'          => 'staf',
            'password'      => bcrypt('password123'),
            'is_active'     => true,
        ]);
    }

    private function buildSqliteSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('master_unit_kerjas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_unit');
            $table->text('deskripsi')->nullable();
            $table->string('jenis_lembur')->default('dibayar');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nik')->unique();
            $table->string('username')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('nomor_whatsapp')->nullable();
            $table->string('role')->default('staf');
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('foto_profil')->nullable();
            $table->integer('saldo_awal_lembur_menit')->default(0);
            $table->text('keterangan_saldo_lembur')->nullable();
            $table->timestamp('updated_saldo_lembur_at')->nullable();
            $table->unsignedBigInteger('updated_saldo_lembur_by')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('pengaturan_aplikasis', function (Blueprint $table) {
            $table->id();
            $table->string('nama_instansi')->default('RSKB Ropanasuri');
            $table->string('tagline')->nullable();
            $table->string('logo')->nullable();
            $table->string('splash_bg')->nullable();
            $table->string('splash_bg_color')->nullable();
            $table->string('latitude')->default('-0.9471');
            $table->string('longitude')->default('100.3511');
            $table->integer('radius_meter')->default(100);
            $table->timestamps();
        });

        Schema::create('master_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('nama_shift');
            $table->string('jam_masuk');
            $table->string('jam_pulang');
            $table->integer('toleransi_terlambat_menit')->default(5);
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jadwal_rosters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->date('tanggal_dinas');
            $table->integer('sesi')->default(1);
            $table->string('custom_nama_shift')->nullable();
            $table->string('custom_jam_masuk')->nullable();
            $table->string('custom_jam_pulang')->nullable();
            $table->timestamps();
        });

        Schema::create('log_absensis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('roster_id')->nullable();
            $table->date('tanggal_dinas')->nullable();
            $table->dateTime('waktu_masuk')->nullable();
            $table->dateTime('waktu_pulang')->nullable();
            $table->decimal('latitude_masuk', 10, 8)->nullable();
            $table->decimal('longitude_masuk', 11, 8)->nullable();
            $table->decimal('latitude_pulang', 10, 8)->nullable();
            $table->decimal('longitude_pulang', 11, 8)->nullable();
            $table->string('foto_masuk')->nullable();
            $table->string('foto_pulang')->nullable();
            $table->string('ip_address_masuk')->nullable();
            $table->string('ip_address_pulang')->nullable();
            $table->string('jenis_absen')->nullable();
            $table->integer('menit_terlambat')->default(0);
            $table->string('status_kehadiran')->nullable();
            $table->integer('durasi_kerja')->nullable();
            $table->timestamps();
        });

        Schema::create('log_lemburs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('jenis_lembur')->nullable();
            $table->dateTime('waktu_mulai_lembur')->nullable();
            $table->dateTime('waktu_selesai_lembur')->nullable();
            $table->decimal('total_jam_lembur', 8, 2)->nullable();
            $table->string('status_validasi')->default('Pending');
            $table->text('keterangan')->nullable();
            $table->text('alasan_lembur')->nullable();
            $table->decimal('latitude_masuk', 10, 8)->nullable();
            $table->decimal('longitude_masuk', 11, 8)->nullable();
            $table->decimal('latitude_keluar', 10, 8)->nullable();
            $table->decimal('longitude_keluar', 11, 8)->nullable();
            $table->string('foto_masuk')->nullable();
            $table->string('foto_keluar')->nullable();
            $table->decimal('accuracy_masuk', 8, 2)->nullable();
            $table->boolean('is_mock_masuk')->default(false);
            $table->unsignedBigInteger('divalidasi_oleh')->nullable();
            $table->timestamp('divalidasi_pada')->nullable();
            $table->string('catatan_validasi')->nullable();
            $table->timestamps();
        });

        Schema::create('pengambilan_lemburs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('tanggal_dinas');
            $table->string('tipe_pengambilan');
            $table->integer('durasi_menit');
            $table->text('keterangan')->nullable();
            $table->string('status_validasi')->default('Pending');
            $table->unsignedBigInteger('divalidasi_oleh')->nullable();
            $table->timestamp('divalidasi_pada')->nullable();
            $table->string('catatan_validasi')->nullable();
            $table->timestamps();
        });

        Schema::create('unit_kerja_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('master_unit_kerja_id');
            $table->timestamps();
        });
    }

    public function test_unauthenticated_app_api_renders_json_not_html(): void
    {
        $response = $this->post('/app/api/check-location', [
            'latitude'  => -0.947110,
            'longitude' => 100.417180,
        ]);

        $response->assertStatus(401)->assertJsonStructure(['message']);
    }

    public function test_csrf_and_today_status_return_fresh_token(): void
    {
        $this->actingAs($this->user)
            ->getJson('/app/api/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['success', 'csrf_token']);

        $this->actingAs($this->user)
            ->getJson('/app/api/today-status')
            ->assertOk()
            ->assertJsonStructure(['success', 'csrf_token', 'data']);
    }

    public function test_check_location_handles_indoor_iphone_accuracy_and_radius(): void
    {
        // Dalam radius RS dengan akurasi Wi-Fi indoor 110m (valid untuk web mobile <= 150m)
        $this->actingAs($this->user)
            ->postJson('/app/api/check-location', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 110,
            ])
            ->assertOk()
            ->assertJson([
                'success'      => true,
                'in_radius'    => true,
                'accuracy_low' => false,
            ]);

        // Akurasi sangat rendah (> 150m)
        $this->actingAs($this->user)
            ->postJson('/app/api/check-location', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 240,
            ])
            ->assertOk()
            ->assertJson([
                'success'      => true,
                'accuracy_low' => true,
            ]);

        // Di luar radius RS
        $this->actingAs($this->user)
            ->postJson('/app/api/check-location', [
                'latitude'  => -0.960000,
                'longitude' => 100.417180,
                'accuracy'  => 20,
            ])
            ->assertOk()
            ->assertJson([
                'success'   => true,
                'in_radius' => false,
            ]);
    }

    public function test_clock_in_and_clock_out_succeed_with_indoor_iphone_accuracy(): void
    {
        $photo = UploadedFile::fake()->image('selfie.jpg', 600, 800);

        // Clock In dengan akurasi 110m (indoor iPhone)
        $this->actingAs($this->user)
            ->postJson('/app/api/absensi/masuk', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 110,
                'foto'      => $photo,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        // Clock Out dengan akurasi 115m
        $photoOut = UploadedFile::fake()->image('selfie_out.jpg', 600, 800);

        $this->actingAs($this->user)
            ->postJson('/app/api/absensi/pulang', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 115,
                'foto'      => $photoOut,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_clock_in_rejects_excessive_accuracy_with_clean_indonesian_json(): void
    {
        $photo = UploadedFile::fake()->image('selfie.jpg', 600, 800);

        $response = $this->actingAs($this->user)
            ->postJson('/app/api/absensi/masuk', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 350, // > 200 dulu melempar validation error Inggris!
                'foto'      => $photo,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertStringContainsString('Akurasi GPS rendah', $response->json('message'));
    }

    public function test_jadwal_dinas_returns_aligned_weekday_offsets(): void
    {
        // Oktober 2026: tanggal 1 = Kamis (index 4: Min=0, Sen=1, Sel=2, Rab=3, Kam=4)
        $oct = $this->actingAs($this->user)
            ->getJson('/app/api/jadwal-dinas?bulan=10&tahun=2026')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'bulan'            => 10,
                    'tahun'            => 2026,
                    'jumlah_hari'      => 31,
                    'hari_awal_minggu' => 4,
                ],
            ]);
        $this->assertSame(4, $oct->json('data.hari.0.hari_ke'));

        // Desember 2026: tanggal 1 = Selasa (index 2)
        $this->actingAs($this->user)
            ->getJson('/app/api/jadwal-dinas?bulan=12&tahun=2026')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'bulan'            => 12,
                    'tahun'            => 2026,
                    'jumlah_hari'      => 31,
                    'hari_awal_minggu' => 2,
                ],
            ]);
    }

    public function test_clock_in_auto_adapts_to_shift_change_when_employee_swaps_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 07:02:00'));

        $shiftPagi = MasterShift::create([
            'nama_shift'                => 'Shift Pagi',
            'jam_masuk'                 => '07:00:00',
            'jam_pulang'                => '14:00:00',
            'toleransi_terlambat_menit' => 5,
            'unit_kerja_id'             => $this->user->unit_kerja_id,
        ]);

        $shiftSiang = MasterShift::create([
            'nama_shift'                => 'Shift Siang',
            'jam_masuk'                 => '14:00:00',
            'jam_pulang'                => '21:00:00',
            'toleransi_terlambat_menit' => 5,
            'unit_kerja_id'             => $this->user->unit_kerja_id,
        ]);

        // Karyawan awalnya dijadwalkan Shift Siang (14:00)
        $roster = JadwalRoster::create([
            'user_id'       => $this->user->id,
            'shift_id'      => $shiftSiang->id,
            'tanggal_dinas' => '2026-11-16',
            'sesi'          => 1,
        ]);

        $photo = UploadedFile::fake()->image('selfie_swap.jpg', 600, 800);

        // Karyawan datang jam 07:02 pagi karena tukar dinas ke Shift Pagi
        $response = $this->actingAs($this->user)
            ->postJson('/app/api/absensi/masuk', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 50,
                'foto'      => $photo,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_perubahan_jadwal' => true,
                    'status'              => 'Tepat Waktu',
                    'nama_shift'          => 'Shift Pagi',
                ],
            ]);

        // Roster otomatis diperbarui ke shift yang dihadiri
        $this->assertEquals($shiftPagi->id, $roster->fresh()->shift_id);

        // Log absensi tercatat dengan jenis perubahan_jadwal
        $log = LogAbsensi::where('user_id', $this->user->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('perubahan_jadwal', $log->jenis_absen);
        $this->assertEquals('Tepat Waktu', $log->status_kehadiran);

        Carbon::setTestNow();
    }

    public function test_clock_in_adapts_when_swapped_from_morning_to_afternoon_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 14:03:00'));

        $shiftPagi = MasterShift::create([
            'nama_shift'                => 'Shift Pagi',
            'jam_masuk'                 => '07:00:00',
            'jam_pulang'                => '14:00:00',
            'toleransi_terlambat_menit' => 5,
            'unit_kerja_id'             => $this->user->unit_kerja_id,
        ]);

        $shiftSiang = MasterShift::create([
            'nama_shift'                => 'Shift Siang',
            'jam_masuk'                 => '14:00:00',
            'jam_pulang'                => '21:00:00',
            'toleransi_terlambat_menit' => 5,
            'unit_kerja_id'             => $this->user->unit_kerja_id,
        ]);

        // Karyawan awalnya dijadwalkan Shift Pagi (07:00 - 14:00), tapi tidak masuk pagi karena tukar dinas siang
        $roster = JadwalRoster::create([
            'user_id'       => $this->user->id,
            'shift_id'      => $shiftPagi->id,
            'tanggal_dinas' => '2026-11-16',
            'sesi'          => 1,
        ]);

        $photo = UploadedFile::fake()->image('selfie_afternoon.jpg', 600, 800);

        // Karyawan datang jam 14:03 untuk dinas siang
        $response = $this->actingAs($this->user)
            ->postJson('/app/api/absensi/masuk', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 50,
                'foto'      => $photo,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_perubahan_jadwal' => true,
                    'status'              => 'Tepat Waktu',
                    'nama_shift'          => 'Shift Siang',
                ],
            ]);

        // Roster otomatis diperbarui ke Shift Siang
        $this->assertEquals($shiftSiang->id, $roster->fresh()->shift_id);

        Carbon::setTestNow();
    }

    public function test_clock_in_with_explicit_shift_id_selection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 07:10:00'));

        $shiftPagi = MasterShift::create([
            'nama_shift'                => 'Shift Pagi Khusus',
            'jam_masuk'                 => '07:00:00',
            'jam_pulang'                => '14:00:00',
            'toleransi_terlambat_menit' => 5,
            'unit_kerja_id'             => $this->user->unit_kerja_id,
        ]);

        $photo = UploadedFile::fake()->image('selfie_manual.jpg', 600, 800);

        // Karyawan memilih shift_id manual lewat popup Tukar Shift
        $response = $this->actingAs($this->user)
            ->postJson('/app/api/absensi/masuk', [
                'latitude'  => -0.947110,
                'longitude' => 100.417180,
                'accuracy'  => 40,
                'foto'      => $photo,
                'shift_id'  => $shiftPagi->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_perubahan_jadwal' => true,
                    'nama_shift'          => 'Shift Pagi Khusus',
                ],
            ]);

        Carbon::setTestNow();
    }

    public function test_today_status_returns_available_shifts(): void
    {
        MasterShift::create([
            'nama_shift'    => 'Shift IGD Pagi',
            'jam_masuk'     => '07:00:00',
            'jam_pulang'    => '14:00:00',
            'unit_kerja_id' => $this->user->unit_kerja_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/app/api/today-status')
            ->assertOk();

        $this->assertTrue($response->json('success'));
        $shifts = $response->json('data.available_shifts');
        $this->assertIsArray($shifts);
        $this->assertNotEmpty($shifts);
        $this->assertEquals('Shift IGD Pagi', $shifts[0]['nama_shift']);
    }

    public function test_jadwal_dinas_shows_attendance_without_roster_and_delays_lateness_calculation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-24 07:45:00'));

        // Absen masuk tanpa jadwal dinas
        LogAbsensi::create([
            'user_id'          => $this->user->id,
            'roster_id'        => null,
            'tanggal_dinas'    => '2026-11-24',
            'waktu_masuk'      => Carbon::parse('2026-11-24 07:45:00'),
            'waktu_pulang'     => Carbon::parse('2026-11-24 15:00:00'),
            'jenis_absen'      => 'luar_jadwal',
            'menit_terlambat'  => 0,
            'status_kehadiran' => 'Tanpa Jadwal',
        ]);

        // Cek halaman jadwal dinas: jam masuk & pulang tetap muncul, belum ada keterlambatan
        $res = $this->actingAs($this->user)
            ->getJson('/app/api/jadwal-dinas?bulan=11&tahun=2026')
            ->assertOk();

        $hari24 = collect($res->json('data.hari'))->firstWhere('tanggal', 24);
        $this->assertNotNull($hari24);
        $this->assertEquals('07:45', $hari24['absen_masuk']);
        $this->assertEquals('15:00', $hari24['absen_pulang']);
        $this->assertFalse($hari24['has_jadwal']);
        $this->assertEquals(0, $hari24['terlambat_menit']); // Belum ada jadwal dinas, keterlambatan 0

        // Sekarang admin menginputkan jadwal dinas: Shift Pagi 07:00 - 14:00 (toleransi 5 menit)
        $shiftPagi = MasterShift::create([
            'nama_shift'                  => 'Shift Pagi',
            'jam_masuk'                   => '07:00:00',
            'jam_pulang'                  => '14:00:00',
            'toleransi_terlambat_menit'   => 5,
            'unit_kerja_id'               => $this->user->unit_kerja_id,
        ]);

        JadwalRoster::create([
            'user_id'       => $this->user->id,
            'tanggal_dinas' => '2026-11-24',
            'shift_id'      => $shiftPagi->id,
            'sesi'          => 1,
        ]);

        // Cek kembali halaman jadwal dinas: jadwal sudah di-input, barulah perhitungan keterlambatan bekerja!
        $resAfter = $this->actingAs($this->user)
            ->getJson('/app/api/jadwal-dinas?bulan=11&tahun=2026')
            ->assertOk();

        $hari24After = collect($resAfter->json('data.hari'))->firstWhere('tanggal', 24);
        $this->assertTrue($hari24After['has_jadwal']);
        $this->assertEquals('07:45', $hari24After['absen_masuk']);
        $this->assertEquals('15:00', $hari24After['absen_pulang']);
        $this->assertEquals('07:00', $hari24After['jam_masuk']);
        $this->assertEquals('14:00', $hari24After['jam_keluar']);
        // Jam masuk 07:45, jadwal 07:00 (toleransi 5m) -> terlambat 45 menit
        $this->assertEquals(45, $hari24After['terlambat_menit']);

        Carbon::setTestNow();
    }

    public function test_lembur_info_shift_works_with_roster_schedule_and_verifies_6h40m_threshold(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-24 10:00:00'));

        $shiftPagi = MasterShift::create([
            'nama_shift'                  => 'Shift Pagi',
            'jam_masuk'                   => '07:00:00',
            'jam_pulang'                  => '14:00:00',
            'toleransi_terlambat_menit'   => 5,
            'unit_kerja_id'               => $this->user->unit_kerja_id,
        ]);

        JadwalRoster::create([
            'user_id'       => $this->user->id,
            'tanggal_dinas' => '2026-11-24',
            'shift_id'      => $shiftPagi->id,
            'sesi'          => 1,
        ]);

        // Belum absen masuk aktual, tapi ada jadwal dinas:
        // Pukul 10:00 (baru 3 jam berjalan) -> belum mencapai 6 jam 40 menit (13:40)
        $resEarly = $this->actingAs($this->user)
            ->getJson('/app/api/lembur/info-shift')
            ->assertOk();

        $this->assertTrue($resEarly->json('success'));
        $this->assertEquals('07:00', $resEarly->json('data.jam_masuk'));
        $this->assertEquals('13:40', $resEarly->json('data.jam_ambang_lembur'));
        $this->assertFalse($resEarly->json('data.bisa_ekstensi'));

        // Pukul 14:10 (sudah 7 jam 10 menit) -> melewati 6 jam 40 menit (13:40)
        Carbon::setTestNow(Carbon::parse('2026-11-24 14:10:00'));

        $resReady = $this->actingAs($this->user)
            ->getJson('/app/api/lembur/info-shift')
            ->assertOk();

        $this->assertTrue($resReady->json('data.bisa_ekstensi'));
        // 14:10 - 13:40 = 30 menit
        $this->assertEquals(30, $resReady->json('data.durasi_otomatis_menit'));

        Carbon::setTestNow();
    }

    public function test_store_ekstensi_auto_clocks_out_active_log_without_undefined_variable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 21:39:00'));

        // Log absensi masuk pukul 08:03:00 (belum absen pulang) -> 6j 40m = 14:43:00
        // 14:43:00 s/d 21:39:00 = 416 menit (6 jam 56 menit)
        $logDinas = LogAbsensi::create([
            'user_id'          => $this->user->id,
            'tanggal_dinas'    => '2026-11-26',
            'waktu_masuk'      => Carbon::parse('2026-11-26 08:03:00'),
            'jenis_absen'      => 'luar_jadwal',
            'menit_terlambat'  => 0,
            'status_kehadiran' => 'Tanpa Jadwal',
        ]);

        $response = $this->actingAs($this->user)
            ->post('/app/api/lembur/ekstensi', [
                'durasi_menit' => 416,
                'keterangan'   => 'ekspo AKREDITASI dari jam 8 lanjut shift sore jam 3-21.40',
                'latitude'     => -0.947110,
                'longitude'    => 100.417180,
                'accuracy'     => 15,
                'foto_masuk'   => UploadedFile::fake()->image('lembur.jpg'),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.auto_clock_out', true)
            ->assertJsonPath('data.durasi_menit', 416);

        $logDinas->refresh();
        $this->assertNotNull($logDinas->waktu_pulang);
        $this->assertEquals('2026-11-26 21:39:00', $logDinas->waktu_pulang->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_oncall_masuk_today_status_and_keluar_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 20:00:00'));

        // On-Call Masuk
        $resIn = $this->actingAs($this->user)
            ->post('/app/api/lembur/oncall-masuk', [
                'keterangan' => 'Operasi Cito Bedah',
                'latitude'   => -0.947110,
                'longitude'  => 100.417180,
                'accuracy'   => 12,
                'foto_masuk' => UploadedFile::fake()->image('oncall_in.jpg'),
            ]);
        $resIn->assertOk()->assertJsonPath('success', true);

        // Today Status harus menampilkan keterangan On-Call dengan benar (bukan null)
        $statusRes = $this->actingAs($this->user)
            ->getJson('/app/api/today-status')
            ->assertOk();
        $this->assertEquals('Operasi Cito Bedah', $statusRes->json('data.on_call_aktif.keterangan'));

        // On-Call Keluar setelah 45 menit
        Carbon::setTestNow(Carbon::parse('2026-11-26 20:45:00'));
        $resOut = $this->actingAs($this->user)
            ->post('/app/api/lembur/oncall-keluar', [
                'latitude'    => -0.947110,
                'longitude'   => 100.417180,
                'accuracy'    => 12,
                'foto_keluar' => UploadedFile::fake()->image('oncall_out.jpg'),
            ]);
        $resOut->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.durasi_menit', 45)
            ->assertJsonPath('data.auto_clock_out', false);

        Carbon::setTestNow();
    }

    public function test_bank_jam_pengambilan_and_validasi_work_across_web_and_sirom_payloads(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 10:00:00'));

        $unitGanti = MasterUnitKerja::create([
            'nama_unit'    => 'Farmasi',
            'jenis_lembur' => 'ganti_jam',
        ]);

        $stafGanti = User::create([
            'name'                    => 'Staf Farmasi',
            'username'                => 'staf_farmasi',
            'nik'                     => '19990002',
            'email'                   => 'farmasi@ropanasuri.test',
            'unit_kerja_id'           => $unitGanti->id,
            'role'                    => 'staf',
            'password'                => bcrypt('password123'),
            'is_active'               => true,
            'saldo_awal_lembur_menit' => 120,
        ]);

        $kaUnit = User::create([
            'name'          => 'Ka Unit Farmasi',
            'username'      => 'ka_farmasi',
            'nik'           => '19990003',
            'email'         => 'kafarmasi@ropanasuri.test',
            'unit_kerja_id' => $unitGanti->id,
            'role'          => 'kepala_unit',
            'password'      => bcrypt('password123'),
            'is_active'     => true,
        ]);

        // 1. Cek Bank Jam menampilkan saldo awal + teks kompatibel
        $lemburData = $this->actingAs($stafGanti)
            ->getJson('/app/api/lembur-data')
            ->assertOk();
        $this->assertEquals(120, $lemburData->json('data.bank_jam.saldo_menit_tersedia'));
        $this->assertEquals('2 Jam', $lemburData->json('data.bank_jam.saldo_jam_teks'));
        $this->assertEquals('2 Jam', $lemburData->json('data.bank_jam.total_lembur_disetujui_teks'));

        // 2. Pengajuan Ambil Jam dengan payload gaya Web Mobile (tanggal_rencana + alasan)
        $resAmbilWeb = $this->actingAs($stafGanti)
            ->postJson('/app/api/lembur/ambil-jam', [
                'tanggal_rencana' => '2026-11-27',
                'durasi_menit'    => 60,
                'alasan'          => 'Izin urusan keluarga',
            ])
            ->assertOk();
        $this->assertTrue($resAmbilWeb->json('success'));
        $this->assertEquals('Izin urusan keluarga', $resAmbilWeb->json('data.alasan'));
        $ambilId = $resAmbilWeb->json('data.id');

        // 3. Validasi Ambil Jam oleh Kepala Unit memakai payload Web Mobile (aksi: 'setujui')
        $this->actingAs($kaUnit)
            ->postJson('/app/api/validasi/ambil-jam/' . $ambilId, ['aksi' => 'setujui'])
            ->assertOk()
            ->assertJsonPath('success', true);

        // 4. Validasi Lembur memakai payload Web Mobile (aksi: 'setujui')
        $lemburPending = \App\Models\LogLembur::create([
            'user_id'              => $stafGanti->id,
            'jenis_lembur'         => 'Ekstensi Shift',
            'waktu_mulai_lembur'   => Carbon::parse('2026-11-26 15:00:00'),
            'waktu_selesai_lembur' => Carbon::parse('2026-11-26 16:00:00'),
            'total_jam_lembur'     => 1.0,
            'status_validasi'      => 'Pending',
            'keterangan'           => 'Stok opname',
        ]);

        $this->actingAs($kaUnit)
            ->postJson('/app/api/validasi/proses/' . $lemburPending->id, ['aksi' => 'setujui'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Carbon::setTestNow();
    }

    public function test_roster_unit_handles_custom_shift_safely(): void
    {
        $kaUnit = User::create([
            'name'          => 'Ka Unit IGD',
            'username'      => 'ka_igd',
            'nik'           => '19990004',
            'email'         => 'kaigd@ropanasuri.test',
            'unit_kerja_id' => $this->user->unit_kerja_id,
            'role'          => 'kepala_unit',
            'password'      => bcrypt('password123'),
            'is_active'     => true,
        ]);

        JadwalRoster::create([
            'user_id'           => $this->user->id,
            'tanggal_dinas'     => '2026-11-26',
            'sesi'              => 1,
            'shift_id'          => null,
            'custom_nama_shift' => 'Shift Khusus Akreditasi',
            'custom_jam_masuk'  => '08:00:00',
            'custom_jam_pulang' => '15:00:00',
        ]);

        $controller = new \App\Http\Controllers\Api\RosterController();
        $req = \Illuminate\Http\Request::create('/api/roster/unit', 'GET', [
            'bulan' => 11,
            'tahun' => 2026,
        ]);
        $req->setUserResolver(fn() => $kaUnit);

        $res = $controller->index($req);
        $this->assertEquals(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertEquals('Shift Khusus Akreditasi', $payload['data']['staf'][0]['jadwal'][0]['nama_shift']);
        $this->assertEquals('2026-11-26', $payload['data']['staf'][0]['jadwal'][0]['tanggal']);
    }

    public function test_admin_laporan_and_monitoring_queries_return_expected_recaps(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 15:30:00'));

        $unitGanti = MasterUnitKerja::create([
            'nama_unit'    => 'Rekam Medis',
            'jenis_lembur' => 'ganti_jam',
        ]);

        $staf = User::create([
            'name'                    => 'Staf RM',
            'username'                => 'staf_rm',
            'nik'                     => '19990005',
            'email'                   => 'rm@ropanasuri.test',
            'unit_kerja_id'           => $unitGanti->id,
            'role'                    => 'staf',
            'password'                => bcrypt('password123'),
            'is_active'               => true,
            'saldo_awal_lembur_menit' => 90,
        ]);

        $admin = User::create([
            'name'          => 'HRD Admin',
            'username'      => 'hrd_admin',
            'nik'           => '19990006',
            'email'         => 'hrd@ropanasuri.test',
            'unit_kerja_id' => $this->user->unit_kerja_id,
            'role'          => 'hrd',
            'password'      => bcrypt('password123'),
            'is_active'     => true,
        ]);

        $shift = MasterShift::create([
            'nama_shift'                => 'Shift Pagi',
            'jam_masuk'                 => '07:00:00',
            'jam_pulang'                => '14:00:00',
            'toleransi_terlambat_menit' => 5,
            'unit_kerja_id'             => $unitGanti->id,
        ]);

        JadwalRoster::create([
            'user_id'       => $staf->id,
            'tanggal_dinas' => '2026-11-26',
            'sesi'          => 1,
            'shift_id'      => $shift->id,
        ]);

        LogAbsensi::create([
            'user_id'          => $staf->id,
            'roster_id'        => null,
            'tanggal_dinas'    => '2026-11-26',
            'waktu_masuk'      => Carbon::parse('2026-11-26 07:02:00'),
            'waktu_pulang'     => Carbon::parse('2026-11-26 14:05:00'),
            'jenis_absen'      => 'dalam_radius',
            'menit_terlambat'  => 0,
            'status_kehadiran' => 'Tepat Waktu',
        ]);

        \App\Models\LogLembur::create([
            'user_id'              => $staf->id,
            'jenis_lembur'         => 'Ekstensi Shift',
            'waktu_mulai_lembur'   => Carbon::parse('2026-11-26 14:10:00'),
            'waktu_selesai_lembur' => Carbon::parse('2026-11-26 15:10:00'),
            'total_jam_lembur'     => 1.0,
            'status_validasi'      => 'Disetujui',
            'keterangan'           => 'Input berkas',
        ]);

        \App\Models\PengambilanLembur::create([
            'user_id'          => $staf->id,
            'tanggal_dinas'    => '2026-11-26',
            'tipe_pengambilan' => 'pulang_dipercepat',
            'durasi_menit'     => 30,
            'keterangan'       => 'Izin pulang awal',
            'status_validasi'  => 'Disetujui',
        ]);

        // Test WebLaporanController::index (Rekap Absensi) & rekapLembur (Bank Jam teragregasi)
        $laporanCtrl = new \App\Http\Controllers\WebLaporanController();
        $reqLap = \Illuminate\Http\Request::create('/laporan-payroll', 'GET', [
            'tanggal_mulai'   => '2026-11-26',
            'tanggal_selesai' => '2026-11-26',
            'unit'            => $unitGanti->id,
        ]);
        $reqLap->setUserResolver(fn() => $admin);
        $viewLap = $laporanCtrl->index($reqLap);
        $dataLap = $viewLap->getData();
        $this->assertEquals(1, $dataLap['stats']['total']);
        $this->assertEquals(1.0, $dataLap['stats']['jam_lembur']);

        $reqLembur = \Illuminate\Http\Request::create('/laporan-payroll/lembur', 'GET', [
            'bulan'   => 11,
            'tahun'   => 2026,
            'unit_id' => $unitGanti->id,
            'tab'     => 'bank_jam',
        ]);
        $reqLembur->setUserResolver(fn() => $admin);
        $viewLembur = $laporanCtrl->rekapLembur($reqLembur);
        $dataLembur = $viewLembur->getData();
        $bankRow = $dataLembur['rowsBankJam']->firstWhere('user_id', $staf->id);
        $this->assertNotNull($bankRow);
        // 90 (saldo awal) + 60 (1 jam lembur) = 150 diperoleh; 30 digunakan -> 120 tersedia
        $this->assertEquals(150, $bankRow['menit_diperoleh']);
        $this->assertEquals(30, $bankRow['menit_digunakan']);
        $this->assertEquals(120, $bankRow['saldo_menit']);

        // Test WebMonitoringController::index (pre-loaded fallback logs tanpa N+1)
        $monCtrl = new \App\Http\Controllers\WebMonitoringController();
        $reqMon = \Illuminate\Http\Request::create('/monitoring', 'GET', [
            'tanggal' => '2026-11-26',
            'unit_id' => $unitGanti->id,
        ]);
        $reqMon->setUserResolver(fn() => $admin);
        $viewMon = $monCtrl->index($reqMon);
        $dataMon = $viewMon->getData();
        $this->assertEquals(1, $dataMon['totalJadwal']);
        $this->assertEquals(1, $dataMon['hadirCount']);
        $this->assertEquals(1, $dataMon['gantiJamCount']);

        Carbon::setTestNow();
    }
}
