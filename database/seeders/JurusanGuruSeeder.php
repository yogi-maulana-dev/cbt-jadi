<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class JurusanGuruSeeder extends Seeder
{
    public function run(): void
    {
        $umum = Jurusan::firstOrCreate(['kode' => 'UMUM'], ['nama' => 'Umum']);
        Jurusan::firstOrCreate(['kode' => 'RPL'], ['nama' => 'Rekayasa Perangkat Lunak']);
        Jurusan::firstOrCreate(['kode' => 'TKJ'], ['nama' => 'Teknik Komputer dan Jaringan']);

        // Mapel yang belum punya jurusan -> Umum.
        MataPelajaran::whereNull('jurusan_id')->update(['jurusan_id' => $umum->id]);

        // Tugaskan guru (operator) ke mapel Matematika.
        $guru = User::where('email', 'guru@cbt.test')->first();
        $mtk = MataPelajaran::where('kode', 'MTK')->first();

        if ($guru && $mtk) {
            $guru->mataPelajarans()->syncWithoutDetaching([$mtk->id]);
        }

        $this->command->info('Jurusan (Umum/RPL/TKJ) & penugasan guru Matematika di-seed.');
    }
}
