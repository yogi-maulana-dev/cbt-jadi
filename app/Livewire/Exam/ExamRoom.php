<?php

namespace App\Livewire\Exam;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\TestAttempt;
use App\Services\ExamSessionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ExamRoom extends Component
{
    /**
     * Hanya ID yang jadi properti publik. #[Locked] mencegah ID di-ubah
     * dari sisi browser — penting agar siswa tak bisa pindah ke attempt lain.
     */
    #[Locked]
    public int $attemptId;

    public int $index = 0;

    public ?int $selectedChoiceId = null;

    public ?string $essayDraft = null;

    public int $pelanggaran = 0;

    public function mount(TestAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id(), 403);

        $this->attemptId = $attempt->id;
        $this->pelanggaran = (int) ($attempt->pelanggaran ?? 0);

        // Sudah selesai atau lewat deadline -> langsung tutup & ke hasil.
        if ($attempt->status === AttemptStatus::Selesai || $attempt->isExpired()) {
            $this->closeAttempt();

            return;
        }

        $this->loadCurrentAnswer();
    }

    /**
     * Model di-resolve via computed: selalu fresh, tak diserialisasi ke frontend.
     */
    #[Computed]
    public function attempt(): TestAttempt
    {
        return TestAttempt::with('test')->findOrFail($this->attemptId);
    }

    /**
     * Daftar soal hasil snapshot (urutan tetap untuk siswa ini).
     */
    #[Computed]
    public function questions()
    {
        return $this->attempt->attemptQuestions()
            ->with('question.choices')
            ->get();
    }

    #[Computed]
    public function current()
    {
        return $this->questions[$this->index] ?? null;
    }

    /**
     * Epoch deadline dari server. Hitung mundur dilakukan di klien dari
     * jam nyata (Date.now), bukan decrement lokal -> kebal drift & tab sleep.
     */
    #[Computed]
    public function deadlineTimestamp(): int
    {
        return $this->attempt->deadline->timestamp;
    }

    /**
     * Ambang auto-submit (0 = nonaktif).
     */
    #[Computed]
    public function maxPelanggaran(): int
    {
        return (int) $this->attempt->test->max_pelanggaran;
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function answeredIds(): array
    {
        return $this->attempt->answers()
            ->where(function ($q) {
                $q->whereNotNull('choice_id')
                    ->orWhere(fn ($q) => $q->whereNotNull('jawaban_essay')->where('jawaban_essay', '!=', ''));
            })
            ->pluck('question_id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function flaggedIds(): array
    {
        return $this->attempt->attemptQuestions()
            ->where('ragu', true)
            ->pluck('question_id')
            ->all();
    }

    private function loadCurrentAnswer(): void
    {
        $answer = $this->current
            ? $this->attempt->answers()->where('question_id', $this->current->question_id)->first()
            : null;

        $this->selectedChoiceId = $answer?->choice_id;
        $this->essayDraft = $answer?->jawaban_essay;
    }

    /**
     * Autosave pilihan ganda: dipanggil tiap siswa memilih opsi.
     */
    public function saveAnswer(int $choiceId): void
    {
        if ($this->guardTime()) {
            return;
        }

        $this->selectedChoiceId = $choiceId;

        // Upsert berkat UNIQUE(test_attempt_id, question_id).
        $this->attempt->answers()->updateOrCreate(
            ['question_id' => $this->current->question_id],
            ['choice_id' => $choiceId],
        );

        unset($this->answeredIds);
    }

    /**
     * Autosave essay: dipicu wire:model.blur="essayDraft".
     */
    public function updatedEssayDraft(?string $value): void
    {
        if ($this->guardTime() || ! $this->current) {
            return;
        }

        $this->attempt->answers()->updateOrCreate(
            ['question_id' => $this->current->question_id],
            ['jawaban_essay' => $value],
        );

        unset($this->answeredIds);
    }

    /**
     * Tandai / batalkan "ragu-ragu" pada soal saat ini.
     */
    public function toggleFlag(): void
    {
        if (! $this->current) {
            return;
        }

        $this->current->update(['ragu' => ! $this->current->ragu]);

        unset($this->questions, $this->flaggedIds);
    }

    /**
     * Catat pelanggaran (siswa keluar tab). Auto-submit bila mencapai ambang.
     */
    public function recordViolation()
    {
        $max = $this->maxPelanggaran;

        if ($max <= 0 || $this->attempt->status === AttemptStatus::Selesai) {
            return null;
        }

        $attempt = $this->attempt;
        $attempt->increment('pelanggaran');
        $this->pelanggaran = $attempt->pelanggaran;

        if ($this->pelanggaran >= $max) {
            return $this->closeAttempt();
        }

        return null;
    }

    public function next(): void
    {
        if ($this->index < $this->questions->count() - 1) {
            $this->index++;
            $this->loadCurrentAnswer();
        }
    }

    public function prev(): void
    {
        if ($this->index > 0) {
            $this->index--;
            $this->loadCurrentAnswer();
        }
    }

    public function goTo(int $i): void
    {
        $this->index = max(0, min($i, $this->questions->count() - 1));
        $this->loadCurrentAnswer();
    }

    /**
     * Selesaikan ujian (tombol "Selesai" atau timer klien mencapai 0).
     */
    public function finish()
    {
        return $this->closeAttempt();
    }

    private function closeAttempt()
    {
        app(ExamSessionService::class)->finish($this->attempt);

        return $this->redirectRoute('exam.result', $this->attemptId, navigate: true);
    }

    /**
     * Server pemegang keputusan waktu (anti manipulasi jam klien).
     */
    private function guardTime(): bool
    {
        if ($this->attempt->isExpired()) {
            $this->closeAttempt();

            return true;
        }

        return false;
    }

    /**
     * Apakah soal saat ini bertipe essay.
     */
    #[Computed]
    public function isEssay(): bool
    {
        return $this->current?->question->tipe === QuestionType::Essay;
    }

    public function render()
    {
        return view('livewire.exam.exam-room')->layout('layouts.exam');
    }
}
