<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tandai soal cadangan pada pivot ujian.
        Schema::table('test_question', function (Blueprint $table) {
            $table->boolean('cadangan')->default(false)->after('bobot')
                ->comment('Soal cadangan untuk ujian ulang setelah buka blokir');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('diblokir_test_id')->nullable()->after('alasan_blokir')
                ->constrained('tests')->nullOnDelete()
                ->comment('Ujian penyebab blokir');
            $table->foreignId('cadangan_test_id')->nullable()->after('diblokir_test_id')
                ->constrained('tests')->nullOnDelete()
                ->comment('Ujian yang attempt berikutnya pakai soal cadangan');
        });
    }

    public function down(): void
    {
        Schema::table('test_question', fn (Blueprint $t) => $t->dropColumn('cadangan'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diblokir_test_id');
            $table->dropConstrainedForeignId('cadangan_test_id');
        });
    }
};
