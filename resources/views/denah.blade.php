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
            <button type="button" class="btn-main" id="btn-add-room"><svg class="i i-sm"><use href="#i-plus"/></svg>Tambah Kamar</button>
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

    <!-- Modal Manajemen Kamar (tambah / ubah) -->
    <div class="modal" id="room-modal" hidden>
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="modal-head">
                <h2 id="modal-title">Manajemen Kamar</h2>
                <button type="button" class="icon-btn" id="modal-x" aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
            </div>

            <div class="seg" role="tablist">
                <button type="button" class="seg-btn" role="tab" data-mode="add">Tambah Kamar Baru</button>
                <button type="button" class="seg-btn" role="tab" data-mode="edit">Edit Kamar</button>
            </div>

            <div class="form" id="room-form">
                <h3 id="form-title">Detail Kamar Baru</h3>

                <div class="field" id="pick-wrap" hidden>
                    <label for="f-pick">Pilih Kamar</label>
                    <select id="f-pick"></select>
                </div>
                <div class="field">
                    <label for="f-no">Nomor Kamar</label>
                    <input type="text" id="f-no" inputmode="numeric" maxlength="20" autocomplete="off">
                </div>
                <div class="field">
                    <label for="f-lantai">Lantai</label>
                    <select id="f-lantai"></select>
                </div>
                <div class="field">
                    <label for="f-tipe">Tipe Kamar</label>
                    <select id="f-tipe"></select>
                </div>
                <div class="field field-top">
                    <label id="f-fas-label">Fasilitas</label>
                    <div class="checks" id="f-fas" role="group" aria-labelledby="f-fas-label"></div>
                </div>
                <div class="field">
                    <label for="f-harga">Harga Per Bulan</label>
                    <input type="text" id="f-harga" inputmode="numeric" placeholder="Rp 0" autocomplete="off">
                </div>
                <div class="field">
                    <label for="f-status">Status Kamar</label>
                    <select id="f-status">
                        <option value="kosong">Kosong</option>
                        <option value="terisi">Terisi</option>
                    </select>
                </div>

                <p class="form-warn" id="form-warn" role="status" hidden></p>
                <p class="form-error" id="form-error" role="alert" hidden></p>

                <div class="form-actions">
                    <button type="button" class="btn-blue" id="btn-save">Simpan</button>
                    <button type="button" class="btn-grey" id="btn-cancel">Batal</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/rooms-api.js') }}"></script>
    <script src="{{ asset('js/denah.js') }}"></script>
@endpush
