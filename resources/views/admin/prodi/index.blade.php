@extends('layouts.app')
@section('judul', 'Kelola Program Studi')

@push('css')
    {{-- Urutan wajib: kelola-akun.css dulu, master-data.css setelahnya --}}
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/master-data.css') }}">
@endpush

@section('konten')
{{-- ===== BAGIAN: Kepala halaman dan ringkasan ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Kelola Program Studi</h1>
        <p>Daftar program studi yang tersedia di SIPKOM. Dipakai saat membuat kelas dan membaca data import.</p>
        <div class="ringkas">
            <div class="ringkas__item">
                <span class="ringkas__angka" data-ringkas="total">{{ $prodi->count() }}</span>
                <span class="ringkas__label">Total Program Studi</span>
            </div>
        </div>
    </div>
</section>

{{-- ===== BAGIAN: Pencarian, tombol tambah, dan daftar ===== --}}
<section class="kontainer kelola">
    <div class="panel">
        <div class="panel__bar">
            <div class="panel__alat">
                <label class="cari">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" placeholder="Cari program studi..." data-cari-input aria-label="Cari program studi">
                </label>
            </div>
            <button type="button" class="btn btn--solid btn--tambah" data-tambah>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m-7-7h14"/></svg>
                Tambah Program Studi
            </button>
        </div>

        {{-- data-url dipakai JS sebagai alamat dasar; tidak ada alamat yang ditulis manual di JS --}}
        <ul class="daftar daftar--master daftar--ringkas" data-daftar="prodi" data-url="{{ route('admin.prodi.index') }}">
            @foreach($prodi as $item)
                @include('admin.prodi._baris', ['item' => $item, 'nomor' => $loop->iteration])
            @endforeach
            {{-- Pesan data kosong; disembunyikan bila sudah ada data --}}
            <li class="kosong" data-kosong-awal @if($prodi->isNotEmpty()) hidden @endif>Belum ada program studi.</li>
        </ul>
        <div class="kosong kosong--cari" data-kosong-cari hidden>Tidak ada program studi yang cocok dengan pencarian.</div>
    </div>
</section>

{{-- ===== BAGIAN: Dialog tambah / ubah ===== --}}
<dialog class="modal" data-dialog="prodi" aria-labelledby="judul-dialog-prodi">
    <form class="modal__isi" data-form novalidate>
        <div class="modal__kepala">
            <div>
                <h2 id="judul-dialog-prodi" data-judul>Tambah Program Studi</h2>
                <p>Tulis dengan jenjang, mis. D4 Rekayasa Perangkat Lunak.</p>
            </div>
            <button type="button" class="modal__tutup" data-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="modal__badan">
            {{-- Galat umum (jaringan, server); galat per kolom ada di bawah kolomnya --}}
            <div class="galat-umum" data-galat-umum hidden></div>

            <div class="field">
                <label for="nama_prodi">Nama Program Studi <span class="wajib" aria-hidden="true">*</span></label>
                <input type="text" id="nama_prodi" name="nama_prodi" maxlength="100" autocomplete="off" placeholder="Contoh: D3 Teknik Informatika">
                <span class="galat" data-galat="nama_prodi" role="alert"></span>
            </div>
        </div>

        <div class="modal__kaki">
            <button type="button" class="btn btn--garis" data-tutup>Batal</button>
            <button type="submit" class="btn btn--solid" data-simpan>Simpan</button>
        </div>
    </form>
</dialog>
@endsection

@push('js')
    {{-- Urutan wajib: pustaka bersama dulu, baru JS halaman --}}
    <script src="{{ asset('js/admin/master-data.js') }}?v={{ filemtime(public_path('js/admin/master-data.js')) }}"></script>
    <script src="{{ asset('js/admin/prodi.js') }}?v={{ filemtime(public_path('js/admin/prodi.js')) }}"></script>
@endpush