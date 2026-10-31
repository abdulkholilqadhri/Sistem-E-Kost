<?php

use App\Enums\StatusPemesanan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Perubahan terhadap ERD pada SDD:
     * - tanggal_mulai, durasi_bulan, dan tanggal_selesai ditambahkan. ERD hanya
     *   menyimpan tanggal_pesan, padahal activity diagram meminta penyewa
     *   menentukan durasi sewa dan mockup menampilkan periode hunian.
     * - harga_per_bulan disalin ke sini sebagai snapshot. Bila pemilik mengubah
     *   harga kos di kemudian hari, pemesanan lama tetap memakai harga saat
     *   pemesanan dibuat.
     * - status memakai App\Enums\StatusPemesanan (7 nilai), menggantikan ENUM
     *   tiga nilai pada ERD yang tidak cukup menampung alur yang dirancang.
     *
     * Relasi memakai restrictOnDelete, bukan cascade: riwayat pemesanan tidak
     * boleh lenyap hanya karena kos atau akun dihapus.
     */
    public function up(): void
    {
        Schema::create('pemesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->restrictOnDelete();
            $table->foreignId('penyewa_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->unsignedTinyInteger('durasi_bulan');
            $table->date('tanggal_selesai');
            $table->unsignedInteger('harga_per_bulan');
            $table->unsignedBigInteger('total_harga');
            $table->string('status', 30)->default(StatusPemesanan::MenungguKonfirmasi->value)->index();
            $table->text('catatan_pemilik')->nullable();
            $table->timestamp('dikonfirmasi_pada')->nullable();
            $table->timestamp('batas_pembayaran')->nullable();
            $table->timestamps();

            $table->index(['kos_id', 'status']);
            $table->index(['penyewa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemesanan');
    }
};
