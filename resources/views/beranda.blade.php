@extends('layouts.app')

@section('judul', 'Beranda')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/beranda.css') }}">
@endpush

@section('konten')

{{-- ===== BAGIAN 1: Hero ===== --}}
<section class="hero">
    <div class="hero__isi kontainer">
        <div class="hero__teks">
            <h1>SIPKOM</h1>
            <h2>SISTEM PENGELOLAAN KOMPENSASI MAHASISWA</h2>
            <p>Sistem Pengelola Kompensasi Mahasiswa (SIPKOM) Jurusan Teknik Informatika Politeknik Negeri Bengkalis merupakan sebuah sistem informasi yang dirancang untuk membantu pengelolaan data dan proses kompensasi mahasiswa secara lebih efektif, efisien, dan terintegrasi. Kompensasi di lingkungan kampus biasanya diberikan kepada mahasiswa yang memiliki ketidakhadiran pada kegiatan perkuliahan, sehingga mereka harus melakukan kegiatan pengganti atau tugas tambahan sebagai bentuk tanggung jawab.</p>
            <p>Sebelum adanya sistem ini, proses pengelolaan kompensasi masih dilakukan secara manual, seperti pencatatan data kompensasi mahasiswa di kertas, serta verifikasi yang memerlukan waktu lama. Akibatnya, sering terjadi kesalahan pencatatan, bahkan kecurangan seperti meniru tanda tangan pengawas dan mengubah jam kompensasi yang tidak seharusnya. Melalui penerapan sistem ini, proses administrasi menjadi lebih cepat, pengawasan lebih mudah, dan lebih terkoordinasi.</p>
            <a href="#lokasi-kompen" class="btn btn--putih">Temukan Lokasi Kompen <span aria-hidden="true">&rsaquo;</span></a>
        </div>
        <div class="hero__gambar">
            <img src="{{ asset('images/hero.png') }}" alt="Mahasiswa di depan gedung Politeknik Negeri Bengkalis">
        </div>
    </div>
</section>

{{-- ===== BAGIAN 2: Statistik kompensasi (dari database, periode aktif) ===== --}}
<section class="bagian statistik" id="statistik">
    <div class="kontainer">
        <h2 class="judul">KOMPENSASI MAHASISWA</h2>
        <p class="subjudul">
            Perhitungan Jumlah Mahasiswa Kompen
            @if ($periode) <span class="periode">(Semester {{ ucfirst($periode->semester) }} {{ $periode->tahun_ajaran }})</span> @endif
        </p>

        <div class="statistik__grid">
            <div class="kartu-stat">
                <h3>Jumlah Mahasiswa Kompen</h3>
                <span class="kartu-stat__angka kartu-stat__angka--putih">{{ $statistik['total'] }}</span>
                <p>Mahasiswa</p>
            </div>
            <div class="kartu-stat">
                <h3>Jumlah Mahasiswa Selesai Kompen</h3>
                <span class="kartu-stat__angka kartu-stat__angka--hijau">{{ $statistik['selesai'] }}</span>
                <p>Mahasiswa</p>
            </div>
            <div class="kartu-stat">
                <h3>Jumlah Mahasiswa Belum Selesai Kompen</h3>
                <span class="kartu-stat__angka kartu-stat__angka--merah">{{ $statistik['belum'] }}</span>
                <p>Mahasiswa</p>
            </div>
        </div>
    </div>
</section>

