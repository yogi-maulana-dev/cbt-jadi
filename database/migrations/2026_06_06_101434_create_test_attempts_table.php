<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('deadline')->nullable()->comment('Batas waktu server-authoritative (waktu_mulai + durasi)');
            $table->timestamp('waktu_selesai')->nullable();
            $table->decimal('skor', 5, 2)->nullable()->comment('Nilai akhir 0-100');
            $table->unsignedSmallInteger('jumlah_benar')->default(0);
            $table->enum('status', ['sedang_dikerjakan', 'selesai'])->default('sedang_dikerjakan');
            $table->timestamps();

            $table->unique(['test_id', 'user_id'])->comment('Satu attempt per siswa per ujian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
    }
};
