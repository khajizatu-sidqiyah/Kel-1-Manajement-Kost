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
            <a href="{{ route('denah') }}" class="btn-main"><svg class="i i-sm"><use href="#i-grid"/></svg>Lihat Denah Lengkap</a>
        </div>
    </section>

    <!-- Ringkasan kamar -->
    <section class="stats" id="stats" aria-label="Ringkasan kamar"></section>

    <!-- Kondisi data kosong / gagal dimuat -->
    <div class="card state" id="state" hidden></div>

    <!-- Pembayaran & komplain -->
    <section class="mini" id="fin" aria-label="Pembayaran dan komplain"></section>

    <section class="panels" id="panels">
        <div class="card panel">
            <div class="panel-head"><h2>Jatuh Tempo Terdekat</h2><a href="{{ route('denah') }}">Lihat denah</a></div>
            <ul class="list" id="list-tempo"></ul>
        </div>
        <div class="card panel">
            <div class="panel-head"><h2>Kamar Kosong Siap Disewa</h2><a href="{{ route('denah') }}">Lihat semua</a></div>
            <ul class="list" id="list-kosong"></ul>
        </div>
    </section>

    <p class="note" id="note">Data kamar masih contoh. Pembayaran dan komplain menunggu endpoint dari backend.</p>

@endsection

@push('scripts')
    <script src="{{ asset('js/rooms-api.js') }}"></script>
    <script src="{{ asset('js/ringkasan.js') }}"></script>
@endpush
