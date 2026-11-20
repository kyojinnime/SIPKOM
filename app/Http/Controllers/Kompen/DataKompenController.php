<?php

namespace App\Http\Controllers\Kompen;

use App\Http\Controllers\Controller;
use App\Models\DosenWali;
use App\Models\JumlahKompen;
use App\Models\Kelas;
use App\Models\Periode;
use App\Services\CakupanKompen;
use App\Services\KompenHitung;
use App\Services\SemesterHelper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Halaman Data Kompen untuk dosen wali dan admin: ringkasan, filter, daftar mahasiswa. */
class DataKompenController extends Controller
{
    // Jumlah baris per halaman
    private const PER_HALAMAN = 25;

    // Status yang boleh dipakai sebagai filter (status lain hanya tampil di "Semua")
    private const STATUS_FILTER = ['belum_mulai', 'berjalan', 'selesai'];

    // Nilai alamat untuk pilihan "Semua periode"
    private const SEMUA_PERIODE = 'semua';

    /** Daftar kompensasi. Cakupan data ditentukan CakupanKompen, bukan sekadar menu. */
    public function index(Request $request): View
    {
        // Hanya admin dan dosen wali; role lain mendapat 403
        Gate::authorize('viewAny', JumlahKompen::class);

        $user = $request->user();
        $admin = CakupanKompen::semua($user);

        // ===== BAGIAN 1: periode terpilih =====
        // Tanpa pilihan di alamat = periode aktif. periode=semua = semua periode ($periode bernilai null).
        $daftarPeriode = Periode::orderByDesc('tahun_ajaran')->orderByDesc('semester')->get();
        $semuaPeriode = $request->query('periode') === self::SEMUA_PERIODE;
        $periode = $semuaPeriode
            ? null
            : ($daftarPeriode->firstWhere('id', (int) $request->query('periode'))
                ?? Periode::yangAktif()
                ?? $daftarPeriode->first());
        $periodeId = $periode?->id;   // null = semua periode

        // Kondisi yang membuat halaman tidak bisa menampilkan data
        $pesanBlokir = null;
        if (!$admin && CakupanKompen::dosenWali($user) === null) {
            $pesanBlokir = 'Data dosen wali untuk akun Anda belum terdaftar. Hubungi admin.';
        } elseif ($daftarPeriode->isEmpty()) {
            $pesanBlokir = $admin
                ? 'Belum ada periode. Buat periode di menu Kelola Data, Periode.'
                : 'Belum ada periode. Hubungi admin.';
        }
        if ($pesanBlokir) {
            return view('kompen.index', ['pesanBlokir' => $pesanBlokir, 'admin' => $admin]);
        }

        // ===== BAGIAN 2: pilihan filter (hanya yang ada isinya) =====
        // Kelas dalam cakupan akun yang punya mahasiswa berjam wajib (pada periode terpilih, atau periode mana pun)
        $daftarKelas = CakupanKompen::kelas($user, $periodeId)
            ->whereHas('mahasiswa.jumlahKompen', fn ($q) => $q
                ->when($periodeId, fn ($w) => $w->where('periode_id', $periodeId)))
            ->with('prodi')
            ->orderByDesc('angkatan')->orderBy('nama_kelas')
            ->get()
            ->mapWithKeys(fn (Kelas $k) => [
                $k->id => $this->labelKelas($k, $periode)['kode'] . ' - ' . ($k->prodi->nama_prodi ?? '-'),
            ]);

        // Filter dosen wali hanya untuk admin
        $daftarDosen = $admin
            ? DosenWali::whereHas('penugasan', fn ($q) => $q
                    ->when($periodeId, fn ($w) => $w->where('periode_id', $periodeId)))
                ->orderBy('nama_dosen')->pluck('nama_dosen', 'id')
            : collect();

        // Nilai filter dari alamat; yang tidak ada di pilihan dibuang
        $kelasId = (int) $request->query('kelas');
        $kelasId = $daftarKelas->has($kelasId) ? $kelasId : null;
        $dosenId = $admin ? (int) $request->query('dosen') : 0;
        $dosenId = $daftarDosen->has($dosenId) ? $dosenId : null;
        $statusAktif = in_array($request->query('status'), self::STATUS_FILTER, true) ? $request->query('status') : 'semua';
        $q = mb_strtolower(trim(mb_substr((string) $request->query('q', ''), 0, 50)));

        // ===== BAGIAN 3: ambil baris jam wajib dalam cakupan akun =====
        $baris = JumlahKompen::query()
            ->when($periodeId, fn ($qr) => $qr->where('periode_id', $periodeId))
            // Cakupan: dosen wali hanya kelas yang ditugaskan padanya; admin semua
            ->whereIn('mahasiswa_id', CakupanKompen::mahasiswa($user, $periodeId)->select('mahasiswa.id'))
            ->when($kelasId, fn ($qr) => $qr->whereHas('mahasiswa', fn ($m) => $m->where('kelas_id', $kelasId)))
            ->when($dosenId, fn ($qr) => $qr->whereHas('mahasiswa.kelas.penugasan', fn ($p) => $p
                ->where('dosen_wali_id', $dosenId)
                ->when($periodeId, fn ($w) => $w->where('periode_id', $periodeId))))
            ->with('mahasiswa.kelas.prodi')
            ->get();

        // Satu baris tampilan per mahasiswa (pada satu periode, tiap mahasiswa hanya punya satu baris)
        $perMahasiswa = $baris->groupBy('mahasiswa_id');

        // Jam wajib, terlaksana, sisa, dan status semua mahasiswa sekaligus (dua query, tanpa N+1)
        $ring = KompenHitung::ringkasanBanyak($perMahasiswa->keys()->all(), $periodeId);
        $ambang = (int) config('sipkom.ambang_merah_jam', 64);

        // Susun item tampilan, urut nama A-Z
        $semua = $perMahasiswa
            ->map(fn (Collection $rows, $mid) => $this->bangunItem($rows, $ring[$mid], $periode, $ambang))
            ->sortBy('urut')->values();

        // ===== BAGIAN 4: kartu ringkasan (mengikuti periode, kelas, dan dosen; tidak ikut pencarian dan status) =====
        $kartu = ['berkompen' => 0, 'selesai' => 0, 'belum' => 0, 'sisa' => 0, 'besar' => 0];
        foreach ($semua as $i) {
            if (in_array($i['status'], ['belum_mulai', 'berjalan', 'selesai'], true)) {
                $kartu['berkompen']++;
                $kartu['sisa'] += $i['sisa'];
                $i['status'] === 'selesai' ? $kartu['selesai']++ : $kartu['belum']++;
            }
            $kartu['besar'] += $i['besar'] ? 1 : 0;
        }

        // ===== BAGIAN 5: pencarian, hitungan per status, filter status =====
        $disaring = $q === ''
            ? $semua
            : $semua->filter(fn ($i) => str_contains($i['cari'], $q))->values();

        $hitungStatus = $disaring->countBy('status');   // jumlah per status untuk lencana pil
        $tabel = $statusAktif === 'semua' ? $disaring : $disaring->where('status', $statusAktif)->values();

        // ===== BAGIAN 6: pagination manual atas koleksi =====
        $terakhir = max(1, (int) ceil($tabel->count() / self::PER_HALAMAN));
        $halaman = min(max(1, LengthAwarePaginator::resolveCurrentPage()), $terakhir);
        $daftar = (new LengthAwarePaginator(
            $tabel->forPage($halaman, self::PER_HALAMAN)->values(),
            $tabel->count(), self::PER_HALAMAN, $halaman,
            ['path' => $request->url()]
        ))->withQueryString();

        return view('kompen.index', [
            'pesanBlokir'   => null,
            'admin'         => $admin,
            'periode'       => $periode,            // null = semua periode
            'daftarPeriode' => $daftarPeriode,
            'daftarKelas'   => $daftarKelas,
            'daftarDosen'   => $daftarDosen,
            'kelasId'       => $kelasId,
            'dosenId'       => $dosenId,
            'statusAktif'   => $statusAktif,
            'q'             => trim((string) $request->query('q', '')),
            'kartu'         => $kartu,
            'daftar'        => $daftar,
            'totalPeriode'  => $semua->count(),
            'hitungSemua'   => $disaring->count(),
            'hitungStatus'  => $hitungStatus,
            'adaFilter'     => $kelasId || $dosenId || $q !== '' || $statusAktif !== 'semua',
        ]);
    }

