<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Relasi Pemesanan-Ulasan adalah 1:1, sesuai ERD. Class diagram pada SDD
     * menuliskan 1:N; ERD yang benar, dan unique pada pemesanan_id inilah yang
     * mencegah satu pemesanan dipakai berkali-kali untuk menaikkan rating.
     *
     * kos_id disimpan ulang di sini (denormalisasi terkendali) agar rata-rata
     * rating per kos dapat dihitung tanpa join ke tabel pemesanan.
     */
    public function up(): void
    {
        Schema::create('ulasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemesanan_id')->unique()->constrained('pemesanan')->cascadeOnDelete();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->foreignId('penyewa_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('komentar')->nullable();
            $table->timestamps();

            $table->index(['kos_id', 'rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ulasan');
    }
};
