<?php

namespace Tests\Feature;

use App\Filament\Resources\SiswaResource;
use App\Models\User;
use App\Services\StudentImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SiswaTest extends TestCase
{
    use RefreshDatabase;

    private function buatFileExcel(array $rows): string
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Siswa');
        $sheet->fromArray(StudentImport::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'siswa').'.xlsx';
        (new Xlsx($ss))->save($path);

        return $path;
    }

    public function test_import_siswa_pakai_no_ujian(): void
    {
        $path = $this->buatFileExcel([
            ['2024001', 'Budi Santoso', 'XII RPL 1', 'Rekayasa Perangkat Lunak'],
            ['2024002', 'Siti Aminah', 'XII TKJ 2', 'Teknik Komputer dan Jaringan'],
        ]);

        $report = app(StudentImport::class)->import($path);
        @unlink($path);

        $this->assertSame(2, $report['imported']);

        $budi = User::where('no_ujian', '2024001')->first();
        $this->assertSame('siswa', $budi->role);
        $this->assertSame('2024001@ujian.local', $budi->email);
        $this->assertSame('XII RPL 1', $budi->kelas);
        $this->assertTrue(Hash::check('2024001', $budi->password)); // password = No Ujian
        $this->assertTrue($budi->must_change_password);             // wajib ganti saat login pertama
    }

    public function test_login_pakai_no_ujian_berhasil(): void
    {
        $siswa = User::factory()->create([
            'role' => 'siswa', 'no_ujian' => 'NU123', 'email' => 'nu123@ujian.local',
            'password' => 'NU123', 'must_change_password' => true,
        ]);

        \Livewire\Volt\Volt::test('pages.auth.login')
            ->set('form.email', 'NU123')      // login pakai No Ujian
            ->set('form.password', 'NU123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($siswa);
    }

    public function test_login_pertama_diarahkan_lengkapi_akun(): void
    {
        $siswa = User::factory()->create(['role' => 'siswa', 'must_change_password' => true]);

        $this->actingAs($siswa)->get('/ujian')->assertRedirect(route('akun.lengkapi'));

        // Setelah dilengkapi -> tidak diarahkan lagi.
        $siswa->update(['must_change_password' => false]);
        $this->actingAs($siswa)->get('/ujian')->assertOk();
    }

    public function test_lengkapi_akun_simpan_password_dan_email(): void
    {
        $siswa = User::factory()->create([
            'role' => 'siswa', 'no_ujian' => 'NU9', 'email' => 'nu9@ujian.local', 'must_change_password' => true,
        ]);
        $this->actingAs($siswa);

        \Livewire\Volt\Volt::test('pages.akun.lengkapi')
            ->set('email', 'budi.asli@gmail.com')
            ->set('password', 'rahasiabaru')
            ->set('password_confirmation', 'rahasiabaru')
            ->call('simpan')
            ->assertHasNoErrors();

        $siswa->refresh();
        $this->assertSame('budi.asli@gmail.com', $siswa->email);
        $this->assertFalse($siswa->must_change_password);
        $this->assertTrue(Hash::check('rahasiabaru', $siswa->password));
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        User::factory()->create([
            'role' => 'siswa', 'no_ujian' => 'OFF1', 'email' => 'off1@ujian.local',
            'password' => 'OFF1', 'aktif' => false, 'must_change_password' => false,
        ]);

        \Livewire\Volt\Volt::test('pages.auth.login')
            ->set('form.email', 'OFF1')
            ->set('form.password', 'OFF1')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();
    }

    public function test_download_kartu_word_hanya_admin(): void
    {
        $siswa = User::factory()->create(['role' => 'siswa', 'name' => 'Andi', 'no_ujian' => 'W1']);

        $res = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('kartu.word', ['ids' => $siswa->id]));
        $res->assertOk();
        $this->assertStringContainsString('wordprocessingml', $res->headers->get('Content-Type'));

        $this->actingAs(User::factory()->create(['role' => 'guru']))
            ->get(route('kartu.word', ['ids' => $siswa->id]))
            ->assertForbidden();
    }

    public function test_cetak_kartu_massal_semua_dan_per_kelas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'siswa', 'name' => 'Andika RPL', 'no_ujian' => 'A1', 'kelas' => 'XII RPL']);
        User::factory()->create(['role' => 'siswa', 'name' => 'Bagus TKJ', 'no_ujian' => 'B1', 'kelas' => 'XII TKJ']);

        // Semua siswa.
        $this->actingAs($admin)->get(route('kartu.ujian', ['all' => 1]))
            ->assertOk()->assertSee('Andika RPL')->assertSee('Bagus TKJ');

        // Per kelas: hanya kelas itu.
        $this->actingAs($admin)->get(route('kartu.ujian', ['kelas' => 'XII RPL']))
            ->assertOk()->assertSee('Andika RPL')->assertDontSee('Bagus TKJ');

        // Tanpa kriteria & bukan "all" -> kosong (tidak mencetak semua tak sengaja).
        $this->actingAs($admin)->get(route('kartu.ujian'))
            ->assertOk()->assertDontSee('Andika RPL');
    }

    public function test_resource_siswa_hanya_admin_dan_hanya_role_siswa(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertTrue(SiswaResource::canViewAny());

        User::factory()->create(['role' => 'siswa', 'no_ujian' => 'S1']);
        User::factory()->create(['role' => 'guru']);

        $this->assertSame(['siswa'], SiswaResource::getEloquentQuery()->pluck('role')->unique()->all());

        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->assertFalse(SiswaResource::canViewAny());
    }

    public function test_cetak_kartu_hanya_admin(): void
    {
        $siswa = User::factory()->create([
            'role' => 'siswa', 'name' => 'Andi Pratama', 'no_ujian' => 'X1', 'kelas' => 'XII RPL', 'program_studi' => 'RPL',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('kartu.ujian', ['ids' => $siswa->id]))
            ->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('X1');

        $this->actingAs(User::factory()->create(['role' => 'guru']))
            ->get(route('kartu.ujian', ['ids' => $siswa->id]))
            ->assertForbidden();
    }
}
