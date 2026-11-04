@extends('layouts.app')
@section('judul', 'Kelola Periode')

@push('css')
    {{-- Urutan wajib: kelola-akun.css dulu, master-data.css setelahnya --}}
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/master-data.css') }}">
@endpush

@section('konten')
@php
    // Label periode aktif untuk kartu ringkasan
    $labelAktif = $aktif ? $aktif->tahun_ajaran . ' ' . ucfirst($aktif->semester) : '-';
@endphp

{{-- ===== BAGIAN: Kepala halaman dan ringkasan ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Kelola Periode</h1>
        <p>Periode akademik untuk data kompensasi. Hanya satu periode yang aktif; Beranda dan import memakai periode itu.</p>
        <div class="ringkas">
            <div class="ringkas__item">
                <span class="ringkas__angka" data-ringkas="total">{{ $periode->count() }}</span>
                <span class="ringkas__label">Total Periode</span>
            </div>
            <div class="ringkas__item">
                <span class="ringkas__angka" data-ringkas="aktif">{{ $labelAktif }}</span>
                <span class="ringkas__label">Periode Aktif</span>
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
                    <input type="search" placeholder="Cari periode..." data-cari-input aria-label="Cari periode">
                </label>
            </div>
            <button type="button" class="btn btn--solid btn--tambah" data-tambah>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m-7-7h14"/></svg>
                Tambah Periode
            </button>
        </div>

        <ul class="daftar daftar--master daftar--periode" data-daftar="periode" data-url="{{ route('admin.periode.index') }}">
            @foreach($periode as $item)
                @include('admin.periode._baris', ['item' => $item, 'nomor' => $loop->iteration, 'jumlah' => $jumlah[$item->id] ?? 0])
            @endforeach
            <li class="kosong" data-kosong-awal @if($periode->isNotEmpty()) hidden @endif>Belum ada periode.</li>
        </ul>
        <div class="kosong kosong--cari" data-kosong-cari hidden>Tidak ada periode yang cocok dengan pencarian.</div>
    </div>
</section>

{{-- ===== BAGIAN: Dialog tambah / ubah ===== --}}
<dialog class="modal" data-dialog="periode" aria-labelledby="judul-dialog-periode">
    <form class="modal__isi" data-form novalidate>
        <div class="modal__kepala">
            <div>
                <h2 id="judul-dialog-periode" data-judul>Tambah Periode</h2>
                <p>Satu tahun ajaran punya satu periode ganjil dan satu genap.</p>
            </div>
            <button type="button" class="modal__tutup" data-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="modal__badan">
            <div class="galat-umum" data-galat-umum hidden></div>

            <div class="field-baris">
                <div class="field">
                    <label for="tahun_ajaran">Tahun Ajaran <span class="wajib" aria-hidden="true">*</span></label>
                    <input type="text" id="tahun_ajaran" name="tahun_ajaran" maxlength="9" autocomplete="off" placeholder="2026/2027">
                    <span class="galat" data-galat="tahun_ajaran" role="alert"></span>
                </div>
                <div class="field">
                    <label for="semester">Semester <span class="wajib" aria-hidden="true">*</span></label>
                    <select id="semester" name="semester">
                        <option value="ganjil">Ganjil</option>
                        <option value="genap">Genap</option>
                    </select>
                    <span class="galat" data-galat="semester" role="alert"></span>
                </div>
            </div>

            {{-- Kotak centang aktif; dikunci bila periode yang diubah memang sedang aktif --}}
            <div class="field field--cek">
                <label for="aktif"><input type="checkbox" id="aktif" name="aktif"> Jadikan periode aktif</label>
                <small data-info-aktif>Periode aktif sebelumnya otomatis dinonaktifkan.</small>
                <span class="galat" data-galat="aktif" role="alert"></span>
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
    <script src="{{ asset('js/admin/master-data.js') }}?v={{ filemtime(public_path('js/admin/master-data.js')) }}"></script>
    <script src="{{ asset('js/admin/periode.js') }}?v={{ filemtime(public_path('js/admin/periode.js')) }}"></script>
@endpush