<?php

namespace App\Livewire\Exam;

use App\Enums\TestStatus;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ExamSessionService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ExamList extends Component
{
    /** Token per ujian: tokens[$testId] */
    public array $tokens = [];

    #[Computed]
    public function exams()
    {
        return Test::query()
            ->where('status', TestStatus::Published)
            ->with('mataPelajaran')
            ->withCount('questions')
            ->latest()
            ->get();
    }

    #[Computed]
    public function attempts()
    {
        return TestAttempt::where('user_id', auth()->id())->get()->keyBy('test_id');
    }

    public function start(int $testId, ExamSessionService $service)
    {
        $test = Test::findOrFail($testId);

        if ($test->status !== TestStatus::Published) {
            return $this->addError("start.$testId", 'Ujian belum dibuka.');
        }
        if ($test->waktu_mulai && now()->lt($test->waktu_mulai)) {
            return $this->addError("start.$testId", 'Ujian belum dimulai.');
        }
        if ($test->waktu_selesai && now()->gt($test->waktu_selesai)) {
            return $this->addError("start.$testId", 'Ujian sudah berakhir.');
        }
        if ($test->questions()->count() === 0) {
            return $this->addError("start.$testId", 'Ujian belum memiliki soal.');
        }
        if ($test->token && ! hash_equals($test->token, (string) ($this->tokens[$testId] ?? ''))) {
            return $this->addError("start.$testId", 'Token ujian salah.');
        }

        $attempt = $service->startOrResume($test, auth()->id());

        return $this->redirectRoute('exam.room', $attempt, navigate: true);
    }

    public function render()
    {
        return view('livewire.exam.exam-list');
    }
}
