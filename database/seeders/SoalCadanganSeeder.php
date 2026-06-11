<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Test;
use Illuminate\Database\Seeder;

/**
 * Menambahkan 10 soal cadangan ke ujian Matematika yang sudah ada,
 * tanpa menyentuh data lain. Idempotent.
 */
class SoalCadanganSeeder extends Seeder
{
    public function run(): void
    {
        $test = Test::where('judul', 'like', 'Ulangan Harian 1%')->first();

        if (! $test) {
            $this->command->warn('Ujian "Ulangan Harian 1" tidak ditemukan.');

            return;
        }

        if ($test->questions()->wherePivot('cadangan', true)->exists()) {
            $this->command->warn('Soal cadangan sudah ada — dilewati.');

            return;
        }

        $cadangan = [
            ['Hasil dari 23 + 19 adalah ...', ['41', '42', '43', '32'], 1],
            ['Hasil dari 7 x 9 adalah ...', ['56', '63', '72', '64'], 1],
            ['Hasil dari 96 : 8 adalah ...', ['11', '12', '13', '14'], 1],
            ['Hasil dari 85 - 47 adalah ...', ['38', '42', '48', '32'], 0],
            ['Pecahan sederhana dari 9/12 adalah ...', ['2/3', '3/4', '4/5', '1/2'], 1],
            ['Hasil dari 1/3 + 1/6 adalah ...', ['1/2', '2/9', '1/9', '2/3'], 0],
            ['Luas persegi dengan sisi 7 cm adalah ...', ['14', '28', '49', '21'], 2],
            ['Keliling persegi panjang p=9 l=4 adalah ...', ['26', '36', '13', '22'], 0],
            ['Hasil dari 3 pangkat 2 ditambah 4 pangkat 2 adalah ...', ['25', '12', '49', '7'], 0],
            ['Nilai 30 persen dari 150 adalah ...', ['30', '45', '50', '60'], 1],
        ];

        $labels = ['A', 'B', 'C', 'D'];

        foreach ($cadangan as $i => [$pertanyaan, $opsi, $benar]) {
            $q = Question::create([
                'mata_pelajaran_id' => $test->mata_pelajaran_id,
                'created_by' => $test->created_by,
                'tipe' => 'pilihan_ganda',
                'pertanyaan' => $pertanyaan,
                'bobot' => 1,
                'tingkat_kesulitan' => 'sedang',
            ]);

            foreach ($opsi as $j => $teks) {
                $q->choices()->create([
                    'label' => $labels[$j],
                    'teks' => $teks,
                    'urutan' => $j + 1,
                    'is_correct' => $j === $benar,
                ]);
            }

            $test->questions()->attach($q->id, ['urutan' => $i + 1, 'cadangan' => true]);
        }

        $this->command->info('10 soal cadangan ditambahkan ke "'.$test->judul.'".');
    }
}
