@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')

    <section class="card page-head">
        <span class="tile"><svg class="i"><use href="#i-chart"/></svg></span>
        <div class="page-head-text">
            <h1>Dashboard</h1>
            <p>Informasi cepat Kost Griya Harmoni</p>
        </div>
        <div class="page-head-actions">
            <a href="{{ route('denah') }}" class="btn-main" id="btn-denah"><svg class="i i-sm"><use href="#i-grid"/></svg>Lihat Denah Lengkap</a>
        </div>
    </section>

    <!-- Ringkasan kamar -->
    <section class="stats" id="stats" aria-label="Ringkasan kamar"></section>

    <!-- Kondisi data kosong / gagal dimuat -->
    <div class="card state" id="state" hidden></div>

    <!-- Pembayaran & komplain -->
    <section class="mini" id="fin" aria-label="Pembayaran dan komplain"></section>

    <!-- Panel / Kotak Utama Sesuai UI/UX (Dua Kolom) -->
    <section class="panels" id="panels">
        <!-- Card 1: Jatuh Tempo Terdekat -->
        <div class="card panel">
            <div class="panel-head">
                <h2>Jatuh Tempo Terdekat</h2>
                <a href="{{ route('denah') }}" class="panel-action">Lihat Semua</a>
            </div>
            <ul class="list" id="list-tempo">
                <!-- Data akan diisi dinamis oleh JavaScript -->
            </ul>
        </div>

        <!-- Card 2: Kamar Kosong Siap Disewa -->
        <div class="card panel">
            <div class="panel-head">
                <h2>Kamar Kosong Siap Disewa</h2>
                <a href="{{ route('denah') }}" class="panel-action">Lihat Semua</a>
            </div>
            <ul class="list" id="list-kosong">
                <!-- Data akan diisi dinamis oleh JavaScript -->
            </ul>
        </div>
    </section>

    <p class="note" id="note">Data kamar masih contoh. Pembayaran dan komplain menunggu endpoint dari backend.</p>

@endsection

@push('scripts')
    <script src="{{ asset('js/kamar-api.js') }}"></script>
    <script src="{{ asset('js/ringkasan.js') }}"></script>
@endpush