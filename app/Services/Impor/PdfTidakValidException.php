<?php

namespace App\Services\Impor;

use RuntimeException;

/**
 * Galat "file bukan Laporan Evaluasi SIAKAD yang bisa dibaca".
 * Pesannya sudah ramah dan boleh langsung ditampilkan ke pengguna.
 */
class PdfTidakValidException extends RuntimeException
{
}