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
        'tipe',
        'pertanyaan',
        'gambar',
        'bobot',
        'tingkat_kesulitan',
        'pembahasan',
    ];

    protected $casts = [
        'tipe' => QuestionType::class,
    ];

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
}
