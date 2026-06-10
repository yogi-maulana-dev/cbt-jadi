<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CaptchaController extends Controller
{
    /**
     * Math captcha sederhana: server membuat soal hitung & menyimpan
     * jawabannya di cache (5 menit). Klien mengirim id + jawaban saat login.
     */
    public function show()
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $id = (string) Str::uuid();

        Cache::put("captcha:{$id}", $a + $b, now()->addMinutes(5));

        return response()->json([
            'captcha_id' => $id,
            'question' => "{$a} + {$b}",
        ]);
    }
}
