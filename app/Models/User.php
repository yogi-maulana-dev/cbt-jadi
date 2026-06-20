<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
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
        'no_ujian',
        'kelas',
        'program_studi',
        'must_change_password',
        'password',
        'role',
        'aktif',
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
    /**
     * Nilai default atribut untuk instance baru.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'diblokir' => 'boolean',
            'diblokir_pada' => 'datetime',
            'must_change_password' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    /**
     * Mata pelajaran yang diajar guru ini (penugasan many-to-many).
     */
    public function mataPelajarans(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MataPelajaran::class, 'guru_mata_pelajaran');
    }

    /**
     * @return array<int, int> ID mapel yang diajar guru ini.
     */
    public function mataPelajaranIds(): array
    {
        return $this->mataPelajarans()->pluck('mata_pelajarans.id')->all();
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

        // Catat riwayat insiden (terbuka; ditutup saat buka blokir) untuk evaluasi.
        PelanggaranLog::create([
            'user_id' => $this->id,
            'test_id' => $testId,
            'test_judul' => $testId ? optional(Test::find($testId))->judul : null,
            'alasan' => $alasan,
            'diblokir_pada' => now(),
        ]);
    }

    /**
     * Buka blokir: ujian penyebab dihapus attempt-nya agar bisa diulang,
     * dan ditandai supaya ujian ulang memakai SOAL CADANGAN.
     */
    public function bukaBlokir(): void
    {
        $testId = $this->diblokir_test_id;

        // Tutup riwayat insiden yang masih terbuka (atau buat bila tak ada — blokir lama).
        $log = PelanggaranLog::where('user_id', $this->id)->whereNull('dibuka_pada')->latest('id')->first();
        if ($log) {
            $log->update(['dibuka_pada' => now(), 'dibuka_oleh' => auth()->id()]);
        } else {
            PelanggaranLog::create([
                'user_id' => $this->id,
                'test_id' => $testId,
                'test_judul' => $testId ? optional(Test::find($testId))->judul : null,
                'alasan' => $this->alasan_blokir,
                'diblokir_pada' => $this->diblokir_pada,
                'dibuka_pada' => now(),
                'dibuka_oleh' => auth()->id(),
            ]);
        }

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

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function isPengawas(): bool
    {
        return $this->role === 'pengawas';
    }

    /**
     * Ujian (jadwal) yang diawasi user ini (relasi balik dari Test::pengawas()).
     * Dinamai `tests` agar Filament RelationManager dapat menebak relasi balik.
     */
    public function tests(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_pengawas')
            ->withPivot('ruangan')
            ->withTimestamps();
    }

    /** Alias yang lebih jelas untuk dipakai di aplikasi. */
    public function ujianDiawasi(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->tests();
    }

    /**
     * Hanya guru/operator/admin/superadmin yang boleh masuk panel admin. Siswa diblokir.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->aktif && in_array($this->role, ['guru', 'operator', 'pengawas', 'admin', 'superadmin'], true);
    }
}
