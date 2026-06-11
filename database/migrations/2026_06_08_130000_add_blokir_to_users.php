<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('diblokir')->default(false)->after('role')
                ->comment('Siswa diblokir login karena pelanggaran ujian');
            $table->timestamp('diblokir_pada')->nullable()->after('diblokir');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['diblokir', 'diblokir_pada']);
        });
    }
};
