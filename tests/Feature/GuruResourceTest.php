<?php

namespace Tests\Feature;

use App\Filament\Resources\GuruResource\Pages\CreateGuru;
use App\Filament\Resources\GuruResource\Pages\ListGurus;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuruResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_bisa_membuat_akun_guru(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(CreateGuru::class)
            ->fillForm([
                'name' => 'Bu Sari',
                'email' => 'sari@sekolah.test',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $guru = User::where('email', 'sari@sekolah.test')->first();
        $this->assertNotNull($guru);
        $this->assertSame('guru', $guru->role);
        $this->assertTrue($guru->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_password_wajib_dan_tervalidasi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Password kosong -> wajib.
        Livewire::actingAs($admin)
            ->test(CreateGuru::class)
            ->fillForm(['name' => 'A', 'email' => 'a@sekolah.test'])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);

        // Password terlalu pendek (< 8).
        Livewire::actingAs($admin)
            ->test(CreateGuru::class)
            ->fillForm(['name' => 'A', 'email' => 'a@sekolah.test', 'password' => '123', 'password_confirmation' => '123'])
            ->call('create')
            ->assertHasFormErrors(['password' => 'min']);

        // Konfirmasi tidak cocok.
        Livewire::actingAs($admin)
            ->test(CreateGuru::class)
            ->fillForm(['name' => 'A', 'email' => 'a@sekolah.test', 'password' => 'rahasia123', 'password_confirmation' => 'beda12345'])
            ->call('create')
            ->assertHasFormErrors(['password']);

        // Tidak ada guru yang berhasil dibuat dari percobaan yang gagal.
        $this->assertSame(0, User::where('role', 'guru')->count());
    }

    public function test_menu_guru_hanya_menampilkan_guru(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guru = User::factory()->create(['role' => 'guru']);
        $siswa = User::factory()->create(['role' => 'siswa']);

        Livewire::actingAs($admin)
            ->test(ListGurus::class)
            ->assertCanSeeTableRecords([$guru])
            ->assertCanNotSeeTableRecords([$siswa, $admin]);
    }
}
