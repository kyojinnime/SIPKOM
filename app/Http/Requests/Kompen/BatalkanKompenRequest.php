<?php

namespace App\Http\Requests\Kompen;

use App\Models\JumlahKompen;
use Illuminate\Foundation\Http\FormRequest;

/** Validasi pembatalan kewajiban: alasan wajib diisi agar ada jejak. */
class BatalkanKompenRequest extends FormRequest
{
    /** Hanya akun yang boleh membatalkan baris ini (admin, atau dosen wali pemegang kelasnya). */
    public function authorize(): bool
    {
        $baris = $this->route('jumlahKompen');

        return $baris instanceof JumlahKompen && (bool) $this->user()?->can('batalkan', $baris);
    }

    public function rules(): array
    {
        return ['alasan' => ['required', 'string', 'min:3', 'max:255']];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.min'      => 'Alasan minimal 3 karakter.',
            'alasan.max'      => 'Alasan maksimal 255 karakter.',
        ];
    }
}