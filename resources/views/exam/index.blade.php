<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Daftar Ujian</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if ($tests->isEmpty())
                <div class="rounded-xl bg-white p-8 text-center text-gray-500 shadow">
                    Belum ada ujian yang tersedia saat ini.
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($tests as $test)
                        @php($attempt = $attempts->get($test->id))
                        <div class="flex flex-col rounded-xl bg-white p-5 shadow">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                    {{ $test->mataPelajaran->nama }}
                                </span>
                                @if ($attempt && $attempt->status->value === 'selesai')
                                    <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Selesai</span>
                                @elseif ($attempt)
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Sedang dikerjakan</span>
                                @endif
                            </div>

                            <h3 class="text-lg font-semibold text-gray-900">{{ $test->judul }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ $test->deskripsi }}</p>

                            <div class="mt-3 flex gap-4 text-sm text-gray-600">
                                <span>{{ $test->questions_count }} soal</span>
                                <span>{{ $test->durasi }} menit</span>
                                <span>KKM {{ $test->kkm }}</span>
                            </div>

                            <div class="mt-4">
                                @if ($attempt && $attempt->status->value === 'selesai')
                                    <a href="{{ route('exam.result', $attempt) }}"
                                       class="inline-block rounded-lg border px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Lihat Hasil
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('exam.start', $test) }}"
                                          class="flex items-end gap-2">
                                        @csrf
                                        @if ($test->token)
                                            <div>
                                                <label class="block text-xs text-gray-500">Token</label>
                                                <input type="text" name="token" required
                                                       class="mt-1 w-32 rounded-md border-gray-300 text-sm shadow-sm">
                                            </div>
                                        @endif
                                        <button type="submit"
                                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">
                                            {{ $attempt ? 'Lanjutkan' : 'Mulai Ujian' }}
                                        </button>
                                    </form>
                                    @error('token')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
