{{-- Satu baris periode. Variabel: $item (model Periode), $nomor (urutan), $jumlah (jumlah mahasiswa berkompensasi) --}}
@php
    $aktif = (bool) $item->aktif;
    $nama = $item->tahun_ajaran . ' · ' . ucfirst($item->semester);
    $teksMeta = $jumlah > 0 ? "{$jumlah} mahasiswa berkompensasi" : 'Belum ada data kompensasi';
@endphp

<li class="baris" data-baris data-id="{{ $item->id }}"
    data-tahun="{{ $item->tahun_ajaran }}" data-semester="{{ $item->semester }}"
    data-aktif="{{ $aktif ? '1' : '0' }}" data-nama="{{ $nama }}"
    data-cari="{{ mb_strtolower($nama . ($aktif ? ' aktif' : '')) }}">

    {{-- Nomor urut: diisi ulang oleh JS --}}
    <span class="nomor">{{ $nomor ?? '' }}</span>

    {{-- Identitas: ikon kalender, nama periode, keterangan pemakaian --}}
    <div class="identitas">
        <span class="avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
        </span>
        <div class="identitas__teks">
            <strong class="identitas__nama">{{ $nama }}</strong>
            <span class="identitas__email">{{ $aktif ? 'Dipakai Beranda dan import' : 'Riwayat periode' }}</span>
        </div>
    </div>

    {{-- Info pendukung: jumlah mahasiswa yang punya data kompensasi di periode ini --}}
    <div class="meta">{{ $teksMeta }}</div>

    {{-- Status: lencana bila aktif, tombol bila belum (lebar kolom tetap agar baris lurus) --}}
    <div class="status-slot">
        @if($aktif)
            <span class="status-akun status-akun--aktif">Aktif</span>
        @else
            <button type="button" class="btn btn--garis btn--mini" data-aksi="aktifkan">Jadikan aktif</button>
        @endif
    </div>

    {{-- Tombol aksi: periode aktif tidak boleh dihapus --}}
    <div class="tindakan">
        <button type="button" class="aksi-ikon" data-aksi="ubah" title="Ubah" aria-label="Ubah {{ $nama }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L8 18l-4 1 1-4z"/></svg>
        </button>
        <button type="button" class="aksi-ikon aksi-ikon--hapus" data-aksi="hapus" aria-label="Hapus {{ $nama }}"
                @if($aktif) disabled title="Periode aktif tidak bisa dihapus" @else title="Hapus" @endif>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m2 0-1 14H7L6 6m4 4v6m4-6v6"/></svg>
        </button>
    </div>
</li>