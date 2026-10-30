<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model program studi. */
class Prodi extends Model
{
    protected $table = 'prodi';
    protected $fillable = ['nama_prodi'];

    /** Kelas-kelas yang berada di prodi ini. */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'prodi_id');
    }
}