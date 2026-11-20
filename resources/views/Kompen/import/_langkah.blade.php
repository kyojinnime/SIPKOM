{{-- Indikator 3 langkah import. Pemakaian: @include('kompen.import._langkah', ['aktif' => 1]) --}}
@php
    // Nama tiap langkah; $aktif = nomor langkah yang sedang dibuka
    $daftarLangkah = [1 => 'Unggah', 2 => 'Pratinjau', 3 => 'Selesai'];
@endphp

<ol class="langkah" aria-label="Tahapan import">
    @foreach ($daftarLangkah as $no => $nama)
        <li class="langkah__item {{ $no === $aktif ? 'langkah__item--aktif' : ($no < $aktif ? 'langkah__item--selesai' : '') }}"
            @if ($no === $aktif) aria-current="step" @endif>
            {{-- Langkah yang sudah lewat memakai tanda centang --}}
            <span class="langkah__no">{!! $no < $aktif ? '&#10003;' : $no !!}</span>
            <span class="langkah__label">{{ $nama }}</span>
        </li>
    @endforeach
</ol>