{{-- Detail progres satu mahasiswa: ringkasan, kewajiban per periode, riwayat kompensasi. --}}
@extends('layouts.app')

@section('judul', 'Detail ' . $mahasiswa->nama_mahasiswa)

@push('css')
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/data-kompen.css') }}">
@endpush

@section('konten')

{{-- ===== Kepala: identitas dan ringkasan ===== --}}
<section class="kelola-kepala">
    <div class="kontainer">
        <a href="{{ route('kompen.index') }}" class="kembali">&lsaquo; Data Kompen</a>
        <h1>{{ $mahasiswa->nama_mahasiswa }}</h1>
        <p>NIM {{ $mahasiswa->nim }} | {{ $mahasiswa->kelas?->label ?? 'Kelas tidak tercatat' }}</p>
        <div class="ringkas">
            <div class="ringkas__item"><span class="ringkas__angka">{{ $ring['wajib'] }}</span><span class="ringkas__label">Jam wajib (semua periode)</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $ring['terlaksana'] }}</span><span class="ringkas__label">Jam terlaksana</span></div>
            <div class="ringkas__item"><span class="ringkas__angka">{{ $ring['sisa'] }}</span><span class="ringkas__label">Sisa jam</span></div>
            <div class="ringkas__item"><span class="ringkas__angka ringkas__angka--teks">{{ $ring['label'] }}</span><span class="ringkas__label">Status</span></div>
        </div>
    </div>
</section>

<section class="kelola">
    <div class="kontainer">
        <div class="panel detail">

            {{-- ===== Progres ===== --}}
            <div class="detail__bar">
                <h2>Progres</h2>
                {{-- Data selalu diambil dari database saat halaman dibuka; tombol ini memuat ulang --}}
                <a href="{{ route('kompen.mahasiswa', $mahasiswa->id) }}" class="btn btn--garis btn--kecil">Segarkan</a>
            </div>

            @if (in_array($ring['status'], ['dibatalkan', 'tidak_ada'], true))
                <p class="kosong kosong--cari">
                    {{ $ring['status'] === 'dibatalkan' ? 'Semua kewajiban kompensasi mahasiswa ini dibatalkan.' : 'Mahasiswa ini tidak punya kewajiban kompensasi.' }}
                </p>
            @else
                <div class="progres progres--besar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                     aria-valuenow="{{ $persen }}" aria-label="Progres kompensasi">
                    <span class="progres__isi {{ $ring['status'] === 'selesai' ? 'progres__isi--selesai' : '' }}" style="width: {{ $persen }}%"></span>
                </div>
                <p class="detail__teks">{{ $ring['terlaksana'] }} dari {{ $ring['wajib'] }} jam terlaksana ({{ $persen }}%), sisa {{ $ring['sisa'] }} jam.</p>
            @endif

            {{-- ===== Akun login mahasiswa ===== --}}
            <h2 class="detail__judul">Akun login</h2>
            @if ($mahasiswa->user)
                <div class="akun-baris">
                    <div class="akun-baris__info">
                        <strong>Username: {{ $mahasiswa->user->username }}</strong>
                        <span>{{ $mahasiswa->user->must_change_password ? 'Masih memakai password awal.' : 'Password sudah pernah diganti oleh mahasiswa.' }}</span>
                    </div>
                    <button type="button" class="btn btn--garis btn--kecil" data-aksi="reset"
                            data-id="{{ $mahasiswa->id }}" data-nama="{{ $mahasiswa->nama_mahasiswa }}"
                            data-nim="{{ $mahasiswa->nim }}" data-awal="{{ config('sipkom.password_awal') }}">
                        Reset Password
                    </button>
                </div>
            @else
                <p class="kosong kosong--cari">Mahasiswa ini belum punya akun login. Akun dibuat otomatis saat import PDF.</p>
            @endif

            {{-- ===== Kewajiban per periode ===== --}}
            <h2 class="detail__judul">Kewajiban per periode</h2>
            @if ($kewajiban->isEmpty())
                <p class="kosong kosong--cari">Belum ada kewajiban kompensasi.</p>
            @else
                <ul class="periode-daftar">
                    @foreach ($kewajiban as $k)
                        <li class="periode-baris {{ $k['batal'] ? 'periode-baris--batal' : '' }}">
                            <div class="periode-baris__info">
                                <strong>{{ $k['periode'] }}</strong>
                                <span>{{ $k['jam'] }} jam wajib{{ $k['ket'] ? ' (' . $k['ket'] . ')' : '' }}, terlaksana {{ $k['terlaksana'] }} jam</span>
                                @if ($k['batal'])
                                    <small>Dibatalkan {{ $k['tgl_batal'] }}: {{ $k['alasan'] }}</small>
                                @endif
                            </div>
                            <span class="lencana-kompen lencana-kompen--{{ $k['batal'] ? 'dibatalkan' : 'berjalan' }}">{{ $k['batal'] ? 'Dibatalkan' : 'Berlaku' }}</span>
                            @unless ($k['batal'])
                                <div class="tindakan">
                                    @include('kompen._aksi', ['id' => $k['id'], 'nama' => $mahasiswa->nama_mahasiswa, 'nim' => $mahasiswa->nim, 'jam' => $k['jam'], 'min' => $k['jam_min'], 'terlaksana' => $ring['terlaksana']])
                                </div>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- ===== Riwayat kompensasi ===== --}}
            <h2 class="detail__judul">Riwayat kompensasi</h2>
            @if ($riwayat->isEmpty())
                <p class="kosong kosong--cari">Belum ada riwayat kompensasi.</p>
            @else
                <div class="rel-bungkus">
                    <table class="rel">
                        <thead>
                            <tr><th>Tanggal</th><th>Ruang</th><th>Mulai</th><th>Selesai</th><th>Jam</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($riwayat as $r)
                                <tr>
                                    <td data-label="Tanggal">{{ $r['tanggal'] ?? '-' }}</td>
                                    <td data-label="Ruang">{{ $r['ruang'] }}</td>
                                    <td data-label="Mulai">{{ $r['mulai'] ?? '-' }}</td>
                                    <td data-label="Selesai">{{ $r['selesai'] ?? '-' }}</td>
                                    <td data-label="Jam">{{ $r['jam'] }} jam</td>
                                    <td data-label="Status">{{ $r['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</section>

{{-- Popup ubah jam dan batalkan kewajiban --}}
@include('kompen._dialog')

{{-- Popup konfirmasi reset password (hanya di halaman detail). Gaya .konf dari kelola-akun.css --}}
<dialog class="modal modal--konfirmasi" id="modal-reset" aria-labelledby="reset-judul"
        data-url="{{ route('kompen.reset', '__ID__') }}">
    <form class="konf" novalidate>
        <div class="konf__ikon konf__ikon--info" aria-hidden="true">
            <svg viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
        </div>
        <h2 id="reset-judul">Reset password?</h2>
        <p data-teks></p>
        <p class="galat-umum" data-galat-umum role="alert" hidden></p>
        <div class="konf__aksi">
            <button type="button" class="btn btn--garis" data-tutup>Batal</button>
            <button type="submit" class="btn btn--solid" data-kirim>Reset Password</button>
        </div>
    </form>
</dialog>@include('kompen._dialog')

@endsection

@push('js')
    <script src="{{ asset('js/data-kompen.js') }}"></script>
@endpush