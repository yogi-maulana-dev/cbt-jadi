<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\PasswordController;
use Illuminate\Support\Facades\Route;

// Publik
Route::get('/captcha', [CaptchaController::class, 'show']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/blocked-status', [AuthController::class, 'blockedStatus']);
Route::post('/password/forgot', [PasswordController::class, 'forgot']);
Route::post('/password/reset', [PasswordController::class, 'reset']);

// Perlu login
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::put('/profile', [AccountController::class, 'updateProfile']);
    Route::put('/password', [AccountController::class, 'changePassword']);

    Route::get('/exams', [ExamController::class, 'index']);
    Route::post('/exams/{test}/start', [ExamController::class, 'start']);

    // Bila attempt sudah direset/dikeluarkan pengawas, attempt tidak ditemukan ->
    // balas 410 Gone + flag "reset" agar aplikasi Android keluar dengan pesan jelas
    // (bukan crash/404 ambigu). Aman koneksi: hanya muncul saat server benar-benar
    // tak menemukan attempt, jadi gangguan jaringan tidak akan menendang siswa.
    $attemptReset = fn () => response()->json([
        'reset' => true,
        'title' => \App\Models\Setting::kickTitle(),
        'message' => \App\Models\Setting::kickMessage(),
    ], 410);

    Route::post('/attempts/{attempt}/answer', [ExamController::class, 'answer'])->missing($attemptReset);
    Route::post('/attempts/{attempt}/flag', [ExamController::class, 'flag'])->missing($attemptReset);
    Route::post('/attempts/{attempt}/violation', [ExamController::class, 'violation'])->missing($attemptReset);
    Route::post('/attempts/{attempt}/finish', [ExamController::class, 'finish'])->missing($attemptReset);
    Route::get('/attempts/{attempt}/result', [ExamController::class, 'result'])->missing($attemptReset);

    // Heartbeat: di-poll aplikasi tiap ~10-15 detik untuk mendeteksi reset/selesai
    // secara real-time. attempt hilang -> 410 (reset); selesai/expired -> field di bawah.
    Route::get('/attempts/{attempt}/heartbeat', [ExamController::class, 'heartbeat'])->missing($attemptReset);
});
