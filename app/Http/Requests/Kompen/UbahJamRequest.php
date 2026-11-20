<?php

namespace App\Http\Requests\Kompen;

use App\Models\JumlahKompen;
use App\Services\KompenHitung;
use Illuminate\Foundation\Http\FormRequest;

/** Validasi ubah jam wajib satu kewajiban (satu baris jumlah_kompen). */
class UbahJamRequest extends FormRequest
{
    // Batas atas jam wajib per kewajiban
    public const MAKS_JAM = 999;

    /** Hanya akun yang boleh mengubah baris ini (admin, atau dosen wali pemegang kelasnya). */
    public function authorize(): bool
    {
        $baris = $this->route('jumlahKompen');

        return $baris instanceof JumlahKompen && (bool) $this->user()?->can('update', $baris);
    }

    /** Batas bawah: tidak di bawah jam yang sudah terlaksana, dan minimal 1 jam. */
    private function jamMinimum(): int
    {
        $baris = $this->route('jumlahKompen');

        return max(1, KompenHitung::jamMinimum((int) $baris->mahasiswa_id, $baris));
    }

    public function rules(): array
    {
        return [
            'jumlah_jam' => ['required', 'integer', 'min:' . $this->jamMinimum(), 'max:' . self::MAKS_JAM],
            'alasan'     => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Pesan galat berbahasa Indonesia; pesan batas bawah menyebut angka yang benar. */
    public function messages(): array
    {
        $min = $this->jamMinimum();

        return [
            'jumlah_jam.required' => 'Jam wajib harus diisi.',
            'jumlah_jam.integer'  => 'Jam wajib harus bilangan bulat.',
            'jumlah_jam.min'      => $min > 1
                ? "Jam wajib tidak boleh kurang dari {$min} jam karena sebagian sudah terlaksana."
                : 'Jam wajib minimal 1 jam. Untuk menghapus kewajiban, gunakan Batalkan.',
            'jumlah_jam.max'      => 'Jam wajib maksimal ' . self::MAKS_JAM . ' jam.',
            'alasan.max'          => 'Alasan maksimal 255 karakter.',
        ];
    }
}