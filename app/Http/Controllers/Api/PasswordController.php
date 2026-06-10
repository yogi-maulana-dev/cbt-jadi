<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Lupa password: kirim kode OTP 6 digit ke email.
     * Mode dev (.env MAIL_MAILER=log): OTP muncul di storage/logs/laravel.log.
     */
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        // Selalu balas sama (cegah penebakan email terdaftar/tidak).
        if ($user) {
            $otp = (string) random_int(100000, 999999);
            Cache::put("otp:{$user->email}", $otp, now()->addMinutes(10));

            Mail::raw(
                "Kode reset password CBT Anda: {$otp}\nBerlaku 10 menit. Abaikan bila Anda tidak meminta.",
                fn ($m) => $m->to($user->email)->subject('Reset Password CBT')
            );
        }

        return response()->json([
            'ok' => true,
            'message' => 'Jika email terdaftar, kode reset telah dikirim.',
        ]);
    }

    /**
     * Reset password dengan OTP.
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'min:6'],
        ]);

        $expected = Cache::get("otp:{$data['email']}");

        if (! $expected || ! hash_equals($expected, (string) $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Kode salah atau sudah kedaluwarsa.'],
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['password' => Hash::make($data['password'])]);

        Cache::forget("otp:{$data['email']}");
        $user->tokens()->delete(); // keluarkan semua sesi lama

        return response()->json(['ok' => true]);
    }
}
