{{-- Halaman daftar notifikasi untuk semua role. Gaya lonceng dan baris ada di notifikasi.css (dimuat layout). --}}
@extends('layouts.app')

@section('judul', 'Notifikasi')

@push('css')
    {{-- kelola-akun.css: kepala, panel, pil filter, keadaan kosong --}}
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
@endpush

@section('konten')

{{-- ===== Kepala halaman ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Notifikasi</h1>
        <p>Pemberitahuan tentang kewajiban dan proses kompensasi Anda.</p>
        <div class="ringkas">
            <div class="ringkas__item"><span class="ringkas__angka" data-jumlah-belum>{{ $jumlahBelum }}</span><span class="ringkas__label">Belum dibaca</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $jumlahSemua }}</span><span class="ringkas__label">Total</span></div>
        </div>
    </div>
</section>

<section class="kelola">
    <div class="kontainer">
        <div class="panel">

            {{-- Filter (tautan biasa, jadi bisa dibagikan) dan tombol tandai semua --}}
            <div class="panel__bar">
                <div class="tab" role="group" aria-label="Filter notifikasi">
                    <a href="{{ route('notifikasi.index') }}" class="tab__btn {{ $filter === 'semua' ? 'tab__btn--aktif' : '' }}"
                       @if ($filter === 'semua') aria-current="true" @endif>Semua</a>
                    <a href="{{ route('notifikasi.index', ['filter' => 'belum']) }}" class="tab__btn {{ $filter === 'belum' ? 'tab__btn--aktif' : '' }}"
                       @if ($filter === 'belum') aria-current="true" @endif>
                        Belum dibaca <span class="tab__jumlah" data-jumlah-belum>{{ $jumlahBelum }}</span>
                    </a>
                </div>

                @if ($jumlahBelum > 0)
                    <button type="button" class="btn btn--garis btn--kecil" data-dibaca-semua
                            data-url="{{ route('notifikasi.dibaca_semua') }}">Tandai semua dibaca</button>
                @endif
            </div>

            @if ($daftar->isEmpty())
                <p class="kosong">{{ $filter === 'belum' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}</p>
            @else
                <ul class="notif-daftar">
                    @foreach ($daftar as $n)
                        <li class="notif-baris {{ $n->sudah_dibaca ? '' : 'notif-baris--baru' }}">
                            {{-- Klik: tandai dibaca lalu menuju tautan notifikasi --}}
                            <a href="{{ route('notifikasi.buka', $n->id) }}" class="notif-tautan">
                                <span class="notif-titik" aria-hidden="true"></span>
                                <span class="notif-teks">
                                    <span class="notif-judul">@unless ($n->sudah_dibaca)<span class="sr-only">Belum dibaca: </span>@endunless{{ $n->judul }}</span>
                                    @if ($n->isi)<span class="notif-isi">{{ $n->isi }}</span>@endif
                                    <time class="notif-waktu" datetime="{{ $n->created_at->toIso8601String() }}"
                                          title="{{ $n->created_at->locale('id')->translatedFormat('j F Y, H:i') }}">{{ $n->created_at->locale('id')->diffForHumans() }}</time>
                                </span>
                            </a>

                            @unless ($n->sudah_dibaca)
                                <button type="button" class="notif-tandai" data-tandai
                                        data-url="{{ route('notifikasi.dibaca', $n->id) }}">Tandai dibaca</button>
                            @endunless
                        </li>
                    @endforeach
                </ul>

                {{-- Navigasi halaman, hanya bila lebih dari satu halaman --}}
                @if ($daftar->hasPages())
                    <nav class="notif-halaman" aria-label="Halaman notifikasi">
                        @if ($daftar->onFirstPage())
                            <span class="notif-halaman__tombol notif-halaman__tombol--mati">&lsaquo; Sebelumnya</span>
                        @else
                            <a class="notif-halaman__tombol" href="{{ $daftar->previousPageUrl() }}">&lsaquo; Sebelumnya</a>
                        @endif

                        <span class="notif-halaman__info">Halaman {{ $daftar->currentPage() }} dari {{ $daftar->lastPage() }}</span>

                        @if ($daftar->hasMorePages())
                            <a class="notif-halaman__tombol" href="{{ $daftar->nextPageUrl() }}">Berikutnya &rsaquo;</a>
                        @else
                            <span class="notif-halaman__tombol notif-halaman__tombol--mati">Berikutnya &rsaquo;</span>
                        @endif
                    </nav>
                @endif
            @endif

        </div>
    </div>
</section>

@endsection