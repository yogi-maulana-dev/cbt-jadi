<?php

namespace App\Filament\Resources\TestAttemptResource\Pages;

use App\Filament\Resources\TestAttemptResource;
use App\Models\Test;
use App\Models\TestAttempt;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Pemantauan real-time peserta sebuah ujian (auto-refresh).
 */
class LiveHasil extends Page
{
    protected static string $resource = TestAttemptResource::class;

    protected static string $view = 'filament.resources.test-attempt-resource.pages.live-hasil';

    protected static bool $shouldRegisterNavigation = false;

    public int $testId;

    public string $judul = '';

    public ?string $mapel = null;

    public function mount(string $test): void
    {
        $t = Test::with('mataPelajaran')->findOrFail($test);
        $this->testId = $t->id;
        $this->judul = $t->judul;
        $this->mapel = optional($t->mataPelajaran)->nama;
    }

    /**
     * Daftar peserta + progres, diurutkan yang sedang mengerjakan dulu.
     *
     * @return Collection<int, TestAttempt>
     */
    public function getPeserta(): Collection
    {
        return TestAttempt::query()
            ->where('test_id', $this->testId)
            ->with('user:id,name')
            ->withCount('attemptQuestions')
            ->withCount(['answers as terjawab_count' => function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('choice_id')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('jawaban_essay')->where('jawaban_essay', '!=', '');
                        });
                });
            }])
            // Benar/salah dihitung live dari pilihan ganda saja (dibandingkan kunci).
            // Jawaban essay tidak punya choice -> otomatis TIDAK ikut benar/salah (dinilai manual oleh guru).
            ->withCount(['answers as benar_count' => fn ($q) => $q->whereHas('choice', fn ($c) => $c->where('is_correct', true))])
            ->withCount(['answers as salah_count' => fn ($q) => $q->whereHas('choice', fn ($c) => $c->where('is_correct', false))])
            ->withCount(['answers as essay_count' => fn ($q) => $q->whereNotNull('jawaban_essay')->where('jawaban_essay', '!=', '')])
            ->orderByRaw("CASE WHEN status = 'sedang_dikerjakan' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get();
    }

    public function getTitle(): string
    {
        return 'Live: '.$this->judul;
    }

    public function getBreadcrumb(): string
    {
        return 'Live Hasil';
    }
}
