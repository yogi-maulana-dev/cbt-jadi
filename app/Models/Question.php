<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mata_pelajaran_id',
        'created_by',
        'import_batch',
        'tipe',
        'pertanyaan',
        'gambar',
        'video_url',
        'video_path',
        'media_pending',
        'bobot',
        'tingkat_kesulitan',
        'pembahasan',
    ];

    protected $casts = [
        'tipe' => QuestionType::class,
        'media_pending' => 'boolean',
    ];

    /**
     * URL video soal: file upload (video_path) diutamakan, lalu URL eksternal.
     */
    public function getVideoSrcAttribute(): ?string
    {
        if ($this->video_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->video_path);
        }

        return $this->video_url ?: null;
    }

    /**
     * True bila video berupa file yang diupload (diputar dengan <video>),
     * false bila berupa URL eksternal (dibuka sebagai tautan).
     */
    public function getVideoIsFileAttribute(): bool
    {
        return (bool) $this->video_path;
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(Choice::class)->orderBy('urutan');
    }

    /**
     * Pilihan jawaban yang benar (pilihan ganda biasanya satu).
     */
    public function correctChoice(): HasMany
    {
        return $this->hasMany(Choice::class)->where('is_correct', true);
    }

    /**
     * Ujian-ujian yang memakai soal ini (bank soal reusable).
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_question')
            ->withPivot(['urutan', 'bobot'])
            ->withTimestamps();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    /**
     * Soal ini sedang dipakai pada ujian yang aktif dikerjakan siswa?
     */
    public function inActiveExam(): bool
    {
        return $this->tests()
            ->whereHas('attempts', fn (\Illuminate\Database\Eloquent\Builder $q) => $q->aktif())
            ->exists();
    }

    protected static function booted(): void
    {
        // Pengaman: jangan hapus soal yang sedang dipakai ujian aktif.
        static::deleting(function (Question $question) {
            if ($question->inActiveExam()) {
                throw new \RuntimeException(
                    'Soal ini tidak dapat dihapus karena sedang dipakai pada ujian yang aktif dikerjakan siswa.'
                );
            }
        });
    }
}
