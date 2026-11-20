{{-- Data Kompen untuk dosen wali dan admin: ringkasan, filter, daftar mahasiswa. --}}
@extends('layouts.app')

@section('judul', 'Data Kompen')

@push('css')
        {{-- kelola-akun.css: kepala, panel, tab, baris. data-kompen.css: khusus halaman ini (sudah memuat gaya dropdown dan nomor) --}}
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/data-kompen.css') }}">
@endpush

@section('konten')

@if ($pesanBlokir)
    {{-- Halaman tidak bisa menampilkan data: tampilkan alasannya --}}
    <section class="kelola-kepala">
        <div class="kontainer">
            <h1>Data Kompensasi</h1>
            <p>Pantau jam kompensasi mahasiswa.</p>
        </div>
    </section>
    <section class="kelola">
        <div class="kontainer">
            <div class="panel">
                <p class="galat-umum" role="alert">{{ $pesanBlokir }}</p>
                <a href="{{ route('beranda') }}" class="btn btn--garis">Ke Beranda</a>
            </div>
        </div>
    </section>
@else
@php
    // Pil status: kode => label. Hitungan "semua" mengikuti pencarian, bukan filter status
    $pil = ['semua' => 'Semua', 'belum_mulai' => 'Belum Mulai', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai'];
@endphp

{{-- ===== Kepala halaman + kartu ringkasan ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Data Kompensasi</h1>
        <p>
            {{ $admin ? 'Seluruh mahasiswa yang berkewajiban kompensasi' : 'Mahasiswa perwalian Anda yang berkewajiban kompensasi' }}
            {{ $periode ? 'pada periode ' . ucfirst($periode->semester) . ' ' . $periode->tahun_ajaran : 'pada semua periode' }}.
        </p>
        <div class="ringkas">
            <div class="ringkas__item"><span class="ringkas__angka">{{ $kartu['berkompen'] }}</span><span class="ringkas__label">Mahasiswa berkompen</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $kartu['selesai'] }}</span><span class="ringkas__label">Selesai</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $kartu['belum'] }}</span><span class="ringkas__label">Belum selesai</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $kartu['sisa'] }}</span><span class="ringkas__label">Total sisa jam</span></div>
            {{-- Kartu perhatian: hanya muncul bila ada mahasiswa dengan jam sangat besar --}}
            @if ($kartu['besar'] > 0)
                <div class="ringkas__item ringkas__item--perhatian"><span class="ringkas__angka">{{ $kartu['besar'] }}</span><span class="ringkas__label">{{ config('sipkom.ambang_merah_jam', 64) }} jam atau lebih</span></div>
            @endif
        </div>
    </div>
</section>

