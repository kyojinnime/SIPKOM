<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit: siapa mengubah apa, kapan, nilai lama dan baru.
     * Dipakai untuk ubah jam, batal, reset password, dan import.
     */
    public function up(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            // Pelaku. Akun dihapus -> jejak tetap ada, pelaku menjadi kosong.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 50);                   // mis. "ubah_jam", "batal_kewajiban"
            $table->string('subjek_tipe', 60)->nullable(); // nama model yang diubah
            $table->unsignedBigInteger('subjek_id')->nullable();
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->timestamps();

            // Mencari seluruh riwayat satu data (mis. satu jumlah_kompen).
            $table->index(['subjek_tipe', 'subjek_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};