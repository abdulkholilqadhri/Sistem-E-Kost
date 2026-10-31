<?php

use App\Enums\StatusPembayaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Perubahan terhadap ERD pada SDD:
     * - jumlah_bayar ditambahkan. Tanpa kolom ini, pemilik kos memverifikasi
     *   bukti transfer tanpa acuan angka di dalam sistem.
     * - status memakai App\Enums\StatusPembayaran.
     *
     * path_bukti menunjuk berkas pada disk privat (storage/app/private).
     * Bukti transfer memuat nomor rekening, sehingga tidak boleh berada di
     * direktori publik yang dapat diakses siapa pun yang menebak URL-nya.
     */
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemesanan_id')->unique()->constrained('pemesanan')->cascadeOnDelete();
            $table->unsignedBigInteger('jumlah_bayar');
            $table->string('path_bukti');
            $table->string('nama_pengirim')->nullable();
            $table->date('tanggal_transfer')->nullable();
            $table->string('status', 20)->default(StatusPembayaran::Menunggu->value)->index();
            $table->text('catatan')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
