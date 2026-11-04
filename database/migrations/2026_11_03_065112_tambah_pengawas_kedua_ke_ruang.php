<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tambah kolom pengawas kedua (pengganti sementara) di tabel ruang. */
    public function up(): void
    {
        Schema::table('ruang', function (Blueprint $table) {
            // Boleh kosong. restrictOnDelete: pengawas yang masih jadi pengawas kedua tidak bisa terhapus
            $table->foreignId('pengawas_kedua_id')->nullable()->after('pengawas_id')
                  ->constrained('pengawas')->restrictOnDelete();
        });
    }

    /** Batalkan: hapus kunci asing dulu, baru kolomnya. */
    public function down(): void
    {
        Schema::table('ruang', function (Blueprint $table) {
            $table->dropForeign(['pengawas_kedua_id']);
            $table->dropColumn('pengawas_kedua_id');
        });
    }
};