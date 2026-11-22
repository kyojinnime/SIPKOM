@extends('layouts.app')

@section('judul', 'Profil Saya')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/kelola-akun.css') }}">
    <link rel="stylesheet" href="{{ asset('css/profil.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/profil.js') }}"></script>
@endpush

@section('konten')
    <div class="kelola-kepala">
        <div class="kontainer">
            <h1>Profil Saya</h1>
            <p>Kelola data diri, foto, dan keamanan akunmu.</p>
        </div>
    </div>

    <div class="kelola kontainer profil">
        <div class="profil-grid">
            {{-- ===== Kartu Data Diri ===== --}}
            <section class="panel">
                <h2 class="panel__judul">Data Diri</h2>

                <form id="form-profil" data-url="{{ route('profil.perbarui') }}" novalidate>
                    @csrf
                    @method('PUT')

                    {{-- Avatar dan aksi foto --}}
                    <div class="profil__avatar">
                        @if ($user->url_foto)
                            <img src="{{ $user->url_foto }}" alt="Foto profil" class="profil__foto">
                        @else
                            <span class="profil__inisial" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        @endif

                        <div>
                            <div class="profil__avatar-aksi">
                                <label class="btn btn--garis btn--kecil">
                                    {{ $user->foto ? 'Ganti Foto' : 'Unggah Foto' }}
                                    <input type="file" id="input-foto" accept="image/jpeg,image/png"
                                           data-url="{{ route('profil.foto') }}" hidden>
                                </label>
                                @if ($user->foto)
                                    <button type="button" class="btn btn--bahaya btn--kecil"
                                            data-url="{{ route('profil.foto.hapus') }}" data-hapus-foto>Hapus</button>
                                @endif
                            </div>
                            <p class="profil__petunjuk">JPG atau PNG, maksimal 2 MB.</p>
                            <span class="galat" data-foto-galat></span>
                        </div>
                    </div>

                    {{-- Galat umum form --}}
                    <div class="galat-umum" data-galat-umum role="alert" hidden></div>

                    {{-- Nama tidak bisa diubah mahasiswa --}}
                    @if ($user->role !== 'mahasiswa')
                        <div class="field">
                            <label for="nama">Nama</label>
                            <input type="text" id="nama" name="nama" value="{{ old('nama', $user->name) }}" maxlength="100">
                            <span class="galat" data-galat="nama"></span>
                        </div>
                    @endif

                    {{-- Data statis khusus mahasiswa --}}
                    @if ($user->role === 'mahasiswa')
                        <dl class="profil__statis">
                            <div>
                                <dt>Nama</dt>
                                <dd>{{ $user->name }}</dd>
                            </div>
                            <div>
                                <dt>NIM</dt>
                                <dd>{{ $profil?->nim ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt>Kelas</dt>
                                <dd>{{ $profil?->kelas?->label ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt>Program Studi</dt>
                                <dd>{{ $profil?->kelas?->prodi?->nama_prodi ?? '-' }}</dd>
                            </div>
                        </dl>
                    @endif

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="50"
                               @if ($user->role === 'mahasiswa') placeholder="Opsional" @endif>
                        <span class="galat" data-galat="email"></span>
                    </div>

                    <div class="field">
                        <label for="telepon">Telepon</label>
                        <input type="text" id="telepon" name="telepon" value="{{ old('telepon', $user->telepon) }}" maxlength="20"
                               placeholder="Opsional">
                        <span class="galat" data-galat="telepon"></span>
                    </div>

                    @if (in_array($user->role, ['dosen_wali', 'pengawas'], true))
                        <div class="field">
                            <label for="nip">NIP</label>
                            <input type="text" id="nip" name="nip" value="{{ old('nip', $profil?->nip) }}"
                                   inputmode="numeric" placeholder="8-30 digit, opsional">
                            <span class="galat" data-galat="nip"></span>
                        </div>
                    @endif

                    <div class="profil__kaki">
                        <button type="submit" class="btn btn--solid" data-simpan>Simpan</button>
                    </div>
                </form>
            </section>

            {{-- ===== Kartu Keamanan ===== --}}
            <section class="panel">
                <h2 class="panel__judul">Keamanan</h2>
                <p class="panel__keterangan">Ganti password secara berkala agar akunmu tetap aman.</p>
                <button type="button" class="btn btn--solid" data-buka-sandi>Ganti Password</button>
            </section>
        </div>
    </div>
@endsection
