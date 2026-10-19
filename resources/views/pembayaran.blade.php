@extends('layouts.dashboard')

@section('title', 'Pembayaran & Tagihan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/penghuni.css') }}">
@endpush

@section('content')
    {{-- HALAMAN SEMENTARA: ganti dengan halaman Pembayaran & Tagihan (desain Dekstop_18 & 19) --}}
    <div class="pg">
        <nav class="crumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Dashboard</a><span>/</span><b>Pembayaran &amp; Tagihan</b>
        </nav>
        <header class="pg-head">
            <div class="pg-title">
                <h1>Pembayaran &amp; Tagihan</h1>
                <p>Halaman ini sedang dikerjakan.</p>
            </div>
        </header>

        @if (request('jenis') === 'DP')
            <div class="pm-info">
                <span>Dibuka dari tombol <b>Catat DP sekarang</b>.
                    Kamar (id) <b>{{ request('kamar') }}</b>, penghunian (id) <b>{{ request('penghunian') }}</b>,
                    jenis <b>{{ request('jenis') }}</b>. Form pembayaran nanti terisi otomatis dari parameter ini,
                    dan kamar tidak bisa diganti.</span>
            </div>
        @endif
    </div>
@endsection