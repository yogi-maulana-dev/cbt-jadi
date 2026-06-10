<?php

use App\Http\Controllers\ExamController;
use App\Livewire\Exam\ExamRoom;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// ---- Ruang Ujian Siswa ----
Route::middleware('auth')->group(function () {
    Route::get('/ujian', [ExamController::class, 'index'])->name('exam.index');
    Route::post('/ujian/{test}/mulai', [ExamController::class, 'start'])->name('exam.start');
    Route::get('/ujian/attempt/{attempt}', ExamRoom::class)->name('exam.room');
    Route::get('/ujian/attempt/{attempt}/hasil', [ExamController::class, 'result'])->name('exam.result');
});

require __DIR__.'/auth.php';
