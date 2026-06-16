<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penyimpanan pengaturan aplikasi sederhana (key-value).
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULT_KICK_TITLE = 'Ujian Dihentikan Sementara';

    public const DEFAULT_KICK_MESSAGE = 'Maaf, ujian dihentikan sementara karena ada pemeliharaan (maintenance). Silakan tunggu arahan pengawas/guru, lalu mulai ujian lagi bila sudah diizinkan.';

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** Judul modal saat siswa dikeluarkan dari ujian. */
    public static function kickTitle(): string
    {
        return static::get('exam_kick_title', self::DEFAULT_KICK_TITLE);
    }

    /** Pesan modal saat siswa dikeluarkan dari ujian. */
    public static function kickMessage(): string
    {
        return static::get('exam_kick_message', self::DEFAULT_KICK_MESSAGE);
    }
}
