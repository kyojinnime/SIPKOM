<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Notifikasi dalam aplikasi (ikon lonceng). Satu baris = satu pesan untuk satu akun. */
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            // Penerima. Akun dihapus -> notifikasinya ikut terhapus (tidak ada gunanya tanpa pemilik).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis', 50);                 // mis. "import_sp", "ringkasan_import"
            $table->string('judul', 150);                // judul singkat di daftar
            $table->text('isi')->nullable();             // penjelasan lengkap
            $table->string('tautan')->nullable();        // halaman yang dibuka saat notifikasi diklik
            $table->timestamp('dibaca_pada')->nullable(); // kosong = belum dibaca
            $table->timestamps();

            // Dipakai untuk menghitung badge "belum dibaca" per akun dengan cepat.
            $table->index(['user_id', 'dibaca_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};