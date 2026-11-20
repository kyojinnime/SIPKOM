{{-- Import Data Kompensasi, langkah 2 dari 3: pratinjau. Belum menyimpan apa pun. --}}
@extends('layouts.app')

@section('judul', 'Pratinjau Import')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/kompen-import.css') }}">
@endpush

@section('konten')
@php
    // Ringkasan seluruh file dan teks tampilan
    $rg = $rencana['ringkasan'];
    $labelStatus = ['hijau' => 'Siap', 'kuning' => 'Peringatan', 'merah' => 'Ditolak'];
    $labelAksi = ['baru' => 'Baru', 'ubah' => 'Ubah jam', 'sama' => 'Tidak berubah', 'aktifkan' => 'Aktifkan kembali', 'dilewati' => 'Dilewati'];
@endphp

{{-- ===== Kepala halaman + ringkasan ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <h1>Pratinjau Import</h1>
        <p>Periksa hasil bacaan sebelum disimpan. Baris merah tidak akan disimpan.</p>
        <div class="ringkas">
            <div class="ringkas__item"><span class="ringkas__angka">{{ $rg['siap'] }}</span><span class="ringkas__label">Siap disimpan</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $rg['merah'] }}</span><span class="ringkas__label">Ditolak</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $rg['jam'] }}</span><span class="ringkas__label">Total jam</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $rg['kelas_baru'] }}</span><span class="ringkas__label">Kelas baru</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $rg['akun_baru'] }}</span><span class="ringkas__label">Akun baru</span></div>
        </div>
    </div>
</section>

