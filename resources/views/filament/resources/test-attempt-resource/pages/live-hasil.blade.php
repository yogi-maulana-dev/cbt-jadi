<x-filament-panels::page>
    {{-- Auto-refresh tiap 5 detik untuk pemantauan real-time. --}}
    <div wire:poll.5s class="space-y-4">
        @php($peserta = $this->getPeserta())
        @php($sedang = $peserta->filter(fn ($p) => $p->status->value === 'sedang_dikerjakan')->count())
        @php($selesai = $peserta->count() - $sedang)

        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 dark:bg-amber-400/10 dark:text-amber-400">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                </span>
                {{ $sedang }} sedang mengerjakan
            </span>
            <span class="rounded-lg bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400">
                {{ $selesai }} selesai
            </span>
            <span class="text-xs text-gray-400">Diperbarui otomatis tiap 5 detik · {{ now()->format('H:i:s') }}</span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Progres</th>
                        <th class="px-4 py-3">Benar</th>
                        <th class="px-4 py-3">Salah</th>
                        <th class="px-4 py-3">Essay</th>
                        <th class="px-4 py-3">Nilai</th>
                        <th class="px-4 py-3">Pelanggaran</th>
                        <th class="px-4 py-3">Sisa Waktu</th>
                        <th class="px-4 py-3">Mulai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($peserta as $p)
                        @php($aktif = $p->status->value === 'sedang_dikerjakan')
                        @php($total = $p->attempt_questions_count ?: 0)
                        @php($jawab = $p->terjawab_count ?: 0)
                        @php($persen = $total > 0 ? (int) round($jawab / $total * 100) : 0)
                        @php($sisa = ($aktif && $p->deadline) ? max(0, now()->diffInSeconds($p->deadline, false)) : null)
                        <tr class="{{ $aktif ? 'bg-amber-50/40 dark:bg-amber-400/5' : '' }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ optional($p->user)->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $aktif ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $aktif ? 'Sedang mengerjakan' : 'Selesai' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-28 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ $persen }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ $jawab }}/{{ $total }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-green-600">{{ $p->benar_count ?: 0 }}</td>
                            <td class="px-4 py-3 font-semibold text-red-600">{{ $p->salah_count ?: 0 }}</td>
                            <td class="px-4 py-3">
                                @if ($p->essay_count)
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-400/10 dark:text-blue-400"
                                          title="Dinilai manual oleh guru">
                                        {{ $p->essay_count }} · nilai manual
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if (! $aktif && $p->skor !== null)
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ rtrim(rtrim(number_format((float) $p->skor, 2), '0'), '.') }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($p->pelanggaran > 0)
                                    <span class="font-semibold text-red-600">{{ $p->pelanggaran }}×</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono">
                                @if ($sisa !== null)
                                    {{ sprintf('%02d:%02d', intdiv($sisa, 60), $sisa % 60) }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ optional($p->waktu_mulai)->format('H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-400">Belum ada peserta yang memulai ujian ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