<section class="kelola">
    <div class="kontainer">
        <div class="panel">

            {{-- Pesan dari unduh rekap (mis. tidak ada mahasiswa dengan sisa) --}}
            @error('rekap')
                <p class="galat-umum" role="alert">{{ $message }}</p>
            @enderror

            {{-- ===== Bilah atas: pil status dan tombol Import ===== --}}
            <div class="panel__bar">
                <div class="tab" role="group" aria-label="Filter status">
                    @foreach ($pil as $kode => $nama)
                        @php $jumlah = $kode === 'semua' ? $hitungSemua : ($hitungStatus[$kode] ?? 0); @endphp
                        {{-- Tautan biasa (bukan tombol): filter lewat alamat, jadi bisa dibagikan dan tombol Kembali bekerja --}}
                        <a href="{{ request()->fullUrlWithQuery(['status' => $kode === 'semua' ? null : $kode, 'page' => null]) }}"
                           class="tab__btn {{ $statusAktif === $kode ? 'tab__btn--aktif' : '' }}"
                           @if ($statusAktif === $kode) aria-current="true" @endif>
                            {{ $nama }} <span class="tab__jumlah">{{ $jumlah }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="panel__alat">
                    {{-- Rekap sisa jam (Excel): mengikuti periode, kelas, dan dosen wali yang sedang dipilih.
                         Rekap butuh satu periode, jadi nonaktif saat "Semua periode". --}}
                    @if ($periode)
                        <a href="{{ route('kompen.rekap', array_filter(['periode' => $periode->id, 'kelas' => $kelasId, 'dosen' => $dosenId])) }}"
                           class="btn btn--garis btn--tambah">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
                            Unduh Rekap
                        </a>
                    @else
                        <button type="button" class="btn btn--garis btn--tambah" disabled title="Pilih satu periode untuk mengunduh rekap">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
                            Unduh Rekap
                        </button>
                    @endif

                    <a href="{{ route('kompen.import') }}" class="btn btn--solid btn--tambah">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                        Import PDF
                    </a>
                </div>
            </div>

            {{-- ===== Bilah filter: periode, kelas, (dosen untuk admin), pencarian ===== --}}
            <form method="GET" action="{{ route('kompen.index') }}" class="saring" data-form-saring>
                {{-- Status ikut terkirim agar tidak hilang saat filter lain diganti --}}
                @if ($statusAktif !== 'semua')<input type="hidden" name="status" value="{{ $statusAktif }}">@endif

                <label class="pilih">
                    <span class="sr-only">Periode</span>
                    <select name="periode" data-otomatis>
                        <option value="semua" @selected($periode === null)>Semua periode</option>
                        @foreach ($daftarPeriode as $p)
                            <option value="{{ $p->id }}" @selected($periode !== null && $p->id === $periode->id)>
                                {{ ucfirst($p->semester) }} {{ $p->tahun_ajaran }}{{ $p->aktif ? ' (aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="pilih">
                    <span class="sr-only">Kelas</span>
                    <select name="kelas" data-otomatis>
                        <option value="">Semua kelas</option>
                        @foreach ($daftarKelas as $id => $nama)
                            <option value="{{ $id }}" @selected($kelasId === $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($admin)
                    <label class="pilih">
                        <span class="sr-only">Dosen wali</span>
                        <select name="dosen" data-otomatis>
                            <option value="">Semua dosen wali</option>
                            @foreach ($daftarDosen as $id => $nama)
                                <option value="{{ $id }}" @selected($dosenId === $id)>{{ $nama }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="cari">
                    <span class="sr-only">Cari nama atau NIM</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" value="{{ $q }}" maxlength="50" placeholder="Cari nama atau NIM" autocomplete="off">
                </label>

                {{-- Reset muncul hanya bila ada filter aktif --}}
                @if ($adaFilter)
                    <a href="{{ route('kompen.index', ['periode' => $periode?->id ?? 'semua']) }}" class="saring__reset">Reset filter</a>
                @endif
            </form>

            {{-- ===== Daftar ===== --}}
            @if ($totalPeriode === 0)
                {{-- Belum ada data sama sekali pada periode ini --}}
                <div class="kosong">
                    <p>Belum ada data kompensasi{{ $periode ? ' pada periode ini' : '' }}.</p>
                    <a href="{{ route('kompen.import') }}" class="btn btn--solid btn--kecil">Import dari PDF SIAKAD</a>
                </div>
            @elseif ($daftar->isEmpty())
                {{-- Ada data, tetapi tersaring habis --}}
                <p class="kosong kosong--cari">Tidak ada mahasiswa yang cocok dengan filter ini.</p>
            @else
                <div class="kompen-tabel">
                    {{-- Judul kolom (hanya di layar lebar) --}}
                    <div class="daftar-kepala" aria-hidden="true">
                         <span>No</span><span>Mahasiswa</span><span>Kelas</span><span>Jam wajib</span><span>Progres</span><span>Status</span><span></span>
                    </div>

                    <ul class="daftar daftar--kompen">
                        @foreach ($daftar as $i)
                            <li class="baris" data-baris>
                                <span class="nomor">{{ $daftar->firstItem() + $loop->index }}</span>

                                {{-- Identitas: lingkaran inisial, nama, NIM --}}
                                <div class="identitas">
                                    <span class="avatar" aria-hidden="true">{{ $i['inisial'] }}</span>
                                    <div class="identitas__teks">
                                           <a href="{{ route('kompen.mahasiswa', $i['mahasiswa_id']) }}" class="identitas__nama identitas__tautan">{{ $i['nama'] }}</a>
                                        <span class="identitas__email">{{ $i['nim'] }}</span>
                                    </div>
                                </div>

                                <div class="meta kol-kelas">
                                    <span class="lbl">Kelas</span>
                                    <strong>{{ $i['kelas']['kode'] }}</strong>
                                    @if ($i['kelas']['prodi'])<span class="meta__sub">{{ $i['kelas']['prodi'] }}</span>@endif
                                </div>

                                <div class="kol-jam">
                                    <span class="lbl">Jam wajib</span>
                                    <strong>{{ $i['jam_wajib'] }} jam</strong>
                                    @if ($i['ket'])<small>{{ $i['ket'] }}</small>@endif
                                    {{-- Mode semua periode: jam wajib adalah total beberapa periode --}}
                                    @if ($i['jumlah_periode'] > 1)<small>total {{ $i['jumlah_periode'] }} periode</small>@endif
                                    @if ($i['besar'])<span class="lencana-besar">{{ config('sipkom.ambang_merah_jam', 64) }} jam+</span>@endif
                                </div>

                                {{-- Progres: bar + teks. Dibatalkan atau tanpa kewajiban tidak punya progres --}}
                                <div class="kol-progres">
                                    @if (in_array($i['status'], ['dibatalkan', 'tidak_ada'], true))
                                        <span class="progres__kosong">-</span>
                                    @else
                                        <div class="progres" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                                             aria-valuenow="{{ $i['persen'] }}" aria-label="Progres kompensasi {{ $i['nama'] }}">
                                            <span class="progres__isi {{ $i['status'] === 'selesai' ? 'progres__isi--selesai' : '' }}"
                                                  style="width: {{ $i['persen'] }}%"></span>
                                        </div>
                                        <small>{{ $i['terlaksana'] }} dari {{ $i['wajib_total'] }} jam, sisa {{ $i['sisa'] }}</small>
                                    @endif
                                </div>

                                 <span class="lencana-kompen lencana-kompen--{{ $i['status'] }}">{{ $i['label'] }}</span>

                                {{-- Tindakan: detail untuk semua baris; ubah dan batalkan hanya bila ada satu kewajiban yang berlaku --}}
                                <div class="tindakan">
                                    <a href="{{ route('kompen.mahasiswa', $i['mahasiswa_id']) }}" class="aksi-ikon"
                                       aria-label="Detail progres {{ $i['nama'] }}" title="Detail progres">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @if ($i['jumlah_id'])
                                        @include('kompen._aksi', ['id' => $i['jumlah_id'], 'nama' => $i['nama'], 'nim' => $i['nim'], 'jam' => $i['jam_wajib'], 'min' => $i['jam_min'], 'terlaksana' => $i['terlaksana']])
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Navigasi halaman, hanya bila lebih dari satu halaman --}}
                @if ($daftar->hasPages())
                    <nav class="halaman" aria-label="Halaman daftar">
                        @if ($daftar->onFirstPage())
                            <span class="halaman__tombol halaman__tombol--mati">&lsaquo; Sebelumnya</span>
                        @else
                            <a class="halaman__tombol" href="{{ $daftar->previousPageUrl() }}">&lsaquo; Sebelumnya</a>
                        @endif

                        <span class="halaman__info">Halaman {{ $daftar->currentPage() }} dari {{ $daftar->lastPage() }}</span>

                        @if ($daftar->hasMorePages())
                            <a class="halaman__tombol" href="{{ $daftar->nextPageUrl() }}">Berikutnya &rsaquo;</a>
                        @else
                            <span class="halaman__tombol halaman__tombol--mati">Berikutnya &rsaquo;</span>
                        @endif
                    </nav>
                @endif
            @endif

        </div>
    </div>
</section>

{{-- Popup ubah jam dan batalkan kewajiban (dipakai tombol di tiap baris) --}}
@include('kompen._dialog')
@endif

@endsection

@push('js')
    <script src="{{ asset('js/data-kompen.js') }}"></script>
@endpush