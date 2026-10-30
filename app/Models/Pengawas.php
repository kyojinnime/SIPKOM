<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model pengawas lapangan. */
class Pengawas extends Model
{
    protected $table = 'pengawas';
    protected $fillable = ['user_id', 'nip', 'nama_pengawas'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ruang yang diawasi pengawas ini. */
    public function ruang(): HasMany
    {
        return $this->hasMany(Ruang::class, 'pengawas_id');
    }
}