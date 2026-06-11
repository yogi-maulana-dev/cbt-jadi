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
}
