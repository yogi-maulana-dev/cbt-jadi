<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Seeder;

class UjianTestSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@cbt.test')->first();

        $mapel = MataPelajaran::firstOrCreate(
            ['kode' => 'IPA'],
            ['nama' => 'Ilmu Pengetahuan Alam', 'deskripsi' => 'Mata pelajaran IPA terpadu.'],
        );

        if (Test::where('judul', 'Ujian Percobaan IPA')->exists()) {
            $this->command->warn('Ujian Percobaan IPA sudah ada — dilewati. Hapus dulu bila ingin dibuat ulang.');

            return;
        }

        $test = Test::create([
            'mata_pelajaran_id' => $mapel->id,
            'created_by' => $admin?->id,
            'judul' => 'Ujian Percobaan IPA',
            'deskripsi' => 'Ujian percobaan untuk uji coba aplikasi (tanpa token).',
            'durasi' => 20,
            'kkm' => 65,
            'acak_soal' => true,
            'acak_jawaban' => true,
            'max_pelanggaran' => 0,
            'tampilkan_hasil' => true,
            'token' => null,
            'status' => 'published',
        ]);

        // [pertanyaan, [opsi], index jawaban benar (0-based), pembahasan]
        $soal = [
            ['Planet yang paling dekat dengan Matahari adalah ...', ['Merkurius', 'Venus', 'Bumi', 'Mars'], 0, 'Merkurius adalah planet terdekat dengan Matahari.'],
            ['Gas yang dihirup manusia untuk bernapas adalah ...', ['Karbon dioksida', 'Oksigen', 'Nitrogen', 'Hidrogen'], 1, 'Manusia menghirup oksigen (O2) saat bernapas.'],
            ['Organ tubuh yang berfungsi memompa darah adalah ...', ['Paru-paru', 'Hati', 'Jantung', 'Ginjal'], 2, 'Jantung memompa darah ke seluruh tubuh.'],
            ['Proses tumbuhan membuat makanannya sendiri disebut ...', ['Respirasi', 'Fotosintesis', 'Transpirasi', 'Pencernaan'], 1, 'Fotosintesis mengubah cahaya menjadi energi makanan.'],
            ['Satuan gaya dalam Sistem Internasional (SI) adalah ...', ['Joule', 'Watt', 'Newton', 'Pascal'], 2, 'Satuan gaya adalah Newton (N).'],
        ];

        $labels = ['A', 'B', 'C', 'D'];

        foreach ($soal as $i => [$pertanyaan, $opsi, $benar, $pembahasan]) {
            $question = Question::create([
                'mata_pelajaran_id' => $mapel->id,
                'created_by' => $admin?->id,
                'tipe' => 'pilihan_ganda',
                'pertanyaan' => $pertanyaan,
                'bobot' => 1,
                'tingkat_kesulitan' => 'sedang',
                'pembahasan' => $pembahasan,
            ]);

            foreach ($opsi as $j => $teks) {
                $question->choices()->create([
                    'label' => $labels[$j],
                    'teks' => $teks,
                    'urutan' => $j + 1,
                    'is_correct' => $j === $benar,
                ]);
            }

            $test->questions()->attach($question->id, ['urutan' => $i + 1]);
        }

        // 1 soal essay (dikoreksi manual)
        $essay = Question::create([
            'mata_pelajaran_id' => $mapel->id,
            'created_by' => $admin?->id,
            'tipe' => 'essay',
            'pertanyaan' => 'Jelaskan secara singkat proses fotosintesis pada tumbuhan.',
            'bobot' => 5,
            'tingkat_kesulitan' => 'sedang',
            'pembahasan' => 'Fotosintesis: tumbuhan mengubah air + karbon dioksida + cahaya matahari menjadi glukosa dan oksigen.',
        ]);
        $test->questions()->attach($essay->id, ['urutan' => 6]);

        $this->command->info('Ujian Percobaan IPA dibuat: 5 soal pilihan ganda + 1 essay, tanpa token, published.');
    }
}
