<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu pengajuan sekaligus satu sesi pelaksanaan kompensasi.
     * Alur status: menunggu -> diterima / ditolak -> selesai.
     */
    public function up(): void
    {
        Schema::create('kompen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jumlah_kompen_id')->constrained('jumlah_kompen')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('ruang_id')->constrained('ruang')->restrictOnDelete();
            $table->enum('status', ['menunggu', 'diterima', 'ditolak', 'selesai'])
                ->default('menunggu')->index();
            $table->date('tgl_kompen')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->unsignedSmallInteger('jumlah_jam_pelaksanaan')->default(0);
            $table->string('dokumentasi')->nullable();           // path foto bukti

            // Anti kecurangan: jam hanya dihitung bila sudah divalidasi pengawas.
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('pengawas')->nullOnDelete();
            $table->timestamp('divalidasi_pada')->nullable();

            $table->softDeletes();   // data tidak hilang permanen, jejak tetap ada
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kompen');
    }
};