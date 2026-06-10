<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajarans';

    protected $fillable = [
        'nama',
        'kode',
        'deskripsi',
    ];

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
