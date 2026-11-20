<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pembatalan kewajiban oleh dosen wali atau admin: tidak ada hapus permanen. */
    public function up(): void
    {
        Schema::table('jumlah_kompen', function (Blueprint $table) {
            // Terisi = kewajiban dibatalkan (status Dibatalkan). Kosong = berlaku.
            $table->timestamp('dibatalkan_pada')->nullable()->after('keterangan');
            $table->string('alasan_batal', 255)->nullable()->after('dibatalkan_pada');
        });
    }

    /** Batalkan: hapus dua kolom tadi. */
    public function down(): void
    {
        Schema::table('jumlah_kompen', function (Blueprint $table) {
            $table->dropColumn(['dibatalkan_pada', 'alasan_batal']);
        });
    }
};