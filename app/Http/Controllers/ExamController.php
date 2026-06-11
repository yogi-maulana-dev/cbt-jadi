<?php

namespace App\Http\Controllers;

use App\Enums\TestStatus;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ExamSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    /**
     * Daftar ujian yang tersedia untuk siswa.
     */
    public function index()
    {
        // Daftar ujian dirender oleh komponen Livewire (real-time auto-refresh).
        return view('exam.index');
    }

    /**
     * Mulai / lanjutkan ujian, lalu masuk ke ruang ujian.
     */
    public function start(Request $request, Test $test, ExamSessionService $service)
    {
        abort_unless($test->status === TestStatus::Published, 403, 'Ujian belum dibuka.');

        if ($test->waktu_mulai && now()->lt($test->waktu_mulai)) {
            abort(403, 'Ujian belum dimulai.');
        }

        if ($test->waktu_selesai && now()->gt($test->waktu_selesai)) {
            abort(403, 'Ujian sudah berakhir.');
        }

        if ($test->questions()->count() === 0) {
            abort(403, 'Ujian belum memiliki soal.');
        }

        // Validasi token bila ujian memakai token akses.
        if ($test->token) {
            $request->validate(['token' => 'required']);

            if (! hash_equals($test->token, (string) $request->input('token'))) {
                throw ValidationException::withMessages(['token' => 'Token ujian salah.']);
            }
        }

        $attempt = $service->startOrResume($test, auth()->id());

        return redirect()->route('exam.room', $attempt);
    }

    /**
     * Halaman hasil ujian.
     */
    public function result(TestAttempt $attempt)
    {
        abort_unless($attempt->user_id === auth()->id(), 403);

        $attempt->load([
            'test.mataPelajaran',
            'answers.question.choices',
            'answers.choice',
        ]);

        return view('exam.result', compact('attempt'));
    }
}
