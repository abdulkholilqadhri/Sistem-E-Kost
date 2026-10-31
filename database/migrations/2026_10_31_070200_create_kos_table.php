<?php

use App\Enums\StatusKos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Perubahan terhadap ERD pada SDD:
     * - latitude/longitude diubah dari VARCHAR(50) nullable menjadi DECIMAL
     *   NOT NULL. Koordinat adalah inti sistem; kos tanpa koordinat tidak akan
     *   pernah muncul di peta, dan perhitungan Haversine pada VARCHAR memaksa
     *   cast di setiap query.
     * - tipe_kos ditambahkan; mockup memakainya sebagai filter dan badge,
     *   tetapi kolomnya tidak ada pada ERD.
     * - kolom foto tidak diletakkan di sini; lihat tabel foto_kos (1:N).
     */
    public function up(): void
    {
        Schema::create('kos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemilik_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama_kos');
            $table->text('deskripsi')->nullable();
            $table->text('alamat');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('tipe_kos', 10)->index();
            $table->unsignedInteger('harga_per_bulan');
            $table->unsignedSmallInteger('jumlah_kamar');
            $table->unsignedSmallInteger('sisa_kamar');
            $table->json('fasilitas')->nullable();
            $table->string('status', 20)->default(StatusKos::Aktif->value)->index();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index(['pemilik_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kos');
    }
};
