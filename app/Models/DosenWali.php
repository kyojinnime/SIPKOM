<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model dosen wali. */
class DosenWali extends Model
{
    protected $table = 'dosen_wali';
    protected $fillable = ['user_id', 'nip', 'nama_dosen'];

    /** Akun login dosen wali. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Penugasan kelas yang diampu dosen wali per periode. */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanDosenWali::class, 'dosen_wali_id');
    }
}