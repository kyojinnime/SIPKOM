{{-- Satu baris ruang. Variabel: $item (Ruang dengan pengawas.user dan pengawasKedua.user), $nomor (urutan) --}}
@php
    $aktif  = (bool) $item->aktif;
    $dibuka = (bool) $item->status_tersedia;
    $utama  = $item->pengawas?->nama_pengawas ?? 'Belum ada pengawas';
    $kedua  = $item->pengawasKedua?->nama_pengawas;   // boleh kosong
    $kuota  = $item->kuota;

    // Lencana: Nonaktif (admin menutup permanen) > Dibuka (pengawas menerima mahasiswa) > Tutup
    if (!$aktif)       { $lencana = 'Nonaktif'; $kelasLencana = 'status-akun--nonaktif'; }
    elseif ($dibuka)   { $lencana = 'Dibuka';   $kelasLencana = 'status-akun--aktif'; }
    else               { $lencana = 'Tutup';    $kelasLencana = 'status-akun--netral'; }
@endphp

<li class="baris {{ $aktif ? '' : 'baris--nonaktif' }}" data-baris data-id="{{ $item->id }}"
    data-nama="{{ $item->nama_ruang }}" data-pengawas-id="{{ $item->pengawas_id }}"
    data-pengawas-kedua-id="{{ $item->pengawas_kedua_id }}" data-aktif="{{ $aktif ? '1' : '0' }}"
    data-cari="{{ mb_strtolower($item->nama_ruang . ' ' . $utama . ' ' . $kedua) }}">

    {{-- Nomor urut: diisi ulang oleh JS --}}
    <span class="nomor">{{ $nomor ?? '' }}</span>

    {{-- Identitas: ikon ruang, nama, kuota (hanya lihat, diatur pengawas) --}}
    <div class="identitas">
        <span class="avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
        </span>
        <div class="identitas__teks">
            <strong class="identitas__nama">{{ $item->nama_ruang }}</strong>
            <span class="identitas__email">{{ $kuota ? "Kuota {$kuota} mahasiswa" : 'Kuota belum diatur pengawas' }}</span>
        </div>
    </div>

    {{-- Pengawas utama dan, bila ada, pengawas kedua --}}
    <div class="meta">
        <span class="{{ $item->pengawas ? '' : 'meta--kosong' }}">Pengawas: {{ $utama }}</span>
        @if($kedua)
            <small class="meta__sub">Kedua: {{ $kedua }}</small>
        @endif
    </div>

    {{-- Lencana status --}}
    <span class="status-akun {{ $kelasLencana }}">{{ $lencana }}</span>

    {{-- Tombol aksi: ubah, dan satu tombol nonaktifkan / aktifkan --}}
    <div class="tindakan">
        <button type="button" class="aksi-ikon" data-aksi="ubah" title="Ubah" aria-label="Ubah {{ $item->nama_ruang }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L8 18l-4 1 1-4z"/></svg>
        </button>
        @if($aktif)
            <button type="button" class="aksi-ikon aksi-ikon--hapus" data-aksi="toggle" title="Nonaktifkan" aria-label="Nonaktifkan {{ $item->nama_ruang }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 100 18 9 9 0 000-18zM5.6 5.6l12.8 12.8"/></svg>
            </button>
        @else
            <button type="button" class="aksi-ikon" data-aksi="toggle" title="Aktifkan kembali" aria-label="Aktifkan {{ $item->nama_ruang }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM8 12l3 3 5-5"/></svg>
            </button>
        @endif
    </div>
</li>