<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Notifikasi dalam aplikasi untuk semua role. Setiap akun hanya bisa mengakses notifikasi miliknya. */
class NotifikasiController extends Controller
{
    // Jumlah notifikasi per halaman dan di dropdown lonceng
    private const PER_HALAMAN = 20;
    private const JUMLAH_DROPDOWN = 8;

    /** Halaman daftar notifikasi, dengan filter semua atau belum dibaca. */
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $filter = $request->query('filter') === 'belum' ? 'belum' : 'semua';

        $daftar = Notifikasi::milik($userId)
            ->when($filter === 'belum', fn ($q) => $q->belumDibaca())
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        return view('notifikasi.index', [
            'daftar'      => $daftar,
            'filter'      => $filter,
            'jumlahSemua' => Notifikasi::milik($userId)->count(),
            'jumlahBelum' => Notifikasi::milik($userId)->belumDibaca()->count(),
        ]);
    }

    /**
     * Data untuk lonceng (JSON): jumlah belum dibaca dan daftar terbaru.
     * Dengan ?tanpa_daftar=1 hanya jumlah (dipakai penyegaran badge berkala).
     */
    public function ringkas(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $jumlah = Notifikasi::milik($userId)->belumDibaca()->count();

        if ($request->boolean('tanpa_daftar')) {
            return response()->json(['jumlah' => $jumlah]);
        }

        $daftar = Notifikasi::milik($userId)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::JUMLAH_DROPDOWN)->get()
            ->map(fn (Notifikasi $n) => [
                'id'     => $n->id,
                'judul'  => $n->judul,
                'isi'    => Str::limit((string) $n->isi, 110),   // dropdown hanya menampilkan ringkasan
                'waktu'  => $n->created_at?->locale('id')->diffForHumans(),
                'dibaca' => $n->sudah_dibaca,
                'url'    => route('notifikasi.buka', $n->id),
            ])->values();

        return response()->json(['jumlah' => $jumlah, 'daftar' => $daftar]);
    }

    /** Tandai satu notifikasi dibaca. Milik orang lain atau tidak ada: 404. */
    public function dibaca(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        Notifikasi::milik($userId)->findOrFail($id)->tandaiDibaca();

        return response()->json(['jumlah' => Notifikasi::milik($userId)->belumDibaca()->count()]);
    }

    /** Tandai semua notifikasi milik akun ini dibaca. */
    public function dibacaSemua(Request $request): JsonResponse
    {
        Notifikasi::milik($request->user()->id)->belumDibaca()->update(['dibaca_pada' => now()]);

        return response()->json(['jumlah' => 0, 'pesan' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    /** Buka notifikasi: tandai dibaca lalu menuju tautannya (bila aman). */
    public function buka(Request $request, int $id): RedirectResponse
    {
        $n = Notifikasi::milik($request->user()->id)->findOrFail($id);
        $n->tandaiDibaca();

        return redirect($this->tujuanAman($n->tautan, $request));
    }

    /**
     * Tautan hanya boleh menuju path internal atau host yang sama dengan aplikasi.
     * Selain itu dialihkan ke halaman Notifikasi (mencegah redirect ke situs lain).
     */
    private function tujuanAman(?string $tautan, Request $request): string
    {
        $cadangan = route('notifikasi.index');

        if ($tautan === null || $tautan === '' || str_contains($tautan, '\\')) {
            return $cadangan;
        }

        // Path internal: diawali satu garis miring ("//host" ditolak karena berarti situs lain)
        if (str_starts_with($tautan, '/') && !str_starts_with($tautan, '//')) {
            return $tautan;
        }

        // URL lengkap: host harus sama dengan host permintaan ini atau host APP_URL
        $host = parse_url($tautan, PHP_URL_HOST);
        $boleh = [$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)];

        return ($host !== null && in_array($host, $boleh, true)) ? $tautan : $cadangan;
    }
}