<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenempatanSiswa extends Model
{
    protected $table = 'penempatan_siswas';

    protected $fillable = ['test_id', 'ruangan_id', 'user_id'];

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
