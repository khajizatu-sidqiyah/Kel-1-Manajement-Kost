@extends('layouts.dashboard')

@section('title', 'Denah & Status Kamar')

@section('content')

    <!-- Judul halaman -->
    <section class="card page-head">
        <span class="tile"><svg class="i"><use href="#i-grid"/></svg></span>
        <div class="page-head-text">
            <h1>Denah &amp; Status Kamar <span class="badge-live">Live Monitor</span></h1>
            <p>Kost Griya Harmoni • Pembaruan otomatis 10 detik lalu</p>
        </div>
        <div class="page-head-actions">
            <span class="sync"><i class="dot"></i>Auto-Sync Aktif</span>
            <button type="button" class="btn-main"><svg class="i i-sm"><use href="#i-userplus"/></svg>Registrasi Penghuni Cepat</button>
        </div>
    </section>

    <!-- Ringkasan -->
    <section class="stats" id="stats" aria-label="Ringkasan kamar"></section>

    <!-- Filter lantai + legenda -->
    <section class="card filters">
        <div class="tabs" id="tabs" role="tablist"></div>
        <div class="legend">
            <span class="chip chip-soft"><i class="dot dot-green"></i>Hijau = Kosong <i class="sep"></i> <i class="dot dot-red"></i>Merah = Terisi</span>
            <span class="chip chip-soft"><svg class="i i-sm"><use href="#i-snow"/></svg>Tipe AC (Semua)</span>
            <span class="chip chip-soft" id="chip-km"><svg class="i i-sm"><use href="#i-shower"/></svg>KM Dalam</span>
        </div>
    </section>

    <!-- Denah per lantai (diisi oleh denah.js) -->
    <div id="floors" aria-live="polite"></div>

@endsection

@push('scripts')
    <script src="{{ asset('js/rooms-api.js') }}"></script>
    <script src="{{ asset('js/denah.js') }}"></script>
@endpush
