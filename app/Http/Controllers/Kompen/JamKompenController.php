<?php

namespace App\Http\Controllers\Kompen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kompen\BatalkanKompenRequest;
use App\Http\Requests\Kompen\UbahJamRequest;
use App\Models\JumlahKompen;
use App\Models\LogAktivitas;
use App\Services\KompenHitung;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Aksi pada satu kewajiban kompensasi: ubah jam wajib dan batalkan. Balasan JSON untuk popup. */
class JamKompenController extends Controller
{
    /** Ubah jam wajib. Tidak boleh di bawah jam yang sudah terlaksana. */
    public function ubah(UbahJamRequest $request, JumlahKompen $jumlahKompen): JsonResponse
    {
        $jam = (int) $request->validated('jumlah_jam');
        $alasan = $request->validated('alasan') ?: null;

        return DB::transaction(function () use ($jumlahKompen, $jam, $alasan) {
            // Kunci baris: perubahan bersamaan (mis. pengawas mencatat jam) tidak boleh saling menimpa
            $baris = JumlahKompen::with('mahasiswa')->whereKey($jumlahKompen->id)->lockForUpdate()->firstOrFail();

            // Kewajiban yang sudah dibatalkan tidak bisa diubah jamnya
            if ($baris->dibatalkan_pada !== null) {
                return response()->json(['message' => 'Kewajiban ini sudah dibatalkan, jamnya tidak bisa diubah.'], 422);
            }

            // Periksa ulang batas bawah di dalam transaksi (data bisa berubah sejak validasi)
            $min = max(1, KompenHitung::jamMinimum((int) $baris->mahasiswa_id, $baris));
            if ($jam < $min) {
                $pesan = "Jam wajib tidak boleh kurang dari {$min} jam karena sebagian sudah terlaksana.";
                return response()->json(['message' => $pesan, 'errors' => ['jumlah_jam' => [$pesan]]], 422);
            }

            $lama = (int) $baris->jumlah_jam;
            if ($lama === $jam) {
                return response()->json(['pesan' => 'Jam wajib tidak berubah.']);
            }

            $baris->update(['jumlah_jam' => $jam]);
            LogAktivitas::catat('ubah_jam', $baris, ['jumlah_jam' => $lama], ['jumlah_jam' => $jam, 'alasan' => $alasan]);

            return response()->json([
                'pesan' => "Jam wajib {$baris->mahasiswa->nama_mahasiswa} diubah dari {$lama} menjadi {$jam} jam.",
            ]);
        });
    }

    /** Batalkan kewajiban dengan alasan. Data tidak dihapus: hanya ditandai dibatalkan. */
    public function batalkan(BatalkanKompenRequest $request, JumlahKompen $jumlahKompen): JsonResponse
    {
        $alasan = trim($request->validated('alasan'));

        return DB::transaction(function () use ($jumlahKompen, $alasan) {
            $baris = JumlahKompen::with('mahasiswa')->whereKey($jumlahKompen->id)->lockForUpdate()->firstOrFail();

            if ($baris->dibatalkan_pada !== null) {
                return response()->json(['message' => 'Kewajiban ini sudah dibatalkan sebelumnya.'], 422);
            }

            // Jam terlaksana dicatat di log sebagai bahan audit (realisasi tidak ikut terhapus)
            $terlaksana = KompenHitung::ringkasan((int) $baris->mahasiswa_id)['terlaksana'];

            $baris->update(['dibatalkan_pada' => now(), 'alasan_batal' => $alasan]);
            LogAktivitas::catat(
                'batal_kewajiban', $baris,
                ['jumlah_jam' => (int) $baris->jumlah_jam, 'dibatalkan' => false],
                ['dibatalkan' => true, 'alasan' => $alasan, 'jam_terlaksana' => $terlaksana]
            );

            return response()->json(['pesan' => "Kewajiban kompensasi {$baris->mahasiswa->nama_mahasiswa} dibatalkan."]);
        });
    }
}