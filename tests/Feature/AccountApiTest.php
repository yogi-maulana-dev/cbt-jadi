<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Siswa', 'email' => 'a@a.test',
            'password' => bcrypt('password'), 'role' => 'siswa',
        ]);
    }

    /** @return array{captcha_id:string,captcha_answer:int} */
    private function captchaPayload(): array
    {
        $c = $this->getJson('/api/captcha')->json();
        [$a, , $b] = explode(' ', $c['question']);

        return ['captcha_id' => $c['captcha_id'], 'captcha_answer' => (int) $a + (int) $b];
    }

    public function test_captcha_memberi_id_dan_soal(): void
    {
        $this->getJson('/api/captcha')
            ->assertOk()
            ->assertJsonStructure(['captcha_id', 'question']);
    }

    public function test_login_tanpa_captcha_ditolak(): void
    {
        $this->user();
        $this->postJson('/api/login', ['email' => 'a@a.test', 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_login_captcha_salah_ditolak(): void
    {
        $this->user();
        $payload = $this->captchaPayload();
        $payload['captcha_answer'] = $payload['captcha_answer'] + 1; // sengaja salah

        $this->postJson('/api/login', array_merge(
            ['email' => 'a@a.test', 'password' => 'password'],
            $payload
        ))->assertStatus(422);
    }

    public function test_blocked_status_endpoint(): void
    {
        $user = $this->user();

        $this->postJson('/api/blocked-status', ['email' => 'a@a.test'])
            ->assertOk()->assertJson(['diblokir' => false]);

        $user->update(['diblokir' => true]);

        $this->postJson('/api/blocked-status', ['email' => 'a@a.test'])
            ->assertOk()->assertJson(['diblokir' => true]);
    }

    public function test_user_diblokir_tidak_bisa_login(): void
    {
        $user = $this->user();
        $user->update(['diblokir' => true]);

        $this->postJson('/api/login', array_merge(
            ['email' => 'a@a.test', 'password' => 'password'],
            $this->captchaPayload()
        ))->assertStatus(422);
    }

    public function test_edit_profil(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['name' => 'Nama Baru', 'email' => 'baru@a.test'])
            ->assertOk()
            ->assertJson(['ok' => true, 'user' => ['name' => 'Nama Baru', 'email' => 'baru@a.test']]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nama Baru', 'email' => 'baru@a.test']);
    }

    public function test_ganti_password(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->putJson('/api/password', [
            'current_password' => 'password',
            'password' => 'rahasia123',
        ])->assertOk();

        $this->assertTrue(Hash::check('rahasia123', $user->fresh()->password));
    }

    public function test_ganti_password_current_salah_ditolak(): void
    {
        Sanctum::actingAs($this->user());

        $this->putJson('/api/password', [
            'current_password' => 'salah',
            'password' => 'rahasia123',
        ])->assertStatus(422);
    }

    public function test_lupa_password_lalu_reset_dengan_otp(): void
    {
        $user = $this->user();

        $this->postJson('/api/password/forgot', ['email' => 'a@a.test'])->assertOk();

        // Di dev OTP ada di cache (di produksi via email).
        $otp = Cache::get('otp:a@a.test');
        $this->assertNotNull($otp);

        $this->postJson('/api/password/reset', [
            'email' => 'a@a.test',
            'otp' => $otp,
            'password' => 'barulagi123',
        ])->assertOk();

        $this->assertTrue(Hash::check('barulagi123', $user->fresh()->password));
    }

    public function test_reset_otp_salah_ditolak(): void
    {
        $this->user();
        $this->postJson('/api/password/forgot', ['email' => 'a@a.test'])->assertOk();

        $this->postJson('/api/password/reset', [
            'email' => 'a@a.test',
            'otp' => '000000',
            'password' => 'barulagi123',
        ])->assertStatus(422);
    }
}
