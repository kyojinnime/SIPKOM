<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah kolom telepon dan foto profil pada tabel users.
     * Dipakai halaman Profil Saya (Langkah 16).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nomor telepon opsional; disimpan sebagai teks agar fleksibel (awalan 0, tanda hubung)
            $table->string('telepon', 20)->nullable()->after('email');

            // Path foto profil relatif terhadap storage/app/public (nama acak saat unggah)
            $table->string('foto')->nullable()->after('telepon');
        });
    }

    /** Mengembalikan tabel users seperti semula. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telepon', 'foto']);
        });
    }
};
