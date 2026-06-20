<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login & terbitkan token Sanctum.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'], // No Ujian (siswa) atau email
            'password' => ['required'],
            'device_name' => ['nullable', 'string'],
            'captcha_id' => ['required', 'string'],
            'captcha_answer' => ['required'],
        ]);

        // Verifikasi captcha (sekali pakai).
        $expected = Cache::pull("captcha:{$data['captcha_id']}");
        if ($expected === null || (int) $data['captcha_answer'] !== (int) $expected) {
            throw ValidationException::withMessages([
                'captcha' => ['Captcha salah atau kedaluwarsa.'],
            ]);
        }

        $login = trim($data['email']);
        $user = User::where('email', $login)->orWhere('no_ujian', $login)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['No Ujian/email atau kata sandi salah.'],
            ]);
        }

        if (! $user->aktif) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda dinonaktifkan. Hubungi admin sekolah.'],
            ]);
        }

        if ($user->isBlocked()) {
            throw ValidationException::withMessages([
                'email' => ['Akun diblokir karena pelanggaran ujian. Hubungi operator/admin.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'android')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Cabut token yang sedang dipakai.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Cek status blokir sebuah email (untuk polling real-time di klien).
     */
    public function blockedStatus(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        return response()->json([
            'diblokir' => (bool) User::where('email', $data['email'])->value('diblokir'),
        ]);
    }
}
