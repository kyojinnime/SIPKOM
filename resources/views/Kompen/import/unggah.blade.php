{{-- Import Data Kompensasi, langkah 1 dari 3: unggah PDF. Untuk dosen wali dan admin. --}}
@extends('layouts.app')

@section('judul', 'Import Data Kompensasi')

@push('css')
    {{-- kelola-akun.css: kepala hijau, panel, kolom isian, kotak galat. kompen-import.css: khusus import --}}
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/kompen-import.css') }}">
@endpush

@section('konten')

{{-- ===== Kepala halaman ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Import Data Kompensasi</h1>
        <p>Unggah PDF Laporan Evaluasi Perkuliahan dari SIAKAD. Sistem membaca jam alpa tiap mahasiswa, lalu Anda memeriksanya sebelum disimpan.</p>
    </div>
</section>

<section class="kelola">
    <div class="kontainer">
        <div class="panel impor">

            @include('kompen.import._langkah', ['aktif' => 1])

            <div class="impor__isi">

                @if ($pesanBlokir)
                    {{-- Form tidak boleh tampil: tampilkan alasannya --}}
                    <p class="galat-umum" role="alert">{{ $pesanBlokir }}</p>
                    <a href="{{ route('kompen.index') }}" class="btn btn--garis">Kembali</a>
                @else
                    {{-- Petunjuk singkat; isinya berbeda untuk admin dan dosen wali --}}
                    <div class="info-password">
                        @if ($admin)
                            Sebagai admin, Anda bisa mengimpor file dari dosen wali mana pun dan memilih periode lain untuk data lama.
                        @else
                            Impor memakai periode yang sedang aktif.
                        @endif
                        Hanya kolom Alpa (A) yang dibaca. Sakit dan Izin diabaikan.
                    </div>

                    <form method="POST" action="{{ route('kompen.import.pratinjau') }}" enctype="multipart/form-data"
                          data-form-impor novalidate>
                        @csrf

                        {{-- Pilihan periode --}}
                        <div class="field {{ $errors->has('periode_id') ? 'field--galat' : '' }}">
                            <label for="periode_id">Periode</label>
                            <select id="periode_id" name="periode_id">
                                @foreach ($daftarPeriode as $p)
                                    <option value="{{ $p->id }}" @selected((int) $periodeDipilih === $p->id)>
                                        {{ ucfirst($p->semester) }} {{ $p->tahun_ajaran }}{{ $p->aktif ? ' (aktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="galat" data-galat="periode_id" role="alert">@error('periode_id'){{ $message }}@enderror</span>
                        </div>

                        {{-- Area unggah: input file transparan menutupi seluruh kotak, jadi klik dan seret-lepas bekerja tanpa skrip --}}
                        <div class="field {{ $errors->has('berkas') ? 'field--galat' : '' }}">
                            <label for="berkas">File PDF <span class="wajib">*</span></label>

                            <div class="unggah" data-zona>
                                <svg class="unggah__ikon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                                    <path d="M14 3v5h5M12 17v-6m0 0-2.5 2.5M12 11l2.5 2.5"/>
                                </svg>
                                <span class="unggah__judul" data-nama-berkas>Pilih file atau seret ke sini</span>
                                <span class="unggah__info" data-info-berkas>PDF, maksimal {{ $maksMb }} MB</span>
                                <input type="file" id="berkas" name="berkas" accept=".pdf,application/pdf"
                                       data-maks-mb="{{ $maksMb }}">
                            </div>

                            <span class="galat" data-galat="berkas" role="alert">@error('berkas'){{ $message }}@enderror</span>
                        </div>

                        <div class="impor__aksi">
                            <a href="{{ route('kompen.index') }}" class="btn btn--garis">Batal</a>
                            <button type="submit" class="btn btn--solid" data-kirim>Baca File</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection

@push('js')
    <script src="{{ asset('js/kompen-import.js') }}"></script>
@endpush