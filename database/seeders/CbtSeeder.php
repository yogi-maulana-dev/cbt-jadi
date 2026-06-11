<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CbtSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Users ----
        $admin = User::updateOrCreate(
            ['email' => 'admin@cbt.test'],
            ['name' => 'Administrator', 'password' => Hash::make('password'), 'role' => 'superadmin'],
        );

        User::updateOrCreate(
            ['email' => 'guru@cbt.test'],
            ['name' => 'Guru Matematika', 'password' => Hash::make('password'), 'role' => 'operator'],
        );

        User::updateOrCreate(
            ['email' => 'siswa@cbt.test'],
            ['name' => 'Siswa Contoh', 'password' => Hash::make('password'), 'role' => 'siswa'],
        );

        // ---- Mata Pelajaran ----
        $matematika = MataPelajaran::updateOrCreate(
            ['kode' => 'MTK'],
            ['nama' => 'Matematika', 'deskripsi' => 'Mata pelajaran Matematika tingkat dasar.'],
        );

        MataPelajaran::updateOrCreate(
            ['kode' => 'BIN'],
            ['nama' => 'Bahasa Indonesia', 'deskripsi' => 'Mata pelajaran Bahasa Indonesia.'],
        );

        MataPelajaran::updateOrCreate(
            ['kode' => 'IPA'],
            ['nama' => 'Ilmu Pengetahuan Alam', 'deskripsi' => 'Mata pelajaran IPA terpadu.'],
        );

        // ---- Ujian ----
        $ujian = Test::updateOrCreate(
            ['judul' => 'Ulangan Harian 1 - Matematika Dasar'],
            [
                'mata_pelajaran_id' => $matematika->id,
                'created_by' => $admin->id,
                'deskripsi' => 'Ulangan harian materi operasi hitung dasar, pecahan, dan geometri sederhana.',
                'durasi' => 60,
                'kkm' => 75,
                'acak_soal' => true,
                'acak_jawaban' => true,
                'max_pelanggaran' => 3,
                'tampilkan_hasil' => true,
                'token' => 'MTK2026',
                'status' => 'published',
            ],
        );

        // Reset agar idempotent: lepas tautan & hapus soal mapel ini.
        $ujian->questions()->detach();
        Question::where('mata_pelajaran_id', $matematika->id)->forceDelete();

        // ---- 10 Soal Pilihan Ganda (masuk ke BANK SOAL) ----
        // Format: [pertanyaan, [opsi...], index jawaban benar (0-based), pembahasan]
        $soal = [
            ['Hasil dari 15 + 27 adalah ...', ['32', '42', '52', '41'], 1, '15 + 27 = 42.'],
            ['Hasil dari 9 × 8 adalah ...', ['63', '72', '81', '64'], 1, '9 × 8 = 72.'],
            ['Hasil dari 144 : 12 adalah ...', ['11', '12', '13', '14'], 1, '144 dibagi 12 sama dengan 12.'],
            ['Hasil dari 100 - 37 adalah ...', ['63', '67', '73', '57'], 0, '100 - 37 = 63.'],
            ['Bentuk pecahan paling sederhana dari 8/12 adalah ...', ['1/2', '2/3', '3/4', '4/6'], 1, '8/12 dibagi 4 = 2/3.'],
            ['Hasil dari 1/2 + 1/4 adalah ...', ['1/6', '2/6', '3/4', '1/8'], 2, '1/2 = 2/4, jadi 2/4 + 1/4 = 3/4.'],
            ['Sebuah persegi memiliki sisi 6 cm. Luasnya adalah ...', ['12 cm²', '24 cm²', '36 cm²', '18 cm²'], 2, 'Luas persegi = sisi × sisi = 6 × 6 = 36 cm².'],
            ['Keliling persegi panjang dengan panjang 8 cm dan lebar 5 cm adalah ...', ['26 cm', '40 cm', '13 cm', '20 cm'], 0, 'Keliling = 2 × (8 + 5) = 26 cm.'],
            ['Hasil dari 2³ (2 pangkat 3) adalah ...', ['6', '8', '9', '12'], 1, '2³ = 2 × 2 × 2 = 8.'],
            ['Nilai dari 25% dari 200 adalah ...', ['25', '40', '50', '75'], 2, '25% × 200 = 0,25 × 200 = 50.'],
        ];

        $labels = ['A', 'B', 'C', 'D'];

        foreach ($soal as $i => [$pertanyaan, $opsi, $benar, $pembahasan]) {
            // 1) Buat soal di BANK SOAL (lepas dari ujian).
            $question = Question::create([
                'mata_pelajaran_id' => $matematika->id,
                'created_by' => $admin->id,
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

            // 2) Tautkan soal ke ujian via pivot, dengan urutan default.
            $ujian->questions()->attach($question->id, ['urutan' => $i + 1]);
        }

        // ---- 1 Soal Essay (dikoreksi manual) ----
        $essay = Question::create([
            'mata_pelajaran_id' => $matematika->id,
            'created_by' => $admin->id,
            'tipe' => 'essay',
            'pertanyaan' => 'Jelaskan langkah-langkah menghitung luas persegi panjang, beserta contohnya.',
            'bobot' => 5,
            'tingkat_kesulitan' => 'sedang',
            'pembahasan' => 'Luas = panjang × lebar.',
        ]);
        $ujian->questions()->attach($essay->id, ['urutan' => count($soal) + 1]);

        // ---- 10 SOAL CADANGAN (dipakai saat ujian ulang setelah buka blokir) ----
        $cadangan = [
            ['Hasil dari 23 + 19 adalah ...', ['41', '42', '43', '32'], 1, '23 + 19 = 42.'],
            ['Hasil dari 7 × 9 adalah ...', ['56', '63', '72', '64'], 1, '7 × 9 = 63.'],
            ['Hasil dari 96 : 8 adalah ...', ['11', '12', '13', '14'], 1, '96 : 8 = 12.'],
            ['Hasil dari 85 - 47 adalah ...', ['38', '42', '48', '32'], 0, '85 - 47 = 38.'],
            ['Pecahan sederhana dari 9/12 adalah ...', ['2/3', '3/4', '4/5', '1/2'], 1, '9/12 dibagi 3 = 3/4.'],
            ['Hasil dari 1/3 + 1/6 adalah ...', ['1/2', '2/9', '1/9', '2/3'], 0, '2/6 + 1/6 = 3/6 = 1/2.'],
            ['Luas persegi dengan sisi 7 cm adalah ...', ['14 cm²', '28 cm²', '49 cm²', '21 cm²'], 2, '7 × 7 = 49 cm².'],
            ['Keliling persegi panjang p=9 cm, l=4 cm adalah ...', ['26 cm', '36 cm', '13 cm', '22 cm'], 0, '2 × (9 + 4) = 26 cm.'],
            ['Hasil dari 3² + 4² adalah ...', ['25', '12', '49', '7'], 0, '9 + 16 = 25.'],
            ['Nilai 30% dari 150 adalah ...', ['30', '45', '50', '60'], 1, '0,3 × 150 = 45.'],
        ];

        foreach ($cadangan as $i => [$pertanyaan, $opsi, $benar, $pembahasan]) {
            $question = Question::create([
                'mata_pelajaran_id' => $matematika->id,
                'created_by' => $admin->id,
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

            // Tandai sebagai CADANGAN di pivot.
            $ujian->questions()->attach($question->id, [
                'urutan' => $i + 1,
                'cadangan' => true,
            ]);
        }

        $this->command->info('CBT seeder selesai: 1 ujian, 10 soal PG + 1 essay + 10 soal cadangan.');
    }
}
