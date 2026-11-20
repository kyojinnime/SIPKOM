{{-- Import Data Kompensasi, langkah 3 dari 3: ringkasan hasil simpan. --}}
@extends('layouts.app')

@section('judul', 'Import Selesai')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/kompen-import.css') }}">
@endpush

@section('konten')
@php
    // Total seluruh file dan teks tampilan
    $t = $ringkasan['total'];
    $labelBlok = ['disimpan' => 'Disimpan', 'dilewati' => 'Dilewati', 'ditolak' => 'Ditolak', 'kosong' => 'Tidak ada data'];
    $warnaBlok = ['disimpan' => 'hijau', 'dilewati' => 'kuning', 'ditolak' => 'merah', 'kosong' => 'netral'];
@endphp

{{-- ===== Kepala halaman + ringkasan ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Import Selesai</h1>
        <p>{{ $t['disimpan'] > 0 ? 'Data kompensasi berhasil disimpan.' : 'Tidak ada data yang tersimpan dari file ini.' }}</p>
        <div class="ringkas">
            <div class="ringkas__item"><span class="ringkas__angka">{{ $t['disimpan'] }}</span><span class="ringkas__label">Baris tersimpan</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $t['ditolak'] }}</span><span class="ringkas__label">Ditolak</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $t['jam'] }}</span><span class="ringkas__label">Total jam</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $t['akun_baru'] }}</span><span class="ringkas__label">Akun baru</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $t['notifikasi'] }}</span><span class="ringkas__label">Notifikasi terkirim</span></div>
        </div>
    </div>
</section>

<section class="kelola">
    <div class="kontainer">
        <div class="panel impor impor--lebar">

            @include('kompen.import._langkah', ['aktif' => 3])

            <div class="impor__isi">

                <p class="berkas-info">
                    <strong>{{ $ringkasan['nama_berkas'] }}</strong>
                    <span>Periode {{ $ringkasan['periode_label'] }}</span>
                    <span>Dicetak {{ $ringkasan['dicetak_teks'] ?? 'tidak terbaca' }}</span>
                    <span>Disimpan {{ $ringkasan['waktu'] }}</span>
                </p>

                @if ($t['disimpan'] === 0)
                    <p class="peringatan" role="alert">Tidak ada baris yang tersimpan. Lihat alasan pada tiap kelas di bawah.</p>
                @endif

                {{-- Hasil per blok kelas --}}
                @foreach ($ringkasan['blok'] as $b)
                    <article class="blok">
                        <header class="blok__kepala">
                            <div>
                                <h2>{{ $b['label'] }}</h2>
                                @if ($b['status'] === 'disimpan')
                                    <p>{{ $b['disimpan'] }} baris tersimpan, total {{ $b['jam'] }} jam{{ $b['ditolak'] ? ", {$b['ditolak']} ditolak" : '' }}</p>
                                @endif
                            </div>
                            <span class="lencana-pt lencana-pt--{{ $warnaBlok[$b['status']] }}">{{ $labelBlok[$b['status']] }}</span>
                        </header>

                        @if ($b['alasan'])
                            <p class="{{ $b['status'] === 'kosong' ? 'info-password' : 'peringatan' }}">{{ $b['alasan'] }}</p>
                        @endif

                        {{-- Rincian: hanya angka yang lebih dari 0 --}}
                        @if ($b['status'] === 'disimpan')
                            <ul class="hasil-rinci">
                                @if ($b['kelas_baru'])<li><strong>Kelas baru</strong> dibuat</li>@endif
                                @if ($b['mhs_baru'])<li><strong>{{ $b['mhs_baru'] }}</strong> mahasiswa baru</li>@endif
                                @if ($b['akun_baru'])<li><strong>{{ $b['akun_baru'] }}</strong> akun baru</li>@endif
                                @if ($b['baru'])<li><strong>{{ $b['baru'] }}</strong> kewajiban baru</li>@endif
                                @if ($b['diubah'])<li><strong>{{ $b['diubah'] }}</strong> jam diubah</li>@endif
                                @if ($b['diaktifkan'])<li><strong>{{ $b['diaktifkan'] }}</strong> diaktifkan kembali</li>@endif
                                @if ($b['tidak_berubah'])<li><strong>{{ $b['tidak_berubah'] }}</strong> tidak berubah</li>@endif
                                @if ($b['dinolkan'])<li><strong>{{ $b['dinolkan'] }}</strong> jam diturunkan</li>@endif
                            </ul>
                        @endif
                    </article>
                @endforeach

                {{-- Unduh daftar baris yang tidak disimpan --}}
                @if (!empty($ringkasan['galat']))
                    <div class="galat-unduh">
                        <p><strong>{{ count($ringkasan['galat']) }} baris tidak disimpan.</strong> Unduh daftarnya beserta alasan untuk diperbaiki.</p>
                        <a href="{{ route('kompen.import.galat', $token) }}" class="btn btn--bahaya">Unduh Daftar (CSV)</a>
                    </div>
                @endif

                <div class="impor__aksi">
                    <a href="{{ route('kompen.import') }}" class="btn btn--garis">Import Lagi</a>
                    <a href="{{ route('kompen.index') }}" class="btn btn--solid">Ke Data Kompen</a>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection