<?php

use App\Enums\StatusPengajuan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel ini melayani FR-14 (pengajuan menjadi pemilik kos) dan FR-15
     * (verifikasi oleh admin).
     *
     * Catatan privasi: nomor KTP (NIK) sengaja TIDAK disimpan sebagai kolom.
     * Admin cukup melihat berkas KTP untuk memverifikasi, sehingga NIK tidak
     * perlu berada di dalam basis data. Berkasnya sendiri disimpan pada disk
     * privat (storage/app/private), bukan pada direktori publik.
     */
    public function up(): void
    {
        Schema::create('pengajuan_pemilik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama_lengkap');
            $table->text('alamat')->nullable();
            $table->string('path_ktp');
            $table->string('path_dokumen_kepemilikan')->nullable();
            $table->string('status', 20)->default(StatusPengajuan::Menunggu->value)->index();
            $table->text('catatan_admin')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_pemilik');
    }
};
