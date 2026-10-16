
@extends('layouts.dashboard')

@section('title', 'Pengaturan Sewa')

@section('content')
    <section class="card page-head">
        <span class="tile">
            <svg class="i">
                <use href="#i-chart"/>
            </svg>
        </span>

        <div class="page-head-text">
            <h1>Pengaturan Sewa</h1>
            <p>
                Atur nominal DP minimum untuk penyewaan kamar tahunan.
            </p>
        </div>
    </section>

    <section class="card" style="padding: 24px; margin-top: 20px;">
        <h2>DP Minimum Tahunan</h2>

        <p>
            Nominal ini digunakan sebagai DP minimum untuk
            penyewaan kamar dengan skema tahunan.
        </p>

        @if (session('success'))
            <div style="padding: 12px; margin: 16px 0;
                background: #dcfce7; color: #166534; border-radius: 8px;">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div style="padding: 12px; margin: 16px 0;
                background: #fee2e2; color: #991b1b; border-radius: 8px;">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('pengaturan-sewa.update') }}"
            method="POST"
        >
            @csrf
            @method('PUT')

            <div style="margin: 20px 0;">
                <label
                    for="dp_minimum_tahunan"
                    style="display: block; margin-bottom: 8px;
                        font-weight: 600;"
                >
                    DP Minimum Tahunan (Rupiah)
                </label>

                <input
                    type="number"
                    id="dp_minimum_tahunan"
                    name="dp_minimum_tahunan"
                    value="{{ old(
                        'dp_minimum_tahunan',
                        $pengaturan?->dp_minimum_tahunan
                    ) }}"
                    min="1"
                    step="0.01"
                    required
                    style="width: 100%; max-width: 450px;
                        padding: 12px; border: 1px solid #d1d5db;
                        border-radius: 8px;"
                >

                <p style="margin-top: 8px; color: #64748b; font-size: 14px;">
                    Contoh: 300000 untuk Rp300.000.
                </p>
            </div>

            <button type="submit" class="btn-main">
                Simpan Pengaturan
            </button>
        </form>

        <p style="margin-top: 20px;">
            <a href="{{ route('dashboard') }}">
                Kembali ke Dashboard
            </a>
        </p>
    </section>
@endsection