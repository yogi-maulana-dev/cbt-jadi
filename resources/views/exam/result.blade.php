<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Hasil Ujian</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

            @php($test = $attempt->test)
            @php($lulus = $attempt->skor !== null && $attempt->skor >= $test->kkm)

            <div class="rounded-xl bg-white p-6 shadow">
                <p class="text-sm text-gray-500">{{ $test->mataPelajaran->nama }}</p>
                <h3 class="text-lg font-semibold text-gray-900">{{ $test->judul }}</h3>

                @if ($test->tampilkan_hasil)
                    <div class="mt-4 grid grid-cols-3 gap-4 text-center">
                        <div class="rounded-lg bg-gray-50 p-4">
                            <div class="text-3xl font-bold {{ $lulus ? 'text-green-600' : 'text-red-600' }}">
                                {{ rtrim(rtrim(number_format($attempt->skor, 2), '0'), '.') }}
                            </div>
                            <div class="text-xs text-gray-500">Nilai</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <div class="text-3xl font-bold text-gray-800">{{ $attempt->jumlah_benar }}</div>
                            <div class="text-xs text-gray-500">Jawaban benar</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <div class="text-3xl font-bold {{ $lulus ? 'text-green-600' : 'text-red-600' }}">
                                {{ $lulus ? 'LULUS' : 'BELUM' }}
                            </div>
                            <div class="text-xs text-gray-500">KKM {{ $test->kkm }}</div>
                        </div>
                    </div>
                @else
                    <p class="mt-4 rounded-lg bg-gray-50 p-4 text-center text-gray-600">
                        Ujian telah selesai. Hasil tidak ditampilkan.
                    </p>
                @endif
            </div>

            {{-- Pembahasan hanya bila diizinkan --}}
            @if ($test->tampilkan_hasil)
                <div class="mt-6 space-y-4">
                    @foreach ($attempt->answers as $answer)
                        @php($q = $answer->question)
                        <div class="rounded-xl bg-white p-5 shadow">
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <p class="font-medium">{{ $loop->iteration }}. {{ $q->pertanyaan }}</p>
                                @if ($q->tipe->value === 'essay')
                                    @if ($answer->is_correct === null && $answer->skor === null)
                                        <span class="shrink-0 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Menunggu koreksi</span>
                                    @else
                                        <span class="shrink-0 rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Skor: {{ rtrim(rtrim(number_format((float) $answer->skor, 2), '0'), '.') }}</span>
                                    @endif
                                @elseif ($answer->is_correct)
                                    <span class="shrink-0 rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Benar</span>
                                @else
                                    <span class="shrink-0 rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Salah</span>
                                @endif
                            </div>

                            @if ($q->tipe->value === 'essay')
                                <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 whitespace-pre-line">{{ $answer->jawaban_essay ?: '(tidak dijawab)' }}</div>
                            @else
                                <div class="space-y-1 text-sm">
                                    @foreach ($q->choices as $c)
                                        <div class="flex items-center gap-2
                                            {{ $c->is_correct ? 'font-semibold text-green-700' : '' }}
                                            {{ $answer->choice_id === $c->id && ! $c->is_correct ? 'text-red-600 line-through' : '' }}">
                                            <span>{{ $c->label }}.</span>
                                            <span>{{ $c->teks }}</span>
                                            @if ($answer->choice_id === $c->id)
                                                <span class="text-xs text-gray-400">(jawaban Anda)</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($q->pembahasan)
                                <div class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                                    <span class="font-medium">Pembahasan:</span> {{ $q->pembahasan }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6">
                <a href="{{ route('exam.index') }}" class="text-sm text-indigo-600 hover:underline">← Kembali ke daftar ujian</a>
            </div>
        </div>
    </div>
</x-app-layout>
