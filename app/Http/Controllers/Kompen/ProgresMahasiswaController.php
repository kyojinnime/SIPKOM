<?php

namespace App\Http\Controllers\Kompen;

use App\Http\Controllers\Controller;
use App\Models\JumlahKompen;
use App\Models\Kompen;
use App\Models\Mahasiswa;
use App\Services\CakupanKompen;
use App\Services\KompenHitung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Detail progres satu mahasiswa: ringkasan, kewajiban per periode, dan riwayat kompensasi. */
class ProgresMahasiswaController extends Controller
{
    // Batas baris riwayat yang dimuat sekali tampil
    private const MAKS_RIWAYAT = 200;

    public function tampil(Request $request, Mahasiswa $mahasiswa): View
    {
        // Hanya admin dan dosen wali
        Gate::authorize('viewAny', JumlahKompen::class);

        // Cakupan lintas periode: dosen wali hanya mahasiswa pada kelas yang pernah ditugaskan kepadanya
        abort_unless(
            CakupanKompen::mahasiswa($request->user(), null)->whereKey($mahasiswa->getKey())->exists(),
            403
        );

        $mahasiswa->load('kelas.prodi', 'user');

        // Ringkasan total lintas periode (aturan B08)
        $ring = KompenHitung::ringkasan($mahasiswa);

        // Jam terlaksana per kewajiban (per jumlah_kompen_id), satu query
        $perKewajiban = Kompen::tervalidasi()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->selectRaw('jumlah_kompen_id, SUM(jumlah_jam_pelaksanaan) AS jam')
            ->groupBy('jumlah_kompen_id')
            ->pluck('jam', 'jumlah_kompen_id');

        // Kewajiban per periode, periode terbaru di atas (genap setelah ganjil pada tahun ajaran yang sama)
        $kewajiban = JumlahKompen::with('periode')
            ->where('mahasiswa_id', $mahasiswa->id)->get()
            ->sortByDesc(fn ($b) => $b->periode->tahun_ajaran . $b->periode->semester)
            ->values()
            ->map(fn (JumlahKompen $b) => [
                'id'         => $b->id,
                'periode'    => ucfirst($b->periode->semester) . ' ' . $b->periode->tahun_ajaran,
                'jam'        => (int) $b->jumlah_jam,
                'ket'        => $b->keterangan,
                'terlaksana' => (int) ($perKewajiban[$b->id] ?? 0),
                'batal'      => $b->dibatalkan_pada !== null,
                'tgl_batal'  => $b->dibatalkan_pada?->locale('id')->translatedFormat('j F Y'),
                'alasan'     => $b->alasan_batal,
                // Batas bawah jam untuk popup ubah jam
                'jam_min'    => max(1, KompenHitung::hitungJamMinimum($ring['terlaksana'], $ring['wajib'], (int) $b->jumlah_jam)),
            ]);

        // Riwayat kompensasi (pengajuan dan pelaksanaan), terbaru di atas
        $riwayat = Kompen::with('ruang')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->orderByDesc('tgl_kompen')->orderByDesc('id')
            ->limit(self::MAKS_RIWAYAT)->get()
            ->map(fn (Kompen $k) => [
                'tanggal' => $k->tgl_kompen?->locale('id')->translatedFormat('j F Y'),
                'ruang'   => $k->ruang->nama_ruang ?? '-',
                'mulai'   => $k->jam_mulai ? substr($k->jam_mulai, 0, 5) : null,
                'selesai' => $k->jam_selesai ? substr($k->jam_selesai, 0, 5) : null,
                'jam'     => (int) $k->jumlah_jam_pelaksanaan,
                // Selesai dan sudah divalidasi = Tervalidasi; selesai tanpa validasi = Menunggu validasi
                'status'  => $k->status === 'selesai'
                    ? ($k->divalidasi_pada ? 'Tervalidasi' : 'Menunggu validasi')
                    : ucfirst($k->status),
            ]);

        return view('kompen.detail', [
            'mahasiswa' => $mahasiswa,
            'ring'      => $ring,
            'persen'    => $ring['wajib'] > 0 ? min(100, (int) round($ring['terlaksana'] / $ring['wajib'] * 100)) : 0,
            'kewajiban' => $kewajiban,
            'riwayat'   => $riwayat,
        ]);
    }
}