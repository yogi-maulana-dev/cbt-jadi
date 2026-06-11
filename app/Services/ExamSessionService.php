<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExamSessionService
{
    /**
     * Mulai attempt baru, atau lanjutkan attempt yang masih berjalan.
     *
     * Saat attempt dibuat, daftar soal & urutan opsi "dibekukan" (snapshot)
     * ke attempt_questions sehingga pengacakan deterministik per siswa dan
     * navigasi tetap stabil walau bank soal berubah.
     */
    public function startOrResume(Test $test, int $userId): TestAttempt
    {
        $existing = TestAttempt::where('test_id', $test->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Ujian ulang setelah buka blokir -> pakai SOAL CADANGAN.
        $user = User::find($userId);
        $pakaiCadangan = $user && (int) $user->cadangan_test_id === $test->id;

        return DB::transaction(function () use ($test, $userId, $user, $pakaiCadangan) {
            $attempt = TestAttempt::create([
                'test_id' => $test->id,
                'user_id' => $userId,
                'waktu_mulai' => now(),
                'deadline' => now()->addMinutes($test->durasi),
                'status' => AttemptStatus::SedangDikerjakan,
            ]);

            $questions = $test->questions()
                ->wherePivot('cadangan', $pakaiCadangan)
                ->with('choices')
                ->get();

            // Fallback: bila tidak ada soal cadangan, pakai soal utama.
            if ($pakaiCadangan && $questions->isEmpty()) {
                $questions = $test->questions()->wherePivot('cadangan', false)->with('choices')->get();
            }

            if ($pakaiCadangan) {
                $user->update(['cadangan_test_id' => null]); // sekali pakai
            }

            if ($test->acak_soal) {
                $questions = $questions->shuffle();
            }

            $urutan = 1;
            foreach ($questions as $question) {
                $opsi = $question->choices->pluck('id');

                if ($test->acak_jawaban) {
                    $opsi = $opsi->shuffle();
                }

                $attempt->attemptQuestions()->create([
                    'question_id' => $question->id,
                    'urutan' => $urutan++,
                    'urutan_opsi' => $opsi->values()->all(),
                ]);
            }

            return $attempt;
        });
    }

    /**
     * Selesaikan attempt: nilai otomatis (pilihan ganda) lalu kunci status.
     * Idempotent & aman dari double-grading via lockForUpdate.
     * Dipakai bersama oleh ruang ujian web (Livewire) dan API Android.
     */
    public function finish(TestAttempt $attempt): TestAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $locked = TestAttempt::lockForUpdate()->findOrFail($attempt->id);

            if ($locked->status !== AttemptStatus::Selesai) {
                app(ScoringService::class)->grade($locked);
                $locked->update([
                    'status' => AttemptStatus::Selesai,
                    'waktu_selesai' => now(),
                ]);
            }

            return $locked->refresh();
        });
    }
}
