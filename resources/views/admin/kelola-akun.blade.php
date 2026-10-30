@extends('layouts.app')
@section('judul', 'Kelola Akun')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
@endpush

@section('konten')
@php
    // Ringkasan di kepala halaman: jumlah akun nonaktif dari kedua daftar
    $nonaktif = $dosen->filter(fn ($d) => !$d->user->is_active)->count()
              + $pengawas->filter(fn ($p) => !$p->user->is_active)->count();
@endphp

{{-- ===== BAGIAN 1: Kepala halaman (gradasi sama dengan hero Beranda) ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Kelola Akun</h1>
        <p>Daftarkan akun dosen wali dan pengawas lapangan. Akun mahasiswa dibuat otomatis saat dosen wali mengimpor data.</p>

        <div class="ringkas">
            <div class="ringkas__item"><span class="ringkas__angka" data-ringkas="dosen">{{ $dosen->count() }}</span><span class="ringkas__label">Dosen Wali</span></div>
            <div class="ringkas__item"><span class="ringkas__angka" data-ringkas="pengawas">{{ $pengawas->count() }}</span><span class="ringkas__label">Pengawas</span></div>
            <div class="ringkas__item"><span class="ringkas__angka" data-ringkas="nonaktif">{{ $nonaktif }}</span><span class="ringkas__label">Akun nonaktif</span></div>
        </div>
    </div>
</section>

{{-- ===== BAGIAN 2: Panel daftar (naik menimpa kepala halaman) ===== --}}
<section class="kontainer kelola">
    <div class="panel">

        {{-- Bilah alat: tab, pencarian, tombol tambah --}}
        <div class="panel__bar">
            <div class="tab" role="tablist" aria-label="Jenis akun">
                <button type="button" role="tab" class="tab__btn tab__btn--aktif" id="tab-dosen"
                        data-tab="dosen" aria-selected="true" aria-controls="panel-dosen">
                    Dosen Wali <span class="tab__jumlah" data-jumlah-tab="dosen">{{ $dosen->count() }}</span>
                </button>
                <button type="button" role="tab" class="tab__btn" id="tab-pengawas"
                        data-tab="pengawas" aria-selected="false" aria-controls="panel-pengawas">
                    Pengawas <span class="tab__jumlah" data-jumlah-tab="pengawas">{{ $pengawas->count() }}</span>
                </button>
            </div>

            <div class="panel__alat">
                <label class="cari">
                    <span class="sr-only">Cari akun</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" id="cari-akun" placeholder="Cari nama atau email" autocomplete="off">
                </label>
                <button type="button" class="btn btn--solid btn--tambah" data-tambah>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah <span data-tambah-label>Dosen Wali</span>
                </button>
            </div>
        </div>

        {{-- Panel tab Dosen Wali --}}
        <div role="tabpanel" id="panel-dosen" aria-labelledby="tab-dosen" data-panel="dosen">
            <ul class="daftar">
                @foreach ($dosen as $d)
                    @include('admin.partials.baris-akun', ['tipe' => 'dosen', 'item' => $d])
                @endforeach
            </ul>
            {{-- Keadaan kosong: belum ada data sama sekali --}}
            <p class="kosong" data-kosong @if ($dosen->isNotEmpty()) hidden @endif>Belum ada dosen wali. Klik "Tambah Dosen Wali" untuk membuat akun pertama.</p>
            {{-- Keadaan kosong: ada data, tapi tidak cocok dengan pencarian --}}
            <p class="kosong" data-kosong-cari hidden>Tidak ada dosen wali yang cocok dengan pencarian.</p>
        </div>

        {{-- Panel tab Pengawas --}}
        <div role="tabpanel" id="panel-pengawas" aria-labelledby="tab-pengawas" data-panel="pengawas" hidden>
            <ul class="daftar">
                @foreach ($pengawas as $p)
                    @include('admin.partials.baris-akun', ['tipe' => 'pengawas', 'item' => $p])
                @endforeach
            </ul>
            <p class="kosong" data-kosong @if ($pengawas->isNotEmpty()) hidden @endif>Belum ada pengawas. Klik "Tambah Pengawas" untuk membuat akun pertama.</p>
            <p class="kosong" data-kosong-cari hidden>Tidak ada pengawas yang cocok dengan pencarian.</p>
        </div>
    </div>
</section>
{{-- ===== BAGIAN 3: Popup tambah / ubah akun (satu popup untuk dosen wali dan pengawas) ===== --}}
<dialog class="modal" id="modal-akun" aria-labelledby="modal-judul">
    {{-- Alamat dikirim lewat atribut; __TIPE__ dan __ID__ diganti oleh JS --}}
    <form method="POST" id="form-akun" class="modal__isi" novalidate
          data-url-simpan="{{ route('admin.akun.simpan', ['tipe' => '__TIPE__']) }}"
          data-url-ubah="{{ route('admin.akun.ubah', ['tipe' => '__TIPE__', 'id' => '__ID__']) }}">
        @csrf

        <header class="modal__kepala">
            <div>
                <h2 id="modal-judul">Tambah Dosen Wali</h2>
                <p id="modal-sub"></p>
            </div>
            <button type="button" class="modal__tutup" data-modal-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
            </button>
        </header>

        <div class="modal__badan">
            {{-- Pesan galat umum (gagal koneksi, sesi habis, dll) --}}
            <div class="galat-umum" data-galat-umum role="alert" hidden></div>

            <div class="field">
                <label for="f-nama">Nama lengkap</label>
                <input type="text" id="f-nama" name="nama" maxlength="100" autocomplete="off" autofocus>
                <span class="galat" data-galat="nama"></span>
            </div>

            <div class="field">
                <label for="f-email">Email</label>
                <input type="email" id="f-email" name="email" maxlength="50" autocomplete="off">
                <small>Dipakai sebagai username untuk login.</small>
                <span class="galat" data-galat="email"></span>
            </div>

            {{-- Hanya tampil saat tambah --}}
            <p class="info-password" data-info-password>
                Password awal: <strong>{{ config('sipkom.password_awal') }}</strong>.
                Pemilik akun wajib menggantinya saat login pertama.
            </p>
        </div>

        <footer class="modal__kaki">
            <button type="button" class="btn btn--garis" data-modal-tutup>Batal</button>
            <button type="submit" class="btn btn--solid" data-simpan>Simpan</button>
        </footer>
    </form>
</dialog>

{{-- ===== BAGIAN 4: Popup konfirmasi (nonaktifkan, reset password, hapus) ===== --}}
{{-- Alamat aksi dikirim lewat atribut; __TIPE__ dan __ID__ diganti oleh JS --}}
<dialog class="modal modal--konfirmasi" id="modal-konfirmasi" aria-labelledby="konf-judul"
        data-password-awal="{{ config('sipkom.password_awal') }}"
        data-url-toggle="{{ route('admin.akun.toggle', ['tipe' => '__TIPE__', 'id' => '__ID__']) }}"
        data-url-reset="{{ route('admin.akun.reset', ['tipe' => '__TIPE__', 'id' => '__ID__']) }}"
        data-url-hapus="{{ route('admin.akun.hapus', ['tipe' => '__TIPE__', 'id' => '__ID__']) }}">
    <div class="konf">
        {{-- Ikon peringatan; warna diatur JS (hijau = info, merah = bahaya) --}}
        <span class="konf__ikon konf__ikon--info" data-konf-ikon aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </span>
        <h2 id="konf-judul">Konfirmasi</h2>
        <p data-konf-teks></p>

        {{-- Alasan penolakan dari server (mis. hapus ditolak karena masih punya kelas) --}}
        <div class="galat-umum" data-konf-galat role="alert" hidden></div>

        <div class="konf__aksi">
            <button type="button" class="btn btn--garis" data-konf-batal>Batal</button>
            <button type="button" class="btn btn--solid" data-konf-ok>Ya</button>
        </div>
    </div>
</dialog>
@endsection

@push('js')
    <script src="{{ asset('js/kelola-akun.js') }}"></script>
@endpush