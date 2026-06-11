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

    Route::post('/attempts/{attempt}/answer', [ExamController::class, 'answer']);
    Route::post('/attempts/{attempt}/flag', [ExamController::class, 'flag']);
    Route::post('/attempts/{attempt}/violation', [ExamController::class, 'violation']);
    Route::post('/attempts/{attempt}/finish', [ExamController::class, 'finish']);
    Route::get('/attempts/{attempt}/result', [ExamController::class, 'result']);
});
