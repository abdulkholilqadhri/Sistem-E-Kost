<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel ini tidak ada pada ERD di SDD, padahal sequence diagram 10
     * menjalankan simpanFoto() dan mockup detail kos menampilkan 12 foto.
     * Satu kolom VARCHAR pada tabel kos tidak cukup untuk itu.
     */
    public function up(): void
    {
        Schema::create('foto_kos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kos_id')->constrained('kos')->cascadeOnDelete();
            $table->string('path_foto');
            $table->string('keterangan')->nullable();
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['kos_id', 'urutan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foto_kos');
    }
};
