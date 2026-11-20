/* =====================================================================
   kompen-import.js - halaman unggah Import Data Kompensasi
   Isi: tampilan nama file, pemeriksaan tipe dan ukuran di browser,
   kunci tombol saat kirim. Pemeriksaan di server tetap yang utama.
   ===================================================================== */

(function () {
    'use strict';

    var form = document.querySelector('[data-form-impor]');
    if (!form) { return; }   // halaman tanpa form (mis. dalam keadaan terblokir)

    // ===== BAGIAN: Elemen dan batas =====
    var input = form.querySelector('#berkas');
    var zona = form.querySelector('[data-zona]');
    var namaBerkas = form.querySelector('[data-nama-berkas]');
    var infoBerkas = form.querySelector('[data-info-berkas]');
    var galat = form.querySelector('[data-galat="berkas"]');
    var tombol = form.querySelector('[data-kirim]');
    var maksMb = Number(input.getAttribute('data-maks-mb')) || 10;

    // Teks awal area unggah, dipulihkan bila pilihan file dibatalkan
    var teksAwal = namaBerkas.textContent;
    var infoAwal = infoBerkas.textContent;

    /** Mengubah byte menjadi teks ramah: "340 KB" atau "2,4 MB". */
    function teksUkuran(byte) {
        if (byte < 1048576) { return Math.max(1, Math.round(byte / 1024)) + ' KB'; }
        return (byte / 1048576).toFixed(1).replace('.', ',') + ' MB';
    }

    /** Menampilkan atau menghapus pesan galat di bawah area unggah. */
    function tampilkanGalat(pesan) {
        galat.textContent = pesan || '';
        input.closest('.field').classList.toggle('field--galat', !!pesan);
    }

    /** Mengembalikan area unggah ke keadaan kosong. */
    function kosongkan() {
        input.value = '';                                 // file tidak ikut terkirim
        namaBerkas.textContent = teksAwal;
        infoBerkas.textContent = infoAwal;
        zona.classList.remove('unggah--terisi');
    }

    /** Memeriksa file terpilih. Hasil: true bila boleh dikirim; bila tidak, pesan galat sudah tampil. */
    function periksa() {
        var file = input.files && input.files[0];

        if (!file) {
            tampilkanGalat('Pilih file PDF terlebih dahulu.');
            return false;
        }
        // Tipe: ekstensi .pdf atau tipe MIME PDF (sebagian browser mengosongkan tipe)
        if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') {
            kosongkan();
            tampilkanGalat('File harus berformat PDF.');
            return false;
        }
        if (file.size > maksMb * 1048576) {
            kosongkan();
            tampilkanGalat('Ukuran file maksimal ' + maksMb + ' MB. File Anda ' + teksUkuran(file.size) + '.');
            return false;
        }
        return true;
    }

    // ===== BAGIAN: Saat file dipilih (klik atau seret-lepas) =====
    input.addEventListener('change', function () {
        zona.classList.remove('unggah--seret');
        tampilkanGalat('');

        var file = input.files && input.files[0];
        if (!file) { kosongkan(); return; }

        // Tampilkan nama dan ukuran; bila tidak lolos, periksa() mengosongkan dan menampilkan galat
        namaBerkas.textContent = file.name;
        infoBerkas.textContent = teksUkuran(file.size);
        zona.classList.add('unggah--terisi');
        periksa();
    });

    // ===== BAGIAN: Penanda seret-lepas (hanya tampilan; unggah ditangani input transparan) =====
    ['dragenter', 'dragover'].forEach(function (nama) {
        zona.addEventListener(nama, function () { zona.classList.add('unggah--seret'); });
    });
    ['dragleave', 'drop'].forEach(function (nama) {
        zona.addEventListener(nama, function () { zona.classList.remove('unggah--seret'); });
    });

    // ===== BAGIAN: Kirim =====
    form.addEventListener('submit', function (e) {
        if (!periksa()) {
            e.preventDefault();       // jangan kirim ke server
            return;
        }
        // Kunci tombol agar tidak terklik dua kali selama PDF dibaca
        tombol.disabled = true;
        tombol.textContent = 'Membaca file...';
    });

    // Tombol Kembali di browser (halaman dari cache): buka kunci tombol
    window.addEventListener('pageshow', function () {
        tombol.disabled = false;
        tombol.textContent = 'Baca File';
    });
})();