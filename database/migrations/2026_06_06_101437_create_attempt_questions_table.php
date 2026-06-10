<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Snapshot urutan soal & urutan opsi hasil pengacakan PER siswa.
     * Membuat ujian deterministik, navigasi prev/next stabil, dan kebal
     * terhadap perubahan bank soal saat ujian berlangsung.
     */
    public function up(): void
    {
        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_attempt_id')->constrained('test_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedInteger('urutan')->comment('Urutan soal hasil acak untuk siswa ini');
            $table->json('urutan_opsi')->nullable()->comment('Array choice_id terurut (hasil acak jawaban)');
            $table->timestamps();

            $table->unique(['test_attempt_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempt_questions');
    }
};
