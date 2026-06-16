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

    // Halaman pemberitahuan saat ujian dihentikan/di-reset pengawas.
    Route::get('/ujian/dihentikan', fn () => view('exam.kicked', [
        'title' => \App\Models\Setting::kickTitle(),
        'message' => \App\Models\Setting::kickMessage(),
    ]))->name('exam.kicked');

    Route::post('/ujian/{test}/mulai', [ExamController::class, 'start'])->name('exam.start');
    Route::get('/ujian/attempt/{attempt}', ExamRoom::class)
        ->name('exam.room')
        // Attempt sudah direset/dihapus pengawas -> jangan 404, tampilkan pemberitahuan.
        ->missing(fn () => redirect()->route('exam.kicked'));
    Route::get('/ujian/attempt/{attempt}/hasil', [ExamController::class, 'result'])
        ->name('exam.result')
        ->missing(fn () => redirect()->route('exam.kicked'));
});

// DEBUG SEMENTARA: cek batas upload server yang sedang berjalan. Hapus setelah selesai.
Route::get('/_cek-upload', function () {
    return response()->json([
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'livewire_max_kb' => config('livewire.temporary_file_upload.rules'),
        'php_ini' => php_ini_loaded_file(),
    ]);
});

require __DIR__.'/auth.php';
