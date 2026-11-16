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
            $table->string('foto_masuk')->nullable();
            $table->string('foto_keluar')->nullable();
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
}