<section class="kelola">
    <div class="kontainer">
        <div class="panel impor impor--lebar">

            @include('kompen.import._langkah', ['aktif' => 2])

            <div class="impor__isi">

                {{-- Identitas file --}}
                <p class="berkas-info">
                    <strong>{{ $rencana['nama_berkas'] }}</strong>
                    <span>Periode {{ $rencana['periode_label'] }}</span>
                    <span>Dicetak {{ $rencana['dicetak_teks'] ?? 'tidak terbaca' }}</span>
                </p>

                {{-- Peringatan untuk seluruh file --}}
                @foreach ($rencana['peringatan_global'] as $p)
                    <p class="peringatan" role="alert">{{ $p }}</p>
                @endforeach

                @if ($rg['baris'] === 0)
                    <p class="kosong">Tidak ada mahasiswa dengan jam alpa pada file ini, jadi tidak ada yang bisa disimpan.</p>
                @else
                    {{-- Filter baris: kontrol pil yang sama dengan tab Kelola Akun --}}
                    <div class="tab" role="tablist" aria-label="Filter baris">
                        <button type="button" class="tab__btn tab__btn--aktif" data-filter="semua" role="tab" aria-selected="true">
                            Semua <span class="tab__jumlah">{{ $rg['baris'] }}</span>
                        </button>
                        <button type="button" class="tab__btn" data-filter="peringatan" role="tab" aria-selected="false">
                            Peringatan <span class="tab__jumlah">{{ $rg['kuning'] }}</span>
                        </button>
                        <button type="button" class="tab__btn" data-filter="ditolak" role="tab" aria-selected="false">
                            Ditolak <span class="tab__jumlah">{{ $rg['merah'] }}</span>
                        </button>
                    </div>
                @endif

                {{-- Galat dari proses simpan (gagal, sedang diproses, periode tidak aktif) --}}
                @error('simpan')
                    <p class="galat-umum" role="alert">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('kompen.import.simpan', $token) }}" data-form-simpan>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    @foreach ($rencana['blok'] as $i => $b)
                        <article class="blok" data-blok>

                            {{-- Kepala blok: identitas kelas --}}
                            <header class="blok__kepala">
                                <div>
                                    <h2>{{ $b['prodi_pdf'] ?: 'Program studi tidak terbaca' }} | Semester {{ $b['semester'] ?? '-' }} | Kelas {{ $b['kelas'] ?? '-' }}</h2>
                                    <p>DPA: {{ $b['dpa'] ?: '-' }} | {{ $b['dibaca'] }} mahasiswa dibaca, {{ count($b['baris']) }} beralpa</p>
                                </div>
                                @if (!empty($b['baris']) && empty($b['galat']))
                                    <span class="lencana-pt {{ $b['kelas_baru'] ? 'lencana-pt--kuning' : 'lencana-pt--hijau' }}">
                                        {{ $b['kelas_baru'] ? 'Kelas baru' : 'Kelas sudah ada' }}
                                    </span>
                                @endif
                            </header>

                            {{-- Pesan tingkat blok --}}
                            @foreach ($b['galat'] as $g)
                                <p class="galat-umum" role="alert">{{ $g }}</p>
                            @endforeach
                            @foreach ($b['peringatan'] as $p)
                                <p class="peringatan" role="alert">{{ $p }}</p>
                            @endforeach
                            @foreach ($b['info'] as $info)
                                <p class="info-password">{{ $info }}</p>
                            @endforeach

                            {{-- Admin: izin menimpa file yang lebih lama dari import terakhir --}}
                            @if ($b['butuh_timpa'])
                                <label class="cek-baris">
                                    <input type="checkbox" name="timpa[]" value="{{ $i }}">
                                    <span>Timpa data kelas ini dengan file yang lebih lama.</span>
                                </label>
                            @endif

                            {{-- Tabel baris mahasiswa --}}
                            @if (!empty($b['baris']))
                                <div class="pt-bungkus">
                                    <table class="pt">
                                        <thead>
                                            <tr><th>NIM</th><th>Nama</th><th>Jam alpa</th><th>Keterangan</th><th>Tindakan</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($b['baris'] as $r)
                                                <tr class="pt__baris pt__baris--{{ $r['status'] }}" data-status="{{ $r['status'] }}">
                                                    <td data-label="NIM">{{ $r['nim'] }}</td>
                                                    <td data-label="Nama"><strong>{{ $r['nama'] }}</strong></td>
                                                    <td data-label="Jam alpa">{{ $r['a'] }} jam</td>
                                                    <td data-label="Keterangan">{{ $r['sp_label'] ?? '-' }}</td>
                                                    <td data-label="Tindakan">
                                                        <span>
                                                            {{ $labelAksi[$r['aksi']] ?? $r['aksi'] }}@if ($r['aksi'] === 'ubah') ({{ $r['jam_lama'] }} &rarr; {{ $r['a'] }})@endif
                                                        </span>
                                                        @if ($r['status'] !== 'merah')
                                                            @if ($r['mhs_baru'])<small>Mahasiswa baru</small>@endif
                                                            @if ($r['akun_baru'])<small>Akun baru</small>@endif
                                                        @endif
                                                    </td>
                                                    <td data-label="Status">
                                                        <span class="lencana-pt lencana-pt--{{ $r['status'] }}">{{ $labelStatus[$r['status']] }}</span>
                                                        @foreach ($r['pesan'] as $ps)
                                                            <small class="pt__pesan">{{ $ps }}</small>
                                                        @endforeach
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="kosong kosong--cari" data-kosong-filter hidden>Tidak ada baris untuk filter ini.</p>
                            @endif

                            {{-- Mahasiswa yang hilang pada file ini: kotak konfirmasi untuk menjadikan jam wajib 0 --}}
                            @if (!empty($b['hilang']))
                                <div class="hilang">
                                    <h3>Tidak ada nilai alpa di file ini</h3>
                                    <p>Mahasiswa berikut punya jam kompensasi dari import sebelumnya, tetapi tidak punya nilai alpa di file ini.
                                       Centang untuk mengubah jam wajibnya. Tanpa centang, jam lama dibiarkan.</p>
                                    @foreach ($b['hilang'] as $h)
                                        <label class="cek-baris">
                                            <input type="checkbox" name="nolkan[]" value="{{ $h['jumlah_kompen_id'] }}">
                                            <span>
                                                {{ $h['nim'] }} {{ $h['nama'] }}: {{ $h['jam_lama'] }} jam &rarr; {{ $h['jam_setelah'] }} jam
                                                @if ($h['jam_setelah'] > 0) <small>(sudah terlaksana {{ $h['jam_setelah'] }} jam)</small> @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach

                    <div class="impor__aksi">
                        <a href="{{ route('kompen.import') }}" class="btn btn--garis">Unggah Ulang</a>
                        {{-- Nonaktif hanya bila tidak ada yang bisa disimpan sama sekali --}}
                        <button type="submit" class="btn btn--solid" data-simpan
                                @if ($rg['siap'] === 0 && $rg['hilang'] === 0) disabled data-mati @endif>Simpan Data</button>                    </div>
                </form>

            </div>
        </div>
    </div>
</section>

@endsection

@push('js')
    <script src="{{ asset('js/kompen-pratinjau.js') }}"></script>
@endpush