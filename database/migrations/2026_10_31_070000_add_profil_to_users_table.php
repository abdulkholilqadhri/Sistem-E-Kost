<?php

use App\Enums\RoleUser;
use App\Enums\StatusAkun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_hp', 20)->nullable()->after('email');
            $table->string('role', 20)->default(RoleUser::Penyewa->value)->index()->after('password');
            $table->string('status_akun', 20)->default(StatusAkun::Aktif->value)->index()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['no_hp', 'role', 'status_akun']);
        });
    }
};
