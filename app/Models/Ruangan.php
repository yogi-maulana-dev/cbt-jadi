<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ruangan extends Model
{
    protected $fillable = ['nama', 'kapasitas'];

    protected $casts = ['kapasitas' => 'integer'];

    /**
     * Daftar pilihan dropdown ruangan (urut natural: 1, 2, .. 10, 11).
     * Value = nama (disimpan di pivot); label menampilkan kapasitas bila ada.
     *
     * @return array<string, string>
     */
    public static function pilihan(): array
    {
        return static::orderByRaw('LENGTH(nama), nama')
            ->get(['nama', 'kapasitas'])
            ->mapWithKeys(fn (Ruangan $r): array => [
                $r->nama => $r->kapasitas ? "{$r->nama} (kap. {$r->kapasitas})" : $r->nama,
            ])
            ->all();
    }
}
