<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestAttempt extends Model
{
    protected $fillable = [
        'test_id',
        'user_id',
        'waktu_mulai',
        'deadline',
        'waktu_selesai',
        'skor',
        'jumlah_benar',
        'pelanggaran',
        'status',
    ];

    protected $attributes = [
        'pelanggaran' => 0,
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'deadline' => 'datetime',
        'waktu_selesai' => 'datetime',
        'skor' => 'decimal:2',
        'status' => AttemptStatus::class,
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Snapshot urutan soal (hasil acak) untuk attempt ini.
     */
    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(AttemptQuestion::class)->orderBy('urutan');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    public function isExpired(): bool
    {
        return $this->deadline !== null && now()->greaterThanOrEqualTo($this->deadline);
    }
}
