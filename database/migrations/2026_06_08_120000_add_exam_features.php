<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fitur "ragu-ragu" (flag) per soal pada attempt.
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->boolean('ragu')->default(false)->after('urutan_opsi')
                ->comment('Ditandai siswa untuk ditinjau ulang');
        });

        // Anti-cheat: jumlah pelanggaran (keluar tab) per attempt.
        Schema::table('test_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('pelanggaran')->default(0)->after('jumlah_benar')
                ->comment('Jumlah kali siswa keluar dari tab ujian');
        });

        // Ambang auto-submit per ujian (0 = nonaktif).
        Schema::table('tests', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_pelanggaran')->default(0)->after('acak_jawaban')
                ->comment('Auto-submit setelah N kali keluar tab (0 = nonaktif)');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_questions', fn (Blueprint $t) => $t->dropColumn('ragu'));
        Schema::table('test_attempts', fn (Blueprint $t) => $t->dropColumn('pelanggaran'));
        Schema::table('tests', fn (Blueprint $t) => $t->dropColumn('max_pelanggaran'));
    }
};
