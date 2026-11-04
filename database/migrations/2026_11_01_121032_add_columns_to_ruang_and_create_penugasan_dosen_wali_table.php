<?php

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
        // 1. Tambah kolom ke tabel ruang
        Schema::table('ruang', function (Blueprint $table) {
            $table->unsignedInteger('kuota')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
        });

        // 2. Buat tabel penugasan_dosen_wali
        Schema::create('penugasan_dosen_wali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->foreignId('dosen_wali_id')->constrained('dosen_wali')->restrictOnDelete();
            $table->timestamps();

            // Unik per kelas per periode
            $table->unique(['kelas_id', 'periode_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penugasan_dosen_wali');

        Schema::table('ruang', function (Blueprint $table) {
            $table->dropColumn(['kuota', 'deskripsi', 'aktif']);
        });
    }
};
