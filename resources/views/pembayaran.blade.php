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
        <symbol id="pb-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></symbol>
        <symbol id="pb-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
        <symbol id="pb-card" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h3"/></symbol>
        <symbol id="pb-cash" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="11" rx="2"/><circle cx="12" cy="12.5" r="2.5"/><path d="M6.5 12.5h.01M17.5 12.5h.01"/></symbol>
        <symbol id="pb-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></symbol>
    </svg>

    <div class="pb">

       
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

    {{-- ===== Modal: Form Catat Pembayaran Baru =====
         Satu form, tiga tampilan (diatur pembayaran.js):
           dp      -> kamar belum punya DP: pilih skema (bulanan/tahunan), catat DP, atur tenggat pelunasan
           bulanan -> setelah DP: Pelunasan bulan pertama / Tagihan bulanan
           tahunan -> setelah DP: Cicilan (periode dikunci: 1 dari 3, 2 dari 3) / Pelunasan (3 dari 3) --}}
    <div class="pb-ov" id="pb-form" hidden>
        <div class="pb-m" role="dialog" aria-modal="true" aria-labelledby="pb-form-title">
            <div class="pb-m-head">
                <h2 id="pb-form-title">Form Catat Pembayaran Baru</h2>
                <button type="button" class="pb-x" id="f-x" aria-label="Tutup"><svg class="i"><use href="#pb-x"/></svg></button>
            </div>

            <div class="pb-m-body">
                {{-- Kartu kamar. Dari tombol header: nama kamar berupa dropdown (kamar pertama terpilih otomatis).
                     Dari tombol "Bayar": nama kamar berupa teks. --}}
                <div class="pb-roomcard" id="f-card">
                    <span class="av"><svg class="i"><use href="#pb-user"/></svg></span>
                    <b id="f-card-t" hidden></b>
                    <div class="pb-in pb-in--sel pb-roomsel" id="f-kamar-w"><select id="f-kamar" aria-label="Pilih kamar / penghuni"></select><svg class="i"><use href="#pb-chev"/></svg></div>
                    <span class="pb-badge" id="f-card-b"></span>
                </div>

                <div class="pb-note" id="f-info-dp" hidden>
                    <svg class="i"><use href="#pb-info"/></svg>
                    <div><b>Informasi Down Payment</b><p>DP dicatat di awal untuk mengamankan kamar. Setelah DP tersimpan, sistem akan otomatis membuka opsi Pelunasan atau Cicilan.</p></div>
                </div>
                <div class="pb-note" id="f-info-ok" hidden>
                    <svg class="i"><use href="#pb-check"/></svg>
                    <div><b>DP sudah tercatat</b><p id="f-info-ok-t"></p></div>
                </div>

                <div class="pb-g" id="f-skema-g" hidden>
                    <label for="f-skema">Skema Sewa</label>
                    <div class="pb-in pb-in--sel"><select id="f-skema"><option value="bulanan">Bulanan</option><option value="tahunan">Tahunan</option></select><svg class="i"><use href="#pb-chev"/></svg></div>
                </div>

                <div class="pb-row" id="f-tarif-row" hidden>
                    <div class="pb-g">
                        <label for="f-tarif" id="f-tarif-l">Tarif Kamar</label>
                        <div class="pb-in pb-in--ro" id="f-tarif-w"><input type="text" id="f-tarif" inputmode="numeric" autocomplete="off" placeholder="Rp0" readonly></div>
                        <p class="pb-help pb-help--err" id="f-tarif-err" hidden></p>
                        <p class="pb-help" id="f-tarif-help"></p>
                    </div>
                    <div class="pb-g">
                        <label for="f-min" id="f-min-l">Min. Rekomendasi DP (50%)</label>
                        <div class="pb-in pb-in--ok"><input type="text" id="f-min" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="pb-g" id="f-jenis-g" hidden>
                    <label for="f-jenis">Jenis Tagihan <span class="req">*</span></label>
                    <div class="pb-in pb-in--sel" id="f-jenis-sel"><select id="f-jenis"></select><svg class="i"><use href="#pb-chev"/></svg></div>
                    <div class="pb-in pb-in--fixed" id="f-jenis-fix" hidden><span>Down Payment (DP)</span><svg class="i"><use href="#pb-tick"/></svg></div>
                    <p class="pb-help pb-help--err" id="f-jenis-err" hidden></p>
                    <p class="pb-help pb-help--note" id="f-jenis-help"></p>
                </div>

                <div class="pb-row" id="f-fields" hidden>
                    {{-- Periode cicilan: teks saja (tahunan), dihitung dari jumlah cicilan yang sudah tercatat --}}
                    <div class="pb-g" id="f-periode-g" hidden>
                        <label for="f-periode">Periode Cicilan <span class="req">*</span></label>
                        <div class="pb-in pb-in--ro"><input type="text" id="f-periode" readonly tabindex="-1"></div>
                    </div>
                    <div class="pb-g">
                        <label for="f-jumlah" id="f-jumlah-l">Jumlah Pembayaran <span class="req">*</span></label>
                        <div class="pb-in" id="f-jumlah-w"><input type="text" id="f-jumlah" inputmode="numeric" placeholder="Rp0" autocomplete="off"></div>
                        <p class="pb-help pb-help--err" id="f-jumlah-help" hidden></p>
                    </div>
                    <div class="pb-g">
                        <label for="f-tgl">Tanggal Bayar <span class="req">*</span></label>
                        <div class="pb-in pb-date"><span class="dv ph" id="f-tgl-v">Pilih Tanggal</span><svg class="i r"><use href="#pb-calendar"/></svg><input type="date" id="f-tgl"></div>
                        <p class="pb-help pb-help--err" id="f-tgl-err" hidden></p>
                    </div>
                    <div class="pb-g" id="f-tenggat-wrap" hidden>
                        <label for="f-tenggat">Tenggat Pelunasan <span class="req">*</span></label>
                        <div class="pb-in pb-date"><span class="dv ph" id="f-tenggat-v">Pilih Tanggal</span><svg class="i r"><use href="#pb-calendar"/></svg><input type="date" id="f-tenggat"></div>
                        <p class="pb-help pb-help--err" id="f-tenggat-err" hidden></p>
                    </div>
                    <div class="pb-g">
                        <span class="lb">Metode Pembayaran <span class="req">*</span></span>
                        <div class="pb-seg" id="f-metode-seg" role="radiogroup" aria-label="Metode pembayaran">
                            <button type="button" role="radio" aria-checked="false" data-metode="Transfer"><svg class="i"><use href="#pb-card"/></svg>Transfer</button>
                            <button type="button" role="radio" aria-checked="false" data-metode="Tunai"><svg class="i"><use href="#pb-cash"/></svg>Tunai</button>
                        </div>
                        <input type="hidden" id="f-metode" value="">
                        <p class="pb-help pb-help--err" id="f-metode-err" hidden></p>
                    </div>
                </div>

                <div class="pb-sum" id="f-sum" hidden>
                    <div><span id="f-sum-l">Sisa Tagihan Setelah Pembayaran</span><b id="f-sum-v">Rp0</b></div>
                    <span class="pb-tag" id="f-sum-p"></span>
                </div>

                <p class="pb-err" id="f-error" role="alert" hidden></p>
            </div>

            <div class="pb-m-foot">
                <button type="button" class="pb-btn-ghost" id="f-batal">Batal</button>
                <button type="button" class="pb-btn-dark" id="f-simpan">Simpan</button>
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
