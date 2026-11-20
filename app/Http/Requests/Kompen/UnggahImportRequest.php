<?php

namespace App\Http\Requests\Kompen;

use App\Models\JumlahKompen;
use App\Models\Periode;
use App\Services\CakupanKompen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validasi unggah PDF import: periode yang boleh dipilih dan berkas PDF. */
class UnggahImportRequest extends FormRequest
{
    /** Batas ukuran berkas (MB). Satu-satunya sumber: dipakai juga oleh view dan JavaScript. */
    public const MAKS_MB = 10;

    /** Hanya admin dan dosen wali (aturan sama dengan halaman Data Kompen). */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', JumlahKompen::class) ?? false;
    }

    public function rules(): array
    {
        // Admin boleh periode apa pun; dosen wali hanya periode aktif
        $periodeBoleh = CakupanKompen::semua($this->user())
            ? Periode::pluck('id')->all()
            : array_filter([Periode::yangAktif()?->id]);

        return [
            'periode_id' => ['required', 'integer', Rule::in($periodeBoleh)],
            // Dua pemeriksaan: ekstensi dan isi file (MIME). Ukuran dalam KB.
            'berkas' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:' . (self::MAKS_MB * 1024)],
        ];
    }

    /** Pesan galat berbahasa Indonesia per kolom. */
    public function messages(): array
    {
        return [
            'periode_id.required' => 'Pilih periode terlebih dahulu.',
            'periode_id.integer'  => 'Periode tidak valid.',
            'periode_id.in'       => 'Periode yang dipilih tidak tersedia untuk Anda.',
            'berkas.required'     => 'Pilih file PDF terlebih dahulu.',
            'berkas.uploaded'     => 'File gagal diunggah. Ukurannya melebihi batas server atau koneksi terputus.',
            'berkas.file'         => 'Berkas tidak valid.',
            'berkas.mimes'        => 'File harus berformat PDF.',
            'berkas.mimetypes'    => 'File harus berformat PDF.',
            'berkas.max'          => 'Ukuran file maksimal ' . self::MAKS_MB . ' MB.',
        ];
    }
}