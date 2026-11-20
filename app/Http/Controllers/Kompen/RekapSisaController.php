<?php

namespace App\Http\Controllers\Kompen;

use App\Http\Controllers\Controller;
use App\Models\DosenWali;
use App\Models\JumlahKompen;
use App\Models\LogAktivitas;
use App\Models\Periode;
use App\Services\CakupanKompen;
use App\Services\Rekap\RekapSisaData;
use App\Services\Rekap\RekapSisaXlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh rekap sisa kompensasi (Excel). Admin: semua atau per kelas/dosen. Dosen wali: kelasnya sendiri. */
class RekapSisaController extends Controller
{
    public function __invoke(Request $request, RekapSisaData $data, RekapSisaXlsx $xlsx): StreamedResponse|RedirectResponse
    {
        // Hanya admin dan dosen wali (aturan yang sama dengan halaman Data Kompen)
        Gate::authorize('viewAny', JumlahKompen::class);

        $user = $request->user();
        $admin = CakupanKompen::semua($user);

        // Rekap selalu untuk SATU periode: dari alamat, bila tidak valid memakai periode aktif
        $periode = Periode::find((int) $request->query('periode')) ?? Periode::yangAktif();
        if ($periode === null) {
            return redirect()->route('kompen.index')->withErrors(['rekap' => 'Belum ada periode, rekap tidak bisa dibuat.']);
        }
        if (!$admin && CakupanKompen::dosenWali($user) === null) {
            return redirect()->route('kompen.index')
                ->withErrors(['rekap' => 'Data dosen wali untuk akun Anda belum terdaftar. Hubungi admin.']);
        }

        // Filter kelas: harus kelas dalam cakupan akun; nilai lain diabaikan (sama seperti halaman Data Kompen)
        $kelasId = (int) $request->query('kelas');
        $kelasId = ($kelasId && CakupanKompen::kelas($user, $periode->id)->whereKey($kelasId)->exists()) ? $kelasId : null;

        // Filter dosen wali: hanya admin
        $dosenId = $admin ? (int) $request->query('dosen') : 0;
        $dosenId = ($dosenId && DosenWali::whereKey($dosenId)->exists()) ? $dosenId : null;

        $hasil = $data->ambil($user, $periode, $kelasId, $dosenId);

        // Tidak ada yang bersisa: jangan buat berkas kosong, kembali dengan pesan
        if (empty($hasil['baris'])) {
            return redirect()
                ->route('kompen.index', array_filter(['periode' => $periode->id, 'kelas' => $kelasId, 'dosen' => $dosenId]))
                ->withErrors(['rekap' => 'Tidak ada mahasiswa dengan sisa kompensasi pada pilihan ini.']);
        }

        $buku = $xlsx->buat(
            $hasil['baris'],
            'SEMESTER ' . mb_strtoupper($periode->semester) . ' ' . $periode->tahunMulai(),   // mis. "SEMESTER GENAP 2025"
            $hasil['keterangan'],
            now()->locale('id')->translatedFormat('j F Y, H:i')
        );

        // Jejak audit: rekap memuat data pribadi mahasiswa
        LogAktivitas::catat('unduh_rekap_sisa', null, null, [
            'periode' => $periode->id, 'kelas' => $kelasId, 'dosen' => $dosenId, 'baris' => count($hasil['baris']),
        ]);

        $namaFile = Str::slug(trim("rekap sisa kompensasi {$periode->semester} {$periode->tahunMulai()} {$hasil['suffix']}")) . '.xlsx';

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
            $buku->disconnectWorksheets();   // bebaskan memori
        }, $namaFile, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }
}