    // =====================================================================
    // Alat bantu
    // =====================================================================

    /**
     * Satu baris tampilan untuk satu mahasiswa.
     * $periode terisi: jam wajib periode itu. $periode null (semua periode): jam wajib = total semua periode.
     */
    private function bangunItem(Collection $rows, array $r, ?Periode $periode, int $ambang): array
    {
        $m = $rows->first()->mahasiswa;
        $aktif = $rows->whereNull('dibatalkan_pada');

        // Dibatalkan bila SEMUA baris yang tampil dibatalkan; selain itu status dari ringkasan jam
        $status = $aktif->isEmpty() ? KompenHitung::DIBATALKAN : $r['status'];

        return [
            'nim'            => $m->nim,
            'nama'           => $m->nama_mahasiswa,
            'inisial'        => mb_strtoupper(mb_substr($m->nama_mahasiswa, 0, 1)),
            'kelas'          => $m->kelas ? $this->labelKelas($m->kelas, $periode) : ['kode' => '-', 'prodi' => null],
            'jam_wajib'      => $periode ? (int) $rows->first()->jumlah_jam : (int) $r['wajib'],
            'ket'            => $periode ? $rows->first()->keterangan : null,   // mis. "SP II"; tidak ditampilkan di semua periode
            'jumlah_periode' => $rows->count(),
            'mahasiswa_id'   => $m->id,
            // Aksi ubah/batal hanya di mode satu periode (satu baris = satu kewajiban); di semua periode lewat halaman detail
            'jumlah_id'      => ($periode && $aktif->isNotEmpty()) ? $rows->first()->id : null,
            'jam_min'        => ($periode && $aktif->isNotEmpty())
                ? max(1, KompenHitung::hitungJamMinimum($r['terlaksana'], $r['wajib'], (int) $rows->first()->jumlah_jam))
                : 1,
            'terlaksana'     => $r['terlaksana'],           // total semua periode
            'wajib_total'    => $r['wajib'],                // total semua periode (termasuk sisa terbawa)
            'sisa'           => $r['sisa'],
            'persen'         => $r['wajib'] > 0 ? min(100, (int) round($r['terlaksana'] / $r['wajib'] * 100)) : 0,
            'status'         => $status,
            'label'          => KompenHitung::label($status),
            // Tanda merah (F16): ada kewajiban aktif yang mencapai ambang
            'besar'          => $aktif->contains(fn ($b) => (int) $b->jumlah_jam >= $ambang),
            'urut'           => mb_strtolower($m->nama_mahasiswa),
            'cari'           => mb_strtolower($m->nama_mahasiswa . ' ' . $m->nim),
        ];
    }

    /**
     * Kode kelas. Satu periode: semester + huruf, mis. "4C".
     * Semua periode: semester berubah tiap periode, jadi dipakai angkatan, mis. "Angkatan 2024 C".
     */
    private function labelKelas(Kelas $k, ?Periode $periode): array
    {
        $kode = $periode
            ? SemesterHelper::hitungTampil((int) $k->angkatan, $periode) . $k->nama_kelas
            : 'Angkatan ' . $k->angkatan . ' ' . $k->nama_kelas;

        return ['kode' => $kode, 'prodi' => $k->prodi->nama_prodi ?? null];
    }
}