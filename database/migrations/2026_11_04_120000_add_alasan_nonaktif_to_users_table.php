<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-16 — alasan penonaktifan akun.
 *
 * Disimpan supaya admin dapat menjawab pengguna yang menanyakan mengapa ia
 * tidak bisa masuk. Tanpa kolom ini, keputusan penonaktifan hanya ada di
 * ingatan admin yang membuatnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('alasan_nonaktif', 500)->nullable()->after('status_akun');
            $table->timestamp('status_diubah_pada')->nullable()->after('alasan_nonaktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['alasan_nonaktif', 'status_diubah_pada']);
        });
    }
};
