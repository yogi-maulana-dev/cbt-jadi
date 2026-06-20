<?php

namespace App\Models;

use App\Enums\TestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Test extends Model
{
    protected $fillable = [
        'mata_pelajaran_id',
        'created_by',
        'judul',
        'deskripsi',
        'durasi',
        'kkm',
        'acak_soal',
        'acak_jawaban',
        'max_pelanggaran',
        'tampilkan_hasil',
        'token',
        'waktu_mulai',
        'waktu_selesai',
        'status',
    ];

    protected $casts = [
        'acak_soal' => 'boolean',
        'acak_jawaban' => 'boolean',
        'tampilkan_hasil' => 'boolean',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'status' => TestStatus::class,
    ];

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Soal yang menyusun ujian ini, diambil dari bank soal via pivot.
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'test_question')
            ->withPivot(['urutan', 'bobot', 'cadangan'])
            ->withTimestamps()
            ->orderByPivot('urutan');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    /**
     * Penempatan siswa ke ruangan untuk jadwal ini.
     */
    public function penempatanSiswa(): HasMany
    {
        return $this->hasMany(PenempatanSiswa::class);
    }

    /**
     * Pengawas ujian ini (banyak pengawas per jadwal, masing-masing punya ruangan).
     */
    public function pengawas(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'test_pengawas')
            ->withPivot('ruangan')
            ->withTimestamps();
    }

    /**
     * Pesan bentrok: pengawas ujian ini yang juga mengawasi ujian LAIN pada waktu
     * yang berbarengan (butuh jadwal mulai & selesai terisi). Mengembalikan nama pengawas.
     *
     * @return array<int, string>
     */
    public function konflikPengawas(): array
    {
        if (! $this->waktu_mulai || ! $this->waktu_selesai) {
            return [];
        }

        $this->loadMissing('pengawas');

        $bentrok = static::query()
            ->where('id', '!=', $this->id)
            ->whereNotNull('waktu_mulai')->whereNotNull('waktu_selesai')
            ->where('waktu_mulai', '<', $this->waktu_selesai)
            ->where('waktu_selesai', '>', $this->waktu_mulai)
            ->with('pengawas')
            ->get();

        $msgs = [];
        foreach ($this->pengawas as $pg) {
            foreach ($bentrok as $o) {
                if ($o->pengawas->contains('id', $pg->id)) {
                    $msgs[] = $pg->name.' juga mengawasi "'.$o->judul.'" pada waktu yang berbarengan'
                        .($o->waktu_mulai ? ' ('.$o->waktu_mulai->format('d/m H:i').')' : '').'.';
                }
            }
        }

        return array_values(array_unique($msgs));
    }

    /**
     * Ada siswa yang sedang mengerjakan ujian ini (belum selesai & waktu belum habis)?
     */
    public function hasActiveAttempts(): bool
    {
        return $this->attempts()->aktif()->exists();
    }

    public function activeAttemptsCount(): int
    {
        return $this->attempts()->aktif()->count();
    }

    protected static function booted(): void
    {
        // Pengaman: jangan biarkan ujian terhapus saat masih dikerjakan siswa.
        static::deleting(function (Test $test) {
            if ($test->hasActiveAttempts()) {
                throw new \RuntimeException(
                    'Ujian "'.$test->judul.'" tidak dapat dihapus karena masih ada siswa yang mengerjakan.'
                );
            }
        });
    }
}
