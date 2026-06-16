<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat blokir/buka-blokir siswa karena pelanggaran ujian (untuk evaluasi).
 * Satu baris = satu insiden: kapan diblokir & kapan dibuka, oleh siapa.
 */
class PelanggaranLog extends Model
{
    protected $fillable = [
        'user_id',
        'test_id',
        'test_judul',
        'alasan',
        'diblokir_pada',
        'dibuka_pada',
        'dibuka_oleh',
    ];

    protected $casts = [
        'diblokir_pada' => 'datetime',
        'dibuka_pada' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dibukaOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }
}
