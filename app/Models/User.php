<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'diblokir',
        'diblokir_pada',
        'alasan_blokir',
        'diblokir_test_id',
        'cadangan_test_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'diblokir' => 'boolean',
            'diblokir_pada' => 'datetime',
        ];
    }

    public function isBlocked(): bool
    {
        return (bool) $this->diblokir;
    }

    public function blokir(string $alasan = 'Pelanggaran ujian', ?int $testId = null): void
    {
        $this->update([
            'diblokir' => true,
            'diblokir_pada' => now(),
            'alasan_blokir' => $alasan,
            'diblokir_test_id' => $testId,
        ]);
    }

    /**
     * Buka blokir: ujian penyebab dihapus attempt-nya agar bisa diulang,
     * dan ditandai supaya ujian ulang memakai SOAL CADANGAN.
     */
    public function bukaBlokir(): void
    {
        $testId = $this->diblokir_test_id;

        if ($testId) {
            $this->attempts()->where('test_id', $testId)->delete();
        }

        $this->update([
            'diblokir' => false,
            'diblokir_pada' => null,
            'alasan_blokir' => null,
            'diblokir_test_id' => null,
            'cadangan_test_id' => $testId,
        ]);
    }

    /**
     * Ujian yang dibuat oleh user ini (operator/admin).
     */
    public function createdTests(): HasMany
    {
        return $this->hasMany(Test::class, 'created_by');
    }

    /**
     * Percobaan ujian milik user ini (siswa).
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin'], true);
    }
}