{{-- ===== BAGIAN 3: Cek jumlah jam kompensasi (pencarian publik) ===== --}}
<section class="cek" id="cek-jam">
    <div class="cek__isi kontainer">
        <h2>CEK JUMLAH JAM KOMPENSASIMU</h2>
        {{-- [UBAH] typo "laboratotium" pada Figma diperbaiki jadi "laboratorium" --}}
        <p>Segera cek jumlah jam kompensasimu, dan laksanakan kompensasi pada laboratorium / ruangan yang tersedia.</p>

        {{-- Formulir memakai GET agar hasil bisa dibagikan lewat tautan; #hasil-cek membawa layar ke hasil --}}
        <form action="{{ route('beranda') }}#hasil-cek" method="GET" class="cek__form">
            {{-- [BARU] label pertama sesuai Figma; disembunyikan di smartphone lewat CSS --}}
            <span class="cek__label" aria-hidden="true">Nama Mahasiswa</span>

            {{-- [UBAH] urutan sesuai Figma: label, dropdown kelas, kolom cari, tombol --}}
            <label class="sr-only" for="kelas">Kelas</label>
            <select id="kelas" name="kelas">
                <option value="">Kelas</option>
                @foreach ($daftarKelas as $k)
                    <option value="{{ $k->id }}" @selected((int) request('kelas') === $k->id)>{{ $k->label }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="q">Nama atau NIM</label>
            <input type="text" id="q" name="q" value="{{ request('q') }}" maxlength="50"
                   placeholder="cari berdasarkan NIM, Nama, Kelas" autocomplete="off">

            {{-- [UBAH] tombol memakai class sendiri (cek__tombol), bukan .btn --}}
            <button type="submit" class="cek__tombol">Cari</button>
        </form>
    </div>
</section>

{{-- Hasil pencarian: hanya muncul bila pengguna mencari sesuatu --}}
@if ($hasilCari || $pesanCari)
<section class="bagian hasil" id="hasil-cek">
    <div class="kontainer">
        @if ($pesanCari)
            <p class="pesan-kosong">{{ $pesanCari }}</p>
        @endif

        @if ($hasilCari && $hasilCari->isNotEmpty())
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Nama</th><th>Kelas</th><th>Jam Wajib</th>
                            <th>Jam Terlaksana</th><th>Sisa Jam</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasilCari as $baris)
                            <tr>
                                {{-- data-label dipakai CSS agar tabel berubah jadi kartu di smartphone --}}
                                <td data-label="Nama">
                                    <strong>{{ $baris->mahasiswa->nama_mahasiswa }}</strong>
                                    <small>{{ $baris->mahasiswa->nim_samar }}</small>
                                </td>
                                <td data-label="Kelas">{{ $baris->mahasiswa->kelas->label }}</td>
                                <td data-label="Jam Wajib">{{ $baris->jumlah_jam }} jam</td>
                                <td data-label="Jam Terlaksana">{{ (int) $baris->jam_terlaksana }} jam</td>
                                <td data-label="Sisa Jam">{{ $baris->sisa_jam }} jam</td>
                                <td data-label="Status">
                                    <span class="lencana {{ $baris->selesai ? 'lencana--selesai' : 'lencana--belum' }}">
                                        {{ $baris->selesai ? 'Selesai' : 'Belum selesai' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Navigasi manual (tanpa Tailwind/Bootstrap): Sebelumnya / Berikutnya --}}
            @if ($hasilCari->hasPages())
                <div class="halaman">
                    @if ($hasilCari->onFirstPage())
                        <span class="halaman__tombol halaman__tombol--mati">&lsaquo; Sebelumnya</span>
                    @else
                        <a class="halaman__tombol" href="{{ $hasilCari->previousPageUrl() }}#hasil-cek">&lsaquo; Sebelumnya</a>
                    @endif

                    <span class="halaman__info">Halaman {{ $hasilCari->currentPage() }} dari {{ $hasilCari->lastPage() }}</span>

                    @if ($hasilCari->hasMorePages())
                        <a class="halaman__tombol" href="{{ $hasilCari->nextPageUrl() }}#hasil-cek">Berikutnya &rsaquo;</a>
                    @else
                        <span class="halaman__tombol halaman__tombol--mati">Berikutnya &rsaquo;</span>
                    @endif
                </div>
            @endif
        @endif
    </div>
</section>
@endif

{{-- ===== BAGIAN 4: Galeri kompen (dari database) ===== --}}
<section class="bagian galeri" id="galeri">
    <div class="kontainer">
        <h2 class="judul">GALERI KOMPEN</h2>

        @if ($galeri->isEmpty())
            <p class="pesan-kosong">Belum ada dokumentasi kompensasi.</p>
        @else
            <div class="galeri__grid">
                @foreach ($galeri as $g)
                    <article class="kartu-galeri">
                        <img src="{{ $g->url_dokumentasi }}" alt="Dokumentasi kompensasi di {{ $g->ruang->nama_ruang }}" loading="lazy">
                        <div class="kartu-galeri__isi">
                            <h3>{{ $g->ruang->nama_ruang }}</h3>
                            <p class="kartu-galeri__nama">{{ $g->mahasiswa->nama_mahasiswa }}</p>
                            <p class="kartu-galeri__info">{{ $g->tgl_kompen->locale('id')->translatedFormat('j F Y') }}</p>
                            <p class="kartu-galeri__info">{{ $g->jumlah_jam_pelaksanaan }} Jam Kompen</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ===== BAGIAN 5: Lokasi kompen (dari database, carousel) ===== --}}
