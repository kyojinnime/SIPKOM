{{-- Tombol ubah jam dan batalkan untuk satu kewajiban. Variabel: $id, $nama, $nim, $jam, $min, $terlaksana.
     Perilakunya ada di data-kompen.js (popup di partial _dialog). --}}
<button type="button" class="aksi-ikon" data-aksi="ubah"
        data-id="{{ $id }}" data-nama="{{ $nama }}" data-nim="{{ $nim }}"
        data-jam="{{ $jam }}" data-min="{{ $min }}" data-terlaksana="{{ $terlaksana }}"
        aria-label="Ubah jam wajib {{ $nama }}" title="Ubah jam wajib">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/></svg>
</button>
<button type="button" class="aksi-ikon aksi-ikon--hapus" data-aksi="batal"
        data-id="{{ $id }}" data-nama="{{ $nama }}" data-nim="{{ $nim }}"
        data-jam="{{ $jam }}" data-min="{{ $min }}" data-terlaksana="{{ $terlaksana }}"
        aria-label="Batalkan kewajiban {{ $nama }}" title="Batalkan kewajiban">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/></svg>
</button>