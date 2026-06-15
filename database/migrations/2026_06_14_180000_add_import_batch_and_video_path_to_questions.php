<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Penanda satu sesi import (untuk halaman "Lengkapi Media").
            $table->string('import_batch')->nullable()->index()->after('created_by');
            // Path file video yang diupload guru (alternatif dari video_url eksternal).
            $table->string('video_path')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['import_batch', 'video_path']);
        });
    }
};
