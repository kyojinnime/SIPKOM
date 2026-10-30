<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tabel ruang/lokasi kompensasi. Satu ruang diawasi satu pengawas. */
    public function up(): void
    {
        Schema::create('ruang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengawas_id')->constrained('pengawas')->restrictOnDelete();
            $table->string('nama_ruang', 100);
            $table->string('foto')->nullable();                 // path foto untuk kartu Lokasi Kompen
            $table->json('jenis_pekerjaan')->nullable();        // daftar tugas, mis. ["Instal OS"]
            $table->boolean('status_tersedia')->default(true);  // label "Available" di Beranda
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruang');
    }
};