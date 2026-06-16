<x-filament-panels::page>
    @php($logs = $this->getLogs())
    @php($ringkasan = $this->getRingkasan())

    @if ($logs->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada riwayat pelanggaran untuk ujian ini.</p>
        </div>
    @else
        {{-- Ringkasan per siswa --}}
        <div class="mb-6">
            <h2 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Ringkasan per siswa</h2>
            <div class="flex flex-wrap gap-3">
                @foreach ($ringkasan as $r)
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $r['nama'] }}</span>
                        <span class="ml-2 text-red-600">{{ $r['insiden'] }}× melanggar</span>
                        <span class="ml-2 text-gray-500">· {{ $r['dibuka'] }}× dibuka</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Detail insiden --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Siswa</th>
                        <th class="px-4 py-3">Alasan</th>
                        <th class="px-4 py-3">Diblokir</th>
                        <th class="px-4 py-3">Dibuka</th>
                        <th class="px-4 py-3">Dibuka oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ optional($log->user)->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $log->alasan }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ optional($log->diblokir_pada)->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($log->dibuka_pada)
                                    {{ $log->dibuka_pada->format('d/m/Y H:i') }}
                                @else
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Masih diblokir</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ optional($log->dibukaOleh)->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
