<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat import PDF: satu baris per kelas per import.
     * dicetak_pada dipakai membandingkan file lama dan baru untuk (kelas, periode) yang sama.
     */
    public function up(): void
    {
        Schema::create('riwayat_impor', function (Blueprint $table) {
            $table->id();
            // Pengimpor: dosen wali atau admin. Akun yang punya riwayat tidak boleh terhapus.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->string('nama_berkas');                  // nama asli file yang diunggah
            $table->char('hash_berkas', 64);                // SHA-256 isi file, untuk deteksi file yang sama
            $table->dateTime('dicetak_pada')->nullable();   // tanggal cetak dari footer PDF
            $table->json('ringkasan')->nullable();          // jumlah berhasil, ditolak, akun baru, dll
            $table->timestamps();

            // Mencari import terakhir untuk (kelas, periode) beserta tanggal cetaknya.
            $table->index(['kelas_id', 'periode_id', 'dicetak_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_impor');
    }
};