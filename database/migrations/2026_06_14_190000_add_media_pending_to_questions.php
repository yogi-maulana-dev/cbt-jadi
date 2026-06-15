<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // true = soal dideklarasikan butuh gambar/video tapi belum dilengkapi.
            $table->boolean('media_pending')->default(false)->index()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('media_pending');
        });
    }
};
