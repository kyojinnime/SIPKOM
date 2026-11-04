<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenugasanDosenWali extends Model
{
    protected $table = 'penugasan_dosen_wali';
    protected $fillable = ['kelas_id', 'periode_id', 'dosen_wali_id'];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(DosenWali::class);
    }
}
