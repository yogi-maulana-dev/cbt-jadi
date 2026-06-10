<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Enums\TestStatus;
use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ExamSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    /**
     * Daftar ujian published + status attempt siswa.
     */
    public function index(Request $request)
    {
        $tests = Test::query()
            ->where('status', TestStatus::Published)
            ->with('mataPelajaran')
            ->withCount('questions')
            ->latest()
            ->get();

        $attempts = TestAttempt::where('user_id', $request->user()->id)->get()->keyBy('test_id');

        return $tests->map(function (Test $t) use ($attempts) {
            $attempt = $attempts->get($t->id);

            return [
                'id' => $t->id,
                'judul' => $t->judul,
                'mata_pelajaran' => $t->mataPelajaran?->nama,
                'deskripsi' => $t->deskripsi,
                'durasi' => $t->durasi,
                'kkm' => $t->kkm,
                'jumlah_soal' => $t->questions_count,
                'butuh_token' => (bool) $t->token,
                'status' => $attempt?->status->value,
                'attempt_id' => $attempt?->id,
            ];
        })->values();
    }

    /**
     * Mulai / lanjutkan ujian → kembalikan snapshot soal (acak per siswa).
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

        if ($test->token) {
            $request->validate(['token' => ['required']]);
            if (! hash_equals($test->token, (string) $request->input('token'))) {
                throw ValidationException::withMessages(['token' => 'Token ujian salah.']);
            }
        }

        $attempt = $service->startOrResume($test, $request->user()->id);

        return response()->json($this->snapshot($attempt));
    }

    /**
     * Autosave jawaban (pilihan ganda atau essay).
     */
    public function answer(Request $request, TestAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $this->ensureActive($attempt);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'choice_id' => ['nullable', 'integer'],
            'jawaban_essay' => ['nullable', 'string'],
        ]);

        abort_unless(
            $attempt->attemptQuestions()->where('question_id', $data['question_id'])->exists(),
            422,
            'Soal bukan bagian dari ujian ini.'
        );

        $payload = isset($data['choice_id'])
            ? ['choice_id' => $data['choice_id']]
            : ['jawaban_essay' => $data['jawaban_essay'] ?? null];

        $attempt->answers()->updateOrCreate(['question_id' => $data['question_id']], $payload);

        return response()->json(['ok' => true]);
    }

    /**
     * Tandai / batalkan ragu-ragu pada satu soal.
     */
    public function flag(Request $request, TestAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $this->ensureActive($attempt);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'ragu' => ['required', 'boolean'],
        ]);

        $attempt->attemptQuestions()
            ->where('question_id', $data['question_id'])
            ->firstOrFail()
            ->update(['ragu' => $data['ragu']]);

        return response()->json(['ok' => true]);
    }

    /**
     * Selesaikan ujian (nilai otomatis) → hasil.
     */
    public function finish(TestAttempt $attempt, ExamSessionService $service)
    {
        $this->authorizeAttempt($attempt);
        $attempt = $service->finish($attempt);

        return response()->json($this->resultPayload($attempt));
    }

    public function result(TestAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        return response()->json($this->resultPayload($attempt));
    }

    // ----------------- helpers -----------------

    private function authorizeAttempt(TestAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id(), 403);
    }

    private function ensureActive(TestAttempt $attempt): void
    {
        if ($attempt->status === AttemptStatus::Selesai) {
            abort(409, 'Ujian sudah selesai.');
        }
        if ($attempt->isExpired()) {
            app(ExamSessionService::class)->finish($attempt);
            abort(409, 'Waktu ujian telah habis.');
        }
    }

    /**
     * Bangun snapshot soal untuk klien.
     * PENTING: tidak pernah menyertakan is_correct (kunci jawaban).
     */
    private function snapshot(TestAttempt $attempt): array
    {
        $attempt->load(['attemptQuestions.question.choices']);
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $questions = $attempt->attemptQuestions->map(function ($aq) use ($answers) {
            $q = $aq->question;
            $answer = $answers->get($q->id);
            $choicesById = $q->choices->keyBy('id');

            $choices = collect($aq->urutan_opsi ?? [])
                ->map(fn ($id) => $choicesById->get($id))
                ->filter()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'teks' => $c->teks,
                    'gambar' => $c->gambar,
                ])->values();

            return [
                'id' => $q->id,
                'urutan' => $aq->urutan,
                'tipe' => $q->tipe->value,
                'pertanyaan' => $q->pertanyaan,
                'gambar' => $q->gambar,
                'choices' => $q->tipe === QuestionType::Essay ? [] : $choices,
                'choice_id' => $answer?->choice_id,
                'jawaban_essay' => $answer?->jawaban_essay,
                'ragu' => (bool) $aq->ragu,
            ];
        })->values();

        return [
            'attempt_id' => $attempt->id,
            'judul' => $attempt->test->judul,
            'deadline' => $attempt->deadline->timestamp,
            'questions' => $questions,
        ];
    }

    private function resultPayload(TestAttempt $attempt): array
    {
        $test = $attempt->test;
        $lulus = $attempt->skor !== null && (float) $attempt->skor >= $test->kkm;

        return [
            'skor' => (float) $attempt->skor,
            'jumlah_benar' => $attempt->jumlah_benar,
            'kkm' => $test->kkm,
            'lulus' => $lulus,
            'tampilkan_hasil' => (bool) $test->tampilkan_hasil,
        ];
    }
}
