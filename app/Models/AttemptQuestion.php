<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptQuestion extends Model
{
    protected $fillable = [
        'test_attempt_id',
        'question_id',
        'urutan',
        'urutan_opsi',
        'ragu',
    ];

    protected $casts = [
        'urutan_opsi' => 'array',
        'ragu' => 'boolean',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class, 'test_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
