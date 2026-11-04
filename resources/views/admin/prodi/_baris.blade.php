{{-- Satu baris prodi. Variabel: $item (model Prodi, sudah withCount('kelas')), $nomor (opsional) --}}
@php
    // Jumlah kelas yang memakai prodi ini; tampil sebagai teks pendukung di baris
    $jumlahKelas = $item->kelas_count ?? 0;
    $teksMeta = $jumlahKelas > 0 ? "{$jumlahKelas} kelas" : 'Belum ada kelas';
@endphp

<li class="baris" data-baris data-id="{{ $item->id }}"
    data-nama="{{ $item->nama_prodi }}" data-cari="{{ mb_strtolower($item->nama_prodi) }}">

    {{-- Nomor urut: diisi ulang oleh JS setiap daftar berubah --}}
    <span class="nomor">{{ $nomor ?? '' }}</span>

    {{-- Identitas: nama program studi --}}
    <div class="identitas">
        <div class="identitas__teks">
            <strong class="identitas__nama">{{ $item->nama_prodi }}</strong>
        </div>
    </div>

    {{-- Info pendukung: jumlah kelas yang memakai prodi --}}
    <div class="meta">{{ $teksMeta }}</div>

    {{-- Tombol aksi berbentuk ikon --}}
    <div class="tindakan">
        <button type="button" class="aksi-ikon" data-aksi="ubah" title="Ubah" aria-label="Ubah {{ $item->nama_prodi }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L8 18l-4 1 1-4z"/></svg>
        </button>
        <button type="button" class="aksi-ikon aksi-ikon--hapus" data-aksi="hapus" title="Hapus" aria-label="Hapus {{ $item->nama_prodi }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m2 0-1 14H7L6 6m4 4v6m4-6v6"/></svg>
        </button>
    </div>
</li>