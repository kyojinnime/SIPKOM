{{-- Dua popup untuk Data Kompen dan Detail Progres: ubah jam wajib, batalkan kewajiban.
     Gaya .modal dan .field dari kelola-akun.css. Isi diatur data-kompen.js. --}}

{{-- ===== Popup ubah jam wajib ===== --}}
<dialog class="modal" id="modal-ubah" aria-labelledby="ubah-judul" data-url="{{ route('kompen.ubah', '__ID__') }}">
    <form class="modal__isi" novalidate>
        <div class="modal__kepala">
            <div>
                <h2 id="ubah-judul">Ubah Jam Wajib</h2>
                <p data-sub></p>
            </div>
            <button type="button" class="modal__tutup" data-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div class="modal__badan">
            <p class="galat-umum" data-galat-umum role="alert" hidden></p>

            <div class="field">
                <label for="ubah-jam">Jam wajib baru</label>
                <input type="number" id="ubah-jam" name="jumlah_jam" min="1" max="999" step="1" inputmode="numeric" required>
                <small data-bantuan></small>
                <span class="galat" data-galat="jumlah_jam" role="alert"></span>
            </div>

            <div class="field">
                <label for="ubah-alasan">Alasan <small>(opsional)</small></label>
                <textarea id="ubah-alasan" name="alasan" rows="2" maxlength="255" placeholder="Mis. koreksi data absensi"></textarea>
                <span class="galat" data-galat="alasan" role="alert"></span>
            </div>
        </div>

        <div class="modal__kaki">
            <button type="button" class="btn btn--garis" data-tutup>Batal</button>
            <button type="submit" class="btn btn--solid" data-kirim>Simpan</button>
        </div>
    </form>
</dialog>

{{-- ===== Popup batalkan kewajiban ===== --}}
<dialog class="modal" id="modal-batal" aria-labelledby="batal-judul" data-url="{{ route('kompen.batal', '__ID__') }}">
    <form class="modal__isi" novalidate>
        <div class="modal__kepala">
            <div>
                <h2 id="batal-judul">Batalkan Kewajiban?</h2>
                <p data-sub></p>
            </div>
            <button type="button" class="modal__tutup" data-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div class="modal__badan">
            <p class="galat-umum" data-galat-umum role="alert" hidden></p>

            {{-- Muncul hanya bila mahasiswa sudah punya jam terlaksana --}}
            <p class="catatan-kuning" data-peringatan hidden></p>

            <p class="batal-info">Data tidak dihapus. Kewajiban ditandai Dibatalkan dan tidak dihitung dalam jam wajib. Import berikutnya dapat mengaktifkannya kembali.</p>

            <div class="field">
                <label for="batal-alasan">Alasan pembatalan <span class="wajib">*</span></label>
                <textarea id="batal-alasan" name="alasan" rows="3" maxlength="255" placeholder="Mis. surat izin diterima, alpa dikoreksi" required></textarea>
                <span class="galat" data-galat="alasan" role="alert"></span>
            </div>
        </div>

        <div class="modal__kaki">
            <button type="button" class="btn btn--garis" data-tutup>Kembali</button>
            <button type="submit" class="btn btn--bahaya" data-kirim>Batalkan Kewajiban</button>
        </div>
    </form>
</dialog>