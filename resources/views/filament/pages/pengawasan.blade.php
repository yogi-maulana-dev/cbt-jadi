<x-filament-panels::page>
    {{-- Auto-refresh token tiap 5 detik (real-time bila admin mengubahnya). --}}
    <div wire:poll.5s class="space-y-4">
        @php($jadwal = $this->getJadwal())

        @if ($jadwal->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada jadwal ujian yang ditugaskan kepada Anda.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($jadwal as $t)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-bold text-gray-900 dark:text-white">{{ $t->judul }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ optional($t->mataPelajaran)->nama }}
                                    @if ($t->pivot?->ruangan)
                                        · Ruangan: <strong>{{ $t->pivot->ruangan }}</strong>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 font-medium',
                                        'bg-green-100 text-green-800' => $t->status?->value === 'published',
                                        'bg-gray-100 text-gray-700' => $t->status?->value !== 'published',
                                    ])>{{ ucfirst($t->status?->value ?? '-') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-lg bg-indigo-50 p-4 text-center dark:bg-indigo-400/10">
                            <div class="text-xs uppercase text-indigo-500">Token Ujian</div>
                            <div class="font-mono text-3xl font-extrabold tracking-widest text-indigo-700 dark:text-indigo-300">
                                {{ $t->token ?: '— tanpa token —' }}
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-xs text-gray-400">Diperbarui otomatis · {{ now()->format('H:i:s') }}</span>
                            <x-filament::button
                                wire:click="gantiToken({{ $t->id }})"
                                wire:confirm="Ganti token ujian ini? Siswa harus memakai token baru."
                                color="warning"
                                size="sm"
                                icon="heroicon-m-arrow-path">
                                Ganti Token
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
