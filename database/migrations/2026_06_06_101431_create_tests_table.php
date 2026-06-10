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
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajarans')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('durasi')->default(60)->comment('Durasi pengerjaan dalam menit');
            $table->unsignedTinyInteger('kkm')->default(0)->comment('Nilai minimal lulus (0-100)');
            $table->boolean('acak_soal')->default(false);
            $table->boolean('acak_jawaban')->default(false);
            $table->boolean('tampilkan_hasil')->default(true)->comment('Tampilkan skor ke siswa setelah selesai');
            $table->string('token', 20)->nullable()->comment('Token akses ujian');
            $table->timestamp('waktu_mulai')->nullable()->comment('Jadwal mulai ujian');
            $table->timestamp('waktu_selesai')->nullable()->comment('Jadwal akhir ujian');
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
