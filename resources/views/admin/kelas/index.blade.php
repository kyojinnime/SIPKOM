@extends('layouts.app')
@section('judul', 'Kelola Kelas')

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
        <h1>Kelola Kelas</h1>
        <p>Satu kelas tetap sama dari masuk sampai lulus. Nama semester (mis. 4C) berubah otomatis mengikuti periode aktif.</p>
        <div class="ringkas">
            <div class="ringkas__item">
                <span class="ringkas__angka" data-ringkas="aktif">{{ $hitung['aktif'] }}</span>
                <span class="ringkas__label">Kelas Aktif</span>
            </div>
            <div class="ringkas__item">
                <span class="ringkas__angka" data-ringkas="tanpa-dosen">{{ $hitung['tanpaDosen'] }}</span>
                <span class="ringkas__label">Belum Ada Dosen Wali</span>
            </div>
            <div class="ringkas__item">
                <span class="ringkas__angka">{{ $labelAktif }}</span>
                <span class="ringkas__label">Periode Aktif</span>
            </div>
        </div>
    </div>
</section>

{{-- ===== BAGIAN: Tab, pencarian, filter, tombol tambah, dan daftar ===== --}}
<section class="kontainer kelola">
    <div class="panel">
        <div class="panel__bar">
            <div class="panel__alat">
                {{-- Tab pil: kelas aktif dan kelas yang sudah lulus --}}
                <div class="tab" role="tablist">
                    <button type="button" class="tab__btn tab__btn--aktif" role="tab" aria-selected="true" data-tab="aktif">
                        Aktif <span class="tab__jumlah" data-tab-jumlah="aktif">{{ $hitung['aktif'] }}</span>
                    </button>
                    <button type="button" class="tab__btn" role="tab" aria-selected="false" data-tab="lulus">
                        Lulus <span class="tab__jumlah" data-tab-jumlah="lulus">{{ $hitung['lulus'] }}</span>
                    </button>
                </div>
                <label class="cari">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" placeholder="Cari kelas atau dosen wali..." data-cari-input aria-label="Cari kelas atau dosen wali">
                </label>
                <label class="pilih">
                    <select data-filter-prodi aria-label="Filter program studi">
                        <option value="">Semua prodi</option>
                        @foreach($prodi as $p)
                            <option value="{{ $p->id }}">{{ $p->nama_prodi }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <button type="button" class="btn btn--solid btn--tambah" data-tambah
                    @if(!$aktif) disabled title="Aktifkan satu periode dulu di menu Kelola Periode" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m-7-7h14"/></svg>
                Tambah Kelas
            </button>
        </div>

        @unless($aktif)
            <p class="info-password">Belum ada periode aktif. Aktifkan satu periode di menu Kelola Periode agar semester dan dosen wali bisa dihitung.</p>
        @endunless

        {{-- data-tahun-mulai dan data-indeks dipakai JS untuk pratinjau semester di dialog --}}
        <ul class="daftar daftar--master" data-daftar="kelas" data-url="{{ route('admin.kelas.index') }}"
            data-tahun-mulai="{{ $aktif?->tahunMulai() }}" data-indeks="{{ $aktif?->getSemesterIndex() }}">
            @foreach($kelas as $item)
                @include('admin.kelas._baris', ['item' => $item, 'nomor' => $loop->iteration])
            @endforeach
            <li class="kosong" data-kosong-awal @if($kelas->isNotEmpty()) hidden @endif>Belum ada kelas.</li>
        </ul>
        <div class="kosong kosong--cari" data-kosong-cari hidden>Tidak ada kelas yang cocok.</div>
    </div>
</section>

{{-- ===== BAGIAN: Dialog tambah / ubah ===== --}}
<dialog class="modal modal--lebar" data-dialog="kelas" aria-labelledby="judul-dialog-kelas">
    <form class="modal__isi" data-form novalidate>
        <div class="modal__kepala">
            <div>
                <h2 id="judul-dialog-kelas" data-judul>Tambah Kelas</h2>
                <p>Nama semester tidak perlu diisi, dihitung otomatis.</p>
            </div>
            <button type="button" class="modal__tutup" data-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="modal__badan">
            <div class="galat-umum" data-galat-umum hidden></div>
            <p class="info-password" data-info-kunci hidden></p>

            <div class="field">
                <label for="prodi_id">Program Studi <span class="wajib" aria-hidden="true">*</span></label>
                <select id="prodi_id" name="prodi_id">
                    <option value="">Pilih program studi</option>
                    @foreach($prodi as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_prodi }}</option>
                    @endforeach
                </select>
                <span class="galat" data-galat="prodi_id" role="alert"></span>
            </div>

            <div class="field-baris">
                <div class="field">
                    <label for="angkatan">Angkatan <span class="wajib" aria-hidden="true">*</span></label>
                    <input type="number" id="angkatan" name="angkatan" min="2000" inputmode="numeric" placeholder="2024">
                    <span class="galat" data-galat="angkatan" role="alert"></span>
                </div>
                <div class="field">
                    <label for="nama_kelas">Huruf Kelas <span class="wajib" aria-hidden="true">*</span></label>
                    <input type="text" id="nama_kelas" name="nama_kelas" maxlength="10" autocomplete="off" placeholder="A" style="text-transform:uppercase">
                    <span class="galat" data-galat="nama_kelas" role="alert"></span>
                </div>
            </div>
            {{-- Pratinjau semester dari angkatan dan periode aktif --}}
            <small class="pratinjau" data-pratinjau></small>

            <div class="field">
                <label for="dosen_wali_id">Dosen Wali (periode aktif)</label>
                <select id="dosen_wali_id" name="dosen_wali_id">
                    <option value="">Belum ada dosen wali</option>
                    @foreach($dosen as $d)
                        <option value="{{ $d->id }}">{{ $d->nama_dosen }}{{ $d->user?->is_active ? '' : ' (nonaktif)' }}</option>
                    @endforeach
                </select>
                <small>Saat periode berikutnya diaktifkan, dosen wali disalin otomatis.</small>
                <span class="galat" data-galat="dosen_wali_id" role="alert"></span>
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
    <script src="{{ asset('js/admin/kelas.js') }}?v={{ filemtime(public_path('js/admin/kelas.js')) }}"></script>
@endpush