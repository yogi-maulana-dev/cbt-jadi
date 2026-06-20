<?php

namespace App\Services;

use App\Models\PenempatanSiswa;
use App\Models\Ruangan;
use App\Models\Test;
use App\Models\User;

/**
 * Membuat penempatan ruangan otomatis untuk sebuah jadwal ujian:
 * - membagi siswa ke ruangan sesuai kapasitas, lalu
 * - menugaskan pengawas (guru/pengawas) ke tiap ruangan yang terpakai,
 *   menghindari pengawas yang bentrok waktu dengan jadwal lain.
 *
 * Hasil tetap bisa diubah manual setelahnya.
 */
class JadwalOtomatisService
{
    /**
     * @return array{ruangan:int, siswa:int, pengawas:int, warnings:array<int,string>}
     */
    public function generate(Test $test, bool $tempatkanSiswa = true, bool $tugaskanPengawas = true): array
    {
        $warnings = [];

        // Ruangan baku yang punya kapasitas, urut natural (Ruang 1, 2, .. 10).
        $rooms = Ruangan::where('kapasitas', '>', 0)
            ->get(['id', 'nama', 'kapasitas'])
            ->sortBy('nama', SORT_NATURAL)
            ->values();

        if ($rooms->isEmpty()) {
            return ['ruangan' => 0, 'siswa' => 0, 'pengawas' => 0, 'warnings' => ['Belum ada ruangan dengan kapasitas. Isi kapasitas ruangan dulu.']];
        }

        $siswaCount = 0;
        $usedRooms = collect();

        if ($tempatkanSiswa) {
            $test->penempatanSiswa()->delete(); // reset penempatan lama

            $siswa = User::where('role', 'siswa')->orderBy('kelas')->orderBy('name')->get(['id']);
            $cursor = 0;

            foreach ($rooms as $room) {
                if ($cursor >= $siswa->count()) {
                    break;
                }
                $chunk = $siswa->slice($cursor, $room->kapasitas);
                foreach ($chunk as $s) {
                    PenempatanSiswa::create([
                        'test_id' => $test->id,
                        'ruangan_id' => $room->id,
                        'user_id' => $s->id,
                    ]);
                }
                $cursor += $chunk->count();
                $usedRooms->push($room);
                $siswaCount += $chunk->count();
            }

            $sisa = $siswa->count() - $cursor;
            if ($sisa > 0) {
                $warnings[] = "{$sisa} siswa belum kebagian ruangan — total kapasitas kurang. Tambah ruangan / kapasitas.";
            }
        } else {
            // Tidak menempatkan siswa: pakai ruangan dari penempatan yang sudah ada.
            $usedRoomIds = $test->penempatanSiswa()->distinct()->pluck('ruangan_id');
            $usedRooms = $rooms->whereIn('id', $usedRoomIds)->values();
            if ($usedRooms->isEmpty()) {
                $usedRooms = $rooms; // belum ada penempatan -> isi pengawas untuk semua ruangan baku
            }
        }

        $pengawasCount = 0;

        if ($tugaskanPengawas) {
            // Kandidat pengawas: guru/pengawas yang aktif.
            $kandidat = User::whereIn('role', ['guru', 'pengawas'])
                ->where('aktif', true)
                ->orderBy('name')
                ->get();

            $sudahTerpakai = $test->pengawas()->pluck('users.id')->all();

            foreach ($usedRooms as $room) {
                // Ruangan ini sudah ada pengawasnya? lewati.
                $adaPengawas = $test->pengawas()
                    ->wherePivot('ruangan', $room->nama)
                    ->exists();
                if ($adaPengawas) {
                    continue;
                }

                $dipilih = $kandidat->first(function (User $u) use ($sudahTerpakai, $test) {
                    if (in_array($u->id, $sudahTerpakai, true)) {
                        return false; // sudah memegang ruangan lain di jadwal ini
                    }

                    return ! $this->bentrokWaktu($u, $test);
                });

                if ($dipilih) {
                    $test->pengawas()->attach($dipilih->id, ['ruangan' => $room->nama]);
                    $sudahTerpakai[] = $dipilih->id;
                    $pengawasCount++;
                } else {
                    $warnings[] = "Ruang {$room->nama} belum dapat pengawas — tidak ada guru/pengawas yang tersedia (semua dipakai/bentrok).";
                }
            }
        }

        return [
            'ruangan' => $usedRooms->count(),
            'siswa' => $siswaCount,
            'pengawas' => $pengawasCount,
            'warnings' => $warnings,
        ];
    }

    /**
     * Apakah pengawas $u sudah mengawasi jadwal lain yang waktunya bertumpuk dengan $test?
     */
    private function bentrokWaktu(User $u, Test $test): bool
    {
        if (! $test->waktu_mulai || ! $test->waktu_selesai) {
            return false; // tanpa waktu, tak bisa dinilai bentrok
        }

        return Test::query()
            ->where('id', '!=', $test->id)
            ->whereNotNull('waktu_mulai')
            ->whereNotNull('waktu_selesai')
            ->where('waktu_mulai', '<', $test->waktu_selesai)
            ->where('waktu_selesai', '>', $test->waktu_mulai)
            ->whereHas('pengawas', fn ($q) => $q->whereKey($u->id))
            ->exists();
    }
}
