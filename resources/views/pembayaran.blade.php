@extends('layouts.dashboard')

@section('title', 'Pembayaran & Tagihan')

{{-- penghuni.css = kerangka sidebar/topbar sesuai desain; pembayaran.css = isi halaman ini --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/penghuni.css') }}?v={{ filemtime(public_path('css/penghuni.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/pembayaran.css') }}?v={{ filemtime(public_path('css/pembayaran.css')) }}">
@endpush

@section('content')

    {{-- Ikon khusus halaman ini --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="pb-cal" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18M8 15h3"/></symbol>
        <symbol id="pb-doc" viewBox="0 0 24 24"><path d="M6 3h8l5 5v13H6zM14 3v5h5M9 13h6M9 17h6"/></symbol>
        <symbol id="pb-bag" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
        <symbol id="pb-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="pb-trend" viewBox="0 0 24 24"><path d="M5 19c2-7 5-10 12-13M12 6h5v5"/></symbol>
        <symbol id="pb-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="pb-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
        <symbol id="pb-chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
        <symbol id="pb-chev-l" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
        <symbol id="pb-chev-r" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
        <symbol id="pb-dots" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="12" cy="19" r="1.3"/></symbol>
        <symbol id="pb-warn" viewBox="0 0 24 24"><path d="M12 3 2 20h20zM12 10v4M12 17h.01"/></symbol>
        <symbol id="pb-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 3 3 5-6"/></symbol>
        <symbol id="pb-tick" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7"/></symbol>
        <symbol id="pb-upload" viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 16v4h16v-4"/></symbol>
        <symbol id="pb-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
        <symbol id="pb-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></symbol>
    </svg>

    <div class="pb">

        {{-- Breadcrumb --}}
        <nav class="pb-crumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Dashboard</a><span>/</span><b>Pembayaran &amp; Tagihan</b>
        </nav>

        {{-- Judul + tombol --}}
        <header class="pb-head">
            <span class="pb-tile"><svg class="i"><use href="#pb-cal"/></svg></span>
            <div class="pb-title">
                <h1>Pembayaran &amp; Tagihan</h1>
                <p>Kelola pembayaran penghuni dan tagihan kos dengan mudah dan terpantau.</p>
            </div>
            <button type="button" class="pb-btn-main" id="pb-catat"><svg class="i"><use href="#pb-plus"/></svg>Catat Pembayaran</button>
        </header>

        {{-- 4 kartu ringkasan (diisi pembayaran.js) --}}
        <section class="pb-stats" id="pb-stats" aria-label="Ringkasan pembayaran"></section>

        {{-- Pencarian + filter --}}
        <div class="pb-filters">
            <label class="pb-search">
                <svg class="i"><use href="#pb-search"/></svg>
                <input type="search" id="pb-q" placeholder="Cari nama penghuni, nomor kamar, atau keterangan..." autocomplete="off">
            </label>
            <div class="pb-sel"><select id="pb-f-bulan" aria-label="Filter bulan"><option value="">Semua Bulan</option></select><svg class="i"><use href="#pb-chev"/></svg></div>
            <div class="pb-sel">
                <select id="pb-f-status" aria-label="Filter status">
                    <option value="">Semua Status</option><option value="belum">Belum Dibayar</option><option value="sudah">Sudah Dibayar</option><option value="menunggu">Menunggu Konfirmasi</option>
                </select><svg class="i"><use href="#pb-chev"/></svg>
            </div>
            <div class="pb-sel"><select id="pb-f-kamar" aria-label="Filter kamar"><option value="">Semua Kamar</option></select><svg class="i"><use href="#pb-chev"/></svg></div>
        </div>

        {{-- Tab --}}
        <div class="pb-tabs" role="tablist">
            <button type="button" class="pb-tab" role="tab" data-tab="tagihan" aria-selected="true">Tagihan</button>
            <button type="button" class="pb-tab" role="tab" data-tab="riwayat" aria-selected="false">Riwayat Pembayaran</button>
        </div>

        {{-- Tabel --}}
        <section class="pb-card" aria-live="polite">
            <div class="pb-scroll">
                <table class="pb-table">
                    <colgroup>
                        <col class="c1"><col class="c2"><col class="c3"><col class="c4">
                        <col class="c5"><col class="c6"><col class="c7"><col class="c8">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="th-no">No.</th><th>Nama<br>Penghuni</th><th>Kamar</th><th>Jenis<br>Tagihan</th>
                            <th>Periode</th><th>Jumlah</th><th>Status</th><th class="th-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="pb-rows"></tbody>
                </table>
                <div id="pb-state" class="pb-state" hidden></div>
            </div>
        </section>

        <footer class="pb-foot">
            <span id="pb-info">&nbsp;</span>
            <nav class="pb-pager" id="pb-pager" aria-label="Halaman"></nav>
        </footer>
    </div>

    {{-- ===== Modal: Form Catat Pembayaran Baru (desain: Dekstop_19) ===== --}}
    <div class="pb-ov" id="pb-form" hidden>
        <div class="pb-m" role="dialog" aria-modal="true" aria-labelledby="pb-form-title">
            <div class="pb-m-head">
                <h2 id="pb-form-title">Form Catat Pembayaran Baru</h2>
                <button type="button" class="pb-x" id="f-x" aria-label="Tutup"><svg class="i"><use href="#pb-x"/></svg></button>
            </div>

            <div class="pb-m-body">
                <div class="pb-g">
                    <label for="f-kamar">Pilih Kamar / Penghuni</label>
                    <div class="pb-in pb-in--sel"><select id="f-kamar"></select><svg class="i"><use href="#pb-chev"/></svg></div>
                    <p class="pb-help" id="f-kamar-help" hidden></p>
                </div>

                <div class="pb-g">
                    <label for="f-nama">Nama Penghuni</label>
                    <div class="pb-in"><input type="text" id="f-nama" readonly tabindex="-1"></div>
                </div>

                <div class="pb-row">
                    <div class="pb-g">
                        <label for="f-jenis">Jenis Tagihan</label>
                        <div class="pb-in pb-in--sel"><select id="f-jenis"></select><svg class="i"><use href="#pb-chev"/></svg></div>
                        <p class="pb-help pb-help--err" id="f-jenis-err" hidden></p>
                    </div>
                    <div class="pb-g">
                        <label for="f-periode">Periode Tagihan</label>
                        <div class="pb-in pb-in--sel"><select id="f-periode"></select><svg class="i"><use href="#pb-chev"/></svg></div>
                        <p class="pb-help pb-help--err" id="f-periode-err" hidden></p>
                    </div>
                </div>

                <div class="pb-row">
                    <div class="pb-g">
                        <label for="f-jumlah">Jumlah Pembayaran (Rp)</label>
                        <div class="pb-in"><input type="text" id="f-jumlah" inputmode="numeric" placeholder="Masukkan Jumlah" autocomplete="off"></div>
                        <p class="pb-help" id="f-jumlah-help"></p>
                    </div>
                    <div class="pb-g">
                        <label for="f-metode">Metode Pembayaran</label>
                        <div class="pb-in pb-in--sel">
                            <select id="f-metode"><option value="">Pilih Metode</option><option value="tunai">Tunai</option><option value="transfer">Transfer</option></select>
                            <svg class="i"><use href="#pb-chev"/></svg>
                        </div>
                        <p class="pb-help pb-help--err" id="f-metode-err" hidden></p>
                    </div>
                </div>

                <div class="pb-g">
                    <label for="f-tgl">Tanggal Bayar</label>
                    <div class="pb-in pb-date"><span class="dv ph" id="f-tgl-v">Pilih Tanggal</span><svg class="i r"><use href="#pb-calendar"/></svg><input type="date" id="f-tgl"></div>
                    <p class="pb-help pb-help--err" id="f-tgl-err" hidden></p>
                </div>

                {{-- Hanya muncul untuk DP skema tahunan (user story 5.2a): nilai awal dari perhitungan 5.1, bisa diubah --}}
                <div class="pb-g" id="f-tenggat-wrap" hidden>
                    <label for="f-tenggat">Tenggat Pelunasan</label>
                    <div class="pb-in pb-date"><span class="dv ph" id="f-tenggat-v">Pilih Tanggal</span><svg class="i r"><use href="#pb-calendar"/></svg><input type="date" id="f-tenggat"></div>
                    <p class="pb-help">Nilai awal dari perhitungan sewa. Dapat disesuaikan sebelum menyimpan; menjadi tenggat kamar ini.</p>
                </div>

                <div class="pb-g">
                    <span class="lb">Bukti Pembayaran <i>(opsional)</i></span>
                    <label class="pb-drop" id="f-drop" for="f-file">
                        <input type="file" id="f-file" accept="image/*,.pdf" hidden>
                        <span class="pb-drop-t" id="f-drop-t"><svg class="i"><use href="#pb-upload"/></svg>Klik atau Seret file</span>
                    </label>
                    <p class="pb-help pb-help--err" id="f-file-err" hidden></p>
                </div>

                <div class="pb-g">
                    <label for="f-ket">Keterangan <i>(opsional)</i></label>
                    <textarea id="f-ket" rows="2" maxlength="255" placeholder="Tambahkan catatan jika ada"></textarea>
                </div>

                <p class="pb-err" id="f-error" role="alert" hidden></p>
            </div>

            <div class="pb-m-foot">
                <button type="button" class="pb-btn-ghost" id="f-batal">Batal</button>
                <button type="button" class="pb-btn-dark" id="f-simpan">Simpan Pembayaran</button>
            </div>
        </div>
    </div>

    {{-- ===== Dialog peringatan: DP di bawah minimum ===== --}}
    <div class="pb-ov pb-ov--full" id="pb-peringatan" hidden>
        <div class="pb-dlg" role="alertdialog" aria-modal="true" aria-labelledby="w-judul">
            <span class="pb-dlg-ico"><svg class="i"><use href="#pb-warn"/></svg></span>
            <div class="pb-dlg-body">
                <b id="w-judul">DP di bawah minimum</b>
                <p id="w-isi"></p>
                <div class="pb-dlg-act">
                    <button type="button" class="pb-btn-ghost" id="w-ubah">Ubah Nominal</button>
                    <button type="button" class="pb-btn-dark" id="w-lanjut">Lanjutkan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Modal: Detail pembayaran kamar ===== --}}
    <div class="pb-ov" id="pb-det" hidden>
        <div class="pb-m pb-m--det" role="dialog" aria-modal="true" aria-labelledby="d-judul">
            <div class="pb-m-head">
                <h2 id="d-judul">Detail Pembayaran</h2>
                <button type="button" class="pb-x" id="d-x" aria-label="Tutup"><svg class="i"><use href="#pb-x"/></svg></button>
            </div>
            <div class="pb-m-body" id="d-body"></div>
            <div class="pb-m-foot">
                <button type="button" class="pb-btn-ghost" id="d-tutup">Tutup</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/pembayaran.js') }}?v={{ filemtime(public_path('js/pembayaran.js')) }}"></script>
@endpush
