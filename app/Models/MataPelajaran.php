<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajarans';

    protected $fillable = [
        'jurusan_id',
        'nama',
        'kode',
        'deskripsi',
    ];

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class);
    }

    /**
     * Guru (operator) yang ditugaskan mengajar mapel ini.
     */
    public function gurus(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guru_mata_pelajaran');
    }

    /**
     * Daftar ujian pada mata pelajaran ini.
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    /**
     * Bank soal milik mata pelajaran ini.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
