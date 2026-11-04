{{-- Satu baris kelas.
     Variabel: $item (Kelas; sudah punya prodi, mahasiswa_count, semester_aktif, sudah_lulus, dosen_aktif), $nomor --}}
@php
    $semester  = $item->semester_aktif;                  // null bila belum ada periode aktif
    $nama      = ($semester ?? '') . $item->nama_kelas;  // mis. "4C"
    $dosen     = $item->dosen_aktif;                     // dosen wali di periode aktif, bisa null
    $lulus     = (bool) $item->sudah_lulus;
    $prodi     = $item->prodi?->nama_prodi ?? '-';
    $jumlahMhs = $item->mahasiswa_count ?? 0;
@endphp

{{-- Baris kelas lulus disembunyikan dari awal; JS menampilkannya saat tab Lulus dipilih --}}
<li class="baris {{ $lulus ? 'baris--nonaktif' : '' }}" data-baris @if($lulus) hidden @endif
    data-id="{{ $item->id }}" data-prodi-id="{{ $item->prodi_id }}" data-angkatan="{{ $item->angkatan }}"
    data-nama-kelas="{{ $item->nama_kelas }}" data-dosen-id="{{ $dosen?->id }}"
    data-mahasiswa="{{ $jumlahMhs }}" data-lulus="{{ $lulus ? '1' : '0' }}"
    data-tanpa-dosen="{{ $dosen ? '0' : '1' }}" data-nama="{{ $nama }}"
    data-cari="{{ mb_strtolower($nama . ' ' . $prodi . ' ' . $item->angkatan . ' ' . ($dosen?->nama_dosen ?? '')) }}">

    {{-- Nomor urut: diisi ulang oleh JS --}}
    <span class="nomor">{{ $nomor ?? '' }}</span>

    {{-- Identitas: lingkaran berisi nama kelas, prodi, angkatan --}}
    <div class="identitas">
        <span class="avatar avatar--kelas" aria-hidden="true">{{ $nama }}</span>
        <div class="identitas__teks">
            <strong class="identitas__nama">Kelas {{ $nama }}</strong>
            <span class="identitas__email">{{ $prodi }} · Angkatan {{ $item->angkatan }}</span>
        </div>
    </div>

    {{-- Dosen wali periode aktif dan jumlah mahasiswa --}}
    <div class="meta">
        @if($dosen)
            <span>Dosen wali: {{ $dosen->nama_dosen }}</span>
        @else
            <span class="meta--kosong">Belum ada dosen wali</span>
        @endif
        <small class="meta__sub">{{ $jumlahMhs }} mahasiswa</small>
    </div>

    {{-- Lencana status --}}
    <span class="status-akun {{ $lulus ? 'status-akun--netral' : 'status-akun--aktif' }}">{{ $lulus ? 'Lulus' : 'Aktif' }}</span>

    {{-- Tombol aksi --}}
    <div class="tindakan">
        <button type="button" class="aksi-ikon" data-aksi="ubah" title="Ubah" aria-label="Ubah kelas {{ $nama }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L8 18l-4 1 1-4z"/></svg>
        </button>
        <button type="button" class="aksi-ikon aksi-ikon--hapus" data-aksi="hapus" title="Hapus" aria-label="Hapus kelas {{ $nama }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m2 0-1 14H7L6 6m4 4v6m4-6v6"/></svg>
        </button>
    </div>
</li>