<section class="bagian lokasi" id="lokasi-kompen">
    <div class="kontainer">
        <div class="lokasi__kepala">
            <h2 class="judul judul--kiri">LOKASI KOMPEN</h2>
            <button type="button" class="btn btn--solid btn--kecil" data-butuh-login>Lihat Semua</button>
        </div>

        @if ($lokasi->isEmpty())
            <p class="pesan-kosong">Belum ada lokasi kompensasi yang terdaftar.</p>
        @else
            <div class="carousel" data-carousel>
                <button type="button" class="carousel__panah carousel__panah--kiri" data-carousel-prev aria-label="Geser ke kiri">&lsaquo;</button>

                <div class="carousel__jalur" data-carousel-jalur>
                    @foreach ($lokasi as $r)
                        <article class="kartu-lokasi {{ $r->url_foto ? '' : 'kartu-lokasi--tanpa-foto' }}"
                                 @if ($r->url_foto) style="background-image: url('{{ $r->url_foto }}')" @endif>
                            <div class="kartu-lokasi__lapisan">
                                <span class="status {{ $r->status_tersedia ? 'status--ada' : 'status--penuh' }}">
                                    {{ $r->status_tersedia ? 'Available' : 'Tidak tersedia' }}
                                </span>

                                <div class="kartu-lokasi__bawah">
                                    <h3>{{ $r->nama_ruang }}</h3>
                                    @if (!empty($r->jenis_pekerjaan))
                                        <ul>
                                            @foreach ($r->jenis_pekerjaan as $kerja)
                                                <li>{{ $kerja }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    {{-- Mengajukan butuh login; tombol nonaktif bila ruang tidak tersedia --}}
                                    <button type="button" class="btn btn--solid btn--kecil"
                                            @if ($r->status_tersedia) data-butuh-login @else disabled @endif>
                                        Ajukan Kompen
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <button type="button" class="carousel__panah carousel__panah--kanan" data-carousel-next aria-label="Geser ke kanan">&rsaquo;</button>
            </div>
        @endif
    </div>
</section>

{{-- ===== BAGIAN 6: Konsultasi / kontak ===== --}}
<section class="konsultasi" id="konsultasi">
    <div class="konsultasi__isi kontainer">
        <div class="konsultasi__teks">
            {{-- [UBAH] judul dipecah 3 baris sesuai Figma --}}
            <h2>Butuh Konsultasi..?<br>Silahkan kontak kami<br>Kami Siap Membantu</h2>

            {{-- [UBAH] kontak memakai ikon SVG sesuai Figma --}}
            <h3 class="kontak__judul">Kontak</h3>
            <ul class="kontak">
                <li>
                    <svg class="kontak__ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    {{ config('sipkom.kontak.nama') }}
                </li>
                <li>
                    <svg class="kontak__ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    {{ config('sipkom.kontak.telepon') }}
                </li>
                <li>
                    <svg class="kontak__ikon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    {{ config('sipkom.kontak.email') }}
                </li>
            </ul>

            <h3 class="kontak__judul">Social Media</h3>
            <ul class="kontak">
                <li>
                    <svg class="kontak__ikon" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    {{ config('sipkom.kontak.instagram') }}
                </li>
            </ul>
        </div>

        <div class="konsultasi__form">
            <h3>ada pertanyaan..?</h3>

            {{-- Pesan berhasil setelah kirim --}}
            @if (session('sukses'))
                <p class="notif notif--sukses">{{ session('sukses') }}</p>
            @endif

            <form action="{{ route('konsultasi.kirim') }}" method="POST" novalidate>
                @csrf
                <label class="sr-only" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Masukkan email anda disini...">
                @error('email') <span class="galat">{{ $message }}</span> @enderror

                <label class="sr-only" for="pertanyaan">Pertanyaan</label>
                <textarea id="pertanyaan" name="pertanyaan" rows="3" placeholder="Pertanyaan Anda...">{{ old('pertanyaan') }}</textarea>
                @error('pertanyaan') <span class="galat">{{ $message }}</span> @enderror

                <button type="submit" class="btn btn--solid btn--penuh">Kirim</button>
            </form>
        </div>
    </div>
</section>

@endsection

@push('js')
    <script src="{{ asset('js/beranda.js') }}"></script>
@endpush