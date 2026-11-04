@extends('layouts.app')
@section('judul', 'Kelola Ruang Kompensasi')

@push('css')
    {{-- Urutan wajib: kelola-akun.css dulu, master-data.css setelahnya --}}
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/master-data.css') }}">
@endpush

@section('konten')
{{-- ===== BAGIAN: Kepala halaman dan ringkasan ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Kelola Ruang</h1>
        <p>Daftarkan ruang atau lab kompensasi dan tentukan pengawasnya. Pengawas yang membuka ruang dan mengatur kuota.</p>
        <div class="ringkas">
            <div class="ringkas__item">
                <span class="ringkas__angka" data-ringkas="total">{{ $ruang->count() }}</span>
                <span class="ringkas__label">Total Ruang</span>
            </div>
            <div class="ringkas__item">
                <span class="ringkas__angka" data-ringkas="dibuka">{{ $dibuka }}</span>
                <span class="ringkas__label">Sedang Dibuka</span>
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
                    <input type="search" placeholder="Cari ruang atau pengawas..." data-cari-input aria-label="Cari ruang atau pengawas">
                </label>
            </div>
            <button type="button" class="btn btn--solid btn--tambah" data-tambah>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m-7-7h14"/></svg>
                Tambah Ruang
            </button>
        </div>

        <ul class="daftar daftar--master" data-daftar="ruang" data-url="{{ route('admin.ruang.index') }}">
            @foreach($ruang as $item)
                @include('admin.ruang._baris', ['item' => $item, 'nomor' => $loop->iteration])
            @endforeach
            <li class="kosong" data-kosong-awal @if($ruang->isNotEmpty()) hidden @endif>Belum ada ruang.</li>
        </ul>
        <div class="kosong kosong--cari" data-kosong-cari hidden>Tidak ada ruang yang cocok dengan pencarian.</div>
    </div>
</section>

{{-- ===== BAGIAN: Dialog tambah / ubah ===== --}}
<dialog class="modal" data-dialog="ruang" aria-labelledby="judul-dialog-ruang">
    <form class="modal__isi" data-form novalidate>
        <div class="modal__kepala">
            <div>
                <h2 id="judul-dialog-ruang" data-judul>Tambah Ruang</h2>
                <p>Ruang baru berstatus Tutup sampai pengawas membukanya.</p>
            </div>
            <button type="button" class="modal__tutup" data-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="modal__badan">
            <div class="galat-umum" data-galat-umum hidden></div>

            <div class="field">
                <label for="nama_ruang">Nama Ruang <span class="wajib" aria-hidden="true">*</span></label>
                <input type="text" id="nama_ruang" name="nama_ruang" maxlength="100" autocomplete="off" placeholder="Contoh: Lab Jaringan">
                <span class="galat" data-galat="nama_ruang" role="alert"></span>
            </div>

            <div class="field">
                <label for="pengawas_id">Pengawas Utama <span class="wajib" aria-hidden="true">*</span></label>
                <select id="pengawas_id" name="pengawas_id">
                    <option value="">Pilih pengawas</option>
                    @foreach($pengawas as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_pengawas }}{{ $p->user?->is_active ? '' : ' (nonaktif)' }}</option>
                    @endforeach
                </select>
                <span class="galat" data-galat="pengawas_id" role="alert"></span>
            </div>

            <div class="field">
                <label for="pengawas_kedua_id">Pengawas Kedua <small>(opsional)</small></label>
                <select id="pengawas_kedua_id" name="pengawas_kedua_id">
                    <option value="">Tidak ada pengawas kedua</option>
                    @foreach($pengawas as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_pengawas }}{{ $p->user?->is_active ? '' : ' (nonaktif)' }}</option>
                    @endforeach
                </select>
                <small>Pengganti sementara saat pengawas utama berhalangan.</small>
                <span class="galat" data-galat="pengawas_kedua_id" role="alert"></span>
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
    <script src="{{ asset('js/admin/ruang.js') }}?v={{ filemtime(public_path('js/admin/ruang.js')) }}"></script>
@endpush