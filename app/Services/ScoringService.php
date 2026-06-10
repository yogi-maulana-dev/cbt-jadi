<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\TestAttempt;

class ScoringService
{
    /**
     * Nilai otomatis seluruh jawaban pilihan ganda, lalu hitung skor total.
     * Soal essay dilewati (is_correct tetap null) untuk dikoreksi manual.
     */
    public function grade(TestAttempt $attempt): void
    {
        $attempt->loadMissing(['answers.question.correctChoice', 'test.questions']);

        foreach ($attempt->answers as $answer) {
            if ($answer->question->tipe !== QuestionType::PilihanGanda) {
                continue;
            }

            $kunci = $answer->question->correctChoice->first();
            $isCorrect = $kunci !== null && $answer->choice_id === $kunci->id;
            $bobot = $this->bobotFor($attempt, $answer->question_id);

            $answer->update([
                'is_correct' => $isCorrect,
                'skor' => $isCorrect ? $bobot : 0,
            ]);
        }

        $this->recalculate($attempt);
    }

    /**
     * Hitung ulang skor total dari nilai per-jawaban saat ini.
     * Dipakai setelah submit DAN setelah guru mengoreksi essay.
     *
     * Skor = (Σ skor semua jawaban / Σ bobot semua soal di ujian) × 100.
     */
    public function recalculate(TestAttempt $attempt): void
    {
        $attempt->loadMissing(['answers', 'test.questions']);

        $totalBobot = $attempt->test->questions
            ->sum(fn ($q) => (int) ($q->pivot->bobot ?? $q->bobot));

        $perolehan = $attempt->answers->sum(fn ($a) => (float) ($a->skor ?? 0));
        $jumlahBenar = $attempt->answers->where('is_correct', true)->count();

        $attempt->update([
            'skor' => $totalBobot > 0 ? round($perolehan / $totalBobot * 100, 2) : 0,
            'jumlah_benar' => $jumlahBenar,
        ]);
    }

    private function bobotFor(TestAttempt $attempt, int $questionId): int
    {
        $question = $attempt->test->questions->firstWhere('id', $questionId);

        return (int) ($question?->pivot->bobot ?? $question?->bobot ?? 0);
    }
}
