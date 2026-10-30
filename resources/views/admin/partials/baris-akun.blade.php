{{-- Satu baris akun. Variabel: $tipe ('dosen' atau 'pengawas'), $item (model DosenWali / Pengawas) --}}
@php
    $nama   = $tipe === 'dosen' ? $item->nama_dosen : $item->nama_pengawas;
    $jumlah = $tipe === 'dosen' ? $item->kelas_count : $item->ruang_count;
    $aktif  = $item->user->is_active;

    // Teks relasi: kelas untuk dosen wali, ruang untuk pengawas
    if ($tipe === 'dosen') {
        $teksMeta = $jumlah > 0 ? "Memegang {$jumlah} kelas" : 'Belum memegang kelas';
    } else {
        $teksMeta = $jumlah > 0 ? "Mengawasi {$jumlah} ruang" : 'Belum mengawasi ruang';
    }

    // Email bisa kosong pada data contoh; tampilkan username sebagai gantinya
    $email = $item->user->email ?: $item->user->username;
@endphp

<li class="baris {{ $aktif ? '' : 'baris--nonaktif' }}" data-baris data-id="{{ $item->id }}"
    data-nama="{{ $nama }}" data-email="{{ $item->user->email }}"
    data-cari="{{ mb_strtolower($nama . ' ' . $email) }}">

    {{-- Identitas: lingkaran inisial, nama, email --}}
    <div class="identitas">
        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($nama, 0, 1)) }}</span>
        <div class="identitas__teks">
            <strong class="identitas__nama">{{ $nama }}</strong>
            <span class="identitas__email">{{ $email }}</span>
        </div>
    </div>

    <div class="meta">{{ $teksMeta }}</div>

    <span class="status-akun {{ $aktif ? 'status-akun--aktif' : 'status-akun--nonaktif' }}">
        {{ $aktif ? 'Aktif' : 'Nonaktif' }}
    </span>

    {{-- Menu aksi (titik tiga). Tombolnya difungsikan di Langkah 4 dan 5 --}}
    <div class="baris__aksi">
        <button type="button" class="menu-tombol" data-menu-tombol aria-haspopup="true" aria-expanded="false"
                aria-label="Aksi untuk {{ $nama }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
        </button>
        <div class="menu" data-menu hidden>
            <button type="button" class="menu-item" data-aksi="ubah" data-tipe="{{ $tipe }}" data-id="{{ $item->id }}">Ubah data</button>
            <button type="button" class="menu-item" data-aksi="toggle" data-tipe="{{ $tipe }}" data-id="{{ $item->id }}">{{ $aktif ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</button>
            <button type="button" class="menu-item" data-aksi="reset" data-tipe="{{ $tipe }}" data-id="{{ $item->id }}">Reset password</button>
            <button type="button" class="menu-item menu-item--bahaya" data-aksi="hapus" data-tipe="{{ $tipe }}" data-id="{{ $item->id }}">Hapus</button>
        </div>
    </div>
</li>