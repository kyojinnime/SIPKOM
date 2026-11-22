<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',   // [UBAH] username untuk login
        'role',       // [UBAH] hak akses: admin/dosen_wali/pengawas/mahasiswa
        'email',
        'password',
        'is_active',            // [BARU] akun aktif / nonaktif
        'must_change_password', // [BARU] wajib ganti password saat login
        'telepon',              // [BARU] nomor telepon profil (boleh kosong)
        'foto',                 // [BARU] path foto profil di storage/app/public
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',            // [BARU] dibaca true/false, bukan 1/0
            'must_change_password' => 'boolean', // [BARU]
        ];
    }

    /**
     * URL foto profil; null bila belum ada foto atau filenya sudah tidak ada
     * (tampilan memakai inisial sebagai cadangan, bukan gambar rusak).
     */
    public function getUrlFotoAttribute(): ?string
    {
        if (!$this->foto) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->exists($this->foto)
            ? asset('storage/' . $this->foto)
            : null;
    }
}
