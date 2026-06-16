<div class="max-w-4xl mx-auto p-4 sm:p-6"
     x-data="examRoom({{ $this->deadlineTimestamp }}, {{ $this->maxPelanggaran }})">

    {{-- Header: judul + timer --}}
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-lg font-semibold">{{ $this->attempt->test->judul }}</h1>
            <p class="text-sm text-gray-500">
                Soal {{ $index + 1 }} dari {{ $this->questions->count() }}
            </p>
        </div>
        <div class="flex items-center gap-2 rounded-lg bg-white px-4 py-2 shadow"
             :class="left < 60 ? 'text-red-600 animate-pulse' : 'text-gray-800'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-xl font-mono font-semibold" x-text="formatted"></span>
        </div>
    </div>

    {{-- Banner peringatan pelanggaran (anti-cheat) --}}
    @if ($this->maxPelanggaran > 0 && $pelanggaran > 0)
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            ⚠️ Anda keluar dari tab ujian <strong>{{ $pelanggaran }}/{{ $this->maxPelanggaran }}</strong> kali.
            Ujian akan otomatis dikumpulkan saat mencapai {{ $this->maxPelanggaran }} kali.
        </div>
    @endif

    @php($aq = $this->current)
    @php($q = $aq?->question)

    @if ($q)
        {{-- Kartu soal --}}
        <div class="rounded-xl bg-white p-6 shadow" wire:key="soal-{{ $aq->id }}">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="prose max-w-none">{!! nl2br(e($q->pertanyaan)) !!}</div>
                {{-- Tombol ragu-ragu --}}
                <button type="button" wire:click="toggleFlag"
                        class="shrink-0 inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium transition
                               {{ $aq->ragu
                                    ? 'border-amber-400 bg-amber-100 text-amber-800'
                                    : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                    <svg class="w-4 h-4" fill="{{ $aq->ragu ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 3v18m0-18l12 4-12 4" />
                    </svg>
                    {{ $aq->ragu ? 'Ditandai ragu' : 'Ragu-ragu' }}
                </button>
            </div>

            @if ($q->gambar)
                <img src="{{ Storage::disk('public')->url($q->gambar) }}" alt="Gambar soal"
                     class="max-h-64 rounded-lg border mb-4">
            @endif

            @if ($q->suara_src)
                <audio controls preload="metadata" src="{{ $q->suara_src }}" class="mb-4 w-full max-w-md">
                    Browser Anda tidak mendukung pemutar audio.
                </audio>
            @endif

            @if ($q->video_is_file)
                <video controls preload="metadata" class="max-h-64 rounded-lg border mb-4">
                    <source src="{{ $q->video_src }}">
                    Browser Anda tidak mendukung pemutar video.
                </video>
            @elseif ($q->youtube_embed)
                {{-- Ditanam inline agar siswa tidak pindah tab (mencegah pelanggaran). --}}
                <div class="mb-4 aspect-video w-full max-w-xl overflow-hidden rounded-lg border">
                    <iframe src="{{ $q->youtube_embed }}" class="h-full w-full"
                            frameborder="0" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                </div>
            @elseif ($q->video_url)
                <a href="{{ $q->video_url }}" target="_blank" rel="noopener"
                   class="mb-4 inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                    ▶ Tonton video pendukung soal
                </a>
            @endif

            @if ($this->isEssay)
                {{-- Essay: autosave saat blur --}}
                <textarea wire:model.blur="essayDraft" wire:key="essay-{{ $aq->id }}"
                          rows="6" placeholder="Tulis jawaban Anda di sini..."
                          class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <p class="mt-1 text-xs text-gray-400">Jawaban tersimpan otomatis saat Anda berpindah / klik di luar kotak.</p>
            @else
                {{-- Pilihan ganda: urutan mengikuti snapshot urutan_opsi --}}
                <div class="space-y-3">
                    @foreach ($aq->urutan_opsi ?? [] as $choiceId)
                        @php($c = $q->choices->firstWhere('id', $choiceId))
                        @if ($c)
                            <button type="button"
                                    wire:click="saveAnswer({{ $c->id }})"
                                    wire:key="opsi-{{ $c->id }}"
                                    class="flex w-full items-start gap-3 rounded-lg border p-3 text-left transition
                                           {{ $selectedChoiceId === $c->id
                                                ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600'
                                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-sm font-semibold
                                             {{ $selectedChoiceId === $c->id ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 text-gray-500' }}">
                                    {{ $c->label ?: chr(65 + $loop->index) }}
                                </span>
                                <span class="pt-0.5">
                                    {{ $c->teks }}
                                    @if ($c->gambar)
                                        <img src="{{ Storage::disk('public')->url($c->gambar) }}" class="mt-2 max-h-32 rounded border">
                                    @endif
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Navigasi --}}
        <div class="mt-6 flex items-center justify-between">
            <button type="button" wire:click="prev" @disabled($index === 0)
                    class="rounded-lg border bg-white px-4 py-2 text-sm font-medium shadow-sm disabled:opacity-40">
                ← Sebelumnya
            </button>

            @if ($index < $this->questions->count() - 1)
                <button type="button" wire:click="next"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">
                    Berikutnya →
                </button>
            @else
                <button type="button" wire:click="finish"
                        wire:confirm="Selesaikan dan kumpulkan ujian? Jawaban tidak bisa diubah lagi."
                        class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-500">
                    Selesaikan Ujian
                </button>
            @endif
        </div>
    @endif

    {{-- Peta soal --}}
    <div class="mt-8 rounded-xl bg-white p-4 shadow">
        <p class="mb-3 text-sm font-medium text-gray-600">
            Peta Soal
            <span class="ml-2 text-xs font-normal text-gray-400">
                ({{ count($this->answeredIds) }}/{{ $this->questions->count() }} terjawab)
            </span>
        </p>
        <div class="grid grid-cols-8 gap-2 sm:grid-cols-10">
            @foreach ($this->questions as $i => $item)
                @php($answered = in_array($item->question_id, $this->answeredIds, true))
                @php($flagged = in_array($item->question_id, $this->flaggedIds, true))
                <button type="button" wire:click="goTo({{ $i }})"
                        class="relative aspect-square rounded-md text-sm font-medium transition
                               {{ $i === $index
                                    ? 'bg-indigo-600 text-white'
                                    : ($answered ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200') }}
                               {{ $flagged ? 'ring-2 ring-amber-400' : '' }}">
                    {{ $i + 1 }}
                    @if ($flagged)
                        <span class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                    @endif
                </button>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-green-500"></span> Dijawab</span>
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-indigo-600"></span> Sekarang</span>
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-gray-100 border"></span> Belum</span>
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded ring-2 ring-amber-400"></span> Ragu-ragu</span>
        </div>
    </div>

    {{--
        Timer berbasis epoch deadline server (kebal drift/tab-sleep) +
        deteksi keluar tab (anti-cheat) bila max_pelanggaran > 0 +
        heartbeat 10 detik: bila ujian direset pengawas, siswa diarahkan ke
        halaman pemberitahuan. Dipicu via Alpine (andal, tak bergantung wire:poll).
    --}}
    <script>
        function examRoom(deadlineTs, maxPelanggaran) {
            return {
                left: 0,
                timer: null,
                hb: null,
                tick() {
                    this.left = Math.max(0, deadlineTs - Math.floor(Date.now() / 1000));
                    if (this.left <= 0) {
                        clearInterval(this.timer);
                        @this.finish();
                    }
                },
                get formatted() {
                    const h = Math.floor(this.left / 3600);
                    const m = String(Math.floor((this.left % 3600) / 60)).padStart(2, '0');
                    const s = String(this.left % 60).padStart(2, '0');
                    return h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
                },
                init() {
                    this.tick();
                    this.timer = setInterval(() => this.tick(), 1000);

                    // Heartbeat: cek tiap 10 detik apakah ujian direset pengawas.
                    this.hb = setInterval(() => { @this.heartbeat(); }, 10000);

                    if (maxPelanggaran > 0) {
                        document.addEventListener('visibilitychange', () => {
                            if (document.hidden) {
                                @this.recordViolation();
                            }
                        });
                    }
                },
            };
        }
    </script>
</div>
