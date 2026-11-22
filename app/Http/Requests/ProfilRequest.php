<?php

namespace App\Http\Requests;

use App\Models\DosenWali;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validasi pembaruan profil sendiri (nama, email, telepon, NIP). */
class ProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $user = $this->user();
        $role = $user->role;

        $aturan = [
            'telepon' => ['nullable', 'string', 'max:20'],
        ];

        // Nama tidak bisa diubah mahasiswa (berasal dari SIAKAD)
        if ($role !== 'mahasiswa') {
            $aturan['nama'] = ['required', 'string', 'max:100'];
        }

        // Email wajib untuk semua kecuali mahasiswa (opsional). Dosen wali dan pengawas:
        // email = username, jadi keduanya harus unik. Username admin tetap "admin".
        if ($role === 'mahasiswa') {
            $aturan['email'] = ['nullable', 'email:rfc', 'max:50', Rule::unique('users', 'email')->ignore($user->id)];
        } else {
            $aturan['email'] = [
                'required', 'email:rfc', 'max:50',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('users', 'username')->ignore($user->id),
            ];
        }

        if (in_array($role, ['dosen_wali', 'pengawas'], true)) {
            $aturan['nip'] = $this->aturanNip($role, $user->id);
        }

        return $aturan;
    }

    /** Aturan NIP: 8-30 digit angka, boleh kosong; unik hanya untuk dosen wali. */
    private function aturanNip(string $role, int $userId): array
    {
        $aturan = ['nullable', 'digits_between:8,30'];

        if ($role === 'dosen_wali') {
            $dosenId = DosenWali::where('user_id', $userId)->value('id');
            $aturan[] = Rule::unique('dosen_wali', 'nip')->ignore($dosenId);
        }

        return $aturan;
    }

    public function messages(): array
    {
        return [
            'nama.required'      => 'Nama wajib diisi.',
            'nama.max'           => 'Nama maksimal 100 karakter.',
            'email.required'     => 'Email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
            'email.max'          => 'Email maksimal 50 karakter.',
            'email.unique'       => 'Email sudah dipakai akun lain.',
            'telepon.max'        => 'Telepon maksimal 20 karakter.',
            'nip.digits_between' => 'NIP harus 8 sampai 30 digit angka.',
            'nip.unique'         => 'NIP sudah dipakai dosen wali lain.',
        ];
    }
}
