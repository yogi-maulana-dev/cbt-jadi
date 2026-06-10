<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bank soal: soal lepas dari ujian, dimiliki oleh mata pelajaran dan
     * dapat dipakai ulang di banyak ujian melalui pivot test_question.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipe', ['pilihan_ganda', 'essay'])->default('pilihan_ganda');
            $table->text('pertanyaan');
            $table->string('gambar')->nullable()->comment('Path gambar pendukung soal');
            $table->unsignedSmallInteger('bobot')->default(1)->comment('Bobot/poin soal');
            $table->enum('tingkat_kesulitan', ['mudah', 'sedang', 'sulit'])->default('sedang');
            $table->text('pembahasan')->nullable();
            $table->timestamps();
            $table->softDeletes()->comment('Soal yang sudah dipakai di attempt tidak di-hard-delete');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
